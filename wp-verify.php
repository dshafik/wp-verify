#!/usr/bin/php
<?php
error_reporting(0);
require_once 'cli.sh';
// Try to include system PEAR Archive_TAR
@include 'Archive/TAR.php';
if (!class_exists('Archive_TAR')) {
    // No guarantees this will work
    echo "Warning: Using built-in Archive_TAR! This has not been tested well!" . PHP_EOL;
    include 'Archive/TAR-mini.php';
}

CLI::seto(
    array(
        'd:' => 'Temporary Data directory',
        'f:' => 'FTP(s) Server address (i.e. ftp.example.org)',
        'u:' => 'FTP Username',
        'p::' => 'FTP Password',
        'P:' => 'FTP Post (default: 21)',
        'w:' => 'Wordpress Path on remote host',
        'v:' => 'Wordpress Version',
		'l' => 'Check Plugins',
    )
);

if (!CLI::geto('f') || !CLI::geto('v') || !CLI::geto('u') || !CLI::geto('p') || !CLI::geto('w')) {
    CLI::gethelp();
    exit(-1);
}

if (!$data_dir = CLI::geto('d')) {
    $data_dir = realpath(sys_get_temp_dir()) .DIRECTORY_SEPARATOR. uniqid('wp-verify-');
	mkdir($data_dir);
} elseif (!file_exists($data_dir)) {
	$result = @mkdir($data_dir);
	if (!$result) {
		echo "Data directory does not exist, and cannot be created." . PHP_EOL;
		exit(-1);
	}
}

$version = CLI::geto('v');

echo "Retrieving Wordpress $version...";
$start = time();
$wp = file_get_contents("http://wordpress.org/wordpress-$version.tar.gz");
$end = time();
$wp_file = $data_dir . DIRECTORY_SEPARATOR . "wordpress-$version.tar.gz";
file_put_contents($wp_file, $wp);
unset($wp);

$kbs = floor((filesize($wp_file)/1024)/($end - $start));

echo "... done! ({$kbs}KB/s)" . PHP_EOL;

echo "Verifying build...";
$md5 = trim(file_get_contents("http://wordpress.org/wordpress-$version.md5"));
$md5_file = md5_file($wp_file);
if ($md5 == $md5_file) {
    echo "... verified!" . PHP_EOL;
} else {
    echo "... failed! ($md5 does not match $md5_file)" . PHP_EOL;
    exit(-1);
}

echo "Unpacking Wordpress...";
$file_dir = $data_dir . DIRECTORY_SEPARATOR . "wordpress-$version";
if (file_exists($file_dir)) {
    echo "... failed! (Unpack directory already exists: $file_dir)" . PHP_EOL;
    exit(-1);
} else {
    mkdir($file_dir);
}
/*$tar = new Archive_Tar($wp_file, 'gz');
$tar->extract($file_dir);*/
`cd $file_dir && tar -zxvf $wp_file > /dev/null`;
echo "... complete!" . PHP_EOL;

$server = CLI::geto('f');
$port = CLI::geto('P') ? CLI::geto('P') : '21';
$user = CLI::geto('u');
$password = CLI::geto('p');
$wordpress = CLI::geto('w');

if (!$password || $password === true) {
	$password = trim(CLI::password_prompt('FTP Password:'));
}

echo "Testing remote connection...";

if (substr($wordpress, -1) != '/') {
    $wordpress .= '/';
}

if ($wordpress{0} != '/') {
    $wordpress = '/' .$wordpress;
}

$dsn = "ftp://$user:$password@$server$wordpress";

if (!$fp = file_get_contents($dsn . 'wp-config.php')) {
    echo "... failed! ($dsn)" . PHP_EOL;
    exit(-1);
} else {
    echo "... success!" . PHP_EOL;
}

$remote_file_dir = $file_dir . '-remote';

