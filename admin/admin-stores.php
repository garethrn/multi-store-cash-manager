<?php
/**
 * Admin store management page.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the stores management page.
 */
function mscm_render_stores_page() {
	if ( ! current_user_can( 'mscm_manage_all_stores' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	$stores     = MSCM()->db->get_stores( false );
	$all_users  = MSCM_Roles::get_mscm_users();
	?>
	<div class="wrap mscm-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Manage Stores', 'multi-store-cash-manager' ); ?></h1>
		<button class="page-title-action" id="mscm-add-store-btn">
			+ <?php esc_html_e( 'Add Store', 'multi-store-cash-manager' ); ?>
		</button>
		<hr class="wp-header-end">

		<!-- Add/Edit Store Form (hidden by default) -->
		<div id="mscm-store-form-container" class="mscm-card" style="display:none;">
			<div class="mscm-card-header">
				<h2 id="mscm-store-form-title"><?php esc_html_e( 'Add New Store', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<form id="mscm-store-form">
					<input type="hidden" id="mscm-store-id" name="store_id" value="0">
					<div class="mscm-form-grid">
						<div class="mscm-form-group">
							<label for="mscm-store-name"><?php esc_html_e( 'Store Name', 'multi-store-cash-manager' ); ?> *</label>
							<input type="text" id="mscm-store-name" name="store_name" required class="regular-text">
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-location"><?php esc_html_e( 'Location', 'multi-store-cash-manager' ); ?></label>
							<input type="text" id="mscm-store-location" name="location" class="regular-text">
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-phone"><?php esc_html_e( 'Phone', 'multi-store-cash-manager' ); ?></label>
							<input type="text" id="mscm-store-phone" name="phone" class="regular-text">
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-email"><?php esc_html_e( 'Email', 'multi-store-cash-manager' ); ?></label>
							<input type="email" id="mscm-store-email" name="email" class="regular-text">
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-manager"><?php esc_html_e( 'Store Manager', 'multi-store-cash-manager' ); ?></label>
							<select id="mscm-store-manager" name="manager_id" class="regular-text">
								<option value=""><?php esc_html_e( 'Select Manager', 'multi-store-cash-manager' ); ?></option>
								<?php foreach ( $all_users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>">
										<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-float"><?php esc_html_e( 'Float Amount', 'multi-store-cash-manager' ); ?></label>
							<input type="number" id="mscm-store-float" name="float_amount" step="0.01" min="0"
								value="<?php echo esc_attr( get_option( 'mscm_default_float', 500 ) ); ?>" class="small-text">
						</div>
						<div class="mscm-form-group">
							<label for="mscm-store-target"><?php esc_html_e( 'Monthly Target', 'multi-store-cash-manager' ); ?></label>
							<input type="number" id="mscm-store-target" name="target_amount" step="0.01" min="0"
								value="0" class="regular-text">
						</div>
						<div class="mscm-form-group mscm-form-full">
							<label for="mscm-store-address"><?php esc_html_e( 'Address', 'multi-store-cash-manager' ); ?></label>
							<textarea id="mscm-store-address" name="address" rows="2" class="large-text"></textarea>
						</div>
					</div>
					<div class="mscm-form-actions">
						<button type="submit" class="button button-primary" id="mscm-save-store-btn">
							<?php esc_html_e( 'Save Store', 'multi-store-cash-manager' ); ?>
						</button>
						<button type="button" class="button" id="mscm-cancel-store-btn">
							<?php esc_html_e( 'Cancel', 'multi-store-cash-manager' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Stores Table -->
		<div class="mscm-card">
			<div class="mscm-card-body">
				<table class="wp-list-table widefat fixed striped mscm-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Store Name', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Location', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Float', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'multi-store-cash-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'multi-store-cash-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $stores ) ) : ?>
							<tr>
								<td colspan="6" style="text-align:center;padding:20px;">
									<?php esc_html_e( 'No stores found. Add your first store!', 'multi-store-cash-manager' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php
							$currency = get_option( 'mscm_currency', 'R' );
							foreach ( $stores as $store ) :
							?>
								<tr>
									<td><strong><?php echo esc_html( $store->name ); ?></strong></td>
									<td><?php echo esc_html( $store->location ); ?></td>
									<td><?php echo esc_html( $store->phone ); ?></td>
									<td><?php echo esc_html( $currency . number_format( $store->float_amount, 2 ) ); ?></td>
									<td>
										<span class="mscm-badge <?php echo $store->is_active ? 'mscm-badge-verified' : 'mscm-badge-pending'; ?>">
											<?php echo $store->is_active ? esc_html__( 'Active', 'multi-store-cash-manager' ) : esc_html__( 'Inactive', 'multi-store-cash-manager' ); ?>
										</span>
									</td>
									<td>
										<button class="button button-small mscm-edit-store"
											data-store='<?php echo esc_attr( wp_json_encode( $store ) ); ?>'>
											<?php esc_html_e( 'Edit', 'multi-store-cash-manager' ); ?>
										</button>
										<button class="button button-small button-link-delete mscm-delete-store"
											data-store-id="<?php echo esc_attr( $store->id ); ?>"
											data-store-name="<?php echo esc_attr( $store->name ); ?>">
											<?php esc_html_e( 'Delete', 'multi-store-cash-manager' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- User-Store Assignments -->
		<div class="mscm-card">
			<div class="mscm-card-header">
				<h2><?php esc_html_e( 'Assign Users to Stores', 'multi-store-cash-manager' ); ?></h2>
			</div>
			<div class="mscm-card-body">
				<form id="mscm-assign-user-form" class="mscm-form-inline">
					<select name="user_id" id="mscm-assign-user" required>
						<option value=""><?php esc_html_e( 'Select User', 'multi-store-cash-manager' ); ?></option>
						<?php foreach ( $all_users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>">
								<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select name="store_id" id="mscm-assign-store" required>
						<option value=""><?php esc_html_e( 'Select Store', 'multi-store-cash-manager' ); ?></option>
						<?php foreach ( $stores as $store ) : ?>
							<option value="<?php echo esc_attr( $store->id ); ?>">
								<?php echo esc_html( $store->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select name="role">
						<option value="store_user"><?php esc_html_e( 'Store User', 'multi-store-cash-manager' ); ?></option>
						<option value="store_manager"><?php esc_html_e( 'Store Manager', 'multi-store-cash-manager' ); ?></option>
					</select>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Assign', 'multi-store-cash-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>
	<?php
}
