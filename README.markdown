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