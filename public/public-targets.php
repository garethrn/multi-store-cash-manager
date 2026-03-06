<?php
/**
 * Sales targets public shortcode.
 *
 * Shortcode: [mscm_targets]
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the sales targets shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function mscm_targets_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="mscm-login-notice"><p>' .
			wp_kses_post( sprintf(
				/* translators: login URL */
				__( 'Please <a href="%s">log in</a> to view sales targets.', 'multi-store-cash-manager' ),
				esc_url( wp_login_url( get_permalink() ) )
			) ) .
			'</p></div>';
	}

	if ( ! current_user_can( 'mscm_view_targets' ) ) {
		return '<div class="mscm-error"><p>' . esc_html__( 'You do not have permission to view targets.', 'multi-store-cash-manager' ) . '</p></div>';
	}

	$atts = shortcode_atts(
		array(
			'year'  => current_time( 'Y' ),
			'month' => current_time( 'n' ),
		),
		$atts,
		'mscm_targets'
	);

	$year     = absint( $atts['year'] );
	$month    = absint( $atts['month'] );
	$currency = get_option( 'mscm_currency', 'R' );

	$month_start = sprintf( '%04d-%02d-01', $year, $month );
	$month_end   = wp_date( 'Y-m-t', strtotime( $month_start ) );
	$today       = current_time( 'Y-m-d' );
	$period_end  = min( $today, $month_end );

	$report = MSCM()->reports->targets_report( 0, $month_start, $period_end );
	$rows   = $report['rows'] ?? array();

	$month_names = array(
		1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
		5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
		9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
	);

	ob_start();
	?>
	<div class="mscm-targets-wrapper">
		<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
			<h2 class="mscm-section-title" style="margin:0;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: month name, 2: year */
						__( '%1$s %2$s — Sales Targets', 'multi-store-cash-manager' ),
						$month_names[ $month ] ?? '',
						$year
					)
				);
				?>
			</h2>
			<?php if ( current_user_can( 'mscm_view_reports' ) || current_user_can( 'manage_options' ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url(
				add_query_arg(
					array(
						'action'    => 'mscm_print_report',
						'type'      => 'targets',
						'store_id'  => 0,
						'date_from' => $month_start,
						'date_to'   => $period_end,
					),
					admin_url( 'admin-post.php' )
				),
				'mscm_print_report'
			) ); ?>" target="_blank" class="mscm-btn mscm-btn-outline mscm-btn-sm">
				🖨️ <?php esc_html_e( 'Print to PDF', 'multi-store-cash-manager' ); ?>
			</a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<div class="mscm-notice">
				<p><?php esc_html_e( 'No targets have been set for this period.', 'multi-store-cash-manager' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $rows as $row ) : ?>
				<div class="mscm-target-item">
					<div class="mscm-target-item-header">
						<div class="mscm-target-item-store"><?php echo esc_html( $row['store_name'] ); ?></div>
						<div class="mscm-target-item-pct <?php echo $row['percentage'] >= 100 ? 'mscm-achieved' : ( $row['on_track'] ? 'mscm-on-track' : 'mscm-behind' ); ?>">
							<?php
							if ( $row['percentage'] >= 100 ) {
								echo '🏆 ';
							}
							echo esc_html( $row['percentage'] . '%' );
							?>
						</div>
					</div>
					<div class="mscm-progress-bar">
						<div class="mscm-progress-fill <?php echo $row['percentage'] >= 100 ? 'mscm-progress-achieved' : ( $row['on_track'] ? 'mscm-progress-success' : 'mscm-progress-warning' ); ?>"
							style="width:<?php echo esc_attr( min( 100, $row['percentage'] ) ); ?>%">
						</div>
					</div>
					<div class="mscm-target-item-details">
						<span><?php echo esc_html( $currency . number_format( $row['actual'], 2 ) . ' / ' . $currency . number_format( $row['target'], 2 ) ); ?></span>
						<?php if ( $row['remaining'] > 0 ) : ?>
							<span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: remaining amount */
										__( '%s to go', 'multi-store-cash-manager' ),
										$currency . number_format( $row['remaining'], 2 )
									)
								);
								?>
							</span>
						<?php else : ?>
							<span class="mscm-achieved">🎉 <?php esc_html_e( 'Target Achieved!', 'multi-store-cash-manager' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $row['working_days'] > 0 ) : ?>
					<div class="mscm-target-working-days" style="margin-top:8px;font-size:13px;color:#6b7280;display:grid;grid-template-columns:repeat(2,1fr);gap:4px;">
						<span>📅 <?php echo esc_html( sprintf(
							/* translators: 1: working days */
							__( 'Working Days: %d', 'multi-store-cash-manager' ),
							$row['working_days']
						) ); ?></span>
						<span>📊 <?php echo esc_html( sprintf(
							/* translators: 1: daily target amount */
							__( 'Daily Target: %s', 'multi-store-cash-manager' ),
							$currency . number_format( $row['target_per_day'], 2 )
						) ); ?></span>
						<span>🎯 <?php echo esc_html( sprintf(
							/* translators: 1: target to date amount */
							__( 'Target to Date: %s', 'multi-store-cash-manager' ),
							$currency . number_format( $row['target_to_date'], 2 )
						) ); ?></span>
						<span style="font-weight:600;<?php echo $row['over_short'] >= 0 ? 'color:#10b981;' : 'color:#ef4444;'; ?>">
							<?php
							if ( $row['over_short'] >= 0 ) {
								echo esc_html( sprintf(
									/* translators: 1: over amount */
									__( '▲ %s Over Target', 'multi-store-cash-manager' ),
									$currency . number_format( $row['over_short'], 2 )
								) );
							} else {
								echo esc_html( sprintf(
									/* translators: 1: short amount */
									__( '▼ %s Short of Target', 'multi-store-cash-manager' ),
									$currency . number_format( abs( $row['over_short'] ), 2 )
								) );
							}
							?>
						</span>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}
