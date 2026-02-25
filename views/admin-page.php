<?php
/**
 * Admin page template for Lean Redirects.
 *
 * @package LeanRedirects
 *
 * Variables available from the calling function:
 *
 * @var array  $rows         Current page of redirect rows.
 * @var int    $total        Total redirects matching the query.
 * @var int    $active_count Total active redirects.
 * @var int    $paged        Current page number.
 * @var int    $pages        Total pages.
 * @var string $search       Current search query.
 * @var string $table        Database table name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
?>
<div class="wrap">
	<h1>
		<?php esc_html_e( 'Lean Redirects', 'lean-redirects' ); ?>
		<small style="font-weight:normal;color:#666;">
			<?php
			/* translators: 1: active redirect count, 2: total redirect count */
			printf( esc_html__( '%1$d active / %2$d total', 'lean-redirects' ), absint( $active_count ), absint( $total ) );
			?>
		</small>
	</h1>

	<!-- Search -->
	<form method="get" style="margin-bottom:1em;">
		<input type="hidden" name="page" value="lean-redirects">
		<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Search redirects...', 'lean-redirects' ); ?>" style="width:300px;">
		<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'lean-redirects' ); ?>">
		<?php if ( $search ) : ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=lean-redirects' ) ); ?>" class="button">
				<?php esc_html_e( 'Clear', 'lean-redirects' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<!-- Add new -->
	<form method="post" style="background:#f9f9f9;padding:12px;margin-bottom:1em;border:1px solid #ddd;">
		<?php wp_nonce_field( 'lean_redirects_nonce' ); ?>
		<strong><?php esc_html_e( 'Add redirect:', 'lean-redirects' ); ?></strong><br>
		<label>
			<input type="text" name="new_from" placeholder="/old/path/" style="width:220px;" required>
		</label>
		→
		<label>
			<input type="text" name="new_to" placeholder="/new/path/ or https://..." style="width:220px;" required>
		</label>
		<label>
			<select name="new_code">
				<option value="301">301 <?php esc_html_e( 'Permanent', 'lean-redirects' ); ?></option>
				<option value="302">302 <?php esc_html_e( 'Temporary', 'lean-redirects' ); ?></option>
				<option value="307">307 <?php esc_html_e( 'Temporary (strict)', 'lean-redirects' ); ?></option>
			</select>
		</label>
		<label>
			<input type="text" name="new_note"
				placeholder="<?php esc_attr_e( 'Note (optional)', 'lean-redirects' ); ?>" style="width:150px;">
		</label>
		<input type="submit" name="lean_add" class="button button-primary"
			value="<?php esc_attr_e( 'Add', 'lean-redirects' ); ?>">
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
			<tr>
				<td colspan="7" style="text-align:center;color:#999;">
					<?php esc_html_e( 'No redirects found.', 'lean-redirects' ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<?php foreach ( $rows as $lean_redirects_r ) :
			$lean_redirects_toggle_url = wp_nonce_url(
				add_query_arg( array( 'page' => 'lean-redirects', 'lean_toggle' => $lean_redirects_r->id ), admin_url( 'options-general.php' ) ),
				'lean_redirects_toggle'
			);
			$lean_redirects_delete_url = wp_nonce_url(
				add_query_arg( array( 'page' => 'lean-redirects', 'lean_delete' => $lean_redirects_r->id ), admin_url( 'options-general.php' ) ),
				'lean_redirects_delete'
			);
		?>
			<tr style="<?php echo $lean_redirects_r->active ? '' : 'opacity:0.5;'; ?>">
				<td><code style="font-size:12px;"><?php echo esc_html( $lean_redirects_r->url_from ); ?></code></td>
				<td><code style="font-size:12px;"><?php echo esc_html( $lean_redirects_r->url_to ); ?></code></td>
				<td><?php echo intval( $lean_redirects_r->code ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $lean_redirects_r->hits ) ); ?></td>
				<td style="font-size:12px;color:#666;"><?php echo esc_html( $lean_redirects_r->note ); ?></td>
				<td>
					<a href="<?php echo esc_url( $lean_redirects_toggle_url ); ?>">
						<?php echo $lean_redirects_r->active ? '✅' : '❌'; ?>
					</a>
				</td>
				<td>
					<a href="<?php echo esc_url( $lean_redirects_delete_url ); ?>"
					   onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect?', 'lean-redirects' ) ); ?>')"
					   style="color:#a00;">
						<?php esc_html_e( 'Delete', 'lean-redirects' ); ?>
					</a>
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
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>

	<hr>

	<!-- Import / Export -->
	<div style="display:flex;gap:2em;flex-wrap:wrap;">
		<div>
			<h3><?php esc_html_e( 'Import CSV', 'lean-redirects' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'lean_redirects_nonce' ); ?>
				<textarea name="csv" rows="4" cols="50" style="font-family:monospace;font-size:12px;"
					placeholder="/old/path/,/new/path/,301"></textarea>
				<br>
				<input type="submit" name="lean_import" class="button"
					value="<?php esc_attr_e( 'Import', 'lean-redirects' ); ?>">
				<span style="color:#666;font-size:12px;">
					<?php esc_html_e( 'Format: from,to,code (code optional, defaults to 301)', 'lean-redirects' ); ?>
				</span>
			</form>
		</div>
		<div>
			<h3><?php esc_html_e( 'Export', 'lean-redirects' ); ?></h3>
			<?php
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$lean_redirects_all_active = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT url_from, url_to, code FROM %i WHERE active = %d ORDER BY id',
					$table,
					1
				)
			);
			?>
			<textarea readonly rows="4" cols="50" style="font-family:monospace;font-size:12px;"><?php
				foreach ( $lean_redirects_all_active as $lean_redirects_r ) {
					echo esc_textarea( "{$lean_redirects_r->url_from},{$lean_redirects_r->url_to},{$lean_redirects_r->code}" ) . "\n";
				}
			?></textarea>
		</div>
	</div>

	<!-- Footer -->
	<hr>
	<p style="color:#999;font-size:12px;">
		<?php echo 'Lean Redirects v' . esc_html( LEAN_REDIRECTS_VERSION ); ?> —
		<?php
		printf(
			/* translators: 1: heart emoji, 2: author website link */
			esc_html__( 'Made with %1$s from Chile by %2$s', 'lean-redirects' ),
			'❤️',
			'<a href="https://cristiantala.com" target="_blank" rel="noopener">cristiantala.com</a>'
		);
		?>
	</p>
</div>
