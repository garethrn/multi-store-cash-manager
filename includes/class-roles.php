<?php
/**
 * User roles and capabilities management.
 *
 * Handles creation of custom WordPress roles and capabilities
 * for store users and store managers.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_Roles class.
 *
 * @since 1.0.0
 */
class MSCM_Roles {

	/**
	 * Store User role slug.
	 */
	const STORE_USER = 'store_user';

	/**
	 * Store Manager role slug.
	 */
	const STORE_MANAGER = 'store_manager';

	/**
	 * Store User capabilities.
	 *
	 * @var array
	 */
	private $store_user_caps = array(
		'read'                        => true,
		'mscm_submit_entry'           => true,
		'mscm_view_own_entries'       => true,
		'mscm_view_own_store'         => true,
		'mscm_view_targets'           => true,
		'mscm_view_dashboard'         => true,
	);

	/**
	 * Store Manager capabilities (includes all store user caps).
	 *
	 * @var array
	 */
	private $store_manager_caps = array(
		'read'                        => true,
		'mscm_submit_entry'           => true,
		'mscm_view_own_entries'       => true,
		'mscm_view_own_store'         => true,
		'mscm_view_targets'           => true,
		'mscm_view_dashboard'         => true,
		'mscm_verify_entry'           => true,
		'mscm_view_store_entries'     => true,
		'mscm_manage_store'           => true,
		'mscm_manage_users'           => true,
		'mscm_set_targets'            => true,
		'mscm_view_reports'           => true,
		'mscm_export_reports'         => true,
		'mscm_view_anomalies'         => true,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing to initialize.
	}

	/**
	 * Create custom roles on plugin activation.
	 */
	public function create_roles() {
		// Create Store User role.
		if ( ! get_role( self::STORE_USER ) ) {
			add_role(
				self::STORE_USER,
				__( 'Store User', 'multi-store-cash-manager' ),
				$this->store_user_caps
			);
		}

		// Create Store Manager role.
		if ( ! get_role( self::STORE_MANAGER ) ) {
			add_role(
				self::STORE_MANAGER,
				__( 'Store Manager', 'multi-store-cash-manager' ),
				$this->store_manager_caps
			);
		}

		// Add all MSCM capabilities to Administrator.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$all_caps = array_merge( $this->store_manager_caps, $this->get_admin_caps() );
			foreach ( $all_caps as $cap => $grant ) {
				$admin->add_cap( $cap, $grant );
			}
		}
	}

	/**
	 * Get administrator-only capabilities.
	 *
	 * @return array Array of capabilities.
	 */
	private function get_admin_caps() {
		return array(
			'mscm_manage_all_stores'  => true,
			'mscm_manage_users'       => true,
			'mscm_manage_settings'    => true,
			'mscm_view_all_reports'   => true,
			'mscm_manage_api'         => true,
			'mscm_delete_entries'     => true,
			'mscm_manage_targets'     => true,
			'mscm_view_audit_log'     => true,
			'mscm_manage_backups'     => true,
			'mscm_resolve_anomalies'  => true,
		);
	}

	/**
	 * Remove custom roles on plugin deactivation.
	 */
	public function remove_roles() {
		remove_role( self::STORE_USER );
		remove_role( self::STORE_MANAGER );

		// Remove MSCM capabilities from Administrator.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$all_caps = array_merge(
				array_keys( $this->store_user_caps ),
				array_keys( $this->store_manager_caps ),
				array_keys( $this->get_admin_caps() )
			);
			foreach ( $all_caps as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Check if the current user can access a specific store.
	 *
	 * @param int $store_id Store ID.
	 * @return bool Whether the user can access the store.
	 */
	public static function user_can_access_store( $store_id ) {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'mscm_manage_all_stores' ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mscm_user_stores';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND store_id = %d",
				$user_id,
				$store_id
			)
		);

		return ! empty( $result );
	}

	/**
	 * Get the current user's stores.
	 *
	 * @return array Array of store objects.
	 */
	public static function get_current_user_stores() {
		$db = new MSCM_DB();
		return $db->get_user_stores( get_current_user_id() );
	}

	/**
	 * Get all users with MSCM roles.
	 *
	 * @param string $role Optional role to filter by.
	 * @return array Array of WP_User objects.
	 */
	public static function get_mscm_users( $role = '' ) {
		$args = array(
			'fields' => 'all',
		);

		if ( $role ) {
			$args['role'] = $role;
		} else {
			$args['role__in'] = array( self::STORE_USER, self::STORE_MANAGER, 'administrator' );
		}

		return get_users( $args );
	}
}
