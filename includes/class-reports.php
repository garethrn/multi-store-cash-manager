<?php
/**
 * Report generation class.
 *
 * Handles all report types including EOD, store performance,
 * consolidated multi-store, and targets reports.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_Reports class.
 *
 * @since 1.0.0
 */
class MSCM_Reports {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing to initialize.
	}

	/**
	 * Generate a report by type.
	 *
	 * @param string $type      Report type (eod, performance, consolidated, targets).
	 * @param int    $store_id  Store ID (0 for all stores).
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Report data.
	 */
	public function generate( $type, $store_id = 0, $date_from = '', $date_to = '' ) {
		if ( empty( $date_from ) ) {
			$date_from = date( 'Y-m-01' );
		}
		if ( empty( $date_to ) ) {
			$date_to = date( 'Y-m-d' );
		}

		switch ( $type ) {
			case 'eod':
				return $this->eod_report( $store_id, $date_from, $date_to );
			case 'performance':
				return $this->store_performance_report( $store_id, $date_from, $date_to );
			case 'consolidated':
				return $this->consolidated_report( $date_from, $date_to );
			case 'targets':
				return $this->targets_report( $store_id, $date_from, $date_to );
			default:
				return array( 'error' => __( 'Invalid report type.', 'multi-store-cash-manager' ) );
		}
	}

	/**
	 * Generate an end-of-day report.
	 *
	 * @param int    $store_id  Store ID.
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Report data.
	 */
	public function eod_report( $store_id, $date_from, $date_to ) {
		$db = MSCM()->db;

		$args = array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'orderby'   => 'entry_date',
			'order'     => 'ASC',
			'limit'     => 1000,
		);

		if ( $store_id ) {
			$args['store_id'] = $store_id;
		}

		$entries = $db->get_entries( $args );

		$report = array(
			'type'        => 'eod',
			'title'       => __( 'End of Day Report', 'multi-store-cash-manager' ),
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'entries'     => array(),
			'totals'      => array(
				'total_cash'   => 0,
				'cash_to_bank' => 0,
				'credit_card'  => 0,
				'eft'          => 0,
				'other_digital' => 0,
				'total_sales'  => 0,
				'pos_reported' => 0,
				'discrepancy'  => 0,
				'net_banking'  => 0,
			),
			'generated_at' => current_time( 'mysql' ),
			'generated_by' => wp_get_current_user()->display_name,
		);

		foreach ( $entries as $entry ) {
			$report['entries'][] = $entry;

			// Accumulate totals.
			foreach ( array_keys( $report['totals'] ) as $field ) {
				if ( isset( $entry->$field ) ) {
					$report['totals'][ $field ] += floatval( $entry->$field );
				}
			}
		}

		return $report;
	}

	/**
	 * Generate a store performance report.
	 *
	 * @param int    $store_id  Store ID.
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Report data.
	 */
	public function store_performance_report( $store_id, $date_from, $date_to ) {
		global $wpdb;
		$db      = MSCM()->db;
		$entries = $wpdb->prefix . 'mscm_daily_entries';

		$where   = 'WHERE entry_date BETWEEN %s AND %s';
		$params  = array( $date_from, $date_to );

		if ( $store_id ) {
			$where   .= ' AND store_id = %d';
			$params[] = absint( $store_id );
		}

		// Daily totals.
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entry_date,
					SUM(total_sales) as total_sales,
					SUM(total_cash) as total_cash,
					SUM(credit_card) as credit_card,
					SUM(eft) as eft,
					SUM(discrepancy) as discrepancy,
					COUNT(*) as entry_count
				FROM {$entries}
				{$where}
				GROUP BY entry_date
				ORDER BY entry_date ASC",
				$params
			)
		);

		// Summary stats.
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_entries,
					SUM(total_sales) as total_sales,
					AVG(total_sales) as avg_daily_sales,
					MAX(total_sales) as max_daily_sales,
					MIN(total_sales) as min_daily_sales,
					SUM(discrepancy) as total_discrepancy,
					AVG(ABS(discrepancy)) as avg_discrepancy
				FROM {$entries}
				{$where}",
				$params
			)
		);

		// Sales by day of week.
		$by_weekday = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(entry_date) as weekday,
					AVG(total_sales) as avg_sales,
					COUNT(*) as count
				FROM {$entries}
				{$where}
				GROUP BY DAYOFWEEK(entry_date)
				ORDER BY weekday ASC",
				$params
			)
		);

		$store = $store_id ? $db->get_store( $store_id ) : null;

		return array(
			'type'        => 'performance',
			'title'       => __( 'Store Performance Report', 'multi-store-cash-manager' ),
			'store'       => $store,
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'daily'       => $daily,
			'summary'     => $summary,
			'by_weekday'  => $by_weekday,
			'generated_at' => current_time( 'mysql' ),
			'generated_by' => wp_get_current_user()->display_name,
		);
	}

	/**
	 * Generate a consolidated multi-store report.
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Report data.
	 */
	public function consolidated_report( $date_from, $date_to ) {
		global $wpdb;
		$entries = $wpdb->prefix . 'mscm_daily_entries';
		$stores  = $wpdb->prefix . 'mscm_stores';

		$store_summaries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.id as store_id,
					s.name as store_name,
					s.location,
					COUNT(e.id) as entry_count,
					COALESCE(SUM(e.total_sales), 0) as total_sales,
					COALESCE(AVG(e.total_sales), 0) as avg_daily_sales,
					COALESCE(SUM(e.total_cash), 0) as total_cash,
					COALESCE(SUM(e.credit_card), 0) as total_credit,
					COALESCE(SUM(e.eft), 0) as total_eft,
					COALESCE(SUM(e.discrepancy), 0) as total_discrepancy,
					COALESCE(SUM(e.net_banking), 0) as total_banking
				FROM {$stores} s
				LEFT JOIN {$entries} e ON s.id = e.store_id
					AND e.entry_date BETWEEN %s AND %s
				WHERE s.is_active = 1
				GROUP BY s.id, s.name, s.location
				ORDER BY total_sales DESC",
				$date_from,
				$date_to
			)
		);

		// Grand totals.
		$totals = array(
			'total_sales'      => 0,
			'total_cash'       => 0,
			'total_credit'     => 0,
			'total_eft'        => 0,
			'total_discrepancy' => 0,
			'total_banking'    => 0,
			'entry_count'      => 0,
		);

		foreach ( $store_summaries as $store ) {
			foreach ( array_keys( $totals ) as $field ) {
				$totals[ $field ] += floatval( $store->$field ?? 0 );
			}
		}

		return array(
			'type'          => 'consolidated',
			'title'         => __( 'Consolidated Multi-Store Report', 'multi-store-cash-manager' ),
			'date_from'     => $date_from,
			'date_to'       => $date_to,
			'stores'        => $store_summaries,
			'totals'        => $totals,
			'store_count'   => count( $store_summaries ),
			'generated_at'  => current_time( 'mysql' ),
			'generated_by'  => wp_get_current_user()->display_name,
		);
	}

	/**
	 * Generate a sales targets report.
	 *
	 * @param int    $store_id  Store ID (0 for all).
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Report data.
	 */
	public function targets_report( $store_id, $date_from, $date_to ) {
		global $wpdb;
		$entries = $wpdb->prefix . 'mscm_daily_entries';
		$targets = $wpdb->prefix . 'mscm_targets';
		$stores  = $wpdb->prefix . 'mscm_stores';

		$year  = date( 'Y', strtotime( $date_from ) );
		$month = date( 'n', strtotime( $date_from ) );

		$where_store  = $store_id ? $wpdb->prepare( ' AND s.id = %d', $store_id ) : '';
		$where_store2 = $store_id ? $wpdb->prepare( ' AND e.store_id = %d', $store_id ) : '';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.id as store_id,
					s.name as store_name,
					t.target_amount,
					t.period_type,
					COALESCE(SUM(e.total_sales), 0) as actual_sales,
					COALESCE(COUNT(e.id), 0) as days_with_entries
				FROM {$stores} s
				LEFT JOIN {$targets} t ON s.id = t.store_id
					AND t.period_year = %d
					AND t.period_month = %d
				LEFT JOIN {$entries} e ON s.id = e.store_id
					AND e.entry_date BETWEEN %s AND %s
				WHERE s.is_active = 1 {$where_store}
				GROUP BY s.id, s.name, t.target_amount, t.period_type
				ORDER BY s.name ASC",
				array( $year, $month, $date_from, $date_to )
			)
		);

		$report_rows = array();
		foreach ( $results as $row ) {
			$target  = floatval( $row->target_amount ?? 0 );
			$actual  = floatval( $row->actual_sales );
			$percent = $target > 0 ? round( ( $actual / $target ) * 100, 1 ) : 0;

			$report_rows[] = array(
				'store_id'    => $row->store_id,
				'store_name'  => $row->store_name,
				'target'      => $target,
				'actual'      => $actual,
				'remaining'   => max( 0, $target - $actual ),
				'percentage'  => $percent,
				'on_track'    => $percent >= $this->get_expected_progress_percentage( $date_from, $date_to ),
			);
		}

		return array(
			'type'        => 'targets',
			'title'       => __( 'Sales Target Progress Report', 'multi-store-cash-manager' ),
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'year'        => $year,
			'month'       => $month,
			'rows'        => $report_rows,
			'generated_at' => current_time( 'mysql' ),
			'generated_by' => wp_get_current_user()->display_name,
		);
	}

	/**
	 * Calculate expected progress percentage based on days elapsed in the month.
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return float Expected progress percentage.
	 */
	private function get_expected_progress_percentage( $date_from, $date_to ) {
		$days_in_month = date( 't', strtotime( $date_from ) );
		$day_of_month  = date( 'j', strtotime( $date_to ) );

		return round( ( $day_of_month / $days_in_month ) * 100, 1 );
	}

	/**
	 * Get recent entries for admin display.
	 *
	 * @param int $limit Number of entries to retrieve.
	 * @return array Array of recent entries.
	 */
	public function get_recent_entries( $limit = 10 ) {
		return MSCM()->db->get_entries(
			array(
				'limit'   => $limit,
				'orderby' => 'created_at',
				'order'   => 'DESC',
			)
		);
	}
}
