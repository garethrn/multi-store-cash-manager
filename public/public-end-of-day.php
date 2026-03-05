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

	if ( ! current_user_can( 'mscm_submit_entry' ) ) {
		return '<div class="mscm-error"><p>' . esc_html__( 'You do not have permission to submit entries.', 'multi-store-cash-manager' ) . '</p></div>';
	}

	$atts = shortcode_atts(
		array(
			'store_id' => absint( $_GET['store'] ?? 0 ),
		),
		$atts,
		'mscm_end_of_day'
	);

	$user_id      = get_current_user_id();
	$stores       = MSCM()->db->get_user_stores( $user_id );
	$currency     = get_option( 'mscm_currency', 'R' );
	$default_float = get_option( 'mscm_default_float', 500 );
	$today        = current_time( 'Y-m-d' );
	$selected_store = absint( $atts['store_id'] );
	$geo_enabled  = get_option( 'mscm_enable_geolocation', 0 );

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
	);

	ob_start();
	?>
	<div class="mscm-eod-wrapper">
		<h2 class="mscm-form-title"><?php esc_html_e( 'Daily End of Day Entry', 'multi-store-cash-manager' ); ?></h2>

		<div id="mscm-eod-message" class="mscm-message" style="display:none;"></div>

		<form id="mscm-eod-form" class="mscm-form" novalidate>
			<?php wp_nonce_field( 'mscm_public_nonce', '_wpnonce' ); ?>
			<input type="hidden" name="action" value="mscm_save_daily_entry">

			<!-- Store & Date Selection -->
			<div class="mscm-form-section">
				<h3><?php esc_html_e( 'Store Information', 'multi-store-cash-manager' ); ?></h3>
				<div class="mscm-form-row">
					<div class="mscm-form-group">
						<label for="mscm-store"><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?> *</label>
						<select id="mscm-store" name="store_id" required class="mscm-select">
							<?php if ( count( $stores ) > 1 ) : ?>
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
					</div>
					<div class="mscm-form-group">
						<label for="mscm-entry-date"><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?> *</label>
						<input type="date" id="mscm-entry-date" name="entry_date"
							value="<?php echo esc_attr( $today ); ?>"
							max="<?php echo esc_attr( $today ); ?>"
							required class="mscm-input">
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
									min="0" value="0"
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
								value="<?php echo esc_attr( $default_float ); ?>"
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
								value="0" min="0" step="0.01" class="mscm-input mscm-payment-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-eft">🏦 <?php esc_html_e( 'EFT', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-eft" name="eft"
								value="0" min="0" step="0.01" class="mscm-input mscm-payment-input">
						</div>
					</div>
					<div class="mscm-form-group">
						<label for="mscm-other-digital">📱 <?php esc_html_e( 'Other Digital', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-other-digital" name="other_digital"
								value="0" min="0" step="0.01" class="mscm-input mscm-payment-input">
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
				<div class="mscm-form-row">
					<div class="mscm-form-group">
						<label for="mscm-pos-reported"><?php esc_html_e( 'POS Reported Sales', 'multi-store-cash-manager' ); ?></label>
						<div class="mscm-input-currency">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="mscm-pos-reported" name="pos_reported"
								value="0" min="0" step="0.01" class="mscm-input">
						</div>
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
					<div class="mscm-summary-row mscm-summary-banking">
						<span><?php esc_html_e( 'Cash to Bank:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-summary-banking"><?php echo esc_html( $currency . '0.00' ); ?></span>
					</div>
					<div class="mscm-summary-row mscm-discrepancy-row">
						<span><?php esc_html_e( 'Discrepancy:', 'multi-store-cash-manager' ); ?></span>
						<span id="mscm-discrepancy"><?php echo esc_html( $currency . '0.00' ); ?></span>
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
						placeholder="<?php esc_attr_e( 'Optional notes about today\'s entry...', 'multi-store-cash-manager' ); ?>"></textarea>
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
					<?php esc_html_e( 'Submit Entry', 'multi-store-cash-manager' ); ?>
				</button>
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
	<?php

	return ob_get_clean();
}
