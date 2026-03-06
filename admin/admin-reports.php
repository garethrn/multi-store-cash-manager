<?php
/**
 * Admin reports page.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the reports page.
 */
function mscm_render_reports_page() {
	if ( ! current_user_can( 'mscm_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$stores    = MSCM()->db->get_user_stores( get_current_user_id() );
	$currency  = get_option( 'mscm_currency', 'R' );

	// Handle direct report generation.
	$report_type = sanitize_text_field( $_GET['report_type'] ?? '' );
	$store_id    = absint( $_GET['store_id'] ?? 0 );
	$date_from   = sanitize_text_field( $_GET['date_from'] ?? wp_date( 'Y-m-01' ) );
	$date_to     = sanitize_text_field( $_GET['date_to'] ?? wp_date( 'Y-m-d' ) );

	$report = null;
	if ( $report_type ) {
		$report = MSCM()->reports->generate( $report_type, $store_id, $date_from, $date_to );
	}
	?>
	<div class="wrap mscm-admin">
		<h1><?php esc_html_e( 'Reports', 'multi-store-cash-manager' ); ?></h1>

		<!-- Report Filters -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Generate Report', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<form method="get" action="" class="mscm-report-form">
					<input type="hidden" name="page" value="mscm-reports">
					<div class="mscm-form-row">
						<div class="mscm-form-group">
							<label><?php esc_html_e( 'Report Type', 'multi-store-cash-manager' ); ?></label>
							<select name="report_type" required>
								<option value=""><?php esc_html_e( 'Select Report Type', 'multi-store-cash-manager' ); ?></option>
								<option value="eod" <?php selected( $report_type, 'eod' ); ?>><?php esc_html_e( 'End of Day Report', 'multi-store-cash-manager' ); ?></option>
								<option value="performance" <?php selected( $report_type, 'performance' ); ?>><?php esc_html_e( 'Store Performance', 'multi-store-cash-manager' ); ?></option>
								<option value="consolidated" <?php selected( $report_type, 'consolidated' ); ?>><?php esc_html_e( 'Consolidated Multi-Store', 'multi-store-cash-manager' ); ?></option>
								<option value="targets" <?php selected( $report_type, 'targets' ); ?>><?php esc_html_e( 'Sales Target Progress', 'multi-store-cash-manager' ); ?></option>
							</select>
						</div>
						<div class="mscm-form-group">
							<label><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></label>
							<select name="store_id">
								<option value="0"><?php esc_html_e( 'All Stores', 'multi-store-cash-manager' ); ?></option>
								<?php foreach ( $stores as $store ) : ?>
									<option value="<?php echo esc_attr( $store->id ); ?>" <?php selected( $store_id, $store->id ); ?>>
										<?php echo esc_html( $store->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="mscm-form-group">
							<label><?php esc_html_e( 'From', 'multi-store-cash-manager' ); ?></label>
							<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
						</div>
						<div class="mscm-form-group">
							<label><?php esc_html_e( 'To', 'multi-store-cash-manager' ); ?></label>
							<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
						</div>
					</div>
					<div class="mscm-form-actions">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Generate Report', 'multi-store-cash-manager' ); ?>
						</button>
						<?php if ( $report ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url(
								add_query_arg(
									array(
										'action'      => 'mscm_print_report',
										'type'        => $report_type,
										'store_id'    => $store_id,
										'date_from'   => $date_from,
										'date_to'     => $date_to,
									),
									admin_url( 'admin-post.php' )
								),
								'mscm_print_report'
							) ); ?>" target="_blank" class="button button-secondary">
								🖨️ <?php esc_html_e( 'Print / PDF', 'multi-store-cash-manager' ); ?>
							</a>
							<button type="button" id="mscm-export-csv" class="button button-secondary"
								data-store-id="<?php echo esc_attr( $store_id ); ?>"
								data-date-from="<?php echo esc_attr( $date_from ); ?>"
								data-date-to="<?php echo esc_attr( $date_to ); ?>">
								📥 <?php esc_html_e( 'Export CSV', 'multi-store-cash-manager' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>

		<?php if ( $report ) : ?>
		<!-- Report Results -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php echo esc_html( $report['title'] ?? __( 'Report Results', 'multi-store-cash-manager' ) ); ?></h2>
				<span class="mscm-report-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: start date, 2: end date */
							__( '%1$s to %2$s', 'multi-store-cash-manager' ),
							$report['date_from'],
							$report['date_to']
						)
					);
					?>
				</span>
			</div>
			<div class="mscm-card-body">
				<?php
				if ( 'eod' === $report['type'] ) {
					mscm_render_eod_report_table( $report, $currency );
				} elseif ( 'performance' === $report['type'] ) {
					mscm_render_performance_report( $report, $currency );
				} elseif ( 'consolidated' === $report['type'] ) {
					mscm_render_consolidated_report_table( $report, $currency );
				} elseif ( 'targets' === $report['type'] ) {
					mscm_render_targets_report_table( $report, $currency );
				}
				?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render EOD report table.
 *
 * @param array  $report   Report data.
 * @param string $currency Currency symbol.
 */
function mscm_render_eod_report_table( $report, $currency ) {
	if ( empty( $report['entries'] ) ) {
		echo '<p class="mscm-empty">' . esc_html__( 'No entries found for the selected period.', 'multi-store-cash-manager' ) . '</p>';
		return;
	}
	?>
	<!-- Summary Cards -->
	<div class="mscm-stats-grid mscm-report-stats">
		<div class="mscm-stat-card mscm-stat-primary">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $report['totals']['total_sales'], 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card mscm-stat-success">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $report['totals']['cash_to_bank'], 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Cash to Bank', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card <?php echo abs( $report['totals']['discrepancy'] ) > 100 ? 'mscm-stat-danger' : 'mscm-stat-info'; ?>">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $report['totals']['discrepancy'], 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Total Discrepancy', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card mscm-stat-info">
			<div class="mscm-stat-value"><?php echo esc_html( count( $report['entries'] ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Entries', 'multi-store-cash-manager' ); ?></div>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped mscm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?></th>
				<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Total Cash', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Card', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'EFT', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Cash to Bank', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Discrepancy', 'multi-store-cash-manager' ); ?></th>
				<th><?php esc_html_e( 'Status', 'multi-store-cash-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report['entries'] as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( $entry->entry_date ); ?></td>
					<td><?php echo esc_html( $entry->store_name ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $entry->total_cash, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $entry->credit_card, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $entry->eft, 2 ) ); ?></td>
					<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $entry->total_sales, 2 ) ); ?></strong></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $entry->cash_to_bank, 2 ) ); ?></td>
					<td class="mscm-num <?php echo abs( floatval( $entry->discrepancy ) ) > 100 ? 'mscm-negative' : ''; ?>">
						<?php echo esc_html( $currency . number_format( $entry->discrepancy, 2 ) ); ?>
					</td>
					<td>
						<span class="mscm-badge mscm-badge-<?php echo esc_attr( $entry->status ); ?>">
							<?php echo esc_html( ucfirst( $entry->status ) ); ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr class="mscm-totals-row">
				<td colspan="2"><strong><?php esc_html_e( 'TOTALS', 'multi-store-cash-manager' ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_cash'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['credit_card'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['eft'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_sales'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['cash_to_bank'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['discrepancy'], 2 ) ); ?></strong></td>
				<td></td>
			</tr>
		</tfoot>
	</table>
	<?php
}

