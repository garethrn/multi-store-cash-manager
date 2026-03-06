<?php
/**
 * Machine learning features.
 *
 * Provides anomaly detection, sales prediction, and business insights
 * using statistical analysis of historical sales data.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_ML class.
 *
 * @since 1.0.0
 */
class MSCM_ML {

	/**
	 * Sensitivity settings for anomaly detection thresholds.
	 *
	 * @var array
	 */
	private $sensitivity_thresholds = array(
		'low'    => 3.0,
		'medium' => 2.0,
		'high'   => 1.5,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing to initialize.
	}

	/**
	 * Detect anomalies for a newly submitted entry.
	 *
	 * @param int $entry_id Entry ID.
	 */
	public function detect_anomalies( $entry_id ) {
		$entry = MSCM()->db->get_entry( $entry_id );

		if ( ! $entry ) {
			return;
		}

		$sensitivity = get_option( 'mscm_ml_sensitivity', 'medium' );
		$threshold   = $this->sensitivity_thresholds[ $sensitivity ] ?? 2.0;

		// Get historical data for this store (last 90 days excluding today).
		global $wpdb;
		$entries_table = $wpdb->prefix . 'mscm_daily_entries';

		$historical = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT total_sales FROM {$entries_table}
				WHERE store_id = %d
				AND entry_date < %s
				AND entry_date >= DATE_SUB(%s, INTERVAL 90 DAY)
				ORDER BY entry_date DESC
				LIMIT 60",
				$entry->store_id,
				$entry->entry_date,
				$entry->entry_date
			)
		);

		if ( count( $historical ) < 7 ) {
			// Not enough data for meaningful analysis.
			return;
		}

		$historical = array_map( 'floatval', $historical );
		$mean       = array_sum( $historical ) / count( $historical );
		$std_dev    = $this->calculate_std_dev( $historical, $mean );

		// Check for unusual total sales.
		if ( $std_dev > 0 ) {
			$z_score = abs( ( floatval( $entry->total_sales ) - $mean ) / $std_dev );

			if ( $z_score > $threshold ) {
				$severity = $z_score > ( $threshold * 1.5 ) ? 'high' : 'medium';
				MSCM()->db->log_anomaly(
					array(
						'store_id'       => $entry->store_id,
						'entry_id'       => $entry_id,
						'type'           => 'unusual_sales',
						'severity'       => $severity,
						'description'    => sprintf(
							/* translators: 1: actual sales amount, 2: average sales amount */
							__( 'Unusual sales amount: %1$s (average: %2$s)', 'multi-store-cash-manager' ),
							number_format( $entry->total_sales, 2 ),
							number_format( $mean, 2 )
						),
						'detected_value' => $entry->total_sales,
						'expected_value' => $mean,
					)
				);
			}
		}

		// Check for large discrepancy.
		$discrepancy = abs( floatval( $entry->discrepancy ) );
		if ( $discrepancy > 100 ) {
			$severity = $discrepancy > 500 ? 'high' : ( $discrepancy > 200 ? 'medium' : 'low' );
			MSCM()->db->log_anomaly(
				array(
					'store_id'       => $entry->store_id,
					'entry_id'       => $entry_id,
					'type'           => 'large_discrepancy',
					'severity'       => $severity,
					'description'    => sprintf(
						/* translators: discrepancy amount */
						__( 'Large discrepancy detected: %s', 'multi-store-cash-manager' ),
						number_format( $entry->discrepancy, 2 )
					),
					'detected_value' => $entry->discrepancy,
					'expected_value' => 0,
				)
			);
		}

		// Check for zero sales (possible missed entry).
		if ( floatval( $entry->total_sales ) === 0.0 && $mean > 0 ) {
			MSCM()->db->log_anomaly(
				array(
					'store_id'       => $entry->store_id,
					'entry_id'       => $entry_id,
					'type'           => 'zero_sales',
					'severity'       => 'medium',
					'description'    => __( 'Zero sales reported for the day.', 'multi-store-cash-manager' ),
					'detected_value' => 0,
					'expected_value' => $mean,
				)
			);
		}
	}

	/**
	 * Predict sales for a future date.
	 *
	 * Uses weighted moving average with day-of-week adjustment.
	 *
	 * @param int    $store_id Store ID.
	 * @param string $date     Target prediction date.
	 * @return array Prediction data including amount and confidence.
	 */
	public function predict_sales( $store_id, $date = null ) {
		if ( null === $date ) {
			$date = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
		}

		global $wpdb;
		$entries_table = $wpdb->prefix . 'mscm_daily_entries';

		// Get same day of week historical data.
		$dow = wp_date( 'w', strtotime( $date ) ); // 0 = Sunday.

		$same_dow_data = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT total_sales FROM {$entries_table}
				WHERE store_id = %d
				AND DAYOFWEEK(entry_date) = %d
				AND entry_date < %s
				ORDER BY entry_date DESC
				LIMIT 12",
				$store_id,
				$dow + 1, // MySQL DAYOFWEEK: 1=Sunday.
				$date
			)
		);

		// Also get recent overall data.
		$recent_data = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT total_sales FROM {$entries_table}
				WHERE store_id = %d
				AND entry_date < %s
				ORDER BY entry_date DESC
				LIMIT 30",
				$store_id,
				$date
			)
		);

		if ( empty( $same_dow_data ) && empty( $recent_data ) ) {
			return array(
				'predicted_sales' => 0,
				'confidence'      => 0,
				'message'         => __( 'Insufficient historical data for prediction.', 'multi-store-cash-manager' ),
			);
		}

		$same_dow_data = array_map( 'floatval', $same_dow_data );
		$recent_data   = array_map( 'floatval', $recent_data );

		// Weighted moving average - more recent data gets higher weight.
		$dow_avg    = $this->weighted_average( $same_dow_data );
		$recent_avg = $this->weighted_average( $recent_data );

		// Blend: 70% same-day-of-week, 30% recent overall.
		$predicted = ! empty( $same_dow_data ) ? ( $dow_avg * 0.7 + $recent_avg * 0.3 ) : $recent_avg;

		// Calculate confidence based on data variance.
		$confidence = $this->calculate_confidence( $same_dow_data ?: $recent_data );

		// Store prediction.
		global $wpdb;
		$predictions_table = $wpdb->prefix . 'mscm_ml_predictions';
		$wpdb->replace(
			$predictions_table,
			array(
				'store_id'        => $store_id,
				'prediction_date' => $date,
				'predicted_sales' => $predicted,
				'confidence'      => $confidence,
				'model_version'   => '1.0',
			)
		);

		return array(
			'predicted_sales' => round( $predicted, 2 ),
			'confidence'      => $confidence,
			'date'            => $date,
		);
	}

	/**
	 * Get business insights for a store.
	 *
	 * @param int|null $store_id Store ID (null for all stores).
	 * @return array Array of insight messages.
	 */
	public function get_insights( $store_id = null ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'mscm_daily_entries';

		$where  = 'WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
		$params = array();

		if ( $store_id ) {
			$where   .= ' AND store_id = %d';
			$params[] = absint( $store_id );
		}

		// Get recent data.
		$data = empty( $params )
			? $wpdb->get_results( "SELECT * FROM {$entries_table} {$where} ORDER BY entry_date ASC" ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$entries_table} {$where} ORDER BY entry_date ASC", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $data ) ) {
			return array(
				array(
					'type'    => 'info',
					'message' => __( 'Not enough data to generate insights yet. Keep submitting daily entries!', 'multi-store-cash-manager' ),
				),
			);
		}

		$insights = array();

		// Best performing day of week.
		$by_dow   = array();
		foreach ( $data as $entry ) {
			$dow = wp_date( 'N', strtotime( $entry->entry_date ) ); // 1=Monday, 7=Sunday.
			if ( ! isset( $by_dow[ $dow ] ) ) {
				$by_dow[ $dow ] = array();
			}
			$by_dow[ $dow ][] = floatval( $entry->total_sales );
		}

		$dow_avgs = array();
		foreach ( $by_dow as $dow => $sales ) {
			$dow_avgs[ $dow ] = array_sum( $sales ) / count( $sales );
		}

		if ( ! empty( $dow_avgs ) ) {
			arsort( $dow_avgs );
			$best_dow  = key( $dow_avgs );
			$days      = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
			$day_name  = $days[ $best_dow ] ?? 'Unknown';

			$insights[] = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: day name */
					__( '📈 %s is your best performing day with average sales of %s', 'multi-store-cash-manager' ),
					$day_name,
					get_option( 'mscm_currency', 'R' ) . number_format( reset( $dow_avgs ), 2 )
				),
			);
		}

		// Sales trend (comparing first half vs second half of the period).
		$sales_values = array_map(
			function( $e ) {
				return floatval( $e->total_sales );
			},
			$data
		);
		$half         = intval( count( $sales_values ) / 2 );

		if ( $half > 0 ) {
			$first_half_avg  = array_sum( array_slice( $sales_values, 0, $half ) ) / $half;
			$second_half_avg = array_sum( array_slice( $sales_values, $half ) ) / max( 1, count( $sales_values ) - $half );

			$trend_pct = $first_half_avg > 0
				? round( ( ( $second_half_avg - $first_half_avg ) / $first_half_avg ) * 100, 1 )
				: 0;

			if ( $trend_pct > 5 ) {
				$insights[] = array(
					'type'    => 'success',
					'message' => sprintf(
						/* translators: percentage */
						__( '📊 Sales trending UP by %s%% over the past 30 days', 'multi-store-cash-manager' ),
						abs( $trend_pct )
					),
				);
			} elseif ( $trend_pct < -5 ) {
				$insights[] = array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: percentage */
						__( '⚠️ Sales trending DOWN by %s%% over the past 30 days', 'multi-store-cash-manager' ),
						abs( $trend_pct )
					),
				);
			}
		}

		// Discrepancy insight.
		$discrepancies = array_filter(
			array_map( fn( $e ) => abs( floatval( $e->discrepancy ) ), $data ),
			fn( $d ) => $d > 0
		);

		if ( ! empty( $discrepancies ) ) {
			$avg_disc = array_sum( $discrepancies ) / count( $discrepancies );
			if ( $avg_disc > 50 ) {
				$insights[] = array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: average discrepancy amount */
						__( '🔍 Average discrepancy of %s detected. Consider reviewing POS reconciliation process.', 'multi-store-cash-manager' ),
						get_option( 'mscm_currency', 'R' ) . number_format( $avg_disc, 2 )
					),
				);
			}
		}

		// Float suggestion.
		$cash_values = array_map( fn( $e ) => floatval( $e->total_cash ), $data );
		if ( ! empty( $cash_values ) ) {
			$avg_cash         = array_sum( $cash_values ) / count( $cash_values );
			$current_float    = get_option( 'mscm_default_float', 500 );
			$suggested_float  = round( $avg_cash * 0.05, -1 ); // 5% of average cash.

			if ( abs( $suggested_float - $current_float ) > 100 ) {
				$insights[] = array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: suggested float amount */
						__( '💡 Based on your average cash flow, a float of %s might be optimal.', 'multi-store-cash-manager' ),
						get_option( 'mscm_currency', 'R' ) . number_format( $suggested_float, 2 )
					),
				);
			}
		}

		// Next day prediction.
		if ( $store_id ) {
			$prediction = $this->predict_sales( $store_id );
			if ( $prediction['confidence'] > 50 ) {
				$insights[] = array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: 1: date, 2: predicted amount, 3: confidence percentage */
						__( '🔮 Predicted sales for %1$s: %2$s (confidence: %3$s%%)', 'multi-store-cash-manager' ),
						$prediction['date'],
						get_option( 'mscm_currency', 'R' ) . number_format( $prediction['predicted_sales'], 2 ),
						$prediction['confidence']
					),
				);
			}
		}

		return $insights;
	}

	/**
	 * Calculate standard deviation.
	 *
	 * @param array $data Array of numeric values.
	 * @param float $mean Pre-calculated mean (optional).
	 * @return float Standard deviation.
	 */
	private function calculate_std_dev( $data, $mean = null ) {
		if ( empty( $data ) ) {
			return 0;
		}

		if ( null === $mean ) {
			$mean = array_sum( $data ) / count( $data );
		}

		$variance = array_sum(
			array_map( fn( $x ) => pow( $x - $mean, 2 ), $data )
		) / count( $data );

		return sqrt( $variance );
	}

	/**
	 * Calculate weighted average (more recent = higher weight).
	 *
	 * @param array $data Array of values (most recent first).
	 * @return float Weighted average.
	 */
	private function weighted_average( $data ) {
		if ( empty( $data ) ) {
			return 0;
		}

		$total_weight = 0;
		$weighted_sum = 0;
		$count        = count( $data );

		foreach ( $data as $i => $value ) {
			// Most recent data (index 0) gets highest weight.
			$weight        = $count - $i;
			$weighted_sum += $value * $weight;
			$total_weight += $weight;
		}

		return $total_weight > 0 ? $weighted_sum / $total_weight : 0;
	}

	/**
	 * Calculate confidence score based on data consistency.
	 *
	 * @param array $data Array of numeric values.
	 * @return float Confidence percentage (0-100).
	 */
	private function calculate_confidence( $data ) {
		if ( count( $data ) < 3 ) {
			return 30;
		}

		$mean    = array_sum( $data ) / count( $data );
		$std_dev = $this->calculate_std_dev( $data, $mean );

		if ( $mean === 0.0 ) {
			return 50;
		}

		// Coefficient of variation - lower CV = higher confidence.
		$cv = ( $std_dev / $mean ) * 100;

		// Map CV to confidence score: CV of 0% = 95, CV of 100% = 20.
		$confidence = max( 20, min( 95, 95 - ( $cv * 0.75 ) ) );

		// Boost confidence with more data.
		$data_boost = min( 10, count( $data ) / 2 );
		$confidence = min( 98, $confidence + $data_boost );

		return round( $confidence );
	}
}
