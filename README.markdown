# wp-verify #


_wp-verify_ is intended to verify the validity
of any given (base) wordpress install. It is run from CLI
against an FTP or FTPS server (the latter is preferred).

_wp-verify_ works by following these steps:

1. Download the original release from wordpress.org
2. Verify it's md5sum
3. Unpack to *--data-dir* (default: `sys_get_temp_dir()`)
4. Build an array of an md5sum of every file in the release
5. Connect via FTP(S)
6. Download each file and compare it's md5sum to that of the original release

*Additionally* I hope to allow verification of a local set of files against the
server too, for custom modifications, plugins and themes.

## Example ##

	./wp-verify.php -v2.9.1 -fftp.example.org -uUSERNAME -pPASSWORD -w/web/content
	Retrieving Wordpress 2.9.1...... done!
	Verifying build...... verified!
	Unpacking Wordpress...... complete!
	Generating MD5sums................................................................................. done!
	Testing remote connection...... success!
	Comparing remote files................................................................................. complete!
	
	   Failed files 
	 | Filename  | 
	 | index.php | 

## Libraries ##

_wp-verify_ uses several libraries to complete it's work.

* PEAR Archive_TAR (http://pear.php.net/Archive_TAR)
	* The git repo includes a hacked version with no PEAR dependencies should you not have Archive_TAR in your path. It is part of the base pear install though, I would be surprised if you needed it.
* PHP CLI Framework (http://cliframework.com/)
	* This basic CLI framework does some of the boring CLI stuff