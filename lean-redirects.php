<?php
/**
 * Plugin Name: Lean Redirects
 * Plugin URI:  https://cristiantala.com/herramientas/lean-redirects/
 * Description: Ultra-lightweight 301/302/307 redirects. One indexed DB query per request. No bloat.
 * Version:     1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author:      Cristian Tala
 * Author URI:  https://cristiantala.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEAN_REDIRECTS_VERSION', '1.0.0' );
define( 'LEAN_REDIRECTS_TABLE',   'lean_redirects' );

/* ═══════════════════════════════════════════════════════════════════════════
   TABLE SETUP
   ═══════════════════════════════════════════════════════════════════════════ */

register_activation_hook( __FILE__, 'lean_redirects_activate' );

function lean_redirects_activate() {
	lean_redirects_ensure_table();
}

function lean_redirects_table_name() {
	global $wpdb;
	return $wpdb->prefix . LEAN_REDIRECTS_TABLE;
}

function lean_redirects_ensure_table() {
	global $wpdb;
	$table   = lean_redirects_table_name();
	$charset = $wpdb->get_charset_collate();

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$table} (
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
	) {$charset};" );
}

/* ═══════════════════════════════════════════════════════════════════════════
   FRONTEND — Process redirects (1 indexed query)
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'template_redirect', 'lean_redirects_process', 1 );

function lean_redirects_process() {
	global $wpdb;
	$table = lean_redirects_table_name();
	$path  = rtrim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

	// Try exact match (with and without trailing slash).
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, url_to, code FROM {$table} WHERE active = 1 AND (url_from = %s OR url_from = %s) LIMIT 1",
			$path,
			$path . '/'
		)
	);

	if ( ! $row ) {
		return;
	}

	// Count hit (fire-and-forget, non-blocking).
	$wpdb->query(
		$wpdb->prepare( "UPDATE {$table} SET hits = hits + 1 WHERE id = %d", $row->id )
	);

	$target = $row->url_to;

	// If target is a relative path, make it absolute.
	if ( strpos( $target, 'http' ) !== 0 ) {
		$target = home_url( $target );
	}

	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	wp_redirect( $target, (int) $row->code );
	exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ADMIN — Settings page under Settings → Redirects
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'lean_redirects_menu' );

function lean_redirects_menu() {
	add_options_page(
		__( 'Lean Redirects', 'lean-redirects' ),
		__( 'Redirects', 'lean-redirects' ),
		'manage_options',
		'lean-redirects',
		'lean_redirects_admin_page'
	);
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lean_redirects_action_links' );

function lean_redirects_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=lean-redirects' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Manage', 'lean-redirects' ) . '</a>' );
	return $links;
}

function lean_redirects_admin_page() {
	global $wpdb;
	$table = lean_redirects_table_name();

	// Ensure table exists (in case plugin was manually copied).
	lean_redirects_ensure_table();

	/* --- Actions -------------------------------------------------------- */

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
			$wpdb->replace(
				$table,
				array( 'url_from' => $from, 'url_to' => $to, 'code' => $code, 'active' => 1, 'note' => $note ),
				array( '%s', '%s', '%d', '%d', '%s' )
			);
			echo '<div class="updated"><p>' . esc_html__( 'Redirect added.', 'lean-redirects' ) . '</p></div>';
		}
	}

	// Delete.
	if ( isset( $_GET['lean_delete'] ) && check_admin_referer( 'lean_redirects_delete' ) ) {
		$wpdb->delete( $table, array( 'id' => intval( $_GET['lean_delete'] ) ), array( '%d' ) );
		echo '<div class="updated"><p>' . esc_html__( 'Redirect deleted.', 'lean-redirects' ) . '</p></div>';
	}

	// Toggle.
	if ( isset( $_GET['lean_toggle'] ) && check_admin_referer( 'lean_redirects_toggle' ) ) {
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET active = 1 - active WHERE id = %d", intval( $_GET['lean_toggle'] ) )
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
			$wpdb->replace(
				$table,
				array(
					'url_from' => trim( $parts[0] ),
					'url_to'   => trim( $parts[1] ),
					'code'     => isset( $parts[2] ) ? intval( $parts[2] ) : 301,
					'active'   => 1,
				),
				array( '%s', '%s', '%d', '%d' )
			);
			++$added;
		}
		/* translators: %d: number of redirects imported */
		echo '<div class="updated"><p>' . sprintf( esc_html__( '%d redirects imported.', 'lean-redirects' ), $added ) . '</p></div>';
	}

	/* --- Query ---------------------------------------------------------- */

	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
	$per_page = 50;
	$offset   = ( $paged - 1 ) * $per_page;

	if ( $search ) {
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		$where = $wpdb->prepare( 'WHERE url_from LIKE %s OR url_to LIKE %s OR note LIKE %s', $like, $like, $like );
	} else {
		$where = '';
	}

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	$rows  = $wpdb->get_results(
		"SELECT * FROM {$table} {$where} ORDER BY hits DESC, id DESC LIMIT {$per_page} OFFSET {$offset}"
	);
	$active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE active = 1" );
	$pages = ceil( $total / $per_page );

	?>
	<div class="wrap">
		<h1>🔀 <?php esc_html_e( 'Lean Redirects', 'lean-redirects' ); ?>
			<small style="font-weight:normal;color:#666;">
				<?php
				/* translators: 1: active count, 2: total count */
				printf( esc_html__( '%1$d active / %2$d total', 'lean-redirects' ), $active_count, $total );
				?>
			</small>
		</h1>

		<!-- Search -->
		<form method="get" style="margin-bottom:1em;">
			<input type="hidden" name="page" value="lean-redirects">
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'lean-redirects' ); ?>" style="width:300px;">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'lean-redirects' ); ?>">
			<?php if ( $search ) : ?>
				<a href="?page=lean-redirects" class="button"><?php esc_html_e( 'Clear', 'lean-redirects' ); ?></a>
			<?php endif; ?>
		</form>

		<!-- Add new -->
		<form method="post" style="background:#f9f9f9;padding:12px;margin-bottom:1em;border:1px solid #ddd;">
			<?php wp_nonce_field( 'lean_redirects_nonce' ); ?>
			<strong><?php esc_html_e( 'Add redirect:', 'lean-redirects' ); ?></strong><br>
			<input type="text" name="new_from" placeholder="/old/path/" style="width:220px;" required>
			→
			<input type="text" name="new_to" placeholder="/new/path/ or https://..." style="width:220px;" required>
			<select name="new_code">
				<option value="301">301 <?php esc_html_e( 'Permanent', 'lean-redirects' ); ?></option>
				<option value="302">302 <?php esc_html_e( 'Temporary', 'lean-redirects' ); ?></option>
				<option value="307">307 <?php esc_html_e( 'Temporary (strict)', 'lean-redirects' ); ?></option>
			</select>
			<input type="text" name="new_note" placeholder="<?php esc_attr_e( 'Note (optional)', 'lean-redirects' ); ?>" style="width:150px;">
			<input type="submit" name="lean_add" class="button button-primary" value="<?php esc_attr_e( 'Add', 'lean-redirects' ); ?>">
		</form>

		<!-- Table -->
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'From', 'lean-redirects' ); ?></th>
					<th><?php esc_html_e( 'To', 'lean-redirects' ); ?></th>
					<th><?php esc_html_e( 'Code', 'lean-redirects' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'lean-redirects' ); ?></th>
					<th><?php esc_html_e( 'Note', 'lean-redirects' ); ?></th>
					<th><?php esc_html_e( 'Active', 'lean-redirects' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="7" style="text-align:center;color:#999;"><?php esc_html_e( 'No redirects found.', 'lean-redirects' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) :
				$toggle_url = wp_nonce_url( "?page=lean-redirects&lean_toggle={$r->id}", 'lean_redirects_toggle' );
				$delete_url = wp_nonce_url( "?page=lean-redirects&lean_delete={$r->id}", 'lean_redirects_delete' );
			?>
				<tr style="<?php echo $r->active ? '' : 'opacity:0.5;'; ?>">
					<td><code style="font-size:12px;"><?php echo esc_html( $r->url_from ); ?></code></td>
					<td><code style="font-size:12px;"><?php echo esc_html( $r->url_to ); ?></code></td>
					<td><?php echo intval( $r->code ); ?></td>
					<td><?php echo number_format_i18n( $r->hits ); ?></td>
					<td style="font-size:12px;color:#666;"><?php echo esc_html( $r->note ); ?></td>
					<td><a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo $r->active ? '✅' : '❌'; ?></a></td>
					<td>
						<a href="<?php echo esc_url( $delete_url ); ?>"
						   onclick="return confirm('<?php esc_attr_e( 'Delete this redirect?', 'lean-redirects' ); ?>')"
						   style="color:#a00;"><?php esc_html_e( 'Delete', 'lean-redirects' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post( paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				) ) );
				?>
			</div>
		</div>
		<?php endif; ?>

		<hr>

		<!-- Import / Export -->
		<div style="display:flex;gap:2em;flex-wrap:wrap;">
			<div>
				<h3>📥 <?php esc_html_e( 'Import CSV', 'lean-redirects' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'lean_redirects_nonce' ); ?>
					<textarea name="csv" rows="4" cols="50" style="font-family:monospace;font-size:12px;"
						placeholder="/old/path/,/new/path/,301"></textarea>
					<br>
					<input type="submit" name="lean_import" class="button" value="<?php esc_attr_e( 'Import', 'lean-redirects' ); ?>">
					<span style="color:#666;font-size:12px;">
						<?php esc_html_e( 'Format: from,to,code (code optional, defaults to 301)', 'lean-redirects' ); ?>
					</span>
				</form>
			</div>
			<div>
				<h3>📤 <?php esc_html_e( 'Export', 'lean-redirects' ); ?></h3>
				<?php
				$all_active = $wpdb->get_results( "SELECT url_from, url_to, code FROM {$table} WHERE active = 1 ORDER BY id" );
				?>
				<textarea readonly rows="4" cols="50" style="font-family:monospace;font-size:12px;"><?php
					foreach ( $all_active as $r ) {
						echo esc_textarea( "{$r->url_from},{$r->url_to},{$r->code}" ) . "\n";
					}
				?></textarea>
			</div>
		</div>

		<!-- Footer -->
		<hr>
		<p style="color:#999;font-size:12px;">
			Lean Redirects v<?php echo esc_html( LEAN_REDIRECTS_VERSION ); ?> —
			<?php
			printf(
				/* translators: %s: link to author site */
				esc_html__( 'Made with %1$s from Chile by %2$s', 'lean-redirects' ),
				'❤️',
				'<a href="https://cristiantala.com" target="_blank" rel="noopener">cristiantala.com</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   REST API — GET / POST / DELETE /wp-json/lean-redirects/v1/redirects
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'lean_redirects_register_api' );

