<?php
	/**
	 * Plugin Name:       Debug Log Manager
	 * Plugin URI:        https://storepress.com/
	 * Description:       View and delete the WordPress debug.log from the admin panel, and block direct URL access to the log file.
	 * Version:           1.0.0
	 * Requires at least: 6.0
	 * Requires PHP:      8.1
	 * Author:            StorePress
	 * Author URI:        https://storepress.com/
	 * License:           GPL-2.0-or-later
	 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
	 * Text Domain:       storepress-debug-log-manager
	 *
	 * @package StorePress\DebugLogManager
	 */
	
	namespace StorePress\DebugLogManager;
	
	defined( 'ABSPATH' ) || exit;
	
	const VERSION    = '1.0.0';
	const CAPABILITY = 'manage_options';
	const PAGE_SLUG  = 'storepress-debug-log';
	const NONCE      = 'storepress_dlm_nonce';
	
	/**
	 * Absolute path to the debug.log file.
	 *
	 * @return string
	 */
	function log_file(): string {
		return WP_CONTENT_DIR . '/debug.log';
	}
	
	/* -------------------------------------------------------------------------
	 * Block direct URL access to debug.log via .htaccess (Apache) on activation.
	 * Nginx users are shown a snippet on the admin page.
	 * ---------------------------------------------------------------------- */
	
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\protect_log' );
	
	/**
	 * Drop an .htaccess rule in wp-content denying access to debug.log.
	 *
	 * @return void
	 */
	function protect_log(): void {
		$htaccess = WP_CONTENT_DIR . '/.htaccess';
		$marker   = 'StorePress Debug Log Manager';
		$rules    = array(
			'<Files debug.log>',
			'    Require all denied',
			'    Order allow,deny',
			'    Deny from all',
			'</Files>',
		);
		
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		
		insert_with_markers( $htaccess, $marker, $rules );
	}
	
	/* -------------------------------------------------------------------------
	 * Plugin list row action link
	 * ---------------------------------------------------------------------- */
	
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\\action_links' );
	
	/**
	 * Add a "View Log" link to the plugin row on the Plugins page.
	 *
	 * @param array<string,string> $links Existing action links.
	 * @return array<string,string>
	 */
	function action_links( array $links ): array {
		$url = add_query_arg( 'page', PAGE_SLUG, admin_url( 'tools.php' ) );
		
		$links['view_log'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'View Log', 'storepress-debug-log-manager' )
		);
		
		return $links;
	}
	
	/* -------------------------------------------------------------------------
	 * Admin menu
	 * ---------------------------------------------------------------------- */
	
	add_action( 'admin_menu', __NAMESPACE__ . '\\register_menu' );
	
	/**
	 * Register the Tools submenu page.
	 *
	 * @return void
	 */
	function register_menu(): void {
		add_management_page(
			__( 'Debug Log', 'storepress-debug-log-manager' ),
			__( 'Debug Log', 'storepress-debug-log-manager' ),
			CAPABILITY,
			PAGE_SLUG,
			__NAMESPACE__ . '\\render_page'
		);
	}
	
	/* -------------------------------------------------------------------------
	 * Handle actions (clear / download)
	 * ---------------------------------------------------------------------- */
	
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_actions' );
	
	/**
	 * Process clear and download requests.
	 *
	 * @return void
	 */
	function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		
		if ( ! current_user_can( CAPABILITY ) ) {
			return;
		}
		
		$action = isset( $_GET['dlm_action'] ) ? sanitize_key( wp_unslash( $_GET['dlm_action'] ) ) : '';
		
		if ( '' === $action ) {
			return;
		}
		
		check_admin_referer( NONCE );
		
		$file = log_file();
		
		if ( 'clear' === $action ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => PAGE_SLUG, 'cleared' => '1' ), admin_url( 'tools.php' ) ) );
			exit;
		}
		
		if ( 'download' === $action && file_exists( $file ) ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="debug.log"' );
			header( 'Content-Length: ' . filesize( $file ) );
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			exit;
		}
	}
	
	/* -------------------------------------------------------------------------
	 * Page output
	 * ---------------------------------------------------------------------- */
	
	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	function render_page(): void {
		$file    = log_file();
		$exists  = file_exists( $file );
		$size    = $exists ? size_format( filesize( $file ) ) : '0 B';
		$base    = add_query_arg( 'page', PAGE_SLUG, admin_url( 'tools.php' ) );
		$clear   = wp_nonce_url( add_query_arg( 'dlm_action', 'clear', $base ), NONCE );
		$dl      = wp_nonce_url( add_query_arg( 'dlm_action', 'download', $base ), NONCE );
		$debug   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		
		// Tail the file: last ~5000 lines to avoid memory blowups.
		$contents = '';
		if ( $exists ) {
			$lines    = array_slice( file( $file, FILE_IGNORE_NEW_LINES ) ?: array(), -5000 );
			$contents = implode( "\n", $lines );
		}
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Debug Log', 'storepress-debug-log-manager' ); ?></h1>
			
			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Debug log deleted.', 'storepress-debug-log-manager' ); ?></p></div>
			<?php endif; ?>
			
			<?php if ( ! $debug ) : ?>
                <div class="notice notice-warning"><p>
						<?php
							echo wp_kses_post(
								__( 'Logging is not fully enabled. Add the following to <code>wp-config.php</code>:', 'storepress-debug-log-manager' )
							);
						?>
                    </p>
                    <pre style="padding:10px;background:#f6f7f7;border:1px solid #dcdcde;">
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );</pre>
                </div>
			<?php endif; ?>

            <p>
                <strong><?php esc_html_e( 'File:', 'storepress-debug-log-manager' ); ?></strong>
                <code><?php echo esc_html( $file ); ?></code>
                &nbsp;|&nbsp;
                <strong><?php esc_html_e( 'Size:', 'storepress-debug-log-manager' ); ?></strong>
				<?php echo esc_html( $size ); ?>
            </p>

            <p>
                <a href="<?php echo esc_url( $base ); ?>" class="button"><?php esc_html_e( 'Refresh', 'storepress-debug-log-manager' ); ?></a>
				<?php if ( $exists ) : ?>
                    <a href="<?php echo esc_url( $dl ); ?>" class="button"><?php esc_html_e( 'Download', 'storepress-debug-log-manager' ); ?></a>
                    <a href="<?php echo esc_url( $clear ); ?>" class="button button-primary"
                       onclick="return confirm('<?php echo esc_js( __( 'Delete the debug log? This cannot be undone.', 'storepress-debug-log-manager' ) ); ?>');">
						<?php esc_html_e( 'Delete Log', 'storepress-debug-log-manager' ); ?>
                    </a>
				<?php endif; ?>
            </p>

            <textarea readonly style="width:100%;height:60vh;font-family:Menlo,Consolas,monospace;font-size:12px;white-space:pre;overflow:auto;" wrap="off"><?php
					echo esc_textarea( $exists ? $contents : __( 'debug.log is empty or does not exist.', 'storepress-debug-log-manager' ) );
				?></textarea>
			
			<?php if ( $exists && count( $lines ?? array() ) >= 5000 ) : ?>
                <p class="description"><?php esc_html_e( 'Showing the last 5,000 lines. Download for the full file.', 'storepress-debug-log-manager' ); ?></p>
			<?php endif; ?>

            <h2><?php esc_html_e( 'Nginx users', 'storepress-debug-log-manager' ); ?></h2>
            <p><?php esc_html_e( 'The activation step writes an .htaccess rule for Apache. On nginx, add this to your server block instead:', 'storepress-debug-log-manager' ); ?></p>
            <pre style="padding:10px;background:#f6f7f7;border:1px solid #dcdcde;">location = /wp-content/debug.log { deny all; }</pre>
        </div>
		<?php
	}