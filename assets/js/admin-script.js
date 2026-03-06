/**
 * Multi-Store Cash Manager - Admin JavaScript
 *
 * @package MultiStoreCashManager
 * @version 1.0.0
 */

/* global mscmAdmin, Chart */

( function ( $ ) {
	'use strict';

	// Cached nonce and AJAX URL.
	const ajaxUrl = mscmAdmin.ajaxUrl;
	const nonce    = mscmAdmin.nonce;
	const strings  = mscmAdmin.strings;
	const currency = mscmAdmin.currency || 'R';

	// =========================================================================
	// Dashboard Chart.
	// =========================================================================

	function initDashboardChart() {
		const canvas = document.getElementById( 'mscm-sales-chart' );
		if ( ! canvas || typeof Chart === 'undefined' ) {
			return;
		}

		// Fetch chart data via AJAX.
		$.post( ajaxUrl, {
			action:   'mscm_get_dashboard_stats',
			nonce:    nonce,
			store_id: 0,
		}, function ( response ) {
			if ( ! response.success ) {
				return;
			}

			const chartData = response.data.chart_data;
			const labels    = Object.keys( chartData );
			const values    = Object.values( chartData );

			new Chart( canvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Daily Sales',
						data:  values,
						borderColor: '#2563eb',
						backgroundColor: 'rgba(37, 99, 235, 0.1)',
						borderWidth: 2,
						fill: true,
						tension: 0.4,
						pointBackgroundColor: '#2563eb',
						pointRadius: 4,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false },
						tooltip: {
							callbacks: {
								label: function ( ctx ) {
									return 'Sales: ' + currency + parseFloat( ctx.raw ).toFixed( 2 );
								},
							},
						},
					},
					scales: {
						x: {
							grid: { display: false },
							ticks: { font: { size: 11 } },
						},
						y: {
							beginAtZero: true,
							ticks: {
								font: { size: 11 },
								callback: function ( val ) {
									return 'R' + val.toLocaleString();
								},
							},
						},
					},
				},
			} );
		} );
	}

	// =========================================================================
	// Performance Chart (Reports page).
	// =========================================================================

	function initPerformanceChart() {
		const canvas = document.getElementById( 'mscm-performance-chart' );
		if ( ! canvas || typeof Chart === 'undefined' ) {
			return;
		}

		const labels = JSON.parse( canvas.dataset.labels || '[]' );
		const values = JSON.parse( canvas.dataset.values || '[]' );

		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [ {
					label: 'Daily Sales',
					data:  values,
					backgroundColor: 'rgba(37, 99, 235, 0.7)',
					borderColor: '#2563eb',
					borderWidth: 1,
					borderRadius: 4,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
				},
				scales: {
					x: { grid: { display: false } },
					y: {
						beginAtZero: true,
						ticks: {
							callback: function ( val ) {
								return 'R' + val.toLocaleString();
							},
						},
					},
				},
			},
		} );
	}

	// =========================================================================
	// Store Management.
	// =========================================================================

	function initStoreManagement() {
		const $form      = $( '#mscm-store-form-container' );
		const $addBtn    = $( '#mscm-add-store-btn' );
		const $cancelBtn = $( '#mscm-cancel-store-btn' );

		if ( ! $form.length ) {
			return;
		}

		// Show add form.
		$addBtn.on( 'click', function () {
			$form.slideDown();
			$( '#mscm-store-form-title' ).text( 'Add New Store' );
			$( '#mscm-store-form' )[ 0 ].reset();
			$( '#mscm-store-id' ).val( 0 );
			$( 'html, body' ).animate( { scrollTop: $form.offset().top - 50 }, 300 );
		} );

		// Hide form.
		$cancelBtn.on( 'click', function () {
			$form.slideUp();
		} );

		// Edit store.
		$( document ).on( 'click', '.mscm-edit-store', function () {
			const store = $( this ).data( 'store' );

			$( '#mscm-store-id' ).val( store.id );
			$( '#mscm-store-name' ).val( store.name );
			$( '#mscm-store-location' ).val( store.location );
			$( '#mscm-store-phone' ).val( store.phone );
			$( '#mscm-store-email' ).val( store.email );
			$( '#mscm-store-manager' ).val( store.manager_id );
			$( '#mscm-store-float' ).val( store.float_amount );
			$( '#mscm-store-target' ).val( store.target_amount );
			$( '#mscm-store-address' ).val( store.address );

			$( '#mscm-store-form-title' ).text( 'Edit Store: ' + store.name );
			$form.slideDown();
			$( 'html, body' ).animate( { scrollTop: $form.offset().top - 50 }, 300 );
		} );

		// Delete store.
		$( document ).on( 'click', '.mscm-delete-store', function () {
			const $btn     = $( this );
			const storeId  = $btn.data( 'store-id' );
			const storeName = $btn.data( 'store-name' );

			if ( ! window.confirm( 'Are you sure you want to delete "' + storeName + '"?' ) ) {
				return;
			}

			$.post( ajaxUrl, {
				action:   'mscm_delete_store',
				nonce:    nonce,
				store_id: storeId,
			}, function ( response ) {
				if ( response.success ) {
					$btn.closest( 'tr' ).fadeOut( 400, function () {
						$( this ).remove();
					} );
				} else {
					alert( response.data.message || strings.error );
				}
			} );
		} );

		// Save store form submission.
		$( '#mscm-store-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			const $submitBtn = $( '#mscm-save-store-btn' );
			$submitBtn.text( strings.saving ).prop( 'disabled', true );

			$.post( ajaxUrl, $( this ).serialize() + '&action=mscm_save_store&nonce=' + nonce,
				function ( response ) {
					$submitBtn.text( 'Save Store' ).prop( 'disabled', false );

					if ( response.success ) {
						$submitBtn.text( strings.saved );
						setTimeout( function () {
							window.location.reload();
						}, 1000 );
					} else {
						alert( response.data.message || strings.error );
					}
				}
			);
		} );

		// Assign user to store.
		$( '#mscm-assign-user-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			$.post( ajaxUrl, $( this ).serialize() + '&action=mscm_assign_user_store&nonce=' + nonce,
				function ( response ) {
					if ( response.success ) {
						alert( response.data.message );
					} else {
						alert( response.data.message || strings.error );
					}
				}
			);
		} );
	}

	// =========================================================================
	// Entries: Verify and View.
	// =========================================================================

	function initEntryActions() {
		// Verify entry.
		$( document ).on( 'click', '.mscm-verify-entry', function () {
			const $btn    = $( this );
			const entryId = $btn.data( 'entry-id' );

			if ( ! window.confirm( 'Verify this entry?' ) ) {
				return;
			}

			$btn.text( 'Verifying...' ).prop( 'disabled', true );

			$.post( ajaxUrl, {
				action:   'mscm_verify_entry',
				nonce:    nonce,
				entry_id: entryId,
			}, function ( response ) {
				if ( response.success ) {
					$btn.closest( 'tr' ).find( '.mscm-badge' )
						.removeClass( 'mscm-badge-pending' )
						.addClass( 'mscm-badge-verified' )
						.text( 'Verified' );
					$btn.remove();
				} else {
					$btn.text( 'Verify' ).prop( 'disabled', false );
					alert( response.data.message || strings.error );
				}
			} );
		} );

		// Resolve anomaly.
		$( document ).on( 'click', '.mscm-resolve-anomaly', function () {
			const $btn      = $( this );
			const anomalyId = $btn.data( 'anomaly-id' );

			if ( ! window.confirm( 'Mark this anomaly as resolved?' ) ) {
				return;
			}

			$btn.text( 'Resolving...' ).prop( 'disabled', true );

			$.post( ajaxUrl, {
				action:     'mscm_resolve_anomaly',
				nonce:      nonce,
				anomaly_id: anomalyId,
			}, function ( response ) {
				if ( response.success ) {
					$btn.closest( 'tr' ).fadeOut( 400, function () {
						$( this ).remove();
					} );
				} else {
					$btn.text( 'Resolve' ).prop( 'disabled', false );
					alert( response.data.message || strings.error );
				}
			} );
		} );
	}

	// =========================================================================
	// Reports: Export CSV.
	// =========================================================================

	function initReportExport() {
		$( document ).on( 'click', '#mscm-export-csv', function () {
			const $btn     = $( this );
			const storeId  = $btn.data( 'store-id' );
			const dateFrom = $btn.data( 'date-from' );
			const dateTo   = $btn.data( 'date-to' );

			$btn.text( '⏳ Exporting...' ).prop( 'disabled', true );

			$.post( ajaxUrl, {
				action:    'mscm_export_data',
				nonce:     nonce,
				format:    'csv',
				store_id:  storeId,
				date_from: dateFrom,
				date_to:   dateTo,
			}, function ( response ) {
				$btn.text( '📥 Export CSV' ).prop( 'disabled', false );

				if ( response.success ) {
					window.open( response.data.url, '_blank' );
				} else {
					alert( response.data.message || strings.error );
				}
			} );
		} );
	}

	// =========================================================================
	// Targets: Save.
	// =========================================================================

	function initTargets() {
		$( '#mscm-target-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			const $btn = $( this ).find( 'button[type="submit"]' );
			$btn.text( strings.saving ).prop( 'disabled', true );

			$.post( ajaxUrl, $( this ).serialize() + '&action=mscm_save_target&nonce=' + nonce,
				function ( response ) {
					$btn.prop( 'disabled', false );

					if ( response.success ) {
						$btn.text( strings.saved );
						setTimeout( function () {
							window.location.reload();
						}, 1000 );
					} else {
						$btn.text( 'Set Target' );
						alert( response.data.message || strings.error );
					}
				}
			);
		} );
	}

	// =========================================================================
	// Settings: Create Backup.
	// =========================================================================

	function initSettings() {
		$( '#mscm-create-backup' ).on( 'click', function () {
			const $btn = $( this );
			$btn.text( 'Creating backup...' ).prop( 'disabled', true );

			$.post( ajaxUrl, {
				action: 'mscm_export_data',
				nonce:  nonce,
				format: 'backup',
			}, function () {
				$btn.text( '✅ Backup created!' );
				setTimeout( function () {
					$btn.text( 'Create Backup Now' ).prop( 'disabled', false );
				}, 3000 );
			} );
		} );
	}

	// =========================================================================
	// ML Insights.
	// =========================================================================

	function initMLInsights() {
		const $container = $( '#mscm-ml-insights' );
		if ( ! $container.length ) {
			return;
		}

		const storeId = $container.data( 'store-id' ) || 0;

		$.post( ajaxUrl, {
			action:   'mscm_get_ml_insights',
			nonce:    nonce,
			store_id: storeId,
		}, function ( response ) {
			if ( ! response.success || ! response.data.insights.length ) {
				return;
			}

			let html = '';
			response.data.insights.forEach( function ( insight ) {
				const cls = 'mscm-insight mscm-insight-' + ( insight.type || 'info' );
				html += '<div class="' + cls + '">' + insight.message + '</div>';
			} );

			$container.html( html );
		} );
	}

	// =========================================================================
	// Init.
	// =========================================================================

	$( document ).ready( function () {
		initDashboardChart();
		initPerformanceChart();
		initStoreManagement();
		initEntryActions();
		initReportExport();
		initTargets();
		initSettings();
		initMLInsights();
	} );

} )( jQuery );
