<?php
/**
 * Database operations class.
 *
 * Handles all database interactions including table creation,
 * data retrieval, updates, and audit logging.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_DB class.
 *
 * @since 1.0.0
 */
class MSCM_DB {

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table names.
	 *
	 * @var array
	 */
	public $tables;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$this->tables = array(
			'stores'       => $wpdb->prefix . 'mscm_stores',
			'user_stores'  => $wpdb->prefix . 'mscm_user_stores',
			'entries'      => $wpdb->prefix . 'mscm_daily_entries',
			'payouts'      => $wpdb->prefix . 'mscm_payouts',
			'purchases'    => $wpdb->prefix . 'mscm_purchases',
			'targets'      => $wpdb->prefix . 'mscm_targets',
			'settings'     => $wpdb->prefix . 'mscm_settings',
			'audit_log'    => $wpdb->prefix . 'mscm_audit_log',
			'predictions'  => $wpdb->prefix . 'mscm_ml_predictions',
			'anomalies'    => $wpdb->prefix . 'mscm_anomalies',
			'notifications' => $wpdb->prefix . 'mscm_notifications',
		);
	}

	/**
	 * Create all plugin database tables.
	 */
	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		// Stores table.
		$sql = "CREATE TABLE {$this->tables['stores']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			location varchar(255) DEFAULT '',
			address text DEFAULT '',
			phone varchar(50) DEFAULT '',
			email varchar(100) DEFAULT '',
			manager_id bigint(20) UNSIGNED DEFAULT NULL,
			float_amount decimal(10,2) NOT NULL DEFAULT 500.00,
			target_amount decimal(10,2) DEFAULT 0.00,
			currency varchar(10) DEFAULT 'ZAR',
			timezone varchar(50) DEFAULT 'Africa/Johannesburg',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY manager_id (manager_id)
		) $charset_collate;";
		dbDelta( $sql );

		// User-store relationships table.
		$sql = "CREATE TABLE {$this->tables['user_stores']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			store_id bigint(20) UNSIGNED NOT NULL,
			role varchar(50) DEFAULT 'store_user',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_store (user_id, store_id),
			KEY user_id (user_id),
			KEY store_id (store_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Daily entries table.
		$sql = "CREATE TABLE {$this->tables['entries']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			entry_date date NOT NULL,
			-- Cash denominations.
			denom_200 int(11) NOT NULL DEFAULT 0,
			denom_100 int(11) NOT NULL DEFAULT 0,
			denom_50 int(11) NOT NULL DEFAULT 0,
			denom_20 int(11) NOT NULL DEFAULT 0,
			denom_10 int(11) NOT NULL DEFAULT 0,
			denom_5 int(11) NOT NULL DEFAULT 0,
			denom_2 int(11) NOT NULL DEFAULT 0,
			denom_1 int(11) NOT NULL DEFAULT 0,
			denom_50c int(11) NOT NULL DEFAULT 0,
			denom_20c int(11) NOT NULL DEFAULT 0,
			denom_10c int(11) NOT NULL DEFAULT 0,
			-- Totals.
			total_cash decimal(10,2) NOT NULL DEFAULT 0.00,
			float_amount decimal(10,2) NOT NULL DEFAULT 500.00,
			cash_to_bank decimal(10,2) NOT NULL DEFAULT 0.00,
			-- Payment types.
			credit_card decimal(10,2) NOT NULL DEFAULT 0.00,
			eft decimal(10,2) NOT NULL DEFAULT 0.00,
			other_digital decimal(10,2) NOT NULL DEFAULT 0.00,
			-- Calculated totals.
			total_sales decimal(10,2) NOT NULL DEFAULT 0.00,
			pos_cash decimal(10,2) NOT NULL DEFAULT 0.00,
			pos_eft decimal(10,2) NOT NULL DEFAULT 0.00,
			pos_credit_card decimal(10,2) NOT NULL DEFAULT 0.00,
			pos_reported decimal(10,2) NOT NULL DEFAULT 0.00,
			discrepancy decimal(10,2) NOT NULL DEFAULT 0.00,
			net_banking decimal(10,2) NOT NULL DEFAULT 0.00,
			-- Banking details.
			banking_date date DEFAULT NULL,
			banked_by varchar(255) DEFAULT '',
			banking_ref varchar(255) DEFAULT '',
			-- Notes and status.
			notes text DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			verified_by bigint(20) UNSIGNED DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			-- Geolocation.
			latitude decimal(10,8) DEFAULT NULL,
			longitude decimal(11,8) DEFAULT NULL,
			-- Photo.
			photo_url varchar(500) DEFAULT '',
			-- Timestamps.
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY store_date (store_id, entry_date),
			KEY store_id (store_id),
			KEY user_id (user_id),
			KEY entry_date (entry_date),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Payouts table.
		$sql = "CREATE TABLE {$this->tables['payouts']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) UNSIGNED NOT NULL,
			store_id bigint(20) UNSIGNED NOT NULL,
			description varchar(255) NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			category varchar(100) DEFAULT 'general',
			payment_type varchar(10) NOT NULL DEFAULT 'cash',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY store_id (store_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Purchases table.
		$sql = "CREATE TABLE {$this->tables['purchases']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) UNSIGNED NOT NULL,
			store_id bigint(20) UNSIGNED NOT NULL,
			description varchar(255) NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			category varchar(100) DEFAULT 'general',
			receipt_number varchar(100) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY store_id (store_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Targets table.
		$sql = "CREATE TABLE {$this->tables['targets']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id bigint(20) UNSIGNED NOT NULL,
			period_type varchar(20) NOT NULL DEFAULT 'monthly',
			period_year int(4) NOT NULL,
			period_month int(2) DEFAULT NULL,
			period_quarter int(1) DEFAULT NULL,
			target_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			working_days int(2) NOT NULL DEFAULT 0,
			notes text DEFAULT '',
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY store_id (store_id),
			KEY period (store_id, period_year, period_month)
		) $charset_collate;";
		dbDelta( $sql );

		// Settings table.
		$sql = "CREATE TABLE {$this->tables['settings']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key varchar(100) NOT NULL,
			setting_value longtext DEFAULT '',
			autoload varchar(20) DEFAULT 'yes',
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) $charset_collate;";
		dbDelta( $sql );

		// Audit log table.
		$sql = "CREATE TABLE {$this->tables['audit_log']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			action varchar(100) NOT NULL,
			object_type varchar(50) DEFAULT '',
			object_id bigint(20) UNSIGNED DEFAULT NULL,
			old_values longtext DEFAULT '',
			new_values longtext DEFAULT '',
			ip_address varchar(45) DEFAULT '',
			user_agent varchar(500) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// ML predictions table.
		$sql = "CREATE TABLE {$this->tables['predictions']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id bigint(20) UNSIGNED NOT NULL,
			prediction_date date NOT NULL,
			predicted_sales decimal(10,2) NOT NULL DEFAULT 0.00,
			confidence decimal(5,2) DEFAULT 0.00,
			model_version varchar(20) DEFAULT '1.0',
			features longtext DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY store_id (store_id),
			KEY prediction_date (prediction_date)
		) $charset_collate;";
		dbDelta( $sql );

		// Anomalies table.
		$sql = "CREATE TABLE {$this->tables['anomalies']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id bigint(20) UNSIGNED NOT NULL,
			entry_id bigint(20) UNSIGNED DEFAULT NULL,
			anomaly_type varchar(100) NOT NULL,
			severity varchar(20) DEFAULT 'medium',
			description text DEFAULT '',
			detected_value decimal(10,2) DEFAULT NULL,
			expected_value decimal(10,2) DEFAULT NULL,
			is_resolved tinyint(1) DEFAULT 0,
			resolved_by bigint(20) UNSIGNED DEFAULT NULL,
			resolved_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY store_id (store_id),
			KEY entry_id (entry_id),
			KEY is_resolved (is_resolved)
		) $charset_collate;";
		dbDelta( $sql );

		// Notifications table.
		$sql = "CREATE TABLE {$this->tables['notifications']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			type varchar(100) NOT NULL,
			title varchar(255) NOT NULL,
			message text DEFAULT '',
			is_read tinyint(1) DEFAULT 0,
			data longtext DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// Store the database version.
		update_option( 'mscm_db_version', MSCM_DB_VERSION );
	}

	/**
	 * Run any needed database upgrades for existing installations.
	 * Called on plugin init to add new columns introduced after initial install.
	 */
	public function maybe_upgrade() {
		$installed = get_option( 'mscm_db_version', '0' );
		if ( version_compare( $installed, MSCM_DB_VERSION, '<' ) ) {
			// Use dbDelta via create_tables() which safely adds new columns.
			$this->create_tables();
		}
	}

	/**
	 * Set default plugin options on activation.
	 */
	public function set_default_options() {
		$defaults = array(
			'mscm_default_float'           => 500,
			'mscm_currency'                => 'R',
			'mscm_currency_symbol'         => 'R',
			'mscm_timezone'                => 'Africa/Johannesburg',
			'mscm_notification_email'      => get_option( 'admin_email' ),
			'mscm_reminder_time'           => '18:00',
			'mscm_enable_reminders'        => 1,
			'mscm_enable_anomaly_detection' => 1,
			'mscm_enable_ml_predictions'   => 1,
			'mscm_ml_sensitivity'          => 'medium',
			'mscm_api_enabled'             => 0,
			'mscm_enable_backups'          => 1,
			'mscm_backup_frequency'        => 'daily',
			'mscm_backup_retention'        => 30,
			'mscm_date_format'             => 'Y-m-d',
			'mscm_week_start'              => 1,
			'mscm_entries_per_page'        => 20,
			'mscm_enable_geolocation'      => 0,
			'mscm_enable_photo_uploads'    => 0,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	// =========================================================================
	// Store operations.
	// =========================================================================

	/**
	 * Get all active stores.
	 *
	 * @param bool $active_only Whether to return only active stores.
	 * @return array Array of store objects.
	 */
	public function get_stores( $active_only = true ) {
		$where = $active_only ? 'WHERE is_active = 1' : '';
		$cache_key = 'mscm_stores_' . ( $active_only ? 'active' : 'all' );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = $this->wpdb->get_results(
			"SELECT * FROM {$this->tables['stores']} {$where} ORDER BY name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		);

		set_transient( $cache_key, $results, 300 );
		return $results;
	}

	/**
	 * Get a single store by ID.
	 *
	 * @param int $store_id Store ID.
	 * @return object|null Store object or null.
	 */
	public function get_store( $store_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['stores']} WHERE id = %d",
				$store_id
			)
		);
	}

	/**
	 * Get stores assigned to a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of store objects.
	 */
	public function get_user_stores( $user_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $this->get_stores();
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT s.* FROM {$this->tables['stores']} s
				INNER JOIN {$this->tables['user_stores']} us ON s.id = us.store_id
				WHERE us.user_id = %d AND s.is_active = 1
				ORDER BY s.name ASC",
				$user_id
			)
		);
	}

	/**
	 * Save a store (create or update).
	 *
	 * @param array $data Store data.
	 * @return int|false Inserted/updated ID or false on failure.
	 */
	public function save_store( $data ) {
		$allowed = array(
			'name', 'location', 'address', 'phone', 'email',
			'manager_id', 'float_amount', 'target_amount',
			'currency', 'timezone', 'is_active',
		);

		$sanitized = array();
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = $data[ $field ];
			}
		}

		// Clear store cache.
		delete_transient( 'mscm_stores_active' );
		delete_transient( 'mscm_stores_all' );

		if ( ! empty( $data['id'] ) ) {
			$result = $this->wpdb->update(
				$this->tables['stores'],
				$sanitized,
				array( 'id' => absint( $data['id'] ) ),
				null,
				array( '%d' )
			);
			return false !== $result ? absint( $data['id'] ) : false;
		}

		$result = $this->wpdb->insert( $this->tables['stores'], $sanitized );
		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Delete a store.
	 *
	 * @param int $store_id Store ID.
	 * @return bool Success status.
	 */
	public function delete_store( $store_id ) {
		$store_id = absint( $store_id );

		// Soft delete by deactivating.
		$result = $this->wpdb->update(
			$this->tables['stores'],
			array( 'is_active' => 0 ),
			array( 'id' => $store_id ),
			array( '%d' ),
			array( '%d' )
		);

		delete_transient( 'mscm_stores_active' );
		delete_transient( 'mscm_stores_all' );

		return false !== $result;
	}

	// =========================================================================
	// User-Store relationship operations.
	// =========================================================================

	/**
	 * Assign a user to a store.
	 *
	 * @param int    $user_id  User ID.
	 * @param int    $store_id Store ID.
	 * @param string $role     Role (store_user or store_manager).
	 * @return bool Success status.
	 */
	public function assign_user_to_store( $user_id, $store_id, $role = 'store_user' ) {
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->tables['user_stores']} WHERE user_id = %d AND store_id = %d",
				$user_id,
				$store_id
			)
		);

		if ( $existing ) {
			return (bool) $this->wpdb->update(
				$this->tables['user_stores'],
				array( 'role' => $role ),
				array( 'user_id' => $user_id, 'store_id' => $store_id ),
				array( '%s' ),
				array( '%d', '%d' )
			);
		}

		return (bool) $this->wpdb->insert(
			$this->tables['user_stores'],
			array(
				'user_id'  => $user_id,
				'store_id' => $store_id,
				'role'     => $role,
			)
		);
	}

	/**
	 * Remove a user from a store.
	 *
	 * @param int $user_id  User ID.
	 * @param int $store_id Store ID.
	 * @return bool Success status.
	 */
	public function remove_user_from_store( $user_id, $store_id ) {
		return (bool) $this->wpdb->delete(
			$this->tables['user_stores'],
			array(
				'user_id'  => $user_id,
				'store_id' => $store_id,
			),
			array( '%d', '%d' )
		);
	}

	// =========================================================================
	// Daily entry operations.
	// =========================================================================

	/**
	 * Save a daily entry.
	 *
	 * @param array $data Entry data.
	 * @return int|false Inserted/updated ID or false on failure.
	 */
	public function save_entry( $data ) {
		$allowed = array(
			'store_id', 'user_id', 'entry_date',
			'denom_200', 'denom_100', 'denom_50', 'denom_20', 'denom_10',
			'denom_5', 'denom_2', 'denom_1', 'denom_50c', 'denom_20c', 'denom_10c',
			'total_cash', 'float_amount', 'cash_to_bank',
			'credit_card', 'eft', 'other_digital',
			'total_sales', 'pos_cash', 'pos_eft', 'pos_credit_card', 'pos_reported',
			'discrepancy', 'net_banking',
			'banking_date', 'banked_by', 'banking_ref',
			'notes', 'status', 'latitude', 'longitude', 'photo_url',
		);

		$sanitized = array();
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = $data[ $field ];
			}
		}

		if ( ! empty( $data['id'] ) ) {
			$result = $this->wpdb->update(
				$this->tables['entries'],
				$sanitized,
				array( 'id' => absint( $data['id'] ) ),
				null,
				array( '%d' )
			);

			if ( false !== $result ) {
				// Update payouts if provided.
				if ( isset( $data['payouts'] ) ) {
					$this->save_entry_payouts( absint( $data['id'] ), $data['store_id'], $data['payouts'] );
				}
				// Update purchases if provided.
				if ( isset( $data['purchases'] ) ) {
					$this->save_entry_purchases( absint( $data['id'] ), $data['store_id'], $data['purchases'] );
				}
				return absint( $data['id'] );
			}
			return false;
		}

		$result = $this->wpdb->insert( $this->tables['entries'], $sanitized );

		if ( $result ) {
			$entry_id = $this->wpdb->insert_id;

			// Save payouts if provided.
			if ( isset( $data['payouts'] ) ) {
				$this->save_entry_payouts( $entry_id, $data['store_id'], $data['payouts'] );
			}
			// Save purchases if provided.
			if ( isset( $data['purchases'] ) ) {
				$this->save_entry_purchases( $entry_id, $data['store_id'], $data['purchases'] );
			}

			return $entry_id;
		}

		return false;
	}

	/**
	 * Save payouts for a daily entry.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param int   $store_id Store ID.
	 * @param array $payouts  Array of payout data.
	 */
	private function save_entry_payouts( $entry_id, $store_id, $payouts ) {
		// Delete existing payouts for this entry.
		$this->wpdb->delete(
			$this->tables['payouts'],
			array( 'entry_id' => $entry_id ),
			array( '%d' )
		);

		if ( ! is_array( $payouts ) ) {
			return;
		}

		foreach ( $payouts as $payout ) {
			if ( empty( $payout['description'] ) || empty( $payout['amount'] ) ) {
				continue;
			}
			$this->wpdb->insert(
				$this->tables['payouts'],
				array(
					'entry_id'     => $entry_id,
					'store_id'     => $store_id,
					'description'  => sanitize_text_field( $payout['description'] ),
					'amount'       => floatval( $payout['amount'] ),
					'category'     => sanitize_text_field( $payout['category'] ?? 'general' ),
					'payment_type' => in_array( $payout['payment_type'] ?? 'cash', array( 'cash', 'bank' ), true ) ? $payout['payment_type'] : 'cash',
				)
			);
		}
	}

	/**
	 * Save purchases for a daily entry.
	 *
	 * @param int   $entry_id  Entry ID.
	 * @param int   $store_id  Store ID.
	 * @param array $purchases Array of purchase data.
	 */
	private function save_entry_purchases( $entry_id, $store_id, $purchases ) {
		// Delete existing purchases for this entry.
		$this->wpdb->delete(
			$this->tables['purchases'],
			array( 'entry_id' => $entry_id ),
			array( '%d' )
		);

		if ( ! is_array( $purchases ) ) {
			return;
		}

		foreach ( $purchases as $purchase ) {
			if ( empty( $purchase['description'] ) || empty( $purchase['amount'] ) ) {
				continue;
			}
			$this->wpdb->insert(
				$this->tables['purchases'],
				array(
					'entry_id'       => $entry_id,
					'store_id'       => $store_id,
					'description'    => sanitize_text_field( $purchase['description'] ),
					'amount'         => floatval( $purchase['amount'] ),
					'category'       => sanitize_text_field( $purchase['category'] ?? 'general' ),
					'receipt_number' => sanitize_text_field( $purchase['receipt_number'] ?? '' ),
				)
			);
		}
	}

	/**
	 * Get entries with filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of entry objects.
	 */
	public function get_entries( $args = array() ) {
		$defaults = array(
			'store_id'   => null,
			'user_id'    => null,
			'date_from'  => null,
			'date_to'    => null,
			'status'     => null,
			'orderby'    => 'entry_date',
			'order'      => 'DESC',
			'limit'      => 20,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $args['store_id'] ) ) {
			$where_clauses[] = 'e.store_id = %d';
			$params[]        = absint( $args['store_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'e.user_id = %d';
			$params[]        = absint( $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'e.entry_date >= %s';
			$params[]        = sanitize_text_field( $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'e.entry_date <= %s';
			$params[]        = sanitize_text_field( $args['date_to'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'e.status = %s';
			$params[]        = sanitize_text_field( $args['status'] );
		}

		$where   = implode( ' AND ', $where_clauses );
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'e.entry_date DESC';
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		$query = "SELECT e.*, s.name as store_name, u.display_name as user_name
			FROM {$this->tables['entries']} e
			LEFT JOIN {$this->tables['stores']} s ON e.store_id = s.id
			LEFT JOIN {$this->wpdb->users} u ON e.user_id = u.ID
			WHERE {$where}
			ORDER BY {$orderby}
			LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get a single entry by ID.
	 *
	 * @param int $entry_id Entry ID.
	 * @return object|null Entry object or null.
	 */
	public function get_entry( $entry_id ) {
		$entry = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT e.*, s.name as store_name
				FROM {$this->tables['entries']} e
				LEFT JOIN {$this->tables['stores']} s ON e.store_id = s.id
				WHERE e.id = %d",
				$entry_id
			)
		);

		if ( $entry ) {
			$entry->payouts   = $this->get_entry_payouts( $entry_id );
			$entry->purchases = $this->get_entry_purchases( $entry_id );
		}

		return $entry;
	}

	/**
	 * Get payouts for an entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Array of payout objects.
	 */
	public function get_entry_payouts( $entry_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['payouts']} WHERE entry_id = %d ORDER BY id ASC",
				$entry_id
			)
		);
	}

	/**
	 * Get purchases for an entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Array of purchase objects.
	 */
	public function get_entry_purchases( $entry_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['purchases']} WHERE entry_id = %d ORDER BY id ASC",
				$entry_id
			)
		);
	}

	/**
	 * Verify a daily entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $user_id  Verifying user ID.
	 * @return bool Success status.
	 */
	public function verify_entry( $entry_id, $user_id ) {
		return (bool) $this->wpdb->update(
			$this->tables['entries'],
			array(
				'status'      => 'verified',
				'verified_by' => $user_id,
				'verified_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $entry_id ) ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @param int    $store_id  Optional store ID filter.
	 * @param string $date      Date to get stats for (default today).
	 * @return array Statistics array.
	 */
	public function get_dashboard_stats( $store_id = null, $date = null ) {
		if ( null === $date ) {
			$date = current_time( 'Y-m-d' );
		}

		$month_start = wp_date( 'Y-m-01', strtotime( $date ) );
		$month_end   = wp_date( 'Y-m-t', strtotime( $date ) );

		$store_where = '';
		$params      = array();

		if ( $store_id ) {
			$store_where = 'AND store_id = %d';
			$params[]    = absint( $store_id );
		}

		// Today's stats.
		$today_sales = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COALESCE(SUM(total_sales), 0) FROM {$this->tables['entries']}
				WHERE entry_date = %s {$store_where}",
				array_merge( array( $date ), $params )
			)
		);

		// MTD stats.
		$mtd_sales = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COALESCE(SUM(total_sales), 0) FROM {$this->tables['entries']}
				WHERE entry_date BETWEEN %s AND %s {$store_where}",
				array_merge( array( $month_start, $date ), $params )
			)
		);

		// Missing entries count.
		$active_stores = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tables['stores']} WHERE is_active = 1"
		);

		$submitted_today = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['entries']} WHERE entry_date = %s {$store_where}",
				array_merge( array( $date ), $params )
			)
		);

		$missing_entries = max( 0, $active_stores - $submitted_today );

		// Anomalies count.
		$anomalies = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['anomalies']}
				WHERE is_resolved = 0 AND created_at >= %s {$store_where}",
				array_merge( array( $month_start ), $params )
			)
		);

		// MTD target progress.
		$mtd_target = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COALESCE(SUM(target_amount), 0) FROM {$this->tables['targets']}
				WHERE period_year = %d AND period_month = %d {$store_where}",
				array_merge( array( wp_date( 'Y', strtotime( $date ) ), wp_date( 'n', strtotime( $date ) ) ), $params )
			)
		);

		return array(
			'today_sales'     => floatval( $today_sales ),
			'mtd_sales'       => floatval( $mtd_sales ),
			'missing_entries' => intval( $missing_entries ),
			'anomalies'       => intval( $anomalies ),
			'mtd_target'      => floatval( $mtd_target ),
			'target_progress' => $mtd_target > 0 ? round( ( $mtd_sales / $mtd_target ) * 100, 1 ) : 0,
		);
	}

	// =========================================================================
	// Target operations.
	// =========================================================================

	/**
	 * Save a sales target.
	 *
	 * @param array $data Target data.
	 * @return int|false Inserted/updated ID or false on failure.
	 */
	public function save_target( $data ) {
		$allowed = array(
			'store_id', 'period_type', 'period_year', 'period_month',
			'period_quarter', 'target_amount', 'working_days', 'notes', 'created_by',
		);

		$sanitized = array();
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = $data[ $field ];
			}
		}

		if ( ! empty( $data['id'] ) ) {
			$result = $this->wpdb->update(
				$this->tables['targets'],
				$sanitized,
				array( 'id' => absint( $data['id'] ) ),
				null,
				array( '%d' )
			);
			return false !== $result ? absint( $data['id'] ) : false;
		}

		$result = $this->wpdb->insert( $this->tables['targets'], $sanitized );
		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get targets for a store and period.
	 *
	 * @param int $store_id Store ID (optional).
	 * @param int $year     Year.
	 * @param int $month    Month (optional).
	 * @return array Array of target objects.
	 */
	public function get_targets( $store_id = null, $year = null, $month = null ) {
		$where  = array( '1=1' );
		$params = array();

		if ( $store_id ) {
			$where[]  = 't.store_id = %d';
			$params[] = absint( $store_id );
		}
		if ( $year ) {
			$where[]  = 't.period_year = %d';
			$params[] = absint( $year );
		}
		if ( $month ) {
			$where[]  = 't.period_month = %d';
			$params[] = absint( $month );
		}

		$where_sql = implode( ' AND ', $where );

		$query = "SELECT t.*, s.name as store_name
			FROM {$this->tables['targets']} t
			LEFT JOIN {$this->tables['stores']} s ON t.store_id = s.id
			WHERE {$where_sql}
			ORDER BY t.period_year DESC, t.period_month DESC";

		if ( empty( $params ) ) {
			return $this->wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	// =========================================================================
	// Anomaly operations.
	// =========================================================================

	/**
	 * Log an anomaly.
	 *
	 * @param array $data Anomaly data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function log_anomaly( $data ) {
		$result = $this->wpdb->insert(
			$this->tables['anomalies'],
			array(
				'store_id'        => absint( $data['store_id'] ),
				'entry_id'        => ! empty( $data['entry_id'] ) ? absint( $data['entry_id'] ) : null,
				'anomaly_type'    => sanitize_text_field( $data['type'] ),
				'severity'        => sanitize_text_field( $data['severity'] ?? 'medium' ),
				'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
				'detected_value'  => ! empty( $data['detected_value'] ) ? floatval( $data['detected_value'] ) : null,
				'expected_value'  => ! empty( $data['expected_value'] ) ? floatval( $data['expected_value'] ) : null,
			)
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get anomalies.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of anomaly objects.
	 */
	public function get_anomalies( $args = array() ) {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['store_id'] ) ) {
			$where[]  = 'a.store_id = %d';
			$params[] = absint( $args['store_id'] );
		}

		if ( isset( $args['is_resolved'] ) ) {
			$where[]  = 'a.is_resolved = %d';
			$params[] = absint( $args['is_resolved'] );
		}

		$where_sql = implode( ' AND ', $where );
		$limit     = absint( $args['limit'] ?? 50 );

		$query = "SELECT a.*, s.name as store_name
			FROM {$this->tables['anomalies']} a
			LEFT JOIN {$this->tables['stores']} s ON a.store_id = s.id
			WHERE {$where_sql}
			ORDER BY a.created_at DESC
			LIMIT %d";

		$params[] = $limit;

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	// =========================================================================
	// Audit logging.
	// =========================================================================

	/**
	 * Log an audit event.
	 *
	 * @param string $action      Action performed.
	 * @param string $object_type Type of object affected.
	 * @param int    $object_id   ID of affected object.
	 * @param array  $old_values  Previous values.
	 * @param array  $new_values  New values.
	 */
	public function log_audit( $action, $object_type = '', $object_id = null, $old_values = array(), $new_values = array() ) {
		$this->wpdb->insert(
			$this->tables['audit_log'],
			array(
				'user_id'     => get_current_user_id(),
				'action'      => sanitize_text_field( $action ),
				'object_type' => sanitize_text_field( $object_type ),
				'object_id'   => $object_id,
				'old_values'  => wp_json_encode( $old_values ),
				'new_values'  => wp_json_encode( $new_values ),
				'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
				'user_agent'  => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ) ),
			)
		);
	}

	/**
	 * Get sales data for chart rendering.
	 *
	 * @param int    $store_id  Store ID (optional for all stores).
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array Associative array of date => total_sales.
	 */
	public function get_sales_chart_data( $store_id = null, $date_from = null, $date_to = null ) {
		if ( null === $date_from ) {
			$date_from = wp_date( 'Y-m-01' );
		}
		if ( null === $date_to ) {
			$date_to = wp_date( 'Y-m-d' );
		}

		$where  = array( 'entry_date BETWEEN %s AND %s' );
		$params = array( $date_from, $date_to );

		if ( $store_id ) {
			$where[]  = 'store_id = %d';
			$params[] = absint( $store_id );
		}

		$where_sql = implode( ' AND ', $where );

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT entry_date, SUM(total_sales) as total
				FROM {$this->tables['entries']}
				WHERE {$where_sql}
				GROUP BY entry_date
				ORDER BY entry_date ASC",
				$params
			)
		);

		$data = array();
		foreach ( $results as $row ) {
			$data[ $row->entry_date ] = floatval( $row->total );
		}

		return $data;
	}
}
