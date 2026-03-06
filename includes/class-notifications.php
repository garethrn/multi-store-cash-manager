<?php
/**
 * Email notifications handler.
 *
 * Handles sending email notifications, daily reminders for missing entries,
 * anomaly alerts, and other plugin notifications.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_Notifications class.
 *
 * @since 1.0.0
 */
class MSCM_Notifications {

	/**
	 * Constructor - registers cron hooks.
	 */
	public function __construct() {
		add_action( 'mscm_daily_reminders', array( $this, 'send_daily_reminders' ) );
	}

	/**
	 * Send daily reminders for missing entries.
	 */
	public function send_daily_reminders() {
		if ( ! get_option( 'mscm_enable_reminders', 1 ) ) {
			return;
		}

		$today  = current_time( 'Y-m-d' );
		$stores = MSCM()->db->get_stores();

		foreach ( $stores as $store ) {
			$this->check_and_notify_missing_entry( $store, $today );
		}
	}

	/**
	 * Check for missing entry and send notification.
	 *
	 * @param object $store Store object.
	 * @param string $date  Date to check.
	 */
	private function check_and_notify_missing_entry( $store, $date ) {
		global $wpdb;
		$entries_table = $wpdb->prefix . 'mscm_daily_entries';

		$entry_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$entries_table} WHERE store_id = %d AND entry_date = %s",
				$store->id,
				$date
			)
		);

		if ( $entry_exists ) {
			return;
		}

		// Get store users to notify.
		$user_stores = $wpdb->prefix . 'mscm_user_stores';
		$users       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.user_email, u.display_name
				FROM {$user_stores} us
				INNER JOIN {$wpdb->users} u ON us.user_id = u.ID
				WHERE us.store_id = %d",
				$store->id
			)
		);

		// Also notify manager.
		if ( $store->manager_id ) {
			$manager = get_user_by( 'id', $store->manager_id );
			if ( $manager ) {
				$this->send_missing_entry_email( $manager, $store, $date );
				$this->create_notification(
					$manager->ID,
					'missing_entry',
					__( 'Missing Entry Reminder', 'multi-store-cash-manager' ),
					sprintf(
						/* translators: 1: store name, 2: date */
						__( 'No entry has been submitted for %1$s on %2$s.', 'multi-store-cash-manager' ),
						$store->name,
						$date
					)
				);
			}
		}

		foreach ( $users as $user ) {
			$wp_user = get_user_by( 'id', $user->ID );
			if ( $wp_user ) {
				$this->send_missing_entry_email( $wp_user, $store, $date );
			}
		}
	}

	/**
	 * Send missing entry email notification.
	 *
	 * @param WP_User $user  User to notify.
	 * @param object  $store Store object.
	 * @param string  $date  Date string.
	 */
	public function send_missing_entry_email( $user, $store, $date ) {
		$subject = sprintf(
			/* translators: store name */
			__( '[MSCM] Reminder: Missing Entry for %s', 'multi-store-cash-manager' ),
			$store->name
		);

		$eod_url = home_url( '/end-of-day/' ); // User should configure this page.

		$message = sprintf(
			/* translators: 1: user name, 2: store name, 3: date, 4: URL */
			__(
				"Hi %1\$s,\n\nThis is a reminder that no daily cash entry has been submitted for %2\$s on %3\$s.\n\nPlease log in and submit your entry as soon as possible:\n%4\$s\n\nThank you,\nMulti-Store Cash Manager",
				'multi-store-cash-manager'
			),
			$user->display_name,
			$store->name,
			$date,
			$eod_url
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Send anomaly alert email.
	 *
	 * @param object $anomaly Anomaly object.
	 */
	public function send_anomaly_alert( $anomaly ) {
		$notification_email = get_option( 'mscm_notification_email', get_option( 'admin_email' ) );

		if ( ! $notification_email ) {
			return;
		}

		$subject = sprintf(
			/* translators: anomaly type */
			__( '[MSCM] Anomaly Detected: %s', 'multi-store-cash-manager' ),
			$anomaly->anomaly_type
		);

		$message = sprintf(
			/* translators: 1: store name, 2: anomaly type, 3: severity, 4: description */
			__(
				"An anomaly has been detected:\n\nStore: %1\$s\nType: %2\$s\nSeverity: %3\$s\nDescription: %4\$s\n\nPlease review and resolve this anomaly in the admin dashboard.",
				'multi-store-cash-manager'
			),
			$anomaly->store_name ?? __( 'Unknown', 'multi-store-cash-manager' ),
			$anomaly->anomaly_type,
			$anomaly->severity,
			$anomaly->description
		);

		wp_mail( $notification_email, $subject, $message );
	}

	/**
	 * Create an in-app notification for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Notification type.
	 * @param string $title   Notification title.
	 * @param string $message Notification message.
	 * @param array  $data    Additional data.
	 * @return int|false Inserted notification ID or false.
	 */
	public function create_notification( $user_id, $type, $title, $message, $data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mscm_notifications';

		$result = $wpdb->insert(
			$table,
			array(
				'user_id' => $user_id,
				'type'    => sanitize_text_field( $type ),
				'title'   => sanitize_text_field( $title ),
				'message' => sanitize_textarea_field( $message ),
				'data'    => wp_json_encode( $data ),
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get unread notification count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Unread notification count.
	 */
	public function get_unread_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mscm_notifications';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
	}

	/**
	 * Send an entry verification notification.
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $user_id  Entry submitter user ID.
	 */
	public function notify_entry_verified( $entry_id, $user_id ) {
		$entry = MSCM()->db->get_entry( $entry_id );
		if ( ! $entry ) {
			return;
		}

		$this->create_notification(
			$user_id,
			'entry_verified',
			__( 'Entry Verified', 'multi-store-cash-manager' ),
			sprintf(
				/* translators: 1: store name, 2: entry date */
				__( 'Your entry for %1$s on %2$s has been verified.', 'multi-store-cash-manager' ),
				$entry->store_name,
				$entry->entry_date
			),
			array( 'entry_id' => $entry_id )
		);
	}
}
