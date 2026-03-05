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
 * Plugin URI:        https://github.com/garethrn/multi-store-cash-manager
 * Description:       A comprehensive multi-store retail cash management plugin with daily end-of-day entries, sales targets, reporting, and AI/ML features.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Multi-Store Cash Manager
 * Author URI:        https://github.com/garethrn
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
define( 'MSCM_DB_VERSION', '1.0.0' );
