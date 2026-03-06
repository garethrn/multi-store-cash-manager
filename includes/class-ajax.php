<?php
/**
 * AJAX request handlers.
 *
 * Handles all WordPress AJAX actions for the plugin frontend and backend.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_Ajax class.
 *
 * @since 1.0.0
 */
class MSCM_Ajax {

	/**
	 * Constructor - registers all AJAX hooks.
	 */
	public function __construct() {
		// Public-facing AJAX actions (logged-in users).
		$actions = array(
			'mscm_save_daily_entry',
			'mscm_get_store_data',
			'mscm_get_dashboard_stats',
			'mscm_save_store',
			'mscm_delete_store',
			'mscm_verify_entry',
			'mscm_generate_report',
			'mscm_export_data',
			'mscm_save_target',
			'mscm_get_ml_insights',
			'mscm_assign_user_store',
			'mscm_remove_user_store',
			'mscm_resolve_anomaly',
			'mscm_get_entry',
			'mscm_save_settings',
			'mscm_get_notifications',
			'mscm_mark_notification_read',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'mscm_', '', $action ) ) );
		}
	}

	/**
	 * Send a JSON error response and exit.
	 *
	 * @param string $message Error message.
	 * @param int    $code    HTTP status code.
	 */
	private function error( $message, $code = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $code );
	}

	/**
	 * Verify a nonce and check if user is logged in.
	 *
	 * @param string $action Nonce action to verify.
	 * @param string $nonce  Nonce value from request.
	 * @return bool True if valid.
	 */
	private function verify_request( $action = 'mscm_public_nonce', $nonce = '' ) {
		if ( ! is_user_logged_in() ) {
			$this->error( __( 'You must be logged in.', 'multi-store-cash-manager' ), 401 );
			return false;
		}

		if ( ! $nonce ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? $_GET['nonce'] ?? '' ) );
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			$this->error( __( 'Security check failed.', 'multi-store-cash-manager' ), 403 );
			return false;
		}

		return true;
	}

	/**
	 * Handle saving a daily entry.
	 */
	public function save_daily_entry() {
		if ( ! $this->verify_request( 'mscm_public_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_submit_entry' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$store_id = absint( $_POST['store_id'] ?? 0 );

		if ( ! $store_id ) {
			$this->error( __( 'Store ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		// Verify user has access to this store.
		if ( ! MSCM_Roles::user_can_access_store( $store_id ) ) {
			$this->error( __( 'You do not have access to this store.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$entry_date = sanitize_text_field( wp_unslash( $_POST['entry_date'] ?? current_time( 'Y-m-d' ) ) );

		// Sanitize denomination values.
		$denoms = array( 200, 100, 50, 20, 10, 5, 2, 1 );
		$entry_data = array(
			'store_id'      => $store_id,
			'user_id'       => get_current_user_id(),
			'entry_date'    => $entry_date,
			'denom_50c'     => absint( $_POST['denom_50c'] ?? 0 ),
			'denom_20c'     => absint( $_POST['denom_20c'] ?? 0 ),
			'denom_10c'     => absint( $_POST['denom_10c'] ?? 0 ),
			'credit_card'   => floatval( $_POST['credit_card'] ?? 0 ),
			'eft'           => floatval( $_POST['eft'] ?? 0 ),
			'other_digital' => floatval( $_POST['other_digital'] ?? 0 ),
			'pos_reported'  => floatval( $_POST['pos_reported'] ?? 0 ),
			'float_amount'  => floatval( $_POST['float_amount'] ?? get_option( 'mscm_default_float', 500 ) ),
			'notes'         => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'status'        => 'pending',
		);

		foreach ( $denoms as $denom ) {
			$entry_data[ 'denom_' . $denom ] = absint( $_POST[ 'denom_' . $denom ] ?? 0 );
		}

		// Calculate totals.
		$total_cash = (
			( $entry_data['denom_200'] * 200 ) +
			( $entry_data['denom_100'] * 100 ) +
			( $entry_data['denom_50'] * 50 ) +
			( $entry_data['denom_20'] * 20 ) +
			( $entry_data['denom_10'] * 10 ) +
			( $entry_data['denom_5'] * 5 ) +
			( $entry_data['denom_2'] * 2 ) +
			( $entry_data['denom_1'] * 1 ) +
			( $entry_data['denom_50c'] * 0.5 ) +
			( $entry_data['denom_20c'] * 0.2 ) +
			( $entry_data['denom_10c'] * 0.1 )
		);

		$entry_data['total_cash']   = $total_cash;
		$entry_data['cash_to_bank'] = max( 0, $total_cash - $entry_data['float_amount'] );

		// Total sales uses cash-to-bank (float excluded) plus other payment methods.
		$total_sales = $entry_data['cash_to_bank'] + $entry_data['credit_card'] + $entry_data['eft'] + $entry_data['other_digital'];
		$entry_data['total_sales'] = $total_sales;
		$entry_data['discrepancy'] = $total_sales - $entry_data['pos_reported'];

		// Parse payouts.
		$payouts   = array();
		$payout_descriptions = isset( $_POST['payout_description'] ) ? (array) $_POST['payout_description'] : array();
		$payout_amounts      = isset( $_POST['payout_amount'] ) ? (array) $_POST['payout_amount'] : array();
		$payout_categories   = isset( $_POST['payout_category'] ) ? (array) $_POST['payout_category'] : array();

		$total_payouts = 0;
		foreach ( $payout_descriptions as $i => $desc ) {
			if ( empty( $desc ) || ! isset( $payout_amounts[ $i ] ) ) {
				continue;
			}
			$amount          = floatval( $payout_amounts[ $i ] );
			$total_payouts  += $amount;
			$payouts[]       = array(
				'description' => sanitize_text_field( wp_unslash( $desc ) ),
				'amount'      => $amount,
				'category'    => sanitize_text_field( wp_unslash( $payout_categories[ $i ] ?? 'general' ) ),
			);
		}

		// Parse purchases.
		$purchases  = array();
		$pur_descriptions = isset( $_POST['purchase_description'] ) ? (array) $_POST['purchase_description'] : array();
		$pur_amounts      = isset( $_POST['purchase_amount'] ) ? (array) $_POST['purchase_amount'] : array();
		$pur_categories   = isset( $_POST['purchase_category'] ) ? (array) $_POST['purchase_category'] : array();
		$pur_receipts     = isset( $_POST['purchase_receipt'] ) ? (array) $_POST['purchase_receipt'] : array();

		$total_purchases = 0;
		foreach ( $pur_descriptions as $i => $desc ) {
			if ( empty( $desc ) || ! isset( $pur_amounts[ $i ] ) ) {
				continue;
			}
			$amount           = floatval( $pur_amounts[ $i ] );
			$total_purchases += $amount;
			$purchases[]      = array(
				'description'    => sanitize_text_field( wp_unslash( $desc ) ),
				'amount'         => $amount,
				'category'       => sanitize_text_field( wp_unslash( $pur_categories[ $i ] ?? 'general' ) ),
				'receipt_number' => sanitize_text_field( wp_unslash( $pur_receipts[ $i ] ?? '' ) ),
			);
		}

		$entry_data['net_banking'] = $entry_data['cash_to_bank'] - $total_payouts - $total_purchases;
		$entry_data['payouts']     = $payouts;
		$entry_data['purchases']   = $purchases;

		// Add geolocation if provided.
		if ( ! empty( $_POST['latitude'] ) && ! empty( $_POST['longitude'] ) ) {
			$entry_data['latitude']  = floatval( $_POST['latitude'] );
			$entry_data['longitude'] = floatval( $_POST['longitude'] );
		}

		// Check if we are updating an existing entry.
		if ( ! empty( $_POST['entry_id'] ) ) {
			$existing = MSCM()->db->get_entry( absint( $_POST['entry_id'] ) );
			if ( $existing && (int) $existing->store_id === $store_id ) {
				$entry_data['id'] = absint( $_POST['entry_id'] );
			}
		}

		$entry_id = MSCM()->db->save_entry( $entry_data );

		if ( ! $entry_id ) {
			$this->error( __( 'Failed to save entry. Please try again.', 'multi-store-cash-manager' ) );
			return;
		}

		// Log the action.
		MSCM()->db->log_audit( 'save_entry', 'entry', $entry_id, array(), $entry_data );

		// Run anomaly detection.
		if ( get_option( 'mscm_enable_anomaly_detection', 1 ) ) {
			MSCM()->ml->detect_anomalies( $entry_id );
		}

		// Determine discrepancy status label.
		$discrepancy        = $entry_data['discrepancy'];
		$currency           = get_option( 'mscm_currency', 'R' );
		$discrepancy_label  = '';
		if ( $discrepancy < 0 ) {
			/* translators: %1$s currency symbol, %2$s formatted amount */
			$discrepancy_label = sprintf(
				__( 'Shortage of %1$s%2$s', 'multi-store-cash-manager' ),
				$currency,
				number_format( abs( $discrepancy ), 2 )
			);
		} elseif ( $discrepancy > 0 ) {
			/* translators: %1$s currency symbol, %2$s formatted amount */
			$discrepancy_label = sprintf(
				__( 'Over by %1$s%2$s', 'multi-store-cash-manager' ),
				$currency,
				number_format( $discrepancy, 2 )
			);
		} else {
			$discrepancy_label = __( 'Balanced', 'multi-store-cash-manager' );
		}

		wp_send_json_success(
			array(
				'entry_id'          => $entry_id,
				'message'           => __( 'Entry saved successfully!', 'multi-store-cash-manager' ),
				'discrepancy_label' => $discrepancy_label,
				'totals'            => array(
					'total_cash'        => $entry_data['total_cash'],
					'cash_to_bank'      => $entry_data['cash_to_bank'],
					'total_sales'       => $entry_data['total_sales'],
					'discrepancy'       => $discrepancy,
					'discrepancy_label' => $discrepancy_label,
					'net_banking'       => $entry_data['net_banking'],
				),
			)
		);
	}

	/**
	 * Handle getting store data.
	 */
	public function get_store_data() {
		if ( ! $this->verify_request( 'mscm_public_nonce' ) ) {
			return;
		}

		$store_id = absint( $_GET['store_id'] ?? $_POST['store_id'] ?? 0 );

		if ( ! $store_id ) {
			$this->error( __( 'Store ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		if ( ! MSCM_Roles::user_can_access_store( $store_id ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$store = MSCM()->db->get_store( $store_id );

		if ( ! $store ) {
			$this->error( __( 'Store not found.', 'multi-store-cash-manager' ), 404 );
			return;
		}

		wp_send_json_success( array( 'store' => $store ) );
	}

	/**
	 * Handle getting dashboard statistics.
	 */
	public function get_dashboard_stats() {
		if ( ! $this->verify_request( 'mscm_public_nonce' ) ) {
			return;
		}

		$store_id = absint( $_POST['store_id'] ?? 0 );
		$date     = sanitize_text_field( $_POST['date'] ?? current_time( 'Y-m-d' ) );

		if ( $store_id && ! MSCM_Roles::user_can_access_store( $store_id ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$stats      = MSCM()->db->get_dashboard_stats( $store_id ?: null, $date );
		$chart_data = MSCM()->db->get_sales_chart_data(
			$store_id ?: null,
			date( 'Y-m-01', strtotime( $date ) ),
			$date
		);

		wp_send_json_success(
			array(
				'stats'      => $stats,
				'chart_data' => $chart_data,
			)
		);
	}

	/**
	 * Handle saving a store (admin or manager).
	 */
	public function save_store() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_manage_all_stores' ) && ! current_user_can( 'mscm_manage_store' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$data = array(
			'id'            => absint( $_POST['store_id'] ?? 0 ),
			'name'          => sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) ),
			'location'      => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
			'address'       => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'email'         => sanitize_email( $_POST['email'] ?? '' ),
			'manager_id'    => absint( $_POST['manager_id'] ?? 0 ),
			'float_amount'  => floatval( $_POST['float_amount'] ?? 500 ),
			'target_amount' => floatval( $_POST['target_amount'] ?? 0 ),
			'timezone'      => sanitize_text_field( wp_unslash( $_POST['timezone'] ?? 'Africa/Johannesburg' ) ),
			'is_active'     => absint( $_POST['is_active'] ?? 1 ),
		);

		// A non-admin manager may only edit stores they are assigned to.
		if ( ! current_user_can( 'mscm_manage_all_stores' ) && ! current_user_can( 'manage_options' ) ) {
			if ( ! empty( $data['id'] ) && ! MSCM_Roles::user_can_access_store( $data['id'] ) ) {
				$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
				return;
			}
		}

		if ( empty( $data['name'] ) ) {
			$this->error( __( 'Store name is required.', 'multi-store-cash-manager' ) );
			return;
		}

		$store_id = MSCM()->db->save_store( $data );

		if ( ! $store_id ) {
			$this->error( __( 'Failed to save store.', 'multi-store-cash-manager' ) );
			return;
		}

		MSCM()->db->log_audit( 'save_store', 'store', $store_id, array(), $data );

		wp_send_json_success(
			array(
				'store_id' => $store_id,
				'message'  => __( 'Store saved successfully!', 'multi-store-cash-manager' ),
			)
		);
	}

	/**
	 * Handle deleting a store (admin only).
	 */
	public function delete_store() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_manage_all_stores' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$store_id = absint( $_POST['store_id'] ?? 0 );

		if ( ! $store_id ) {
			$this->error( __( 'Store ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		$result = MSCM()->db->delete_store( $store_id );

		if ( ! $result ) {
			$this->error( __( 'Failed to delete store.', 'multi-store-cash-manager' ) );
			return;
		}

		MSCM()->db->log_audit( 'delete_store', 'store', $store_id );

		wp_send_json_success( array( 'message' => __( 'Store deleted successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle verifying a daily entry.
	 */
	public function verify_entry() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_verify_entry' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$entry_id = absint( $_POST['entry_id'] ?? 0 );

		if ( ! $entry_id ) {
			$this->error( __( 'Entry ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		$entry = MSCM()->db->get_entry( $entry_id );

		if ( ! $entry ) {
			$this->error( __( 'Entry not found.', 'multi-store-cash-manager' ), 404 );
			return;
		}

		// Check store access.
		if ( ! MSCM_Roles::user_can_access_store( $entry->store_id ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$result = MSCM()->db->verify_entry( $entry_id, get_current_user_id() );

		if ( ! $result ) {
			$this->error( __( 'Failed to verify entry.', 'multi-store-cash-manager' ) );
			return;
		}

		MSCM()->db->log_audit( 'verify_entry', 'entry', $entry_id );

		wp_send_json_success( array( 'message' => __( 'Entry verified successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle generating a report.
	 */
	public function generate_report() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$report_type = sanitize_text_field( $_POST['report_type'] ?? 'eod' );
		$store_id    = absint( $_POST['store_id'] ?? 0 );
		$date_from   = sanitize_text_field( $_POST['date_from'] ?? wp_date( 'Y-m-01' ) );
		$date_to     = sanitize_text_field( $_POST['date_to'] ?? wp_date( 'Y-m-d' ) );

		$report = MSCM()->reports->generate( $report_type, $store_id, $date_from, $date_to );

		wp_send_json_success( array( 'report' => $report ) );
	}

	/**
	 * Handle exporting data.
	 */
	public function export_data() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_export_reports' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$format    = sanitize_text_field( $_POST['format'] ?? 'csv' );
		$store_id  = absint( $_POST['store_id'] ?? 0 );
		$date_from = sanitize_text_field( $_POST['date_from'] ?? wp_date( 'Y-m-01' ) );
		$date_to   = sanitize_text_field( $_POST['date_to'] ?? wp_date( 'Y-m-d' ) );

		$url = MSCM()->export->export( $format, $store_id, $date_from, $date_to );

		if ( ! $url ) {
			$this->error( __( 'Export failed.', 'multi-store-cash-manager' ) );
			return;
		}

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Handle saving a sales target.
	 */
	public function save_target() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_set_targets' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$data = array(
			'id'             => absint( $_POST['target_id'] ?? 0 ),
			'store_id'       => absint( $_POST['store_id'] ?? 0 ),
			'period_type'    => sanitize_text_field( $_POST['period_type'] ?? 'monthly' ),
			'period_year'    => absint( $_POST['period_year'] ?? wp_date( 'Y' ) ),
			'period_month'   => absint( $_POST['period_month'] ?? wp_date( 'n' ) ),
			'period_quarter' => absint( $_POST['period_quarter'] ?? 0 ),
			'target_amount'  => floatval( $_POST['target_amount'] ?? 0 ),
			'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'created_by'     => get_current_user_id(),
		);

		if ( ! $data['store_id'] || ! $data['target_amount'] ) {
			$this->error( __( 'Store and target amount are required.', 'multi-store-cash-manager' ) );
			return;
		}

		$target_id = MSCM()->db->save_target( $data );

		if ( ! $target_id ) {
			$this->error( __( 'Failed to save target.', 'multi-store-cash-manager' ) );
			return;
		}

		wp_send_json_success(
			array(
				'target_id' => $target_id,
				'message'   => __( 'Target saved successfully!', 'multi-store-cash-manager' ),
			)
		);
	}

	/**
	 * Handle getting ML insights.
	 */
	public function get_ml_insights() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$store_id = absint( $_POST['store_id'] ?? 0 );
		$insights = MSCM()->ml->get_insights( $store_id ?: null );

		wp_send_json_success( array( 'insights' => $insights ) );
	}

	/**
	 * Handle assigning a user to a store.
	 */
	public function assign_user_store() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_manage_users' ) && ! current_user_can( 'mscm_manage_store' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$user_id  = absint( $_POST['user_id'] ?? 0 );
		$store_id = absint( $_POST['store_id'] ?? 0 );
		$role     = sanitize_text_field( $_POST['role'] ?? 'store_user' );

		if ( ! $user_id || ! $store_id ) {
			$this->error( __( 'User ID and Store ID are required.', 'multi-store-cash-manager' ) );
			return;
		}

		// A manager may only assign users to their own store.
		if ( ! current_user_can( 'mscm_manage_users' ) && ! current_user_can( 'manage_options' ) ) {
			if ( ! MSCM_Roles::user_can_access_store( $store_id ) ) {
				$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
				return;
			}
		}

		$result = MSCM()->db->assign_user_to_store( $user_id, $store_id, $role );

		if ( ! $result ) {
			$this->error( __( 'Failed to assign user to store.', 'multi-store-cash-manager' ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'User assigned to store successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle removing a user from a store.
	 */
	public function remove_user_store() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_manage_users' ) && ! current_user_can( 'mscm_manage_store' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$user_id  = absint( $_POST['user_id'] ?? 0 );
		$store_id = absint( $_POST['store_id'] ?? 0 );

		if ( ! $user_id || ! $store_id ) {
			$this->error( __( 'User ID and Store ID are required.', 'multi-store-cash-manager' ) );
			return;
		}

		// A manager may only remove users from their own store.
		if ( ! current_user_can( 'mscm_manage_users' ) && ! current_user_can( 'manage_options' ) ) {
			if ( ! MSCM_Roles::user_can_access_store( $store_id ) ) {
				$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
				return;
			}
		}

		$result = MSCM()->db->remove_user_from_store( $user_id, $store_id );

		if ( ! $result ) {
			$this->error( __( 'Failed to remove user from store.', 'multi-store-cash-manager' ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'User removed from store successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle resolving an anomaly.
	 */
	public function resolve_anomaly() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_resolve_anomalies' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$anomaly_id = absint( $_POST['anomaly_id'] ?? 0 );

		if ( ! $anomaly_id ) {
			$this->error( __( 'Anomaly ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mscm_anomalies';

		$result = $wpdb->update(
			$table,
			array(
				'is_resolved' => 1,
				'resolved_by' => get_current_user_id(),
				'resolved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $anomaly_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			$this->error( __( 'Failed to resolve anomaly.', 'multi-store-cash-manager' ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Anomaly resolved successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle getting a single entry.
	 */
	public function get_entry() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		$entry_id = absint( $_GET['entry_id'] ?? $_POST['entry_id'] ?? 0 );

		if ( ! $entry_id ) {
			$this->error( __( 'Entry ID is required.', 'multi-store-cash-manager' ) );
			return;
		}

		$entry = MSCM()->db->get_entry( $entry_id );

		if ( ! $entry ) {
			$this->error( __( 'Entry not found.', 'multi-store-cash-manager' ), 404 );
			return;
		}

		if ( ! MSCM_Roles::user_can_access_store( $entry->store_id ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		wp_send_json_success( array( 'entry' => $entry ) );
	}

	/**
	 * Handle saving settings.
	 */
	public function save_settings() {
		if ( ! $this->verify_request( 'mscm_admin_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'mscm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			$this->error( __( 'Permission denied.', 'multi-store-cash-manager' ), 403 );
			return;
		}

		$settings = array(
			'mscm_default_float'            => floatval( $_POST['default_float'] ?? 500 ),
			'mscm_currency'                 => sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'R' ) ),
			'mscm_notification_email'       => sanitize_email( $_POST['notification_email'] ?? '' ),
			'mscm_enable_reminders'         => absint( $_POST['enable_reminders'] ?? 0 ),
			'mscm_reminder_time'            => sanitize_text_field( $_POST['reminder_time'] ?? '18:00' ),
			'mscm_enable_anomaly_detection' => absint( $_POST['enable_anomaly_detection'] ?? 0 ),
			'mscm_enable_ml_predictions'    => absint( $_POST['enable_ml_predictions'] ?? 0 ),
			'mscm_ml_sensitivity'           => sanitize_text_field( $_POST['ml_sensitivity'] ?? 'medium' ),
			'mscm_api_enabled'              => absint( $_POST['api_enabled'] ?? 0 ),
			'mscm_enable_backups'           => absint( $_POST['enable_backups'] ?? 0 ),
			'mscm_backup_retention'         => absint( $_POST['backup_retention'] ?? 30 ),
		);

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully!', 'multi-store-cash-manager' ) ) );
	}

	/**
	 * Handle getting user notifications.
	 */
	public function get_notifications() {
		if ( ! $this->verify_request( 'mscm_public_nonce' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		global $wpdb;
		$table = $wpdb->prefix . 'mscm_notifications';

		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
				$user_id
			)
		);

		wp_send_json_success( array( 'notifications' => $notifications ) );
	}

	/**
	 * Handle marking a notification as read.
	 */
	public function mark_notification_read() {
		if ( ! $this->verify_request( 'mscm_public_nonce' ) ) {
			return;
		}

		$notification_id = absint( $_POST['notification_id'] ?? 0 );
		$user_id         = get_current_user_id();

		global $wpdb;
		$table = $wpdb->prefix . 'mscm_notifications';

		$wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array( 'id' => $notification_id, 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d', '%d' )
		);

		wp_send_json_success( array( 'message' => __( 'Notification marked as read.', 'multi-store-cash-manager' ) ) );
	}
}
