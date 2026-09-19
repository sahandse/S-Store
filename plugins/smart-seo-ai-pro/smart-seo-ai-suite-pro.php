<?php
/**
 * Plugin Name: Smart SEO AI Suite Pro
 * Plugin URI: https://smartseoai.pro
 * Description: Enterprise All-in-One SEO Engine, AI Content Generator, WooCommerce Optimizer, Security Scanner, Performance Profiler, and Auto-Fix Suite for WordPress.
 * Version: 1.0.0
 * Author: Smart SEO AI Suite Team
 * Author URI: https://smartseoai.pro
 * Text Domain: smart-seo-ai-suite-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'SMART_SEO_AI_VERSION', '1.0.0' );
define( 'SMART_SEO_AI_FILE', __FILE__ );
define( 'SMART_SEO_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMART_SEO_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'SMART_SEO_AI_BASENAME', plugin_basename( __FILE__ ) );
define( 'SMART_SEO_AI_DB_VERSION', '1.0.0' );

// Include Core Loader
require_once SMART_SEO_AI_PATH . 'core/loader.php';

// Activation Hook
register_activation_hook( __FILE__, array( 'Smart_SEO_AI_Installer', 'activate' ) );

// Deactivation Hook
register_deactivation_hook( __FILE__, array( 'Smart_SEO_AI_Installer', 'deactivate' ) );

// Initialize the plugin
function smart_seo_ai_suite_pro_init() {
	return Smart_SEO_AI_Loader::get_instance();
}

add_action( 'plugins_loaded', 'smart_seo_ai_suite_pro_init' );
