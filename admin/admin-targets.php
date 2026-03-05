<?php
/**
 * Admin sales targets page.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the sales targets page.
 */
function mscm_render_targets_page() {
	if ( ! current_user_can( 'mscm_view_targets' ) && ! current_user_can( 'read' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$stores   = MSCM()->db->get_user_stores( get_current_user_id() );
	$currency = get_option( 'mscm_currency', 'R' );
	$year     = absint( $_GET['year'] ?? date( 'Y' ) );
	$month    = absint( $_GET['month'] ?? date( 'n' ) );

	// Get targets and actual sales for the selected period.
	$month_start = sprintf( '%04d-%02d-01', $year, $month );
	$month_end   = date( 'Y-m-t', strtotime( $month_start ) );
	$today       = current_time( 'Y-m-d' );
	$period_end  = min( $today, $month_end );

	$can_set_targets = current_user_can( 'mscm_set_targets' ) || current_user_can( 'manage_options' );
	?>
	<div class="wrap mscm-admin">
		<h1><?php esc_html_e( 'Sales Targets', 'multi-store-cash-manager' ); ?></h1>

		<!-- Period Selector -->
		<div class="mscm-filter-bar">
			<form method="get" action="">
				<input type="hidden" name="page" value="mscm-targets">
				<select name="year">
					<?php for ( $y = date( 'Y' ) + 1; $y >= date( 'Y' ) - 2; $y-- ) : ?>
						<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( $y ); ?></option>
					<?php endfor; ?>
				</select>
				<select name="month">
					<?php
					$months = array(
						1  => __( 'January', 'multi-store-cash-manager' ),
						2  => __( 'February', 'multi-store-cash-manager' ),
						3  => __( 'March', 'multi-store-cash-manager' ),
						4  => __( 'April', 'multi-store-cash-manager' ),
						5  => __( 'May', 'multi-store-cash-manager' ),
						6  => __( 'June', 'multi-store-cash-manager' ),
						7  => __( 'July', 'multi-store-cash-manager' ),
						8  => __( 'August', 'multi-store-cash-manager' ),
						9  => __( 'September', 'multi-store-cash-manager' ),
						10 => __( 'October', 'multi-store-cash-manager' ),
						11 => __( 'November', 'multi-store-cash-manager' ),
						12 => __( 'December', 'multi-store-cash-manager' ),
					);
					foreach ( $months as $m_num => $m_name ) :
					?>
						<option value="<?php echo esc_attr( $m_num ); ?>" <?php selected( $month, $m_num ); ?>>
							<?php echo esc_html( $m_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'View', 'multi-store-cash-manager' ); ?></button>
			</form>
		</div>

		<!-- Set Targets (managers/admins only) -->
		<?php if ( $can_set_targets ) : ?>
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Set Target', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<form id="mscm-target-form" class="mscm-form-inline">
					<select name="store_id" required>
						<option value=""><?php esc_html_e( 'Select Store', 'multi-store-cash-manager' ); ?></option>
						<?php foreach ( $stores as $store ) : ?>
							<option value="<?php echo esc_attr( $store->id ); ?>"><?php echo esc_html( $store->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="period_year">
						<?php for ( $y = date( 'Y' ) + 1; $y >= date( 'Y' ) - 1; $y-- ) : ?>
							<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( $y ); ?></option>
						<?php endfor; ?>
					</select>
					<select name="period_month">
						<?php foreach ( $months as $m_num => $m_name ) : ?>
							<option value="<?php echo esc_attr( $m_num ); ?>" <?php selected( $month, $m_num ); ?>>
								<?php echo esc_html( $m_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="hidden" name="period_type" value="monthly">
					<div style="display:flex;align-items:center;gap:4px;">
						<span><?php echo esc_html( $currency ); ?></span>
						<input type="number" name="target_amount" min="0" step="0.01"
							placeholder="<?php esc_attr_e( 'Target Amount', 'multi-store-cash-manager' ); ?>"
							class="regular-text" required>
					</div>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Set Target', 'multi-store-cash-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php endif; ?>

		<!-- Targets Progress -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: month name, 2: year */
							__( '%1$s %2$s - Sales Targets', 'multi-store-cash-manager' ),
							$months[ $month ],
							$year
						)
					);
					?>
				</h2>
			</div>
			<div class="mscm-card-body">
				<?php
				$report = MSCM()->reports->targets_report( 0, $month_start, $period_end );
				$rows   = $report['rows'] ?? array();

				if ( empty( $rows ) ) :
				?>
					<p class="mscm-empty"><?php esc_html_e( 'No targets set for this period.', 'multi-store-cash-manager' ); ?></p>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<div class="mscm-target-card">
							<div class="mscm-target-header">
								<div>
									<div class="mscm-target-store-name"><?php echo esc_html( $row['store_name'] ); ?></div>
									<div class="mscm-target-meta">
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: actual amount, 2: target amount */
												__( '%1$s of %2$s target', 'multi-store-cash-manager' ),
												$currency . number_format( $row['actual'], 2 ),
												$currency . number_format( $row['target'], 2 )
											)
										);
										?>
									</div>
								</div>
								<div class="mscm-target-percent <?php echo $row['on_track'] ? 'mscm-positive' : 'mscm-negative'; ?>">
									<?php echo esc_html( $row['percentage'] . '%' ); ?>
								</div>
							</div>
							<div class="mscm-progress-bar">
								<div class="mscm-progress-fill <?php echo $row['on_track'] ? 'mscm-progress-success' : 'mscm-progress-warning'; ?>"
									style="width:<?php echo esc_attr( min( 100, $row['percentage'] ) ); ?>%">
								</div>
							</div>
							<div class="mscm-target-footer">
								<?php if ( $row['remaining'] > 0 ) : ?>
									<span>
										<?php
										echo esc_html(
											sprintf(
												/* translators: remaining amount */
												__( '%s remaining to reach target', 'multi-store-cash-manager' ),
												$currency . number_format( $row['remaining'], 2 )
											)
										);
										?>
									</span>
								<?php else : ?>
									<span class="mscm-positive">
										🎉 <?php esc_html_e( 'Target achieved!', 'multi-store-cash-manager' ); ?>
									</span>
								<?php endif; ?>
								<span class="mscm-target-status <?php echo $row['on_track'] ? 'mscm-on-track' : 'mscm-off-track'; ?>">
									<?php echo $row['on_track'] ? esc_html__( '✅ On Track', 'multi-store-cash-manager' ) : esc_html__( '⚠️ Behind', 'multi-store-cash-manager' ); ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}
