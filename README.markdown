# wp-verify #


_wp-verify_ is intended to verify the validity
of any given (base) wordpress install. It is run from CLI
against an FTP or FTPS server (the latter is preferred).

_wp-verify_ works by following these steps:

1. Download the original release from wordpress.org
2. Verify it's md5sum
3. Unpack to *--data-dir* (default: `sys_get_temp_dir()`)
4. Connect via FTP(S)
5. [Optional] Download a list of installed plugins
6. Download the original sources for each plugin
7. Build an array of an md5sum of every file in the release
8  [Optional] Build an array of an md5sum of every file in the plugins
8. Download each file from the FTP(S) and compare it's md5sum to that of the original

*Additionally* I hope to allow verification of a local set of files against the
server too, for custom modifications, plugins and themes.

## Example ##

	./wp-verify.php -v3.0.1 -fftp.example.org -uUSERNAME -p -w/htdocs/blog
	Retrieving Wordpress 3.0.1...... done!
	Verifying build...... verified!
	Unpacking Wordpress...... complete!
	FTP Password: [input here]
	Testing remote connection...... success!
	Retrieving Plugin List...... found 11 plugins.
	Retrieving Plugins................. success!
	Generating MD5sums......................................................................................................... done!
	Comparing 300 remote files................................................................................. complete!
	
	 ----------------------- Failed files -----------------------
	 |                         Filename                         |
	 | index.php                                                |


## Libraries ##

_wp-verify_ uses several libraries to complete it's work.

* Devin Doucette's Archive Classes
* PHP CLI Framework (http://cliframework.com/)
	* This basic CLI framework does some of the boring CLI stuff