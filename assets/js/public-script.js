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

			const float = parseFloat( $( '#mscm-float' ).val() ) || 0;

			// Calculate cash payouts (payment_type === 'cash').
			let totalCashPayouts = 0;
			let totalBankPayouts = 0;
			$( '#mscm-payouts-container .mscm-dynamic-row' ).each( function () {
				const amount = parseFloat( $( this ).find( '.mscm-payout-amount' ).val() ) || 0;
				const pt     = $( this ).find( '.mscm-dyn-payment-type' ).val() || 'cash';
				if ( 'cash' === pt ) {
					totalCashPayouts += amount;
				} else {
					totalBankPayouts += amount;
				}
			} );
			const totalPayouts = totalCashPayouts + totalBankPayouts;

			// Cash payouts come out of the drawer, so they reduce what is banked.
			const cashToBank = Math.max( 0, totalCash - float - totalCashPayouts );

			const creditCard   = parseFloat( $( '#mscm-credit-card' ).val() ) || 0;
			const eft          = parseFloat( $( '#mscm-eft' ).val() ) || 0;
			const otherDigital = parseFloat( $( '#mscm-other-digital' ).val() ) || 0;

			// Total sales uses cash-to-bank (float & cash payouts excluded) + other payment types.
			const totalSales = cashToBank + creditCard + eft + otherDigital;

			// POS total is sum of three POS breakdown fields.
			const posCash       = parseFloat( $( '#mscm-pos-cash' ).val() ) || 0;
			const posEft        = parseFloat( $( '#mscm-pos-eft' ).val() ) || 0;
			const posCreditCard = parseFloat( $( '#mscm-pos-credit-card' ).val() ) || 0;
			const posReported   = posCash + posEft + posCreditCard;
			const discrepancy   = totalSales - posReported;

			const totalPurchases = calcDynamicTotal( '.mscm-purchase-amount' );
			// Net banking: cash_to_bank minus bank-type payouts minus purchases.
			const netBanking = cashToBank - totalBankPayouts - totalPurchases;

			// Update display.
			$( '#mscm-total-cash' ).text( formatCurrency( totalCash ) );
			$( '#mscm-cash-to-bank' ).text( formatCurrency( cashToBank ) );

			$( '#mscm-summary-cash' ).text( formatCurrency( totalCash ) );
			$( '#mscm-summary-float' ).text( '- ' + formatCurrency( float ) );

			// Show/hide cash payouts line.
			if ( totalCashPayouts > 0 ) {
				$( '#mscm-cash-payouts-row' ).show();
				$( '#mscm-summary-cash-payouts' ).text( '- ' + formatCurrency( totalCashPayouts ) );
			} else {
				$( '#mscm-cash-payouts-row' ).hide();
			}

			$( '#mscm-summary-card' ).text( formatCurrency( creditCard ) );
			$( '#mscm-summary-eft' ).text( formatCurrency( eft ) );
			$( '#mscm-total-sales' ).text( formatCurrency( totalSales ) );
			$( '#mscm-summary-banking' ).text( formatCurrency( cashToBank ) );
			$( '#mscm-total-payouts' ).text( formatCurrency( totalPayouts ) );
			$( '#mscm-total-purchases' ).text( formatCurrency( totalPurchases ) );
			$( '#mscm-net-banking' ).text( formatCurrency( netBanking ) );
			$( '#mscm-pos-total' ).text( formatCurrency( posReported ) );

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
		$form.on( 'input change', '.mscm-denom-count, #mscm-float, .mscm-payment-input, .mscm-pos-input, .mscm-payout-amount, .mscm-purchase-amount, .mscm-dyn-payment-type', recalculateAll );

		// =========================================================================
		// Dynamic Rows: Payouts.
		// =========================================================================

		const payoutTemplate   = document.getElementById( 'mscm-payout-template' );
		const purchaseTemplate = document.getElementById( 'mscm-purchase-template' );

		/**
		 * Add a payout row, optionally pre-filled with data.
		 *
		 * @param {object} data Optional data to pre-fill.
		 */
		function addPayoutRow( data ) {
			if ( ! payoutTemplate ) {
				return;
			}
			const clone = payoutTemplate.content.cloneNode( true );
			if ( data ) {
				clone.querySelector( '[name="payout_description[]"]' ).value = data.description || '';
				clone.querySelector( '[name="payout_amount[]"]' ).value      = data.amount || 0;
				const catSelect = clone.querySelector( '[name="payout_category[]"]' );
				if ( catSelect && data.category ) {
					catSelect.value = data.category;
				}
				const ptSelect = clone.querySelector( '[name="payout_payment_type[]"]' );
				if ( ptSelect && data.payment_type ) {
					ptSelect.value = data.payment_type;
				}
			}
			$( '#mscm-payouts-container' ).append( clone );
		}

		/**
		 * Add a purchase row, optionally pre-filled with data.
		 *
		 * @param {object} data Optional data to pre-fill.
		 */
		function addPurchaseRow( data ) {
			if ( ! purchaseTemplate ) {
				return;
			}
			const clone = purchaseTemplate.content.cloneNode( true );
			if ( data ) {
				clone.querySelector( '[name="purchase_description[]"]' ).value = data.description || '';
				clone.querySelector( '[name="purchase_amount[]"]' ).value      = data.amount || 0;
				const receiptInput = clone.querySelector( '[name="purchase_receipt[]"]' );
				if ( receiptInput && data.receipt_number ) {
					receiptInput.value = data.receipt_number;
				}
			}
			$( '#mscm-purchases-container' ).append( clone );
		}

		$( '#mscm-add-payout' ).on( 'click', function () {
			addPayoutRow( null );
		} );

		$( '#mscm-add-purchase' ).on( 'click', function () {
			addPurchaseRow( null );
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

			// Get store_id - may be from hidden field in edit mode.
			const storeId = $form.find( '[name="store_id"]' ).last().val();
			if ( ! storeId ) {
				showMessage( strings.selectStore || 'Please select a store.', 'error' );
				return;
			}

			if ( ! window.confirm( strings.confirmSubmit ) ) {
				return;
			}

			const $submitBtn  = $( '#mscm-submit-btn' );
			const isEditMode  = $form.find( '[name="entry_id"]' ).length > 0;
			const btnText     = isEditMode ? 'Update Entry' : 'Submit Entry';

			$submitBtn.text( strings.submitting ).prop( 'disabled', true );

			const formData = $form.serialize();

			$.post( ajaxUrl, formData, function ( response ) {
				$submitBtn.prop( 'disabled', false ).text( btnText );

				if ( response.success ) {
					const successMsg = isEditMode ? 'Entry Updated Successfully!' : 'Entry Submitted Successfully!';
					showMessage( isEditMode ? successMsg : strings.submitted, 'success' );

					// Show totals.
					const totals = response.data.totals;
					if ( totals ) {
						const discLabel = totals.discrepancy_label || '';
						const summaryLines = [
							'<strong>' + successMsg + '</strong>',
							'Total Sales: ' + formatCurrency( totals.total_sales ),
							'Cash to Bank: ' + formatCurrency( totals.cash_to_bank ),
							'Discrepancy: ' + formatCurrency( totals.discrepancy ),
						];
						if ( discLabel ) {
							summaryLines.push( '<strong>' + discLabel + '</strong>' );
						}

						$( '#mscm-eod-message' ).html( summaryLines.join( '<br>' ) ).show();
					}

					// Only reset form for new entries (not edits).
					if ( ! isEditMode ) {
						setTimeout( function () {
							$form[ 0 ].reset();
							$( '.mscm-denom-total' ).text( formatCurrency( 0 ) );
							$( '#mscm-payouts-container, #mscm-purchases-container' ).empty();
							recalculateAll();
						}, 2000 );
					}
				} else {
					showMessage( response.data.message || strings.error, 'error' );
				}
			} ).fail( function () {
				$submitBtn.prop( 'disabled', false ).text( btnText );
				showMessage( strings.error, 'error' );
			} );
		} );

		// Pre-populate form when in edit mode.
		if ( window.mscmEditEntry ) {
			const entry = window.mscmEditEntry;

			// Payouts.
			if ( entry.payouts && entry.payouts.length ) {
				entry.payouts.forEach( function ( p ) {
					addPayoutRow( p );
				} );
			}

			// Purchases.
			if ( entry.purchases && entry.purchases.length ) {
				entry.purchases.forEach( function ( p ) {
					addPurchaseRow( p );
				} );
			}

			// POS fields.
			if ( entry.pos_cash !== undefined ) {
				$( '#mscm-pos-cash' ).val( entry.pos_cash );
			}
			if ( entry.pos_eft !== undefined ) {
				$( '#mscm-pos-eft' ).val( entry.pos_eft );
			}
			if ( entry.pos_credit_card !== undefined ) {
				$( '#mscm-pos-credit-card' ).val( entry.pos_credit_card );
			}

			// Banking fields.
			if ( entry.banking_date ) {
				$( '#mscm-banking-date' ).val( entry.banking_date );
			}
			if ( entry.banked_by ) {
				$( '#mscm-banked-by' ).val( entry.banked_by );
			}
			if ( entry.banking_ref ) {
				$( '#mscm-banking-ref' ).val( entry.banking_ref );
			}
		}

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
