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
	$month_end   = date( 'Y-m-t', strtotime( $month_start ) );
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
		<h2 class="mscm-section-title">
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
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}
