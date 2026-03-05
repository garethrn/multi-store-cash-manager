<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin data: database tables and options.
 *
 * @package MultiStoreCashManager
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop all plugin tables.
$tables = array(
	'mscm_notifications',
	'mscm_anomalies',
	'mscm_ml_predictions',
	'mscm_audit_log',
	'mscm_settings',
	'mscm_targets',
	'mscm_purchases',
	'mscm_payouts',
	'mscm_daily_entries',
	'mscm_user_stores',
	'mscm_stores',
);

foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Remove plugin options.
$options = array(
	'mscm_version',
	'mscm_db_version',
	'mscm_settings',
	'mscm_installed',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove custom user roles.
remove_role( 'store_user' );
remove_role( 'store_manager' );

// Remove scheduled cron events.
$cron_hooks = array(
	'mscm_daily_reminder',
	'mscm_daily_backup',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}
