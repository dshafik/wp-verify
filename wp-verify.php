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
        'd:' => 'Data directory',
        'f:' => 'FTP(s) Server address (i.e. ftp.example.org)',
        'u:' => 'FTP Username',
        'p:' => 'FTP Password',
        'P:' => 'FTP Post (default: 21)',
        'w:' => 'Wordpress Path on remote host',
        'v:' => 'Wordpress Version'
    )
);

/*if (!CLI::geto('f') || !CLI::geto('v')) {
    CLI::gethelp();
    exit(-1);
}
*/

if (!$data_dir = CLI::geto('d')) {
    $data_dir = realpath(sys_get_temp_dir());
}

$version = CLI::geto('v');

echo "Retrieving Wordpress $version...";
$wp = file_get_contents("http://wordpress.org/wordpress-$version.tar.gz");
$wp_file = $data_dir . DIRECTORY_SEPARATOR . "wordpress-$version.tar.gz";
file_put_contents($wp_file, $wp);
unset($wp);
echo "... done!" . PHP_EOL;

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
$tar = new Archive_Tar($wp_file, 'gz');
$tar->extract($file_dir);
echo "... complete!" . PHP_EOL;

echo "Generating MD5sums...";
$wp = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file_dir . DIRECTORY_SEPARATOR . 'wordpress'));

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



echo "Testing remote connection...";
$server = CLI::geto('f');
$port = CLI::geto('P') ? CLI::geto('P') : '21';
$user = CLI::geto('u');
$password = CLI::geto('p');
$wordpress = CLI::geto('w');

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

echo "Comparing remote files...";
$i = 0;
$remote_file_dir = $file_dir . '-remote';
if (file_exists($remote_file_dir)) {
    echo "... failed! (Temp directory already exist: $remote_file_dir)";
    exit(-1);
}

mkdir($remote_file_dir);

$fp = ftp_connect($server, $port);
ftp_login($fp, $user, $password);
$failed = array();
$i = 0;
foreach (array_keys($md5sums) as $file) {
    $i++;
    if ($i % 10 == 0) {
        echo '.';
    }

    ftp_get($fp, $remote_file_dir . DIRECTORY_SEPARATOR . basename($file), $wordpress . $file, FTP_BINARY);
    if (md5_file($remote_file_dir . DIRECTORY_SEPARATOR . basename($file)) != $md5sums[$file]) {
        $failed[] = $file;
    }
}
ftp_close($fp);
echo "... complete!" . PHP_EOL;

if ($failed) {
    echo CLI::theme_table(array($failed), "Filename", "Failed files");
} else {
    echo "Wordpress install is pristine!" . PHP_EOL;
}
?>
