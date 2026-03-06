<?php
/**
 * Admin menu registration and main admin pages.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all admin menus for the plugin.
 */
function mscm_register_admin_menus() {
	// Main menu.
	add_menu_page(
		__( 'Cash Manager', 'multi-store-cash-manager' ),
		__( 'Cash Manager', 'multi-store-cash-manager' ),
		'read',
		'mscm-dashboard',
		'mscm_admin_dashboard_page',
		'dashicons-money-alt',
		30
	);

	// Dashboard submenu.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Dashboard', 'multi-store-cash-manager' ),
		__( 'Dashboard', 'multi-store-cash-manager' ),
		'read',
		'mscm-dashboard',
		'mscm_admin_dashboard_page'
	);

	// Entries.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Daily Entries', 'multi-store-cash-manager' ),
		__( 'Daily Entries', 'multi-store-cash-manager' ),
		'mscm_view_store_entries',
		'mscm-entries',
		'mscm_admin_entries_page'
	);

	// Stores (admin and managers).
	add_submenu_page(
		'mscm-dashboard',
		__( 'Manage Stores', 'multi-store-cash-manager' ),
		__( 'Stores', 'multi-store-cash-manager' ),
		'mscm_manage_store',
		'mscm-stores',
		'mscm_admin_stores_page'
	);

	// Reports.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Reports', 'multi-store-cash-manager' ),
		__( 'Reports', 'multi-store-cash-manager' ),
		'mscm_view_reports',
		'mscm-reports',
		'mscm_admin_reports_page'
	);

	// Sales Targets.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Sales Targets', 'multi-store-cash-manager' ),
		__( 'Sales Targets', 'multi-store-cash-manager' ),
		'mscm_view_targets',
		'mscm-targets',
		'mscm_admin_targets_page'
	);

	// Anomalies.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Anomalies', 'multi-store-cash-manager' ),
		__( 'Anomalies', 'multi-store-cash-manager' ),
		'mscm_view_anomalies',
		'mscm-anomalies',
		'mscm_admin_anomalies_page'
	);

	// Settings.
	add_submenu_page(
		'mscm-dashboard',
		__( 'Settings', 'multi-store-cash-manager' ),
		__( 'Settings', 'multi-store-cash-manager' ),
		'mscm_manage_settings',
		'mscm-settings',
		'mscm_admin_settings_page'
	);
}

/**
 * Render the admin dashboard page.
 */
function mscm_admin_dashboard_page() {
	require_once MSCM_PLUGIN_DIR . 'admin/admin-dashboard.php';
	mscm_render_admin_dashboard();
}

/**
 * Render the daily entries admin page.
 */
