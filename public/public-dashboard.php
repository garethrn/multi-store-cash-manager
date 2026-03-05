<?php
/**
 * User dashboard shortcode.
 *
 * Shortcode: [mscm_dashboard]
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the user dashboard shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function mscm_dashboard_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="mscm-login-notice"><p>' .
			wp_kses_post( sprintf(
				/* translators: login URL */
				__( 'Please <a href="%s">log in</a> to view your dashboard.', 'multi-store-cash-manager' ),
				esc_url( wp_login_url( get_permalink() ) )
			) ) .
			'</p></div>';
	}

	$atts = shortcode_atts(
		array(
			'show_chart'  => 'yes',
			'show_stores' => 'yes',
		),
		$atts,
		'mscm_dashboard'
	);

	$user_id  = get_current_user_id();
	$stores   = MSCM()->db->get_user_stores( $user_id );
	$stats    = MSCM()->db->get_dashboard_stats();
	$currency = get_option( 'mscm_currency', 'R' );
	$today    = current_time( 'Y-m-d' );

	ob_start();
	?>
	<div class="mscm-dashboard">
		<div class="mscm-stats-row">
			<div class="mscm-stat-box">
				<div class="mscm-stat-number"><?php echo esc_html( $currency . number_format( $stats['today_sales'], 2 ) ); ?></div>
				<div class="mscm-stat-title"><?php esc_html_e( "Today's Sales", 'multi-store-cash-manager' ); ?></div>
			</div>
			<div class="mscm-stat-box">
				<div class="mscm-stat-number"><?php echo esc_html( $currency . number_format( $stats['mtd_sales'], 2 ) ); ?></div>
				<div class="mscm-stat-title"><?php esc_html_e( 'Month to Date', 'multi-store-cash-manager' ); ?></div>
			</div>
			<?php if ( $stats['mtd_target'] > 0 ) : ?>
			<div class="mscm-stat-box">
				<div class="mscm-stat-number"><?php echo esc_html( $stats['target_progress'] . '%' ); ?></div>
				<div class="mscm-stat-title"><?php esc_html_e( 'Target Progress', 'multi-store-cash-manager' ); ?></div>
			</div>
			<?php endif; ?>
			<?php if ( $stats['missing_entries'] > 0 ) : ?>
			<div class="mscm-stat-box mscm-stat-warning">
				<div class="mscm-stat-number"><?php echo esc_html( $stats['missing_entries'] ); ?></div>
				<div class="mscm-stat-title"><?php esc_html_e( 'Missing Entries', 'multi-store-cash-manager' ); ?></div>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $stores ) && 'yes' === $atts['show_stores'] ) : ?>
		<div class="mscm-section">
			<h3><?php esc_html_e( 'Your Stores', 'multi-store-cash-manager' ); ?></h3>
			<div class="mscm-store-cards">
				<?php foreach ( $stores as $store ) : ?>
					<?php
					$store_stats   = MSCM()->db->get_dashboard_stats( $store->id );
					// Check if today's entry is submitted.
					global $wpdb;
					$entries_table = $wpdb->prefix . 'mscm_daily_entries';
					$today_entry   = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$entries_table} WHERE store_id = %d AND entry_date = %s",
							$store->id,
							$today
						)
					);
					?>
					<div class="mscm-store-card-public">
						<div class="mscm-store-card-header">
							<h4><?php echo esc_html( $store->name ); ?></h4>
							<?php if ( $store->location ) : ?>
								<span class="mscm-store-location">📍 <?php echo esc_html( $store->location ); ?></span>
							<?php endif; ?>
						</div>
						<div class="mscm-store-card-stats">
							<div class="mscm-store-today">
								<span class="mscm-label"><?php esc_html_e( "Today's Sales:", 'multi-store-cash-manager' ); ?></span>
								<span class="mscm-value"><?php echo esc_html( $currency . number_format( $store_stats['today_sales'], 2 ) ); ?></span>
							</div>
							<div class="mscm-store-mtd">
								<span class="mscm-label"><?php esc_html_e( 'MTD:', 'multi-store-cash-manager' ); ?></span>
								<span class="mscm-value"><?php echo esc_html( $currency . number_format( $store_stats['mtd_sales'], 2 ) ); ?></span>
							</div>
						</div>
						<div class="mscm-store-card-footer">
							<?php if ( $today_entry ) : ?>
								<span class="mscm-entry-status mscm-entry-submitted">
									✅ <?php esc_html_e( "Today's entry submitted", 'multi-store-cash-manager' ); ?>
								</span>
							<?php elseif ( current_user_can( 'mscm_submit_entry' ) ) : ?>
								<span class="mscm-entry-status mscm-entry-missing">
									⚠️ <?php esc_html_e( "Today's entry not submitted", 'multi-store-cash-manager' ); ?>
								</span>
								<a href="<?php echo esc_url( add_query_arg( 'store', $store->id, get_permalink( get_option( 'mscm_eod_page_id' ) ) ) ); ?>"
									class="mscm-btn mscm-btn-primary mscm-btn-sm">
									<?php esc_html_e( 'Submit Entry', 'multi-store-cash-manager' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Quick Links -->
		<div class="mscm-section">
			<h3><?php esc_html_e( 'Quick Actions', 'multi-store-cash-manager' ); ?></h3>
			<div class="mscm-quick-links">
				<?php if ( current_user_can( 'mscm_submit_entry' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/end-of-day/' ) ); ?>" class="mscm-quick-link">
						📝 <?php esc_html_e( 'Submit Daily Entry', 'multi-store-cash-manager' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/entry-history/' ) ); ?>" class="mscm-quick-link">
					📋 <?php esc_html_e( 'Entry History', 'multi-store-cash-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/sales-targets/' ) ); ?>" class="mscm-quick-link">
					🎯 <?php esc_html_e( 'Sales Targets', 'multi-store-cash-manager' ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
