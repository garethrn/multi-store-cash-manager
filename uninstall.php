<?php
/**
 * Uninstall script for Multi-Store Daily Cash Manager.
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access and ensure this is a legitimate uninstall call.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin options.
$options = array(
	'mscm_db_version',
	'mscm_version',
	'mscm_default_float',
	'mscm_currency',
	'mscm_currency_symbol',
	'mscm_timezone',
	'mscm_notification_email',
	'mscm_reminder_time',
	'mscm_enable_reminders',
	'mscm_enable_anomaly_detection',
	'mscm_enable_ml_predictions',
	'mscm_ml_sensitivity',
	'mscm_api_enabled',
	'mscm_api_key',
	'mscm_enable_backups',
	'mscm_backup_frequency',
	'mscm_backup_retention',
	'mscm_date_format',
	'mscm_week_start',
	'mscm_entries_per_page',
	'mscm_enable_geolocation',
	'mscm_enable_photo_uploads',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Drop plugin tables.
$tables = array(
	$wpdb->prefix . 'mscm_stores',
	$wpdb->prefix . 'mscm_user_stores',
	$wpdb->prefix . 'mscm_daily_entries',
	$wpdb->prefix . 'mscm_payouts',
	$wpdb->prefix . 'mscm_purchases',
	$wpdb->prefix . 'mscm_targets',
	$wpdb->prefix . 'mscm_settings',
	$wpdb->prefix . 'mscm_audit_log',
	$wpdb->prefix . 'mscm_ml_predictions',
	$wpdb->prefix . 'mscm_anomalies',
	$wpdb->prefix . 'mscm_notifications',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove custom user roles.
remove_role( 'store_user' );
remove_role( 'store_manager' );

// Delete user meta related to the plugin.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		'mscm_%'
	)
);

// Remove uploaded files directory.
$upload_dir  = wp_upload_dir();
$plugin_dir  = $upload_dir['basedir'] . '/mscm-uploads';

if ( is_dir( $plugin_dir ) ) {
	mscm_uninstall_remove_directory( $plugin_dir );
}

// Remove backup directory.
$backup_dir = $upload_dir['basedir'] . '/mscm-backups';

if ( is_dir( $backup_dir ) ) {
	mscm_uninstall_remove_directory( $backup_dir );
}

// Clear any scheduled cron jobs.
wp_clear_scheduled_hook( 'mscm_daily_reminders' );
wp_clear_scheduled_hook( 'mscm_daily_backup' );

// Delete transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_mscm_%',
		'_transient_timeout_mscm_%'
	)
);

/**
 * Recursively remove a directory and its contents.
 *
 * @param string $dir Directory path to remove.
 */
function mscm_uninstall_remove_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			mscm_uninstall_remove_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	rmdir( $dir );
}
