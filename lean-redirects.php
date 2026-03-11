<?php
/**
 * Plugin Name: Lean Redirects
 * Plugin URI:  https://github.com/ctala/lean-redirects
 * Description: Ultra-lightweight 301/302/307 redirects. One indexed DB query per request. No bloat.
 * Version:     1.1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author:      Cristian Tala
 * Author URI:  https://cristiantala.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-redirects
 * Domain Path: /languages
 *
 * @package LeanRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEAN_REDIRECTS_VERSION', '1.1.0' );
define( 'LEAN_REDIRECTS_DB_TABLE', 'lean_redirects' );
define( 'LEAN_REDIRECTS_FILE', __FILE__ );

/* ═══════════════════════════════════════════════════════════════════════════
   TABLE SETUP
   ═══════════════════════════════════════════════════════════════════════════ */

register_activation_hook( LEAN_REDIRECTS_FILE, 'lean_redirects_activate' );

/**
 * Plugin activation: create the database table.
 *
 * @return void
 */
function lean_redirects_activate() {
	lean_redirects_ensure_table();
}

/**
 * Get the full table name with prefix.
 *
 * @return string
 */
function lean_redirects_table_name() {
	global $wpdb;
	return $wpdb->prefix . LEAN_REDIRECTS_DB_TABLE;
}

/**
 * Create the redirects table if it does not exist.
 *
 * @return void
 */
function lean_redirects_ensure_table() {
	global $wpdb;
	$table   = lean_redirects_table_name();
	$charset = $wpdb->get_charset_collate();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// dbDelta requires the full CREATE TABLE statement with the table name inline.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	dbDelta(
		"CREATE TABLE {$table} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url_from   VARCHAR(500)    NOT NULL,
			url_to     VARCHAR(500)    NOT NULL,
			code       SMALLINT        NOT NULL DEFAULT 301,
			active     TINYINT(1)      NOT NULL DEFAULT 1,
			hits       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			note       VARCHAR(255)    DEFAULT '',
			created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_from (url_from(191)),
			KEY idx_active (active)
		) {$charset};"
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
   FRONTEND — Process redirects (1 indexed query)
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'template_redirect', 'lean_redirects_process', 1 );

/**
 * Check the current request against active redirects and redirect if matched.
 *
 * @return void
 */
function lean_redirects_process() {
	global $wpdb;
	$table = lean_redirects_table_name();

	// Sanitize the request path.
	$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path    = rtrim( wp_parse_url( $raw_uri, PHP_URL_PATH ), '/' );

	if ( empty( $path ) ) {
		return;
	}

	// Try exact match (with and without trailing slash).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, url_to, code FROM %i WHERE active = 1 AND ( url_from = %s OR url_from = %s ) LIMIT 1',
			$table,
			$path,
			$path . '/'
		)
	);

	if ( ! $row ) {
		return;
	}

	// Count hit.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE %i SET hits = hits + 1 WHERE id = %d',
			$table,
			$row->id
		)
	);

	$target = $row->url_to;
	$code   = (int) $row->code;

	// If target is a relative path, make it absolute.
	if ( 0 !== strpos( $target, 'http' ) ) {
		$target = home_url( $target );
	}

	// Use wp_safe_redirect for internal targets, wp_redirect for external.
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	$dest = wp_parse_url( $target, PHP_URL_HOST );

	if ( $dest === $home || empty( $dest ) ) {
		wp_safe_redirect( $target, $code );
	} else {
		// Allow external redirects — filtered by allowed_redirect_hosts.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $target, $code );
	}
	exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ADMIN — Settings page under Settings → Redirects
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'lean_redirects_menu' );

/**
 * Register the admin menu page under Settings.
 *
 * @return void
 */
function lean_redirects_menu() {
	add_options_page(
		__( 'Lean Redirects', 'lean-redirects' ),
		__( 'Redirects', 'lean-redirects' ),
		'manage_options',
		'lean-redirects',
		'lean_redirects_admin_page'
	);
}

add_filter( 'plugin_action_links_' . plugin_basename( LEAN_REDIRECTS_FILE ), 'lean_redirects_action_links' );

/**
 * Add a "Manage" link to the Plugins page.
 *
 * @param array $links Existing action links.
 * @return array
 */
function lean_redirects_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=lean-redirects' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Manage', 'lean-redirects' ) . '</a>' );
	return $links;
}

/**
 * Process admin actions (add, delete, toggle, import).
 *
 * @return void
 */
