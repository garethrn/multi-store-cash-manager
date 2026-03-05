<?php
/**
 * REST API endpoints.
 *
 * Registers and handles REST API endpoints for external integration.
 *
 * @package MultiStoreCashManager
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSCM_API class.
 *
 * @since 1.0.0
 */
class MSCM_API {

	/**
	 * API namespace.
	 */
	const NAMESPACE = 'mscm/v1';

	/**
	 * Constructor - registers REST API routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 */
	public function register_routes() {
		if ( ! get_option( 'mscm_api_enabled', 0 ) ) {
			return;
		}

		// Stores endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/stores',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stores' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stores/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_store' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => fn( $v ) => is_numeric( $v ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Entries endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/entries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_entries' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'store_id'  => array(
							'sanitize_callback' => 'absint',
						),
						'date_from' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'date_to'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'     => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Dashboard stats endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Reports endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/reports/(?P<type>[a-z]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_report' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'type'      => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'store_id'  => array(
							'sanitize_callback' => 'absint',
						),
						'date_from' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'date_to'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check API permission - validates API key or WordPress authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_permission( $request ) {
		// Allow WordPress cookie auth for admin users.
		if ( is_user_logged_in() && current_user_can( 'mscm_view_reports' ) ) {
			return true;
		}

		// Check API key.
		$api_key = $request->get_header( 'X-MSCM-API-Key' );
		if ( ! $api_key ) {
			$api_key = $request->get_param( 'api_key' );
		}

		$stored_key = get_option( 'mscm_api_key', '' );
		if ( $api_key && hash_equals( $stored_key, $api_key ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Invalid API key or insufficient permissions.', 'multi-store-cash-manager' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Get all stores.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_stores( $request ) {
		$stores = MSCM()->db->get_stores();
		return rest_ensure_response( array( 'stores' => $stores, 'count' => count( $stores ) ) );
	}

	/**
	 * Get a single store.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_store( $request ) {
		$store_id = $request->get_param( 'id' );
		$store    = MSCM()->db->get_store( $store_id );

		if ( ! $store ) {
			return new WP_Error( 'not_found', __( 'Store not found.', 'multi-store-cash-manager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $store );
	}

	/**
	 * Get entries.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_entries( $request ) {
		$args = array(
			'store_id'  => $request->get_param( 'store_id' ),
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
			'limit'     => min( 100, $request->get_param( 'limit' ) ?? 20 ),
		);

		$entries = MSCM()->db->get_entries( array_filter( $args ) );

		return rest_ensure_response(
			array(
				'entries' => $entries,
				'count'   => count( $entries ),
			)
		);
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_stats( $request ) {
		$store_id = $request->get_param( 'store_id' );
		$stats    = MSCM()->db->get_dashboard_stats( $store_id ?: null );

		return rest_ensure_response( $stats );
	}

	/**
	 * Get a report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_report( $request ) {
		$type      = $request->get_param( 'type' );
		$store_id  = $request->get_param( 'store_id' ) ?? 0;
		$date_from = $request->get_param( 'date_from' ) ?? date( 'Y-m-01' );
		$date_to   = $request->get_param( 'date_to' ) ?? date( 'Y-m-d' );

		$report = MSCM()->reports->generate( $type, $store_id, $date_from, $date_to );

		return rest_ensure_response( $report );
	}
}
