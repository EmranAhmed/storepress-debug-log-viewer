# Debug Log Manager

View, download, and delete the WordPress `debug.log` from the admin panel — and block direct URL access to the log file.

## What it does

- **View** the log in a scrollable panel under **Tools → Debug Log** (tails the last 5,000 lines to stay fast on large files).
- **Download** the complete raw `debug.log`.
- **Delete** the log with one click (nonce-protected, with a confirm prompt).
- **Blocks direct URL access** by writing an Apache `.htaccess` deny rule into `wp-content/` on activation. Nginx users get a copy-paste snippet on the admin page.
- Adds a **View Log** link to the plugin's row on the Plugins page for quick access.

## Installation

1. Copy the `storepress-debug-log-manager` folder into `wp-content/plugins/`.
2. Activate **Debug Log Manager** from the Plugins page.
3. On activation the plugin adds an `.htaccess` rule blocking `debug.log` (Apache only — see [Nginx](#nginx) below).

## Enabling logging

The plugin reads `wp-content/debug.log`, which WordPress only writes when debug logging is on. Add this to `wp-config.php` (above the `/* That's all, stop editing! */` line):

```php
define( 'WP_DEBUG_LOG', true );    // writes to wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false ); // keep errors out of page output
```

If logging is off, the admin page shows a warning with this same snippet.

## Usage

| Action           | Where                                                      |
|------------------|------------------------------------------------------------|
| Open the viewer  | **Tools → Debug Log**, or **View Log** on the Plugins page |
| Refresh contents | **Refresh** button                                         |
| Save a full copy | **Download** button                                        |
| Wipe the log     | **Delete Log** button (confirms first)                     |

The viewer shows the most recent 5,000 lines. For the full history, use **Download**.

## Nginx

`.htaccess` only works on Apache. On nginx, add this to your server block and reload:

```nginx
location = /wp-content/debug.log { deny all; }
```

## When to use it

- **During development or debugging** — watch errors, deprecations, and `error_log()` output without SSH or FTP.
- **On staging/production incidents** — quickly inspect what's being logged, grab a copy for a bug report, then clear it.
- **Routine cleanup** — `debug.log` can grow to hundreds of MB; delete it to reclaim space.
- **Security hardening** — ensure the log isn't readable at `https://example.com/wp-content/debug.log`, which can leak paths, queries, and stack traces.

## When *not* to use it

- **As a permanent production logger.** Leaving `WP_DEBUG` on in production is discouraged. Enable it to diagnose an issue, then turn it back off.
- **For high-volume log analysis.** This is a viewer, not a log aggregator. For heavy or long-term logging, ship logs to a dedicated service.
- **If you rely solely on `.htaccess` for protection on nginx.** Nginx ignores `.htaccess`; you must add the `location` block yourself.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A user with the `manage_options` capability (administrators)

## Security notes

- All actions are gated behind the `manage_options` capability and verified with nonces.
- File deletion uses `wp_delete_file()`; the download streams the raw file with no-cache headers.
- The `.htaccess` rule is added on activation only. If you change web servers or the file is regenerated, re-check that the deny rule (or nginx equivalent) is still in place.

## License

GPL-2.0-or-later.