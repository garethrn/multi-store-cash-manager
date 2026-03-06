<?php
/**
 * End of day form shortcode.
 *
 * Shortcode: [mscm_end_of_day]
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the end of day entry form shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function mscm_end_of_day_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="mscm-login-notice"><p>' .
			wp_kses_post( sprintf(
				/* translators: login URL */
				__( 'Please <a href="%s">log in</a> to submit your daily entry.', 'multi-store-cash-manager' ),
				esc_url( wp_login_url( get_permalink() ) )
			) ) .
			'</p></div>';
	}

	$can_submit = current_user_can( 'mscm_submit_entry' );
	$can_edit   = current_user_can( 'mscm_verify_entry' ) || current_user_can( 'manage_options' );

	// Support edit mode: ?edit_entry=<entry_id> for admins/managers.
	$edit_entry_id   = absint( $_GET['edit_entry'] ?? 0 );
	$existing_entry  = null;
	$is_edit_mode    = false;

	if ( $edit_entry_id && $can_edit ) {
		$existing_entry = MSCM()->db->get_entry( $edit_entry_id );
		if ( $existing_entry && MSCM_Roles::user_can_access_store( $existing_entry->store_id ) ) {
			$is_edit_mode = true;
		} else {
			$existing_entry = null;
		}
	}

	if ( ! $can_submit && ! ( $is_edit_mode && $can_edit ) ) {
		return '<div class="mscm-error"><p>' . esc_html__( 'You do not have permission to submit entries.', 'multi-store-cash-manager' ) . '</p></div>';
	}

	$atts = shortcode_atts(
		array(
			'store_id' => absint( $_GET['store'] ?? 0 ),
		),
		$atts,
		'mscm_end_of_day'
	);

	$user_id       = get_current_user_id();
	$stores        = MSCM()->db->get_user_stores( $user_id );
	$currency      = get_option( 'mscm_currency', 'R' );
	$default_float = get_option( 'mscm_default_float', 500 );
	$today         = current_time( 'Y-m-d' );
	$selected_store = $is_edit_mode ? absint( $existing_entry->store_id ) : absint( $atts['store_id'] );
	$geo_enabled   = get_option( 'mscm_enable_geolocation', 0 );

	// In edit mode, admins can see all stores.
	if ( $is_edit_mode && empty( $stores ) ) {
		$stores = MSCM()->db->get_stores();
	}

	if ( empty( $stores ) ) {
		return '<div class="mscm-notice"><p>' . esc_html__( 'You are not assigned to any stores yet. Please contact your administrator.', 'multi-store-cash-manager' ) . '</p></div>';
	}

	// Denominations.
	$denominations = array(
		'denom_200' => array( 'label' => 'R200', 'value' => 200 ),
		'denom_100' => array( 'label' => 'R100', 'value' => 100 ),
		'denom_50'  => array( 'label' => 'R50', 'value' => 50 ),
		'denom_20'  => array( 'label' => 'R20', 'value' => 20 ),
		'denom_10'  => array( 'label' => 'R10', 'value' => 10 ),
		'denom_5'   => array( 'label' => 'R5', 'value' => 5 ),
		'denom_2'   => array( 'label' => 'R2', 'value' => 2 ),
		'denom_1'   => array( 'label' => 'R1', 'value' => 1 ),
		'denom_50c' => array( 'label' => '50c', 'value' => 0.5 ),
		'denom_20c' => array( 'label' => '20c', 'value' => 0.2 ),
		'denom_10c' => array( 'label' => '10c', 'value' => 0.1 ),
	);

	// Opening float denomination fields (maps to open_float_denom_* columns).
	$open_float_denominations = array(
		'open_float_denom_200' => array( 'label' => 'R200', 'value' => 200 ),
		'open_float_denom_100' => array( 'label' => 'R100', 'value' => 100 ),
		'open_float_denom_50'  => array( 'label' => 'R50', 'value' => 50 ),
		'open_float_denom_20'  => array( 'label' => 'R20', 'value' => 20 ),
		'open_float_denom_10'  => array( 'label' => 'R10', 'value' => 10 ),
		'open_float_denom_5'   => array( 'label' => 'R5', 'value' => 5 ),
		'open_float_denom_2'   => array( 'label' => 'R2', 'value' => 2 ),
		'open_float_denom_1'   => array( 'label' => 'R1', 'value' => 1 ),
		'open_float_denom_50c' => array( 'label' => '50c', 'value' => 0.5 ),
		'open_float_denom_20c' => array( 'label' => '20c', 'value' => 0.2 ),
		'open_float_denom_10c' => array( 'label' => '10c', 'value' => 0.1 ),
	);

	// For edit mode, pass existing entry data to JS.
	$entry_js_data = array();
	if ( $is_edit_mode && $existing_entry ) {
		$entry_js_data = array(
			'entry_id'        => (int) $existing_entry->id,
			'store_id'        => (int) $existing_entry->store_id,
			'entry_date'      => $existing_entry->entry_date,
			'float_amount'    => floatval( $existing_entry->float_amount ),
			'credit_card'     => floatval( $existing_entry->credit_card ),
			'eft'             => floatval( $existing_entry->eft ),
			'other_digital'   => floatval( $existing_entry->other_digital ),
			'pos_cash'        => floatval( $existing_entry->pos_cash ?? 0 ),
			'pos_eft'         => floatval( $existing_entry->pos_eft ?? 0 ),
			'pos_credit_card' => floatval( $existing_entry->pos_credit_card ?? 0 ),
			'pos_reported'    => floatval( $existing_entry->pos_reported ),
			'banking_date'    => $existing_entry->banking_date ?? '',
			'banked_by'       => $existing_entry->banked_by ?? '',
			'banking_ref'     => $existing_entry->banking_ref ?? '',
			'notes'           => $existing_entry->notes,
			'payouts'         => array_map( function( $p ) {
				return array(
					'description'  => $p->description,
					'category'     => $p->category,
					'amount'       => floatval( $p->amount ),
					'payment_type' => $p->payment_type ?? 'cash',
				);
			}, $existing_entry->payouts ?? array() ),
			'purchases'       => array_map( function( $p ) {
				return array(
					'description'    => $p->description,
					'amount'         => floatval( $p->amount ),
					'receipt_number' => $p->receipt_number ?? '',
				);
			}, $existing_entry->purchases ?? array() ),
		);
		// Denomination counts.
		foreach ( $denominations as $field => $denom ) {
			$entry_js_data[ $field ] = (int) ( $existing_entry->$field ?? 0 );
		}
		// Opening float denomination counts.
		foreach ( $open_float_denominations as $field => $denom ) {
			$entry_js_data[ $field ] = (int) ( $existing_entry->$field ?? 0 );
		}
	}

	ob_start();
	?>
	<div class="mscm-eod-wrapper">
		<h2 class="mscm-form-title">
			<?php echo $is_edit_mode
				? esc_html__( 'Edit Daily Entry', 'multi-store-cash-manager' )
				: esc_html__( 'Daily End of Day Entry', 'multi-store-cash-manager' );
			?>
		</h2>
		<?php if ( $is_edit_mode ) : ?>
		<div class="mscm-notice" style="margin-bottom:16px;">
			<p><?php
				echo esc_html( sprintf(
					/* translators: entry date */
					__( 'Editing entry for %s', 'multi-store-cash-manager' ),
					$existing_entry->entry_date
				) );
			?></p>
		</div>
		<?php endif; ?>

		<div id="mscm-eod-message" class="mscm-message" style="display:none;"></div>

		<form id="mscm-eod-form" class="mscm-form" novalidate>
			<?php wp_nonce_field( 'mscm_public_nonce', 'nonce' ); ?>
			<input type="hidden" name="action" value="mscm_save_daily_entry">
			<?php if ( $is_edit_mode ) : ?>
			<input type="hidden" name="entry_id" value="<?php echo esc_attr( $existing_entry->id ); ?>">
			<?php endif; ?>

			<!-- Store & Date Selection -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'Store Information', 'multi-store-cash-manager' ); ?></h3>
				<div class="mscm-form-row">
					<div class="mscm-form-group">
						<label for="mscm-store"><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?> *</label>
						<select id="mscm-store" name="store_id" required class="mscm-select" <?php echo $is_edit_mode ? 'disabled' : ''; ?>>
							<?php if ( ! $is_edit_mode && count( $stores ) > 1 ) : ?>
								<option value=""><?php esc_html_e( 'Select Store', 'multi-store-cash-manager' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( $store->id ); ?>"
									data-float="<?php echo esc_attr( $store->float_amount ); ?>"
									<?php selected( $selected_store ? $selected_store : ( count( $stores ) === 1 ? $store->id : '' ), $store->id ); ?>>
									<?php echo esc_html( $store->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( $is_edit_mode ) : ?>
						<input type="hidden" name="store_id" value="<?php echo esc_attr( $existing_entry->store_id ); ?>">
						<?php endif; ?>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-entry-date"><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?> *</label>
						<input type="date" id="mscm-entry-date" name="entry_date"
							value="<?php echo esc_attr( $is_edit_mode ? $existing_entry->entry_date : $today ); ?>"
							<?php if ( ! $is_edit_mode ) : ?>max="<?php echo esc_attr( $today ); ?>"<?php endif; ?>
							<?php echo $is_edit_mode ? 'readonly' : ''; ?>
							required class="mscm-input">
					</div>
				</div>
			</div>

			<!-- Opening Float Check -->
			<div class="mscm-form-section mscm-opening-float-section">
				<h3>🏦 <?php esc_html_e( 'Opening Float Check', 'multi-store-cash-manager' ); ?></h3>
				<p class="mscm-section-desc"><?php esc_html_e( 'Enter the actual denominations of cash in the float at the start of the day. This verifies your opening float is correct.', 'multi-store-cash-manager' ); ?></p>
				<div class="mscm-denomination-grid">
					<?php foreach ( $open_float_denominations as $field => $denom ) : ?>
						<div class="mscm-denom-row">
							<label class="mscm-denom-label">
								<span class="mscm-denom-value"><?php echo esc_html( $denom['label'] ); ?></span>
							</label>
							<div class="mscm-denom-input-group">
								<span class="mscm-denom-mult">×</span>
								<input type="number" name="<?php echo esc_attr( $field ); ?>"
									id="<?php echo esc_attr( $field ); ?>"
									class="mscm-input mscm-open-float-count"
									min="0" value="<?php echo esc_attr( $is_edit_mode ? intval( $existing_entry->$field ?? 0 ) : 0 ); ?>"
									data-value="<?php echo esc_attr( $denom['value'] ); ?>">
								<span class="mscm-denom-equals">=</span>
								<span class="mscm-denom-total" id="<?php echo esc_attr( $field ); ?>_total">
									<?php echo esc_html( $currency . '0.00' ); ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="mscm-cash-summary mscm-opening-float-summary">
					<div class="mscm-summary-row mscm-summary-total">
						<span><?php esc_html_e( 'Opening Float Counted:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-opening-float-total"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Expected Float:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-expected-float"><?php echo esc_html( $currency . number_format( $default_float, 2 ) ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Variance:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-opening-float-variance"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span></span>
						<span id="mscm-opening-float-status"></span>
					</div>
				</div>
			</div>

			<!-- Cash Denominations -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'Cash Count', 'multi-store-cash-manager' ); ?></h3>
				<div class="mscm-denomination-grid">
					<?php foreach ( $denominations as $field => $denom ) : ?>
						<div class="mscm-denom-row">
							<label class="mscm-denom-label">
								<span class="mscm-denom-value"><?php echo esc_html( $denom['label'] ); ?></span>
							</label>
							<div class="mscm-denom-input-group">
								<span class="mscm-denom-mult">×</span>
								<input type="number" name="<?php echo esc_attr( $field ); ?>"
									id="<?php echo esc_attr( $field ); ?>"
									class="mscm-input mscm-denom-count"
									min="0" value="<?php echo esc_attr( $is_edit_mode ? intval( $existing_entry->$field ?? 0 ) : 0 ); ?>"
									data-value="<?php echo esc_attr( $denom['value'] ); ?>">
								<span class="mscm-denom-equals">=</span>
								<span class="mscm-denom-total" id="<?php echo esc_attr( $field ); ?>_total">
									<?php echo esc_html( $currency . '0.00' ); ?>
								</span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Cash Summary -->
				<div class="mscm-cash-summary">
					<div class="mscm-summary-row mscm-summary-total">
						<span><?php esc_html_e( 'Total Cash:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-total-cash"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Float Amount:', 'multi-store-cash-manager' ); ?></span>
						<div class="mscm-float-input">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" name="float_amount" id="mscm-float"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->float_amount ) : $default_float ); ?>"
								min="0" step="0.01" class="mscm-input mscm-input-sm">
						</div>
					</div>
					<div class="mscm-summary-row mscm-summary-bank">
						<span><?php esc_html_e( 'Cash to Bank:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-cash-to-bank"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Payment Types -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'Other Payments', 'multi-store-cash-manager' ); ?></h3>
				<div class="mscm-payment-grid">
					<div class="mscm-form-group">
						<label for="mscm-credit-card">💳 <?php esc_html_e( 'Credit/Debit Card', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-credit-card" name="credit_card"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->credit_card ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-payment-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-eft">🏦 <?php esc_html_e( 'EFT', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-eft" name="eft"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->eft ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-payment-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-other-digital">📱 <?php esc_html_e( 'Other Digital', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-other-digital" name="other_digital"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->other_digital ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-payment-input">
						</div>
					</div>
				</div>
			</div>

			<!-- Payouts -->
			<div class="mscm-form-section">
				<h3>
					<?php esc_html_e( 'Payouts', 'multi-store-cash-manager' ); ?>
					<button type="button" class="mscm-btn mscm-btn-sm mscm-btn-outline" id="mscm-add-payout">
						+ <?php esc_html_e( 'Add Payout', 'multi-store-cash-manager' ); ?>
					</button>
				</h3>
				<div id="mscm-payouts-container">
					<!-- Dynamic payout rows added by JS -->
				</div>
				<div class="mscm-summary-row">
					<strong><?php esc_html_e( 'Total Payouts:', 'multi-store-cash-manager' ); ?></strong>
					<strong id="mscm-total-payouts"><?php echo esc_html( $currency . '0.00' ); ?></strong>
				</div>
			</div>

			<!-- Purchases / Expenses -->
			<div class="mscm-form-section">
				<h3>
					<?php esc_html_e( 'Purchases / Expenses', 'multi-store-cash-manager' ); ?>
					<button type="button" class="mscm-btn mscm-btn-sm mscm-btn-outline" id="mscm-add-purchase">
						+ <?php esc_html_e( 'Add Purchase', 'multi-store-cash-manager' ); ?>
					</button>
				</h3>
				<div id="mscm-purchases-container">
					<!-- Dynamic purchase rows added by JS -->
				</div>
				<div class="mscm-summary-row">
					<strong><?php esc_html_e( 'Total Purchases:', 'multi-store-cash-manager' ); ?></strong>
					<strong id="mscm-total-purchases"><?php echo esc_html( $currency . '0.00' ); ?></strong>
				</div>
			</div>

			<!-- POS Comparison -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'POS System Comparison', 'multi-store-cash-manager' ); ?></h3>
				<p class="mscm-section-desc"><?php esc_html_e( 'Enter sales figures from the Point of Sale system. The total will be used to calculate the discrepancy.', 'multi-store-cash-manager' ); ?></p>
				<div class="mscm-payment-grid">
					<div class="mscm-form-group">
						<label for="mscm-pos-cash">💵 <?php esc_html_e( 'POS Cash Takings', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-pos-cash" name="pos_cash"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->pos_cash ?? 0 ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-pos-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-pos-eft">🏦 <?php esc_html_e( 'POS EFT', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-pos-eft" name="pos_eft"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->pos_eft ?? 0 ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-pos-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-pos-credit-card">💳 <?php esc_html_e( 'POS Credit Cards', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-pos-credit-card" name="pos_credit_card"
								value="<?php echo esc_attr( $is_edit_mode ? floatval( $existing_entry->pos_credit_card ?? 0 ) : 0 ); ?>"
								min="0" step="0.01" class="mscm-input mscm-pos-input">
						</div>
					</div>
				</div>
				<div class="mscm-summary-row mscm-summary-total" style="margin-top:8px;">
					<strong><?php esc_html_e( 'POS Total Sales:', 'multi-store-cash-manager' ); ?></strong>
					<strong id="mscm-pos-total"><?php echo esc_html( $currency . '0.00' ); ?></strong>
				</div>
			</div>

			<!-- Banking Details -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'Banking Details', 'multi-store-cash-manager' ); ?></h3>
				<p class="mscm-section-desc"><?php esc_html_e( 'Banking is usually done at a later date. Fill in these details once the cash has been banked.', 'multi-store-cash-manager' ); ?></p>
				<div class="mscm-form-row">
					<div class="mscm-form-group">
						<label for="mscm-banking-date"><?php esc_html_e( 'Date Banked', 'multi-store-cash-manager' ); ?></label>
						<input type="date" id="mscm-banking-date" name="banking_date"
							value="<?php echo esc_attr( $is_edit_mode ? ( $existing_entry->banking_date ?? '' ) : '' ); ?>"
							class="mscm-input">
					</div>
					<div class="mscm-form-group">
						<label for="mscm-banked-by"><?php esc_html_e( 'Banked By', 'multi-store-cash-manager' ); ?></label>
						<input type="text" id="mscm-banked-by" name="banked_by"
							value="<?php echo esc_attr( $is_edit_mode ? ( $existing_entry->banked_by ?? '' ) : '' ); ?>"
							placeholder="<?php esc_attr_e( 'Name of person who banked the cash', 'multi-store-cash-manager' ); ?>"
							class="mscm-input">
					</div>
					<div class="mscm-form-group">
						<label for="mscm-banking-ref"><?php esc_html_e( 'Banking Reference', 'multi-store-cash-manager' ); ?></label>
						<input type="text" id="mscm-banking-ref" name="banking_ref"
							value="<?php echo esc_attr( $is_edit_mode ? ( $existing_entry->banking_ref ?? '' ) : '' ); ?>"
							placeholder="<?php esc_attr_e( 'Bank deposit slip / reference number', 'multi-store-cash-manager' ); ?>"
							class="mscm-input">
					</div>
				</div>
			</div>

			<!-- Final Summary -->
			<div class="mscm-form-section mscm-summary-section">
				<h3><?php esc_html_e( 'Summary', 'multi-store-cash-manager' ); ?></h3>
				<div class="mscm-banking-summary">
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Total Cash:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-cash"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Credit/Debit Card:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-card"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'EFT:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-eft"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row mscm-summary-total">
						<span><?php esc_html_e( 'Total Sales:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-total-sales"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-divider"></div>
					<div class="mscm-summary-row">
						<span><?php esc_html_e( 'Less Float:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-float">- <?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row" id="mscm-cash-payouts-row" style="display:none;">
						<span><?php esc_html_e( 'Cash Payouts (included in sales):', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-cash-payouts"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row mscm-summary-banking">
						<span><?php esc_html_e( 'Cash to Bank:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-banking"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row mscm-discrepancy-row">
						<span><?php esc_html_e( 'Discrepancy:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-discrepancy"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row" id="mscm-discrepancy-status-row" style="display:none;">
						<span></span>
						<span id="mscm-discrepancy-status" class="mscm-discrepancy-label"></span>
					</div>
					<div class="mscm-summary-row mscm-summary-net">
						<span><?php esc_html_e( 'Net Banking:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-net-banking"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Notes -->
			<div class="mscm-form-section">
				<div class="mscm-form-group">
					<label for="mscm-notes"><?php esc_html_e( 'Notes', 'multi-store-cash-manager' ); ?></label>
					<textarea id="mscm-notes" name="notes" rows="3" class="mscm-textarea"
						placeholder="<?php esc_attr_e( 'Optional notes about today\'s entry...', 'multi-store-cash-manager' ); ?>"><?php echo esc_textarea( $is_edit_mode ? $existing_entry->notes : '' ); ?></textarea>
				</div>
			</div>

			<?php if ( $geo_enabled ) : ?>
			<!-- Hidden geolocation fields -->
			<input type="hidden" id="mscm-latitude" name="latitude" value="">
			<input type="hidden" id="mscm-longitude" name="longitude" value="">
			<?php endif; ?>

			<!-- Submit -->
			<div class="mscm-form-actions">
				<button type="submit" id="mscm-submit-btn" class="mscm-btn mscm-btn-primary mscm-btn-lg">
					<?php echo $is_edit_mode
						? esc_html__( 'Update Entry', 'multi-store-cash-manager' )
						: esc_html__( 'Submit Entry', 'multi-store-cash-manager' );
					?>
				</button>
				<?php if ( $is_edit_mode ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( 'edit_entry' ) ); ?>" class="mscm-btn mscm-btn-outline">
					<?php esc_html_e( 'Cancel', 'multi-store-cash-manager' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</form>
	</div>

	<!-- Payout row template -->
	<template id="mscm-payout-template">
		<div class="mscm-dynamic-row">
			<input type="text" name="payout_description[]" placeholder="<?php esc_attr_e( 'Description', 'multi-store-cash-manager' ); ?>"
				class="mscm-input mscm-dyn-description">
			<select name="payout_category[]" class="mscm-select mscm-dyn-category">
				<option value="general"><?php esc_html_e( 'General', 'multi-store-cash-manager' ); ?></option>
				<option value="wages"><?php esc_html_e( 'Wages', 'multi-store-cash-manager' ); ?></option>
				<option value="supplier"><?php esc_html_e( 'Supplier', 'multi-store-cash-manager' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other', 'multi-store-cash-manager' ); ?></option>
			</select>
			<select name="payout_payment_type[]" class="mscm-select mscm-dyn-payment-type">
				<option value="cash">💵 <?php esc_html_e( 'Cash', 'multi-store-cash-manager' ); ?></option>
				<option value="bank">🏦 <?php esc_html_e( 'Bank Transfer', 'multi-store-cash-manager' ); ?></option>
			</select>
			<div class="mscm-input-currency">
				<span><?php echo esc_html( $currency ); ?></span>
				<input type="number" name="payout_amount[]" min="0" step="0.01" value="0"
					class="mscm-input mscm-dyn-amount mscm-payout-amount">
			</div>
			<button type="button" class="mscm-btn mscm-btn-danger mscm-btn-sm mscm-remove-row">✕</button>
		</div>
	</template>

	<!-- Purchase row template -->
	<template id="mscm-purchase-template">
		<div class="mscm-dynamic-row">
			<input type="text" name="purchase_description[]" placeholder="<?php esc_attr_e( 'Description', 'multi-store-cash-manager' ); ?>"
				class="mscm-input mscm-dyn-description">
			<input type="text" name="purchase_receipt[]" placeholder="<?php esc_attr_e( 'Receipt #', 'multi-store-cash-manager' ); ?>"
				class="mscm-input mscm-dyn-receipt">
			<div class="mscm-input-currency">
				<span><?php echo esc_html( $currency ); ?></span>
				<input type="number" name="purchase_amount[]" min="0" step="0.01" value="0"
					class="mscm-input mscm-dyn-amount mscm-purchase-amount">
			</div>
			<button type="button" class="mscm-btn mscm-btn-danger mscm-btn-sm mscm-remove-row">✕</button>
		</div>
	</template>

	<?php if ( ! empty( $entry_js_data ) ) : ?>
	<script>
	window.mscmEditEntry = <?php echo wp_json_encode( $entry_js_data ); ?>;
	</script>
	<?php endif; ?>
	<?php

	return ob_get_clean();
}