function lean_redirects_process_admin_actions() {
	global $wpdb;
	$table = lean_redirects_table_name();

	// Add.
	if ( isset( $_POST['lean_add'] ) && check_admin_referer( 'lean_redirects_nonce' ) ) {
		$from = sanitize_text_field( wp_unslash( $_POST['new_from'] ?? '' ) );
		$to   = sanitize_text_field( wp_unslash( $_POST['new_to'] ?? '' ) );
		$code = intval( $_POST['new_code'] ?? 301 );
		$note = sanitize_text_field( wp_unslash( $_POST['new_note'] ?? '' ) );

		if ( ! in_array( $code, array( 301, 302, 307 ), true ) ) {
			$code = 301;
		}

		if ( $from && $to ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->replace(
				$table,
				array(
					'url_from' => $from,
					'url_to'   => $to,
					'code'     => $code,
					'active'   => 1,
					'note'     => $note,
				),
				array( '%s', '%s', '%d', '%d', '%s' )
			);
			add_settings_error( 'lean_redirects', 'added', __( 'Redirect added.', 'lean-redirects' ), 'updated' );
		}
	}

	// Delete.
	if ( isset( $_GET['lean_delete'] ) && check_admin_referer( 'lean_redirects_delete' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => intval( $_GET['lean_delete'] ) ), array( '%d' ) );
		add_settings_error( 'lean_redirects', 'deleted', __( 'Redirect deleted.', 'lean-redirects' ), 'updated' );
	}

	// Toggle.
	if ( isset( $_GET['lean_toggle'] ) && check_admin_referer( 'lean_redirects_toggle' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET active = 1 - active WHERE id = %d',
				$table,
				intval( $_GET['lean_toggle'] )
			)
		);
	}

	// Import CSV.
	if ( isset( $_POST['lean_import'] ) && check_admin_referer( 'lean_redirects_nonce' ) ) {
		$csv   = sanitize_textarea_field( wp_unslash( $_POST['csv'] ?? '' ) );
		$lines = array_filter( array_map( 'trim', explode( "\n", $csv ) ) );
		$added = 0;
		foreach ( $lines as $line ) {
			$parts = str_getcsv( $line );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->replace(
				$table,
				array(
					'url_from' => sanitize_text_field( trim( $parts[0] ) ),
					'url_to'   => sanitize_text_field( trim( $parts[1] ) ),
					'code'     => isset( $parts[2] ) ? intval( $parts[2] ) : 301,
					'active'   => 1,
				),
				array( '%s', '%s', '%d', '%d' )
			);
			++$added;
		}
		/* translators: %d: number of redirects imported */
		add_settings_error( 'lean_redirects', 'imported', sprintf( __( '%d redirects imported.', 'lean-redirects' ), $added ), 'updated' );
	}
}

/**
 * Render the admin page.
 *
 * @return void
 */
function lean_redirects_admin_page() {
	global $wpdb;
	$table = lean_redirects_table_name();

	// Ensure table exists (in case plugin was manually copied).
	lean_redirects_ensure_table();

	// Process actions.
	lean_redirects_process_admin_actions();

	// Show notices.
	settings_errors( 'lean_redirects' );

	/* --- Query ---------------------------------------------------------- */

	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search/pagination, no state change.
	$paged    = max( 1, intval( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per_page = 50;
	$offset   = ( $paged - 1 ) * $per_page;

	if ( $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE url_from LIKE %s OR url_to LIKE %s OR note LIKE %s',
				$table,
				$like,
				$like,
				$like
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE url_from LIKE %s OR url_to LIKE %s OR note LIKE %s ORDER BY hits DESC, id DESC LIMIT %d OFFSET %d',
				$table,
				$like,
				$like,
				$like,
				$per_page,
				$offset
			)
		);
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY hits DESC, id DESC LIMIT %d OFFSET %d',
				$table,
				$per_page,
				$offset
			)
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$active_count = (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE active = %d', $table, 1 )
	);
	$pages = (int) ceil( $total / $per_page );

	// Render the admin template.
	// When used as mu-plugin, views may be in a subdirectory.
	$views_dir = defined( 'LEAN_REDIRECTS_VIEWS_DIR' ) ? LEAN_REDIRECTS_VIEWS_DIR : __DIR__ . '/views';
	include $views_dir . '/admin-page.php';
}

/* ═══════════════════════════════════════════════════════════════════════════
   REST API — GET / POST / DELETE /wp-json/lean-redirects/v1/redirects
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'lean_redirects_register_api' );

/**
 * Register REST API routes.
 *
 * @return void
 */
function lean_redirects_register_api() {
	$ns = 'lean-redirects/v1';

	register_rest_route(
		$ns,
		'/redirects',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'lean_redirects_api_list',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'lean_redirects_api_add',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'from' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'to'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'code' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 301,
					),
					'note' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => 'lean_redirects_api_delete',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'from' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		)
	);
}

/**
 * REST API: List all redirects.
 *
 * @return array
 */
function lean_redirects_api_list() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', lean_redirects_table_name() )
	);
}

/**
 * REST API: Add a redirect.
 *
 * @param WP_REST_Request $request The request object.
 * @return array|WP_Error
 */
function lean_redirects_api_add( $request ) {
	global $wpdb;
	$table = lean_redirects_table_name();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->replace(
		$table,
		array(
			'url_from' => $request->get_param( 'from' ),
			'url_to'   => $request->get_param( 'to' ),
			'code'     => intval( $request->get_param( 'code' ) ) ?: 301,
			'active'   => 1,
			'note'     => $request->get_param( 'note' ) ?? '',
		),
		array( '%s', '%s', '%d', '%d', '%s' )
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$total = (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
	);
	return array( 'ok' => true, 'total' => $total );
}

/**
 * REST API: Delete a redirect by its "from" path.
 *
 * @param WP_REST_Request $request The request object.
 * @return array|WP_Error
 */
function lean_redirects_api_delete( $request ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted = $wpdb->delete(
		lean_redirects_table_name(),
		array( 'url_from' => $request->get_param( 'from' ) ),
		array( '%s' )
	);

	return array( 'ok' => true, 'deleted' => $deleted );
}
