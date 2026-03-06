<?php
/**
 * Entry history public shortcode.
 *
 * Shortcode: [mscm_history]
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the entry history shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function mscm_history_shortcode( $atts ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="mscm-login-notice"><p>' .
			wp_kses_post( sprintf(
				/* translators: login URL */
				__( 'Please <a href="%s">log in</a> to view your entry history.', 'multi-store-cash-manager' ),
				esc_url( wp_login_url( get_permalink() ) )
			) ) .
			'</p></div>';
	}

	$atts = shortcode_atts(
		array(
			'limit'    => 20,
			'store_id' => 0,
		),
		$atts,
		'mscm_history'
	);

	$user_id  = get_current_user_id();
	$currency = get_option( 'mscm_currency', 'R' );
	$limit    = absint( $atts['limit'] );
	$paged    = max( 1, absint( $_GET['mscm_page'] ?? 1 ) );

	$args = array(
		'limit'  => $limit,
		'offset' => ( $paged - 1 ) * $limit,
		'order'  => 'DESC',
	);

	// If the user is a store manager, show all store entries. Otherwise only own entries.
	if ( ! current_user_can( 'mscm_view_store_entries' ) ) {
		$args['user_id'] = $user_id;
	}

	if ( $atts['store_id'] ) {
		$store_id = absint( $atts['store_id'] );
		if ( MSCM_Roles::user_can_access_store( $store_id ) ) {
			$args['store_id'] = $store_id;
		}
	}

	$entries  = MSCM()->db->get_entries( $args );

	ob_start();
	?>
	<div class="mscm-history-wrapper">
		<h2 class="mscm-section-title"><?php esc_html_e( 'Entry History', 'multi-store-cash-manager' ); ?></h2>

		<?php if ( empty( $entries ) ) : ?>
			<div class="mscm-notice">
				<p><?php esc_html_e( 'No entries found.', 'multi-store-cash-manager' ); ?></p>
			</div>
		<?php else : ?>
			<div class="mscm-history-table-wrapper">
				<table class="mscm-table mscm-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Store', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Total Sales', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Cash', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Card', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Discrepancy', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'multi-store-cash-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->entry_date ) ) ); ?></td>
								<td><?php echo esc_html( $entry->store_name ); ?></td>
								<td><?php echo esc_html( $currency . number_format( $entry->total_sales, 2 ) ); ?></td>
								<td><?php echo esc_html( $currency . number_format( $entry->total_cash, 2 ) ); ?></td>
								<td><?php echo esc_html( $currency . number_format( $entry->credit_card, 2 ) ); ?></td>
								<td class="<?php echo abs( floatval( $entry->discrepancy ) ) > 100 ? 'mscm-text-danger' : ''; ?>">
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
				</table>
			</div>

			<!-- Pagination -->
			<?php if ( count( $entries ) === $limit ) : ?>
				<div class="mscm-pagination">
					<?php if ( $paged > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'mscm_page', $paged - 1 ) ); ?>" class="mscm-btn mscm-btn-outline">
							← <?php esc_html_e( 'Previous', 'multi-store-cash-manager' ); ?>
						</a>
					<?php endif; ?>
					<span><?php echo esc_html( sprintf( __( 'Page %d', 'multi-store-cash-manager' ), $paged ) ); ?></span>
					<a href="<?php echo esc_url( add_query_arg( 'mscm_page', $paged + 1 ) ); ?>" class="mscm-btn mscm-btn-outline">
						<?php esc_html_e( 'Next', 'multi-store-cash-manager' ); ?> →
					</a>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}
