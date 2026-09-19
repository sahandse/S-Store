<?php
/**
 * Plugin Name: قیمت نقدی و قسطی
 * Plugin URI:  https://github.com/sahandse/price
 * Description: نمایش قیمت نقدی و قسطی در فروشگاه WooCommerce با انتخاب نوع پرداخت هنگام افزودن به سبد
 * Version:     1.3.8
 * Author:      sahandse
 * Text Domain: woo-price-type
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 */

defined( 'ABSPATH' ) || exit;

define( 'WPT_VERSION',  '1.3.8' );
define( 'WPT_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WPT_URL',      plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'wpt_boot', 5 );

function wpt_boot() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . esc_html__( 'افزونه «قیمت نقدی و قسطی» نیاز به WooCommerce دارد.', 'woo-price-type' )
               . '</p></div>';
        } );
        return;
    }

    require_once WPT_DIR . 'includes/class-admin-settings.php';
    require_once WPT_DIR . 'includes/class-product-display.php';
    require_once WPT_DIR . 'includes/class-cart-handler.php';

    new WPT_Admin_Settings();
    new WPT_Product_Display();
    new WPT_Cart_Handler();
}
