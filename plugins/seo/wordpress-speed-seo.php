<?php
/**
 * Plugin Name: سئو سهند
 * Plugin URI:  https://github.com/sahandse/seo
 * Description: افزونه جامع بهینه‌سازی سرعت و سئو وردپرس با پنل مدیریت کامل
 * Version:     3.1.1
 * Author:      Speed SEO Team
 * License:     GPL v2 or later
 * Text Domain: wp-speed-seo
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WSS_VERSION',     '3.1.1' );
define( 'WSS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WSS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WSS_PLUGIN_FILE', __FILE__ );
define( 'WSS_DB_VERSION',  '2.0' );

// Core modules
require_once WSS_PLUGIN_DIR . 'includes/class-speed-optimizer.php';
require_once WSS_PLUGIN_DIR . 'includes/class-seo-meta.php';
require_once WSS_PLUGIN_DIR . 'includes/class-sitemap.php';
require_once WSS_PLUGIN_DIR . 'includes/class-schema.php';
require_once WSS_PLUGIN_DIR . 'includes/class-redirects.php';
require_once WSS_PLUGIN_DIR . 'includes/class-breadcrumbs.php';
require_once WSS_PLUGIN_DIR . 'includes/class-analyzer.php';

// New v3 modules
require_once WSS_PLUGIN_DIR . 'includes/class-health-check.php';
require_once WSS_PLUGIN_DIR . 'includes/class-page-cache.php';
require_once WSS_PLUGIN_DIR . 'includes/class-webp-converter.php';
require_once WSS_PLUGIN_DIR . 'includes/class-broken-links.php';
require_once WSS_PLUGIN_DIR . 'includes/class-rank-tracker.php';
require_once WSS_PLUGIN_DIR . 'includes/class-critical-css.php';
require_once WSS_PLUGIN_DIR . 'includes/class-local-fonts.php';
require_once WSS_PLUGIN_DIR . 'includes/class-advanced-seo.php';
require_once WSS_PLUGIN_DIR . 'includes/class-core-web-vitals.php';

// Admin
require_once WSS_PLUGIN_DIR . 'admin/class-admin.php';

class WP_Speed_SEO {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( WSS_PLUGIN_FILE,   [ $this, 'activate' ] );
        register_deactivation_hook( WSS_PLUGIN_FILE, [ $this, 'deactivate' ] );
    }

    public function init() {
        load_plugin_textdomain( 'wp-speed-seo', false, dirname( plugin_basename( WSS_PLUGIN_FILE ) ) . '/languages' );

        // Core
        WSS_Speed_Optimizer::instance();
        WSS_SEO_Meta::instance();
        WSS_Sitemap::instance();
        WSS_Schema::instance();
        WSS_Redirects::instance();
        WSS_Breadcrumbs::instance();

        // v3 modules
        WSS_Page_Cache::instance();
        WSS_WebP_Converter::instance();
        WSS_Broken_Links::instance();
        WSS_Rank_Tracker::instance();
        WSS_Critical_CSS::instance();
        WSS_Local_Fonts::instance();
        WSS_Advanced_SEO::instance();
        WSS_Core_Web_Vitals::instance();

        if ( is_admin() ) {
            WSS_Admin::instance();
        }
    }

    public function activate() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Redirects table
        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wss_redirects (
            id         bigint(20)   NOT NULL AUTO_INCREMENT,
            source_url varchar(512) NOT NULL,
            target_url varchar(512) NOT NULL,
            type       smallint(4)  NOT NULL DEFAULT 301,
            hits       bigint(20)   NOT NULL DEFAULT 0,
            enabled    tinyint(1)   NOT NULL DEFAULT 1,
            created_at datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_url (source_url(191))
        ) $charset;" );

        // Broken links table
        WSS_Broken_Links::create_table();

        // Rank tracker table
        WSS_Rank_Tracker::create_table();

        // Default options
        $defaults = [
            'wss_speed' => [
                'minify_html'           => 1,
                'minify_css'            => 1,
                'minify_js'             => 1,
                'defer_js'              => 1,
                'async_js'              => 0,
                'lazy_images'           => 1,
                'lazy_iframes'          => 1,
                'remove_query_str'      => 1,
                'disable_emoji'         => 1,
                'disable_embeds'        => 1,
                'disable_xmlrpc'        => 0,
                'disable_dashicons'     => 1,
                'remove_jquery_migrate' => 1,
                'google_fonts_async'    => 1,
                'browser_cache'         => 1,
                'gzip'                  => 1,
                'preconnect'            => '',
                'dns_prefetch'          => '',
                'preload_fonts'         => '',
            ],
            'wss_seo' => [
                'title_separator'  => '|',
                'title_home'       => '',
                'desc_home'        => '',
                'og_enabled'       => 1,
                'twitter_enabled'  => 1,
                'twitter_card'     => 'summary_large_image',
                'twitter_site'     => '',
                'noindex_archives' => 0,
                'noindex_tags'     => 0,
                'noindex_404'      => 1,
                'canonical_enabled'=> 1,
                'auto_description' => 1,
                'auto_desc_length' => 160,
                'default_og_image' => '',
                'breadcrumbs'      => 1,
                'breadcrumb_home'  => 'خانه',
            ],
            'wss_social'    => [ 'facebook'=>'','twitter'=>'','instagram'=>'','linkedin'=>'','youtube'=>'','telegram'=>'' ],
            'wss_webmaster' => [ 'google_verify'=>'','bing_verify'=>'','yandex_verify'=>'','google_analytics'=>'','gtm_id'=>'','pagespeed_key'=>'' ],
            'wss_schema'    => [ 'type'=>'Organization','name'=>get_bloginfo('name'),'logo'=>'','phone'=>'','email'=>'','address'=>'','city'=>'','country'=>'IR','social_profiles'=>1 ],
            'wss_cache'     => [ 'enabled'=>0,'ttl'=>3600,'gzip_cache'=>1,'cache_logged_in'=>0,'exclude_urls'=>'' ],
            'wss_webp'      => [ 'enabled'=>1,'serve_webp'=>1,'quality'=>82 ],
            'wss_local_fonts' => [ 'enabled'=>0 ],
            'wss_critical_css'=> [ 'enabled'=>0,'critical_handles'=>'' ],
        ];

        foreach ( $defaults as $key => $val ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $val );
            }
        }

        // Cron schedules
        if ( ! wp_next_scheduled( 'wss_db_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'wss_db_cleanup' );
        }
        if ( ! wp_next_scheduled( 'wss_rank_check' ) ) {
            wp_schedule_event( time(), 'daily', 'wss_rank_check' );
        }
        if ( ! wp_next_scheduled( 'wss_scan_links' ) ) {
            wp_schedule_event( time(), 'weekly', 'wss_scan_links' );
        }
        if ( ! wp_next_scheduled( 'wss_cache_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'wss_cache_cleanup' );
        }

        flush_rewrite_rules();
        update_option( 'wss_db_version', WSS_DB_VERSION );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'wss_db_cleanup' );
        wp_clear_scheduled_hook( 'wss_rank_check' );
        wp_clear_scheduled_hook( 'wss_scan_links' );
        wp_clear_scheduled_hook( 'wss_cache_cleanup' );
        flush_rewrite_rules();
    }
}

WP_Speed_SEO::instance();

// Frontend assets
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('wss-frontend', WSS_PLUGIN_URL . 'assets/css/frontend.css', [], WSS_VERSION);
});

// Clear cache handler
add_action('admin_post_wss_clear_cache', function(){
    if (!current_user_can('manage_options')) wp_die();
    check_admin_referer('wss_clear_cache');
    WSS_Page_Cache::instance()->clear_all();
    set_transient('wss_admin_notice', ['msg'=>'کش با موفقیت پاک شد.','type'=>'success'], 30);
    wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=wss-cache'));
    exit;
});

// Save settings handler for new groups
add_filter('wss_save_settings_groups', function($groups){
    return array_merge($groups, ['wss_cache','wss_webp','wss_local_fonts','wss_critical_css']);
});

// SERP API key save
add_action('admin_post_wss_save_serp_key', function(){
    if (!current_user_can('manage_options')) wp_die();
    check_admin_referer('wss_save_serp_key');
    update_option('wss_serp_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
    set_transient('wss_admin_notice', ['msg'=>'API Key ذخیره شد.','type'=>'success'], 30);
    wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=wss-rank-tracker'));
    exit;
});

// PageSpeed API key save (via webmaster settings group)
add_filter('wss_webmaster_extra_fields', function($fields){
    $fields['pagespeed_key'] = sanitize_text_field($_POST['pagespeed_key'] ?? '');
    return $fields;
});