$plugins = CLI::geto('l');
if ($plugins) {
	if (!class_exists('ZipArchive')) {
		echo "PHP Zip Extension is required to check plugins. Skipping." . PHP_EOL;
	} else {
		if (!file_exists($remote_file_dir . DIRECTORY_SEPARATOR .'wp-content')) {
			mkdir($remote_file_dir . DIRECTORY_SEPARATOR .'wp-content');
		}

		$plugins_dir = $file_dir . DIRECTORY_SEPARATOR .'wordpress'. DIRECTORY_SEPARATOR .'wp-content'. DIRECTORY_SEPARATOR .'plugins';

		if (!file_exists($file_dir . DIRECTORY_SEPARATOR .'wp-content'. DIRECTORY_SEPARATOR .'plugins')) {
			mkdir($plugins_dir);
		}

		echo "Retrieving Plugin List...";
		$fp = ftp_connect($server, $port);
		ftp_login($fp, $user, $password);
		$plugins_list = ftp_nlist($fp, $wordpress .'/wp-content/plugins');
		foreach ($plugins_list as $key => $plugin) {
			$slug = basename($plugin);
			if ($slug{0} == '.' || $slug == '__MACOSX') {
				unset($plugins_list[$key]);
			}
		}
		ftp_close($fp);

		echo "... found " .sizeof($plugins_list) .' plugins.' . PHP_EOL;

		echo "Retrieving Plugins...";

		$plugin_errors = array();

		foreach ($plugins_list as $plugin) {
			/* Query the Wordpress API for plugin info */

			// The data to send
			$fields = array(
				'description' => false,
				'sections' => false,
				'tested'  => false,
				'requires' => false,
				'rating' => false,
				'downloaded' => false,
				'downloadlink' => true,
				'last_updated'  => false,
				'homepage' => false,
				'tags' => false
			);

			$data = array('action' => 'plugin_information', 'request' => serialize((object) array('slug' => basename($plugin), 'fields' => $fields)));

			// URL Encode the data
			$urlencoded = http_build_query($data);

			$options = array (
				   'http' => array (
					  'method' => 'POST',
					  'header'=>
						 "Content-type: application/x-www-form-urlencoded\r\n"
					   . "Content-Length: " . strlen($urlencoded) . "\r\n",
				   'content' => $urlencoded
				)
			);

			$context = stream_context_create($options);

			$response = file_get_contents("http://api.wordpress.org/plugins/info/1.0/", false, $context);
			$plugin_result = unserialize($response);
			if (isset($plugin_result->error)) {
				$plugin_errors[$slug] = $plugin_result->error;
				continue;
			}

			$plugin_dir = $plugins_dir .DIRECTORY_SEPARATOR. $slug;

			/* @todo Doing this any other way (i.e. fopen/fread/fwrite, file_get_contents) results in a segfault */
			`cd $plugins_dir && wget $plugin_result->download_link -q`;

			$zip = new ZipArchive();
			$zip->open($plugins_dir .DIRECTORY_SEPARATOR. basename($plugin_result->download_link));
			$zip->extractTo($plugins_dir);

			echo ".";
		}

		$result = (sizeof($plugin_errors) > 0) ? " with " .sizeof($plugin_errors). " errors." : "success!";
		echo "... " .$result. PHP_EOL;
	}
}

echo "Generating MD5sums...";

try {
	$rdi = new RecursiveDirectoryIterator($file_dir . DIRECTORY_SEPARATOR . 'wordpress');
	$wp = new RecursiveIteratorIterator($rdi);
} catch (Exception $e) {
	echo $e;
}

$i = 0;
foreach ($wp as $file) {
    if (!$file->isFile() || basename($file->getFileName()) == 'wp-config-sample.php') {
        continue;
    }

    $i++;
    if ($i % 10 == 0) {
        echo '.';
    }

    $filename = str_replace($file_dir . DIRECTORY_SEPARATOR . 'wordpress', '', $file->getPathname());
    // Strip slash and ./
    if ($filename{0} == '/') {
        $filename = substr($filename, 1);
    } elseif ($filename{0} == '.') {
        $filename = substr($filename, 2);
    }

    $md5sums[$filename] = md5_file($file->getPathname());
}
echo "... done!" . PHP_EOL;

echo "Comparing remote files...";
$i = 0;
if (file_exists($remote_file_dir)) {
    echo "... failed! (Temp directory already exist: $remote_file_dir)";
    exit(-1);
}

mkdir($remote_file_dir);

$failed = array();
$i = 0;
$fp = ftp_connect($server, $port);
ftp_login($fp, $user, $password);
foreach (array_keys($md5sums) as $file) {
    $i++;

	if ($i % 10 == 0) {
		echo '.';
	}

    ftp_get($fp, $remote_file_dir . DIRECTORY_SEPARATOR . basename($file), $wordpress . $file, FTP_BINARY);
	//$dsn = "ftp://$user:$password@$server$wordpress$file";
	//file_get_contents($dsn);
	//$md5 = md5();

    if (md5_file($remote_file_dir . DIRECTORY_SEPARATOR . basename($file)) != $md5sums[$file]) {
        $failed[] = array($file);
    }
}
echo "... complete!" . PHP_EOL;
ftp_close($fp);

if ($failed) {
    echo CLI::theme_table($failed, array("Filename"), "Failed files");
} else {
    echo "Wordpress install is pristine!" . PHP_EOL;
}
?>