function lean_redirects_register_api() {
	$ns = 'lean-redirects/v1';

	register_rest_route( $ns, '/redirects', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'lean_redirects_api_list',
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'lean_redirects_api_add',
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => 'lean_redirects_api_delete',
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		),
	) );
}

function lean_redirects_api_list() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . lean_redirects_table_name() . ' ORDER BY id DESC' );
}

function lean_redirects_api_add( $request ) {
	global $wpdb;
	$table = lean_redirects_table_name();
	$from  = sanitize_text_field( $request->get_param( 'from' ) );
	$to    = sanitize_text_field( $request->get_param( 'to' ) );
	$code  = intval( $request->get_param( 'code' ) ) ?: 301;
	$note  = sanitize_text_field( $request->get_param( 'note' ) ?? '' );

	if ( ! $from || ! $to ) {
		return new WP_Error( 'missing_params', 'from and to are required.', array( 'status' => 400 ) );
	}

	$wpdb->replace(
		$table,
		array( 'url_from' => $from, 'url_to' => $to, 'code' => $code, 'active' => 1, 'note' => $note ),
		array( '%s', '%s', '%d', '%d', '%s' )
	);

	$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );
	return array( 'ok' => true, 'total' => $total );
}

function lean_redirects_api_delete( $request ) {
	global $wpdb;
	$from = sanitize_text_field( $request->get_param( 'from' ) );

	if ( ! $from ) {
		return new WP_Error( 'missing_params', 'from is required.', array( 'status' => 400 ) );
	}

	$deleted = $wpdb->delete( lean_redirects_table_name(), array( 'url_from' => $from ), array( '%s' ) );
	return array( 'ok' => true, 'deleted' => $deleted );
}

/* ═══════════════════════════════════════════════════════════════════════════
   UNINSTALL — Clean up table on plugin deletion
   ═══════════════════════════════════════════════════════════════════════════ */

register_uninstall_hook( __FILE__, 'lean_redirects_uninstall' );

function lean_redirects_uninstall() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . LEAN_REDIRECTS_TABLE );
}
