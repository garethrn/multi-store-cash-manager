<?php
/**
 * Admin dashboard page.
 *
 * Displays statistics, charts, recent activity and quick actions.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the admin dashboard.
 */
function mscm_render_admin_dashboard() {
	if ( ! current_user_can( 'mscm_view_dashboard' ) && ! current_user_can( 'read' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$stores   = MSCM()->db->get_user_stores( get_current_user_id() );
	$stats    = MSCM()->db->get_dashboard_stats();
	$currency = get_option( 'mscm_currency', 'R' );
	$recent   = MSCM()->reports->get_recent_entries( 5 );

	// Activation notice.
	if ( get_transient( 'mscm_activated' ) ) {
		delete_transient( 'mscm_activated' );
		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e( '🎉 Multi-Store Cash Manager activated successfully! Start by adding your stores.', 'multi-store-cash-manager' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=mscm-stores' ) ) . '">' . esc_html__( 'Add Stores →', 'multi-store-cash-manager' ) . '</a>';
		echo '</p></div>';
	}
	?>
	<div class="wrap mscm-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Cash Manager Dashboard', 'multi-store-cash-manager' ); ?></h1>
		<hr class="wp-header-end">

		<!-- Stats Cards -->
		<div class="mscm-stats-grid">
			<div class="mscm-stat-card mscm-stat-primary">
				<div class="mscm-stat-icon">💰</div>
				<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $stats['today_sales'], 2 ) ); ?></div>
				<div class="mscm-stat-label"><?php esc_html_e( "Today's Sales", 'multi-store-cash-manager' ); ?></div>
			</div>
			<div class="mscm-stat-card mscm-stat-success">
				<div class="mscm-stat-icon">📅</div>
				<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $stats['mtd_sales'], 2 ) ); ?></div>
				<div class="mscm-stat-label"><?php esc_html_e( 'Month-to-Date Sales', 'multi-store-cash-manager' ); ?></div>
			</div>
			<div class="mscm-stat-card <?php echo $stats['missing_entries'] > 0 ? 'mscm-stat-warning' : 'mscm-stat-info'; ?>">
				<div class="mscm-stat-icon"><?php echo $stats['missing_entries'] > 0 ? '⚠️' : '✅'; ?></div>
				<div class="mscm-stat-value"><?php echo esc_html( $stats['missing_entries'] ); ?></div>
				<div class="mscm-stat-label"><?php esc_html_e( 'Missing Entries', 'multi-store-cash-manager' ); ?></div>
			</div>
			<div class="mscm-stat-card <?php echo $stats['anomalies'] > 0 ? 'mscm-stat-danger' : 'mscm-stat-success'; ?>">
				<div class="mscm-stat-icon"><?php echo $stats['anomalies'] > 0 ? '🚨' : '✅'; ?></div>
				<div class="mscm-stat-value"><?php echo esc_html( $stats['anomalies'] ); ?></div>
				<div class="mscm-stat-label"><?php esc_html_e( 'Active Anomalies', 'multi-store-cash-manager' ); ?></div>
			</div>
		</div>

		<?php if ( $stats['mtd_target'] > 0 ) : ?>
		<!-- Target Progress -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Monthly Target Progress', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<div class="mscm-progress-info">
					<span><?php echo esc_html( $currency . number_format( $stats['mtd_sales'], 2 ) ); ?> / <?php echo esc_html( $currency . number_format( $stats['mtd_target'], 2 ) ); ?></span>
					<span><?php echo esc_html( $stats['target_progress'] ); ?>%</span>
				</div>
				<div class="mscm-progress-bar">
					<div class="mscm-progress-fill" style="width:<?php echo esc_attr( min( 100, $stats['target_progress'] ) ); ?>%"></div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="mscm-grid-2">
			<!-- Sales Chart -->
			<div class="mscm-card">
				<div class="mscm-card-header">
					<h2><?php esc_html_e( 'Sales This Month', 'multi-store-cash-manager' ); ?></h2>
				</div>
				<div class="mscm-card-body">
					<div class="mscm-chart-container">
						<canvas id="mscm-sales-chart"></canvas>
					</div>
				</div>
			</div>

			<!-- Recent Entries -->
			<div class="mscm-card">
				<div class="mscm-card-header">
					<h2><?php esc_html_e( 'Recent Entries', 'multi-store-cash-manager' ); ?></h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-entries' ) ); ?>" class="mscm-link">
						<?php esc_html_e( 'View All', 'multi-store-cash-manager' ); ?>
					</a>
				</div>
				<div class="mscm-card-body">
					<?php if ( empty( $recent ) ) : ?>
						<p class="mscm-empty"><?php esc_html_e( 'No entries yet.', 'multi-store-cash-manager' ); ?></p>
					<?php else : ?>
						<table class="mscm-compact-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?></th>
									<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
									<th><?php esc_html_e( 'Sales', 'multi-store-cash-manager' ); ?></th>
									<th><?php esc_html_e( 'Status', 'multi-store-cash-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( $entry->entry_date ); ?></td>
										<td><?php echo esc_html( $entry->store_name ); ?></td>
										<td><?php echo esc_html( $currency . number_format( $entry->total_sales, 2 ) ); ?></td>
										<td>
											<span class="mscm-badge mscm-badge-<?php echo esc_attr( $entry->status ); ?>">
												<?php echo esc_html( ucfirst( $entry->status ) ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Store Summary -->
		<?php if ( ! empty( $stores ) ) : ?>
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Store Summary', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<div class="mscm-stores-grid">
					<?php foreach ( $stores as $store ) : ?>
						<?php $store_stats = MSCM()->db->get_dashboard_stats( $store->id ); ?>
						<div class="mscm-store-card">
							<div class="mscm-store-name"><?php echo esc_html( $store->name ); ?></div>
							<div class="mscm-store-location"><?php echo esc_html( $store->location ); ?></div>
							<div class="mscm-store-stats">
								<div class="mscm-store-stat">
									<span class="mscm-store-stat-value"><?php echo esc_html( $currency . number_format( $store_stats['today_sales'], 2 ) ); ?></span>
									<span class="mscm-store-stat-label"><?php esc_html_e( 'Today', 'multi-store-cash-manager' ); ?></span>
								</div>
								<div class="mscm-store-stat">
									<span class="mscm-store-stat-value"><?php echo esc_html( $currency . number_format( $store_stats['mtd_sales'], 2 ) ); ?></span>
									<span class="mscm-store-stat-label"><?php esc_html_e( 'MTD', 'multi-store-cash-manager' ); ?></span>
								</div>
								<?php if ( $store_stats['mtd_target'] > 0 ) : ?>
								<div class="mscm-store-stat" style="grid-column:1/-1;margin-top:6px;">
									<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
										<span><?php esc_html_e( 'Target', 'multi-store-cash-manager' ); ?>: <?php echo esc_html( $currency . number_format( $store_stats['mtd_target'], 2 ) ); ?></span>
										<span style="font-weight:600;"><?php echo esc_html( $store_stats['target_progress'] ); ?>%</span>
									</div>
									<div class="mscm-progress-bar" style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
										<div class="mscm-progress-fill <?php echo $store_stats['target_progress'] >= 100 ? 'mscm-progress-success' : ( $store_stats['target_progress'] >= 75 ? 'mscm-progress-success' : 'mscm-progress-warning' ); ?>"
											style="width:<?php echo esc_attr( min( 100, $store_stats['target_progress'] ) ); ?>%;height:100%;border-radius:3px;">
										</div>
									</div>
								</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Quick Actions -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Quick Actions', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<div class="mscm-quick-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-entries' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">📋</span>
						<span><?php esc_html_e( 'View Entries', 'multi-store-cash-manager' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-reports' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">📊</span>
						<span><?php esc_html_e( 'Generate Report', 'multi-store-cash-manager' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-targets' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">🎯</span>
						<span><?php esc_html_e( 'Sales Targets', 'multi-store-cash-manager' ); ?></span>
					</a>
					<?php if ( current_user_can( 'mscm_manage_all_stores' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-stores' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">🏪</span>
						<span><?php esc_html_e( 'Manage Stores', 'multi-store-cash-manager' ); ?></span>
					</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-anomalies' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">🔍</span>
						<span><?php esc_html_e( 'View Anomalies', 'multi-store-cash-manager' ); ?></span>
					</a>
					<?php if ( current_user_can( 'mscm_manage_settings' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-settings' ) ); ?>" class="mscm-quick-action">
						<span class="mscm-qa-icon">⚙️</span>
						<span><?php esc_html_e( 'Settings', 'multi-store-cash-manager' ); ?></span>
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}
