/**
 * Multi-Store Cash Manager - Frontend JavaScript
 *
 * @package MultiStoreCashManager
 * @version 1.0.0
 */

/* global mscmPublic */

( function ( $ ) {
	'use strict';

	const ajaxUrl     = mscmPublic.ajaxUrl;
	const nonce       = mscmPublic.nonce;
	const currency    = mscmPublic.currency || 'R';
	const floatAmount = parseFloat( mscmPublic.floatAmount ) || 500;
	const strings     = mscmPublic.strings;

	// =========================================================================
	// Utilities.
	// =========================================================================

	/**
	 * Format a number as currency string.
	 *
	 * @param {number} amount Amount to format.
	 * @return {string} Formatted currency string.
	 */
	function formatCurrency( amount ) {
		return currency + parseFloat( amount || 0 ).toFixed( 2 );
	}

	/**
	 * Show a message inside the form's message container.
	 *
	 * @param {string} message Message text.
	 * @param {string} type    Message type: 'success' or 'error'.
	 */
	function showMessage( message, type ) {
		const $msg = $( '#mscm-eod-message' );
		$msg
			.removeClass( 'mscm-success mscm-error' )
			.addClass( 'mscm-' + type )
			.text( message )
			.show();

		if ( 'success' === type ) {
			$( 'html, body' ).animate( { scrollTop: $msg.offset().top - 80 }, 400 );
		}
	}

	// =========================================================================
	// End of Day Form Calculations.
	// =========================================================================

	function initEODForm() {
		const $form = $( '#mscm-eod-form' );
		if ( ! $form.length ) {
			return;
		}

		// Set initial float from store data.
		const $storeSelect = $( '#mscm-store' );
		updateFloatFromStore();

		$storeSelect.on( 'change', updateFloatFromStore );

		/**
		 * Update float amount when store changes.
		 */
		function updateFloatFromStore() {
			const $selected = $storeSelect.find( ':selected' );
			const storeFloat = parseFloat( $selected.data( 'float' ) );

			if ( storeFloat > 0 ) {
				$( '#mscm-float' ).val( storeFloat.toFixed( 2 ) );
			}

			recalculateAll();
		}

		/**
		 * Recalculate all totals.
		 */
		function recalculateAll() {
			let totalCash = 0;

			// Calculate denomination totals.
			$( '.mscm-denom-count' ).each( function () {
				const $input  = $( this );
				const count   = parseInt( $input.val() ) || 0;
				const value   = parseFloat( $input.data( 'value' ) ) || 0;
				const subtotal = count * value;
				const fieldId  = $input.attr( 'id' );

				$( '#' + fieldId + '_total' ).text( formatCurrency( subtotal ) );
				totalCash += subtotal;
			} );

			const float      = parseFloat( $( '#mscm-float' ).val() ) || 0;
			const cashToBank = Math.max( 0, totalCash - float );

			const creditCard   = parseFloat( $( '#mscm-credit-card' ).val() ) || 0;
			const eft          = parseFloat( $( '#mscm-eft' ).val() ) || 0;
			const otherDigital = parseFloat( $( '#mscm-other-digital' ).val() ) || 0;

			// Total sales uses cash-to-bank (float excluded) + other payment types.
			const totalSales = cashToBank + creditCard + eft + otherDigital;

			const posReported  = parseFloat( $( '#mscm-pos-reported' ).val() ) || 0;
			const discrepancy  = totalSales - posReported;

			const totalPayouts    = calcDynamicTotal( '.mscm-payout-amount' );
			const totalPurchases  = calcDynamicTotal( '.mscm-purchase-amount' );
			const netBanking      = cashToBank - totalPayouts - totalPurchases;

			// Update display.
			$( '#mscm-total-cash' ).text( formatCurrency( totalCash ) );
			$( '#mscm-cash-to-bank' ).text( formatCurrency( cashToBank ) );

			$( '#mscm-summary-cash' ).text( formatCurrency( totalCash ) );
			$( '#mscm-summary-float' ).text( '- ' + formatCurrency( float ) );
			$( '#mscm-summary-card' ).text( formatCurrency( creditCard ) );
			$( '#mscm-summary-eft' ).text( formatCurrency( eft ) );
			$( '#mscm-total-sales' ).text( formatCurrency( totalSales ) );
			$( '#mscm-summary-banking' ).text( formatCurrency( cashToBank ) );
			$( '#mscm-total-payouts' ).text( formatCurrency( totalPayouts ) );
			$( '#mscm-total-purchases' ).text( formatCurrency( totalPurchases ) );
			$( '#mscm-net-banking' ).text( formatCurrency( netBanking ) );

			// Discrepancy with color and shortage/over label.
			const $discEl     = $( '#mscm-discrepancy' );
			const $statusRow  = $( '#mscm-discrepancy-status-row' );
			const $statusEl   = $( '#mscm-discrepancy-status' );

			$discEl.text( formatCurrency( discrepancy ) );
			$discEl.removeClass( 'mscm-text-danger mscm-text-success mscm-text-warning' );

			let statusText  = '';
			let statusClass = '';

			if ( discrepancy < 0 ) {
				statusText  = 'Shortage of ' + formatCurrency( Math.abs( discrepancy ) );
				statusClass = 'mscm-text-danger';
				$discEl.addClass( 'mscm-text-danger' );
			} else if ( discrepancy > 0 ) {
				statusText  = 'Over by ' + formatCurrency( discrepancy );
				statusClass = 'mscm-text-warning';
				$discEl.addClass( 'mscm-text-warning' );
			} else {
				statusText  = 'Balanced';
				statusClass = 'mscm-text-success';
				$discEl.addClass( 'mscm-text-success' );
			}

			$statusEl.text( statusText ).removeClass( 'mscm-text-danger mscm-text-warning mscm-text-success' ).addClass( statusClass );
			$statusRow.toggle( posReported > 0 );
		}

		/**
		 * Calculate sum of all inputs matching a selector.
		 *
		 * @param {string} selector jQuery selector.
		 * @return {number} Sum of values.
		 */
		function calcDynamicTotal( selector ) {
			let total = 0;
			$( selector ).each( function () {
				total += parseFloat( $( this ).val() ) || 0;
			} );
			return total;
		}

		// Bind calculation events.
		$form.on( 'input change', '.mscm-denom-count, #mscm-float, .mscm-payment-input, #mscm-pos-reported, .mscm-payout-amount, .mscm-purchase-amount', recalculateAll );

		// =========================================================================
		// Dynamic Rows: Payouts.
		// =========================================================================

		const payoutTemplate   = document.getElementById( 'mscm-payout-template' );
		const purchaseTemplate = document.getElementById( 'mscm-purchase-template' );

		$( '#mscm-add-payout' ).on( 'click', function () {
			if ( ! payoutTemplate ) {
				return;
			}
			const clone = payoutTemplate.content.cloneNode( true );
			$( '#mscm-payouts-container' ).append( clone );
		} );

		$( '#mscm-add-purchase' ).on( 'click', function () {
			if ( ! purchaseTemplate ) {
				return;
			}
			const clone = purchaseTemplate.content.cloneNode( true );
			$( '#mscm-purchases-container' ).append( clone );
		} );

		// Remove dynamic row.
		$( document ).on( 'click', '.mscm-remove-row', function () {
			$( this ).closest( '.mscm-dynamic-row' ).remove();
			recalculateAll();
		} );

		// =========================================================================
		// Geolocation.
		// =========================================================================

		if ( $( '#mscm-latitude' ).length && navigator.geolocation ) {
			navigator.geolocation.getCurrentPosition(
				function ( pos ) {
					$( '#mscm-latitude' ).val( pos.coords.latitude );
					$( '#mscm-longitude' ).val( pos.coords.longitude );
				},
				function () {
					// Silently fail if geolocation not available.
				}
			);
		}

		// =========================================================================
		// Form Submission.
		// =========================================================================

		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			// Validate.
			const storeId = $( '#mscm-store' ).val();
			if ( ! storeId ) {
				showMessage( 'Please select a store.', 'error' );
				return;
			}

			if ( ! window.confirm( strings.confirmSubmit ) ) {
				return;
			}

			const $submitBtn = $( '#mscm-submit-btn' );
			$submitBtn.text( strings.submitting ).prop( 'disabled', true );

			const formData = $form.serialize();

			$.post( ajaxUrl, formData, function ( response ) {
				$submitBtn.prop( 'disabled', false ).text( 'Submit Entry' );

				if ( response.success ) {
					showMessage( strings.submitted, 'success' );

					// Show totals.
					const totals = response.data.totals;
					if ( totals ) {
						const discLabel = totals.discrepancy_label || '';
						const summaryLines = [
							'<strong>Entry Submitted Successfully!</strong>',
							'Total Sales: ' + formatCurrency( totals.total_sales ),
							'Cash to Bank: ' + formatCurrency( totals.cash_to_bank ),
							'Discrepancy: ' + formatCurrency( totals.discrepancy ),
						];
						if ( discLabel ) {
							summaryLines.push( '<strong>' + discLabel + '</strong>' );
						}

						$( '#mscm-eod-message' ).html( summaryLines.join( '<br>' ) ).show();
					}

					// Reset form after delay.
					setTimeout( function () {
						$form[ 0 ].reset();
						$( '.mscm-denom-total' ).text( formatCurrency( 0 ) );
						$( '#mscm-payouts-container, #mscm-purchases-container' ).empty();
						recalculateAll();
					}, 2000 );
				} else {
					showMessage( response.data.message || strings.error, 'error' );
				}
			} ).fail( function () {
				$submitBtn.prop( 'disabled', false ).text( 'Submit Entry' );
				showMessage( strings.error, 'error' );
			} );
		} );

		// Initial calculation.
		recalculateAll();
	}

	// =========================================================================
	// Init.
	// =========================================================================

	$( document ).ready( function () {
		initEODForm();
	} );

} )( jQuery );
