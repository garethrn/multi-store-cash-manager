<?php
/**
 * Multi-Store Daily Cash Manager
 *
 * @package           MultiStoreCashManager
 * @author            Multi-Store Cash Manager
 * @copyright         2024 Multi-Store Cash Manager
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Multi-Store Daily Cash Manager
 * Plugin URI:        https://example.com/multi-store-cash-manager
 * Description:       A comprehensive multi-store retail cash management plugin with daily end-of-day entries, sales targets, reporting, and AI/ML features.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Multi-Store Cash Manager
 * Author URI:        https://example.com
 * Text Domain:       multi-store-cash-manager
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MSCM_VERSION', '1.0.0' );
define( 'MSCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSCM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MSCM_DB_VERSION', '1.1.0' );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class Multi_Store_Cash_Manager {

	/**
	 * Single instance of the class.
	 *
	 * @var Multi_Store_Cash_Manager
	 */
	private static $instance = null;

	/**
	 * Database handler instance.
	 *
	 * @var MSCM_DB
	 */
	public $db;

	/**
	 * Roles handler instance.
	 *
	 * @var MSCM_Roles
	 */
	public $roles;

	/**
	 * AJAX handler instance.
	 *
	 * @var MSCM_Ajax
	 */
	public $ajax;

	/**
	 * Reports handler instance.
	 *
	 * @var MSCM_Reports
	 */
	public $reports;

	/**
	 * ML handler instance.
	 *
	 * @var MSCM_ML
	 */
	public $ml;

	/**
	 * API handler instance.
	 *
	 * @var MSCM_API
	 */
	public $api;

	/**
	 * Notifications handler instance.
	 *
	 * @var MSCM_Notifications
	 */
	public $notifications;

	/**
	 * Export handler instance.
	 *
	 * @var MSCM_Export
	 */
	public $export;

	/**
	 * PDF generator instance.
	 *
	 * @var MSCM_PDF_Generator
	 */
	public $pdf;

	/**
	 * Get the single instance of the class.
	 *
	 * @return Multi_Store_Cash_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - private to enforce singleton.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once MSCM_PLUGIN_DIR . 'includes/class-db.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-roles.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-ajax.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-reports.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-ml.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-api.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-notifications.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-export.php';
		require_once MSCM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Init all components.
		add_action( 'init', array( $this, 'init_components' ) );

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		// Register shortcodes.
		add_action( 'init', array( $this, 'register_shortcodes' ) );

		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Plugin action links.
		add_filter(
			'plugin_action_links_' . MSCM_PLUGIN_BASENAME,
			array( $this, 'add_action_links' )
		);
	}

	/**
	 * Initialize all plugin components.
	 */
	public function init_components() {
		$this->db            = new MSCM_DB();
		$this->roles         = new MSCM_Roles();
		$this->ajax          = new MSCM_Ajax();
		$this->reports       = new MSCM_Reports();
		$this->ml            = new MSCM_ML();
		$this->api           = new MSCM_API();
		$this->notifications = new MSCM_Notifications();
		$this->export        = new MSCM_Export();
		$this->pdf           = new MSCM_PDF_Generator();

		// Run DB upgrades if needed.
		$this->db->maybe_upgrade();
	}

	/**
	 * Register admin menus.
	 */
	public function register_admin_menus() {
		require_once MSCM_PLUGIN_DIR . 'admin/admin-menu.php';
		mscm_register_admin_menus();
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only on our plugin pages.
		if ( strpos( $hook, 'mscm' ) === false && strpos( $hook, 'multi-store' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'mscm-admin-style',
			MSCM_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			MSCM_VERSION
		);

		// Chart.js from CDN.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'mscm-admin-script',
			MSCM_PLUGIN_URL . 'assets/js/admin-script.js',
			array( 'jquery', 'chartjs' ),
			MSCM_VERSION,
			true
		);

		wp_localize_script(
			'mscm-admin-script',
			'mscmAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mscm_admin_nonce' ),
				'pluginUrl' => MSCM_PLUGIN_URL,
				'currency'  => get_option( 'mscm_currency', 'R' ),
				'strings'   => array(
					'confirmDelete'  => __( 'Are you sure you want to delete this item?', 'multi-store-cash-manager' ),
					'saving'         => __( 'Saving...', 'multi-store-cash-manager' ),
					'saved'          => __( 'Saved!', 'multi-store-cash-manager' ),
					'error'          => __( 'An error occurred. Please try again.', 'multi-store-cash-manager' ),
					'generating'     => __( 'Generating report...', 'multi-store-cash-manager' ),
					'noDataSelected' => __( 'Please select a date range.', 'multi-store-cash-manager' ),
				),
			)
		);
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_assets() {
		global $post;

		// Only enqueue when shortcodes are present.
		$shortcodes = array( 'mscm_dashboard', 'mscm_end_of_day', 'mscm_targets', 'mscm_history' );
		$has_shortcode = false;

		if ( is_a( $post, 'WP_Post' ) ) {
			foreach ( $shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					$has_shortcode = true;
					break;
				}
			}
		}

		if ( ! $has_shortcode ) {
			return;
		}

		wp_enqueue_style(
			'mscm-public-style',
			MSCM_PLUGIN_URL . 'assets/css/public-style.css',
			array(),
			MSCM_VERSION
		);

		wp_enqueue_script(
			'mscm-public-script',
			MSCM_PLUGIN_URL . 'assets/js/public-script.js',
			array( 'jquery' ),
			MSCM_VERSION,
			true
		);

		wp_localize_script(
			'mscm-public-script',
			'mscmPublic',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'mscm_public_nonce' ),
				'floatAmount' => get_option( 'mscm_default_float', 500 ),
				'currency'    => get_option( 'mscm_currency', 'R' ),
				'strings'     => array(
					'submitting'        => __( 'Submitting...', 'multi-store-cash-manager' ),
					'submitted'         => __( 'Entry submitted successfully!', 'multi-store-cash-manager' ),
					'error'             => __( 'An error occurred. Please try again.', 'multi-store-cash-manager' ),
					'validationError'   => __( 'Please fill in all required fields.', 'multi-store-cash-manager' ),
					'confirmSubmit'     => __( 'Are you sure you want to submit this entry?', 'multi-store-cash-manager' ),
					'selectStore'       => __( 'Please select a store.', 'multi-store-cash-manager' ),
				),
			)
		);
	}

	/**
	 * Register plugin shortcodes.
	 */
	public function register_shortcodes() {
		require_once MSCM_PLUGIN_DIR . 'public/public-dashboard.php';
		require_once MSCM_PLUGIN_DIR . 'public/public-end-of-day.php';
		require_once MSCM_PLUGIN_DIR . 'public/public-targets.php';
		require_once MSCM_PLUGIN_DIR . 'public/public-history.php';

		add_shortcode( 'mscm_dashboard', 'mscm_dashboard_shortcode' );
		add_shortcode( 'mscm_end_of_day', 'mscm_end_of_day_shortcode' );
		add_shortcode( 'mscm_targets', 'mscm_targets_shortcode' );
		add_shortcode( 'mscm_history', 'mscm_history_shortcode' );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multi-store-cash-manager',
			false,
			dirname( MSCM_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Add action links to the plugin list.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			'<a href="' . admin_url( 'admin.php?page=mscm-dashboard' ) . '">' . __( 'Dashboard', 'multi-store-cash-manager' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=mscm-settings' ) . '">' . __( 'Settings', 'multi-store-cash-manager' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}
}

/**
 * Plugin activation hook.
 */
function mscm_activate() {
	require_once MSCM_PLUGIN_DIR . 'includes/class-db.php';
	require_once MSCM_PLUGIN_DIR . 'includes/class-roles.php';

	$db    = new MSCM_DB();
	$roles = new MSCM_Roles();

	$db->create_tables();
	$db->set_default_options();
	$roles->create_roles();

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'mscm_daily_reminders' ) ) {
		wp_schedule_event( strtotime( 'today 18:00:00' ), 'daily', 'mscm_daily_reminders' );
	}
	if ( ! wp_next_scheduled( 'mscm_daily_backup' ) ) {
		wp_schedule_event( strtotime( 'today 02:00:00' ), 'daily', 'mscm_daily_backup' );
	}

	// Set activation transient for admin notice.
	set_transient( 'mscm_activated', true, 5 );

	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 */
function mscm_deactivate() {
	// Clear scheduled cron jobs.
	wp_clear_scheduled_hook( 'mscm_daily_reminders' );
	wp_clear_scheduled_hook( 'mscm_daily_backup' );

	flush_rewrite_rules();
}

// Register activation/deactivation hooks.
register_activation_hook( __FILE__, 'mscm_activate' );
register_deactivation_hook( __FILE__, 'mscm_deactivate' );

/**
 * Initialize the plugin.
 *
 * @return Multi_Store_Cash_Manager
 */
function MSCM() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Multi_Store_Cash_Manager::get_instance();
}

// Start the plugin.
MSCM();
