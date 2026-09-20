<?php
/**
 * Plugin Name: دکمه پشتیبانی
 * Plugin URI: https://github.com/sahandse/support-button-
 * Description: A bilingual support and live chat button for WordPress with on-site conversations, FAQ, social links, support agents, customizable themes, working hours and optional Bale integration.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Sahand Rezvan
 * Author URI: https://t.me/sahandse
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: support-button
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SB_VERSION', '1.0.1' );
define( 'SB_FILE', __FILE__ );
define( 'SB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SB_URL', plugin_dir_url( __FILE__ ) );

require_once SB_DIR . 'includes/class-sb-install.php';
require_once SB_DIR . 'includes/class-sb-plugin.php';

register_activation_hook( __FILE__, array( 'SB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SB_Install', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'support-button', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    SB_Plugin::instance()->boot();
} );