/**
 * Render performance report.
 *
 * @param array  $report   Report data.
 * @param string $currency Currency symbol.
 */
function mscm_render_performance_report( $report, $currency ) {
	if ( empty( $report['daily'] ) ) {
		echo '<p class="mscm-empty">' . esc_html__( 'No data found for the selected period.', 'multi-store-cash-manager' ) . '</p>';
		return;
	}

	$summary = $report['summary'];
	?>
	<div class="mscm-stats-grid">
		<div class="mscm-stat-card mscm-stat-primary">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $summary->total_sales, 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card mscm-stat-success">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $summary->avg_daily_sales, 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Avg Daily Sales', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card mscm-stat-info">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $summary->max_daily_sales, 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Best Day', 'multi-store-cash-manager' ); ?></div>
		</div>
		<div class="mscm-stat-card mscm-stat-warning">
			<div class="mscm-stat-value"><?php echo esc_html( $currency . number_format( $summary->avg_discrepancy, 2 ) ); ?></div>
			<div class="mscm-stat-label"><?php esc_html_e( 'Avg Discrepancy', 'multi-store-cash-manager' ); ?></div>
		</div>
	</div>

	<div class="mscm-chart-container" style="height:250px;margin-bottom:20px;">
		<canvas id="mscm-performance-chart"
			data-labels='<?php echo esc_attr( wp_json_encode( array_column( $report['daily'], 'entry_date' ) ) ); ?>'
			data-values='<?php echo esc_attr( wp_json_encode( array_map( fn( $d ) => floatval( $d->total_sales ), $report['daily'] ) ) ); ?>'>
		</canvas>
	</div>

	<table class="wp-list-table widefat fixed striped mscm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Cash', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Credit Card', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'EFT', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Discrepancy', 'multi-store-cash-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report['daily'] as $day ) : ?>
				<tr>
					<td><?php echo esc_html( $day->entry_date ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $day->total_sales, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $day->total_cash, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $day->credit_card, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $day->eft, 2 ) ); ?></td>
					<td class="mscm-num <?php echo abs( floatval( $day->discrepancy ) ) > 100 ? 'mscm-negative' : ''; ?>">
						<?php echo esc_html( $currency . number_format( $day->discrepancy, 2 ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Render consolidated report table.
 *
 * @param array  $report   Report data.
 * @param string $currency Currency symbol.
 */
function mscm_render_consolidated_report_table( $report, $currency ) {
	if ( empty( $report['stores'] ) ) {
		echo '<p class="mscm-empty">' . esc_html__( 'No data found.', 'multi-store-cash-manager' ) . '</p>';
		return;
	}
	?>
	<table class="wp-list-table widefat fixed striped mscm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
				<th><?php esc_html_e( 'Location', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Cash', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Credit Card', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'EFT', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Avg Daily', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Entries', 'multi-store-cash-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report['stores'] as $store ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $store->store_name ); ?></strong></td>
					<td><?php echo esc_html( $store->location ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $store->total_sales, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $store->total_cash, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $store->total_credit, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $store->total_eft, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $store->avg_daily_sales, 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $store->entry_count ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr class="mscm-totals-row">
				<td colspan="2"><strong><?php esc_html_e( 'TOTALS', 'multi-store-cash-manager' ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_sales'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_cash'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_credit'], 2 ) ); ?></strong></td>
				<td class="mscm-num"><strong><?php echo esc_html( $currency . number_format( $report['totals']['total_eft'], 2 ) ); ?></strong></td>
				<td></td>
				<td class="mscm-num"><strong><?php echo esc_html( $report['totals']['entry_count'] ); ?></strong></td>
			</tr>
		</tfoot>
	</table>
	<?php
}

/**
 * Render targets report table.
 *
 * @param array  $report   Report data.
 * @param string $currency Currency symbol.
 */
function mscm_render_targets_report_table( $report, $currency ) {
	if ( empty( $report['rows'] ) ) {
		echo '<p class="mscm-empty">' . esc_html__( 'No target data found.', 'multi-store-cash-manager' ) . '</p>';
		return;
	}
	?>
	<table class="wp-list-table widefat fixed striped mscm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Monthly Target', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Working Days', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Daily Target', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Target to Date', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Actual Sales', 'multi-store-cash-manager' ); ?></th>
				<th class="mscm-num"><?php esc_html_e( 'Over / Short', 'multi-store-cash-manager' ); ?></th>
				<th><?php esc_html_e( 'Progress', 'multi-store-cash-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report['rows'] as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row['store_name'] ); ?></strong></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $row['target'], 2 ) ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $row['working_days'] > 0 ? $row['working_days'] : '—' ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $row['target_per_day'] > 0 ? $currency . number_format( $row['target_per_day'], 2 ) : '—' ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $row['target_to_date'] > 0 ? $currency . number_format( $row['target_to_date'], 2 ) : '—' ); ?></td>
					<td class="mscm-num"><?php echo esc_html( $currency . number_format( $row['actual'], 2 ) ); ?></td>
					<td class="mscm-num <?php echo isset( $row['over_short'] ) && $row['over_short'] >= 0 ? 'mscm-positive' : 'mscm-negative'; ?>">
						<?php
						if ( isset( $row['over_short'] ) ) {
							echo esc_html( ( $row['over_short'] >= 0 ? '▲ ' : '▼ ' ) . $currency . number_format( abs( $row['over_short'] ), 2 ) );
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<div class="mscm-progress-container">
							<div class="mscm-progress-bar mscm-progress-sm">
								<div class="mscm-progress-fill <?php echo $row['on_track'] ? 'mscm-progress-success' : 'mscm-progress-warning'; ?>"
									style="width:<?php echo esc_attr( min( 100, $row['percentage'] ) ); ?>%">
								</div>
							</div>
							<span class="<?php echo $row['on_track'] ? 'mscm-positive' : 'mscm-warning-text'; ?>">
								<?php echo esc_html( $row['percentage'] . '%' ); ?>
							</span>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
