<?php
/**
 * Admin settings page.
 *
 * Displays and handles the plugin settings form with multiple tabs.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the settings page.
 */
function mscm_render_settings_page() {
	if ( ! current_user_can( 'mscm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'multi-store-cash-manager' ) );
	}

	// Handle form submission.
	if ( isset( $_POST['mscm_settings_submit'] ) ) {
		check_admin_referer( 'mscm_save_settings' );

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
			'mscm_entries_per_page'         => absint( $_POST['entries_per_page'] ?? 20 ),
			'mscm_enable_geolocation'       => absint( $_POST['enable_geolocation'] ?? 0 ),
			'mscm_enable_photo_uploads'     => absint( $_POST['enable_photo_uploads'] ?? 0 ),
		);

		// Generate new API key if requested.
		if ( ! empty( $_POST['regenerate_api_key'] ) ) {
			$settings['mscm_api_key'] = wp_generate_password( 32, false );
		}

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', 'multi-store-cash-manager' ) . '</p></div>';
	}

	$active_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
	$tabs       = array(
		'general'       => __( 'General', 'multi-store-cash-manager' ),
		'notifications' => __( 'Notifications', 'multi-store-cash-manager' ),
		'ai_ml'         => __( 'AI/ML', 'multi-store-cash-manager' ),
		'api'           => __( 'API', 'multi-store-cash-manager' ),
		'backup'        => __( 'Backup', 'multi-store-cash-manager' ),
	);
	?>
	<div class="wrap mscm-admin">
		<h1><?php esc_html_e( 'Settings', 'multi-store-cash-manager' ); ?></h1>

		<nav class="nav-tab-wrapper mscm-tabs">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mscm-settings&tab=' . $tab_key ) ); ?>"
					class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="">
			<?php wp_nonce_field( 'mscm_save_settings' ); ?>

			<?php if ( 'general' === $active_tab ) : ?>
			<div class="mscm-settings-section">
				<h2><?php esc_html_e( 'General Settings', 'multi-store-cash-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Default Float Amount', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="number" name="default_float" step="0.01" min="0"
								value="<?php echo esc_attr( get_option( 'mscm_default_float', 500 ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Default cash float kept in the till at end of day.', 'multi-store-cash-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Currency Symbol', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="text" name="currency" maxlength="5"
								value="<?php echo esc_attr( get_option( 'mscm_currency', 'R' ) ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Currency symbol displayed (e.g., R, $, €).', 'multi-store-cash-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Entries Per Page', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="number" name="entries_per_page" min="5" max="100"
								value="<?php echo esc_attr( get_option( 'mscm_entries_per_page', 20 ) ); ?>" class="small-text">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enable Geolocation', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_geolocation" value="1"
									<?php checked( 1, get_option( 'mscm_enable_geolocation', 0 ) ); ?>>
								<?php esc_html_e( 'Capture user location when submitting entries', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enable Photo Uploads', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_photo_uploads" value="1"
									<?php checked( 1, get_option( 'mscm_enable_photo_uploads', 0 ) ); ?>>
								<?php esc_html_e( 'Allow users to attach receipt photos', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<?php elseif ( 'notifications' === $active_tab ) : ?>
			<div class="mscm-settings-section">
				<h2><?php esc_html_e( 'Notification Settings', 'multi-store-cash-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Notification Email', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="email" name="notification_email"
								value="<?php echo esc_attr( get_option( 'mscm_notification_email', get_option( 'admin_email' ) ) ); ?>"
								class="regular-text">
							<p class="description"><?php esc_html_e( 'Email address for anomaly alerts and admin notifications.', 'multi-store-cash-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enable Daily Reminders', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_reminders" value="1"
									<?php checked( 1, get_option( 'mscm_enable_reminders', 1 ) ); ?>>
								<?php esc_html_e( 'Send reminder emails when entries are missing', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Reminder Time', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="time" name="reminder_time"
								value="<?php echo esc_attr( get_option( 'mscm_reminder_time', '18:00' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Time to check for missing entries and send reminders.', 'multi-store-cash-manager' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php elseif ( 'ai_ml' === $active_tab ) : ?>
			<div class="mscm-settings-section">
				<h2><?php esc_html_e( 'AI / Machine Learning Settings', 'multi-store-cash-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Anomaly Detection', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_anomaly_detection" value="1"
									<?php checked( 1, get_option( 'mscm_enable_anomaly_detection', 1 ) ); ?>>
								<?php esc_html_e( 'Automatically detect unusual sales amounts and large discrepancies', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Anomaly Sensitivity', 'multi-store-cash-manager' ); ?></th>
						<td>
							<select name="ml_sensitivity">
								<option value="low" <?php selected( 'low', get_option( 'mscm_ml_sensitivity', 'medium' ) ); ?>><?php esc_html_e( 'Low (fewer alerts)', 'multi-store-cash-manager' ); ?></option>
								<option value="medium" <?php selected( 'medium', get_option( 'mscm_ml_sensitivity', 'medium' ) ); ?>><?php esc_html_e( 'Medium (balanced)', 'multi-store-cash-manager' ); ?></option>
								<option value="high" <?php selected( 'high', get_option( 'mscm_ml_sensitivity', 'medium' ) ); ?>><?php esc_html_e( 'High (more alerts)', 'multi-store-cash-manager' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enable Sales Predictions', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_ml_predictions" value="1"
									<?php checked( 1, get_option( 'mscm_enable_ml_predictions', 1 ) ); ?>>
								<?php esc_html_e( 'Generate sales predictions based on historical data', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<?php elseif ( 'api' === $active_tab ) : ?>
			<div class="mscm-settings-section">
				<h2><?php esc_html_e( 'REST API Settings', 'multi-store-cash-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable REST API', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="api_enabled" value="1"
									<?php checked( 1, get_option( 'mscm_api_enabled', 0 ) ); ?>>
								<?php esc_html_e( 'Enable REST API for external integrations', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'API Key', 'multi-store-cash-manager' ); ?></th>
						<td>
							<?php $api_key = get_option( 'mscm_api_key', '' ); ?>
							<?php if ( $api_key ) : ?>
								<code><?php echo esc_html( $api_key ); ?></code><br>
							<?php else : ?>
								<em><?php esc_html_e( 'No API key generated.', 'multi-store-cash-manager' ); ?></em><br>
							<?php endif; ?>
							<label>
								<input type="checkbox" name="regenerate_api_key" value="1">
								<?php esc_html_e( 'Generate new API key', 'multi-store-cash-manager' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'API Endpoint:', 'multi-store-cash-manager' ); ?> <code><?php echo esc_url( rest_url( 'mscm/v1/' ) ); ?></code></p>
						</td>
					</tr>
				</table>
			</div>

			<?php elseif ( 'backup' === $active_tab ) : ?>
			<div class="mscm-settings-section">
				<h2><?php esc_html_e( 'Backup Settings', 'multi-store-cash-manager' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Automated Backups', 'multi-store-cash-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_backups" value="1"
									<?php checked( 1, get_option( 'mscm_enable_backups', 1 ) ); ?>>
								<?php esc_html_e( 'Create daily database backups automatically', 'multi-store-cash-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Backup Retention (days)', 'multi-store-cash-manager' ); ?></th>
						<td>
							<input type="number" name="backup_retention" min="1" max="365"
								value="<?php echo esc_attr( get_option( 'mscm_backup_retention', 30 ) ); ?>"
								class="small-text">
							<p class="description"><?php esc_html_e( 'Number of days to keep backup files.', 'multi-store-cash-manager' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="mscm-create-backup" class="button button-secondary">
						<?php esc_html_e( 'Create Backup Now', 'multi-store-cash-manager' ); ?>
					</button>
				</p>
			</div>
			<?php endif; ?>

			<p class="submit">
				<input type="submit" name="mscm_settings_submit" class="button button-primary"
					value="<?php esc_attr_e( 'Save Settings', 'multi-store-cash-manager' ); ?>">
			</p>
		</form>
	</div>
	<?php
}