function mscm_admin_entries_page() {
	if ( ! current_user_can( 'mscm_view_store_entries' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$stores = MSCM()->db->get_user_stores( get_current_user_id() );

	$filter_store = absint( $_GET['store_id'] ?? 0 );
	$filter_from  = sanitize_text_field( $_GET['date_from'] ?? wp_date( 'Y-m-01' ) );
	$filter_to    = sanitize_text_field( $_GET['date_to'] ?? wp_date( 'Y-m-d' ) );
	$filter_status = sanitize_text_field( $_GET['status'] ?? '' );
	$paged        = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$per_page     = absint( get_option( 'mscm_entries_per_page', 20 ) );

	$query_args = array(
		'date_from' => $filter_from,
		'date_to'   => $filter_to,
		'limit'     => $per_page,
		'offset'    => ( $paged - 1 ) * $per_page,
	);

	if ( $filter_store ) {
		$query_args['store_id'] = $filter_store;
	}
	if ( $filter_status ) {
		$query_args['status'] = $filter_status;
	}

	$entries  = MSCM()->db->get_entries( $query_args );
	$currency = get_option( 'mscm_currency', 'R' );
	?>
	<div class="wrap mscm-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Daily Entries', 'multi-store-cash-manager' ); ?></h1>
		<hr class="wp-header-end">

		<div class="mscm-filter-bar">
			<form method="get" action="">
				<input type="hidden" name="page" value="mscm-entries">
				<div class="mscm-filter-row">
					<select name="store_id">
						<option value=""><?php esc_html_e( 'All Stores', 'multi-store-cash-manager' ); ?></option>
						<?php foreach ( $stores as $store ) : ?>
							<option value="<?php echo esc_attr( $store->id ); ?>" <?php selected( $filter_store, $store->id ); ?>>
								<?php echo esc_html( $store->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="date" name="date_from" value="<?php echo esc_attr( $filter_from ); ?>">
					<span><?php esc_html_e( 'to', 'multi-store-cash-manager' ); ?></span>
					<input type="date" name="date_to" value="<?php echo esc_attr( $filter_to ); ?>">
					<select name="status">
						<option value=""><?php esc_html_e( 'All Statuses', 'multi-store-cash-manager' ); ?></option>
						<option value="pending" <?php selected( $filter_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'multi-store-cash-manager' ); ?></option>
						<option value="verified" <?php selected( $filter_status, 'verified' ); ?>><?php esc_html_e( 'Verified', 'multi-store-cash-manager' ); ?></option>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'multi-store-cash-manager' ); ?></button>
				</div>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped mscm-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Cash to Bank', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Discrepancy', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Submitted By', 'multi-store-cash-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'multi-store-cash-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) ) : ?>
					<tr>
						<td colspan="8" style="text-align:center;padding:20px;">
							<?php esc_html_e( 'No entries found.', 'multi-store-cash-manager' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry->entry_date ); ?></td>
							<td><?php echo esc_html( $entry->store_name ); ?></td>
							<td><?php echo esc_html( $currency . number_format( $entry->total_sales, 2 ) ); ?></td>
							<td><?php echo esc_html( $currency . number_format( $entry->cash_to_bank, 2 ) ); ?></td>
							<td class="<?php echo floatval( $entry->discrepancy ) > 50 ? 'mscm-negative' : ''; ?>">
								<?php echo esc_html( $currency . number_format( $entry->discrepancy, 2 ) ); ?>
							</td>
							<td>
								<span class="mscm-badge mscm-badge-<?php echo esc_attr( $entry->status ); ?>">
									<?php echo esc_html( ucfirst( $entry->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $entry->user_name ); ?></td>
							<td>
								<?php if ( current_user_can( 'mscm_verify_entry' ) && 'pending' === $entry->status ) : ?>
									<button class="button button-small mscm-verify-entry"
										data-entry-id="<?php echo esc_attr( $entry->id ); ?>">
										<?php esc_html_e( 'Verify', 'multi-store-cash-manager' ); ?>
									</button>
								<?php endif; ?>
								<a href="#" class="button button-small mscm-view-entry"
									data-entry-id="<?php echo esc_attr( $entry->id ); ?>">
									<?php esc_html_e( 'View', 'multi-store-cash-manager' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Render the anomalies admin page.
 */
function mscm_admin_anomalies_page() {
	if ( ! current_user_can( 'mscm_view_anomalies' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$anomalies = MSCM()->db->get_anomalies(
		array(
			'is_resolved' => 0,
			'limit'       => 50,
		)
	);
	?>
	<div class="wrap mscm-admin">
		<h1><?php esc_html_e( 'Anomalies', 'multi-store-cash-manager' ); ?></h1>

		<?php if ( empty( $anomalies ) ) : ?>
			<div class="mscm-alert mscm-alert-success">
				<strong><?php esc_html_e( '✅ No active anomalies!', 'multi-store-cash-manager' ); ?></strong>
				<?php esc_html_e( 'All looks good.', 'multi-store-cash-manager' ); ?>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped mscm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
						<th><?php esc_html_e( 'Type', 'multi-store-cash-manager' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'multi-store-cash-manager' ); ?></th>
						<th><?php esc_html_e( 'Description', 'multi-store-cash-manager' ); ?></th>
						<th><?php esc_html_e( 'Detected', 'multi-store-cash-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'multi-store-cash-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $anomalies as $anomaly ) : ?>
						<tr>
							<td><?php echo esc_html( $anomaly->store_name ); ?></td>
							<td><?php echo esc_html( str_replace( '_', ' ', $anomaly->anomaly_type ) ); ?></td>
							<td>
								<span class="mscm-badge mscm-badge-<?php echo esc_attr( $anomaly->severity ); ?>">
									<?php echo esc_html( ucfirst( $anomaly->severity ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $anomaly->description ); ?></td>
							<td><?php echo esc_html( $anomaly->created_at ); ?></td>
							<td>
								<?php if ( current_user_can( 'mscm_resolve_anomalies' ) ) : ?>
									<button class="button button-small mscm-resolve-anomaly"
										data-anomaly-id="<?php echo esc_attr( $anomaly->id ); ?>">
										<?php esc_html_e( 'Resolve', 'multi-store-cash-manager' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render admin stores page.
 */
function mscm_admin_stores_page() {
	require_once MSCM_PLUGIN_DIR . 'admin/admin-stores.php';
	mscm_render_stores_page();
}

/**
 * Render admin reports page.
 */
function mscm_admin_reports_page() {
	require_once MSCM_PLUGIN_DIR . 'admin/admin-reports.php';
	mscm_render_reports_page();
}

/**
 * Render admin targets page.
 */
function mscm_admin_targets_page() {
	require_once MSCM_PLUGIN_DIR . 'admin/admin-targets.php';
	mscm_render_targets_page();
}

/**
 * Render admin settings page.
 */
function mscm_admin_settings_page() {
	require_once MSCM_PLUGIN_DIR . 'admin/admin-settings.php';
	mscm_render_settings_page();
}
