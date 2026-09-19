<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_wss_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_wss_add_redirect',  [ $this, 'handle_add_redirect' ] );
        add_action( 'admin_post_wss_delete_redirect', [ $this, 'handle_delete_redirect' ] );
        add_action( 'admin_post_wss_toggle_redirect', [ $this, 'handle_toggle_redirect' ] );
        add_action( 'admin_post_wss_import_redirects', [ $this, 'handle_import_redirects' ] );
        add_action( 'admin_post_wss_run_cleanup', [ $this, 'handle_db_cleanup' ] );
        add_action( 'wp_ajax_wss_analyze',    [ $this, 'ajax_analyze' ] );
        add_action( 'wp_ajax_wss_flush_sitemap', [ $this, 'ajax_flush_sitemap' ] );
        add_action( 'wp_ajax_wss_enable_recommended', [ $this, 'ajax_enable_recommended' ] );
        add_action( 'wp_ajax_wss_health_check',       [ $this, 'ajax_health_check' ] );
        add_action( 'admin_notices',          [ $this, 'admin_notices' ] );
    }

    public function register_menus() {
        add_menu_page(
            'Speed & SEO Optimizer',
            'Speed & SEO',
            'manage_options',
            'wss-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-chart-line',
            65
        );

        $pages = [
            [ 'wss-speed',        'بهینه‌سازی سرعت', [ $this, 'page_speed' ] ],
            [ 'wss-seo',          'تنظیمات سئو',      [ $this, 'page_seo' ] ],
            [ 'wss-schema',       'Schema.org',        [ $this, 'page_schema' ] ],
            [ 'wss-sitemap',      'نقشه سایت',         [ $this, 'page_sitemap' ] ],
            [ 'wss-redirects',    'ریدایرکت‌ها',       [ $this, 'page_redirects' ] ],
            [ 'wss-cache',        'کش صفحات',          [ $this, 'page_cache' ] ],
            [ 'wss-webp',         'تبدیل WebP',        [ $this, 'page_webp' ] ],
            [ 'wss-broken-links', 'لینک‌های شکسته',    [ $this, 'page_broken_links' ] ],
            [ 'wss-rank-tracker', 'ردیاب رتبه',        [ $this, 'page_rank_tracker' ] ],
            [ 'wss-vitals',       'Core Web Vitals',   [ $this, 'page_vitals' ] ],
            [ 'wss-advanced-seo', 'سئو پیشرفته',       [ $this, 'page_advanced_seo' ] ],
            [ 'wss-local-fonts',  'فونت‌های محلی',     [ $this, 'page_local_fonts' ] ],
            [ 'wss-critical-css', 'Critical CSS',      [ $this, 'page_critical_css' ] ],
            [ 'wss-health',       'سلامت سایت',         [ $this, 'page_health' ] ],
            [ 'wss-tools',        'ابزارها',            [ $this, 'page_tools' ] ],
        ];

        foreach ( $pages as $p ) {
            add_submenu_page( 'wss-dashboard', $p[1], $p[1], 'manage_options', $p[0], $p[2] );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos($hook, 'wss-') === false && strpos($hook, 'wss_') === false ) return;

        wp_enqueue_style(  'wss-admin', WSS_PLUGIN_URL . 'admin/css/admin.css', [], WSS_VERSION );
        wp_enqueue_script( 'wss-admin', WSS_PLUGIN_URL . 'admin/js/admin.js',  ['jquery', 'wp-color-picker'], WSS_VERSION, true );
        wp_enqueue_style( 'wp-color-picker' );

        wp_localize_script( 'wss-admin', 'wssAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wss_admin_nonce'),
        ]);
    }

    private function nonce_field( $action ) {
        echo wp_nonce_field( $action, '_wpnonce', true, false );
    }

    private function redirect_back( $msg = '', $type = 'success' ) {
        $url = wp_get_referer() ?: admin_url('admin.php?page=wss-dashboard');
        if ( $msg ) {
            set_transient( 'wss_admin_notice', [ 'msg' => $msg, 'type' => $type ], 30 );
        }
        wp_redirect( $url );
        exit;
    }

    public function admin_notices() {
        $notice = get_transient('wss_admin_notice');
        if ( ! $notice ) return;
        delete_transient('wss_admin_notice');
        $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($notice['msg']) . '</p></div>';
    }

    // ─── Save settings ────────────────────────────────────────────────────────

    public function save_settings() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_save_settings');

        $group = sanitize_key( $_POST['wss_group'] ?? '' );

        $allowed_groups = apply_filters( 'wss_save_settings_groups', [
            'wss_speed','wss_seo','wss_social','wss_webmaster','wss_schema',
            'wss_cache','wss_webp','wss_local_fonts','wss_critical_css',
        ]);
        if ( ! in_array($group, $allowed_groups, true) ) {
            $this->redirect_back('گروه تنظیمات معتبر نیست.', 'error');
        }

        $data = [];

        switch ( $group ) {
            case 'wss_speed':
                $checkboxes = ['minify_html','minify_css','minify_js','defer_js','async_js','lazy_images','lazy_iframes',
                               'remove_query_str','disable_emoji','disable_embeds','disable_xmlrpc','disable_dashicons',
                               'remove_jquery_migrate','google_fonts_async','browser_cache','gzip','db_cleanup'];
                foreach ( $checkboxes as $k ) {
                    $data[$k] = isset($_POST[$k]) ? 1 : 0;
                }
                $data['dns_prefetch']  = sanitize_textarea_field( $_POST['dns_prefetch'] ?? '' );
                $data['preconnect']    = sanitize_textarea_field( $_POST['preconnect']   ?? '' );
                $data['preload_fonts'] = sanitize_textarea_field( $_POST['preload_fonts'] ?? '' );
                break;

            case 'wss_seo':
                $data = [
                    'title_separator'   => sanitize_text_field( $_POST['title_separator'] ?? '|' ),
                    'title_home'        => sanitize_text_field( $_POST['title_home'] ?? '' ),
                    'desc_home'         => sanitize_textarea_field( $_POST['desc_home'] ?? '' ),
                    'default_og_image'  => esc_url_raw( $_POST['default_og_image'] ?? '' ),
                    'twitter_card'      => sanitize_key( $_POST['twitter_card'] ?? 'summary_large_image' ),
                    'twitter_site'      => sanitize_text_field( $_POST['twitter_site'] ?? '' ),
                    'auto_desc_length'  => (int) ( $_POST['auto_desc_length'] ?? 160 ),
                    'breadcrumb_home'   => sanitize_text_field( $_POST['breadcrumb_home'] ?? 'خانه' ),
                ];
                $checkboxes = ['og_enabled','twitter_enabled','noindex_archives','noindex_tags','noindex_404',
                               'canonical_enabled','auto_description','breadcrumbs','breadcrumbs_auto'];
                foreach ( $checkboxes as $k ) {
                    $data[$k] = isset($_POST[$k]) ? 1 : 0;
                }
                break;

            case 'wss_social':
                foreach ( ['facebook','twitter','instagram','linkedin','youtube','telegram','pinterest','whatsapp'] as $k ) {
                    $data[$k] = esc_url_raw( $_POST[$k] ?? '' );
                }
                break;

            case 'wss_webmaster':
                $data = [
                    'google_verify'    => sanitize_text_field( $_POST['google_verify']    ?? '' ),
                    'bing_verify'      => sanitize_text_field( $_POST['bing_verify']      ?? '' ),
                    'yandex_verify'    => sanitize_text_field( $_POST['yandex_verify']    ?? '' ),
                    'google_analytics' => sanitize_text_field( $_POST['google_analytics'] ?? '' ),
                    'gtm_id'           => sanitize_text_field( $_POST['gtm_id']           ?? '' ),
                ];
                break;

            case 'wss_schema':
                $data = [
                    'type'    => sanitize_text_field( $_POST['schema_type'] ?? 'Organization' ),
                    'name'    => sanitize_text_field( $_POST['schema_name'] ?? '' ),
                    'logo'    => esc_url_raw( $_POST['schema_logo'] ?? '' ),
                    'phone'   => sanitize_text_field( $_POST['schema_phone'] ?? '' ),
                    'email'   => sanitize_email( $_POST['schema_email'] ?? '' ),
                    'address' => sanitize_text_field( $_POST['schema_address'] ?? '' ),
                    'city'    => sanitize_text_field( $_POST['schema_city'] ?? '' ),
                    'country' => sanitize_text_field( $_POST['schema_country'] ?? 'IR' ),
                    'social_profiles' => isset($_POST['social_profiles']) ? 1 : 0,
                ];
                break;

            case 'wss_cache':
                $data = [
                    'enabled'          => isset($_POST['enabled'])          ? 1 : 0,
                    'gzip_cache'       => isset($_POST['gzip_cache'])       ? 1 : 0,
                    'cache_logged_in'  => isset($_POST['cache_logged_in'])  ? 1 : 0,
                    'ttl'              => max(60, (int)($_POST['ttl']       ?? 3600)),
                    'exclude_urls'     => sanitize_textarea_field($_POST['exclude_urls'] ?? ''),
                ];
                break;

            case 'wss_webp':
                $data = [
                    'enabled'   => isset($_POST['enabled'])   ? 1 : 0,
                    'serve_webp'=> isset($_POST['serve_webp'])? 1 : 0,
                    'quality'   => min(100, max(1, (int)($_POST['quality'] ?? 82))),
                ];
                break;

            case 'wss_local_fonts':
                $data = [
                    'enabled' => isset($_POST['enabled']) ? 1 : 0,
                ];
                break;

            case 'wss_critical_css':
                $data = [
                    'enabled'          => isset($_POST['enabled']) ? 1 : 0,
                    'critical_handles' => sanitize_text_field($_POST['critical_handles'] ?? ''),
                ];
                break;
        }

        update_option( $group, $data );

        // Flush sitemap caches
        foreach ( ['index','posts','pages','terms','images','news','video'] as $t ) {
            delete_transient('wss_sitemap_' . $t);
        }

        $this->redirect_back('تنظیمات با موفقیت ذخیره شد.');
    }

    // ─── Redirect handlers ────────────────────────────────────────────────────

    public function handle_add_redirect() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_add_redirect');

        $source = sanitize_text_field( $_POST['source_url'] ?? '' );
        $target = sanitize_text_field( $_POST['target_url'] ?? '' );
        $type   = (int) ( $_POST['redirect_type'] ?? 301 );

        if ( $source && $target ) {
            WSS_Redirects::add( $source, $target, $type );
            $this->redirect_back('ریدایرکت با موفقیت اضافه شد.');
        } else {
            $this->redirect_back('لطفاً URL مبدا و مقصد را وارد کنید.', 'error');
        }
    }

    public function handle_delete_redirect() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_delete_redirect');
        WSS_Redirects::delete( (int)($_GET['id'] ?? 0) );
        $this->redirect_back('ریدایرکت حذف شد.');
    }

    public function handle_toggle_redirect() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_toggle_redirect');
        WSS_Redirects::toggle( (int)($_GET['id'] ?? 0) );
        $this->redirect_back('وضعیت ریدایرکت تغییر کرد.');
    }

    public function handle_import_redirects() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_import_redirects');

        $csv = sanitize_textarea_field( $_POST['csv_content'] ?? '' );
        if ( $csv ) {
            $count = WSS_Redirects::import_csv($csv);
            $this->redirect_back("$count ریدایرکت وارد شد.");
        } else {
            $this->redirect_back('محتوای CSV خالی است.', 'error');
        }
    }

    public function handle_db_cleanup() {
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');
        check_admin_referer('wss_run_cleanup');
        WSS_Speed_Optimizer::instance()->cleanup_database();
        $this->redirect_back('پاکسازی پایگاه داده با موفقیت انجام شد.');
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public function ajax_analyze() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if ( ! current_user_can('edit_posts') ) wp_die('Access denied');

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error('Invalid post ID');

        $result = WSS_Analyzer::analyze_post($post_id);
        wp_send_json_success($result);
    }

    public function ajax_enable_recommended() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');

        // Speed settings
        $speed = array_merge( (array) get_option('wss_speed', []), [
            'minify_html' => 1, 'minify_css' => 1, 'minify_js' => 1,
            'defer_js' => 1, 'async_js' => 0,
            'lazy_images' => 1, 'lazy_iframes' => 1,
            'remove_query_str' => 1, 'browser_cache' => 1, 'gzip' => 1,
            'disable_emoji' => 1, 'disable_embeds' => 1,
            'disable_dashicons' => 1, 'remove_jquery_migrate' => 1,
            'google_fonts_async' => 1,
        ]);
        update_option('wss_speed', $speed);

        // Cache
        $cache = (array) get_option('wss_cache', []);
        $cache['enabled'] = 1;
        update_option('wss_cache', $cache);

        // WebP
        $webp = (array) get_option('wss_webp', []);
        $webp['enabled'] = 1;
        $webp['serve_webp'] = 1;
        update_option('wss_webp', $webp);

        // SEO recommended
        $seo = array_merge( (array) get_option('wss_seo', []), [
            'og_enabled' => 1, 'twitter_enabled' => 1,
            'canonical_enabled' => 1, 'auto_description' => 1, 'breadcrumbs' => 1,
        ]);
        update_option('wss_seo', $seo);

        wp_send_json_success('تنظیمات پیشنهادی با موفقیت اعمال شدند.');
    }

    public function ajax_health_check() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');

        if ( ! class_exists('WSS_Health_Check') ) {
            require_once WSS_PLUGIN_DIR . 'includes/class-health-check.php';
        }
        $results = WSS_Health_Check::instance()->run_all();
        wp_send_json_success( $results );
    }

    public function ajax_flush_sitemap() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_die('Access denied');

        foreach ( ['index','posts','pages','terms','images','news','video'] as $t ) {
            delete_transient('wss_sitemap_' . $t);
        }
        flush_rewrite_rules();

        wp_send_json_success('نقشه سایت بازسازی شد.');
    }

    // ─── Pages ────────────────────────────────────────────────────────────────

    public function page_dashboard() {
        include WSS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_speed() {
        include WSS_PLUGIN_DIR . 'admin/views/speed.php';
    }

    public function page_seo() {
        include WSS_PLUGIN_DIR . 'admin/views/seo.php';
    }

    public function page_schema() {
        include WSS_PLUGIN_DIR . 'admin/views/schema.php';
    }

    public function page_sitemap() {
        include WSS_PLUGIN_DIR . 'admin/views/sitemap.php';
    }

    public function page_redirects() {
        include WSS_PLUGIN_DIR . 'admin/views/redirects.php';
    }

    public function page_tools() {
        include WSS_PLUGIN_DIR . 'admin/views/tools.php';
    }

    public function page_cache() {
        include WSS_PLUGIN_DIR . 'admin/views/cache.php';
    }

    public function page_webp() {
        include WSS_PLUGIN_DIR . 'admin/views/webp.php';
    }

    public function page_broken_links() {
        include WSS_PLUGIN_DIR . 'admin/views/broken-links.php';
    }

    public function page_rank_tracker() {
        include WSS_PLUGIN_DIR . 'admin/views/rank-tracker.php';
    }

    public function page_vitals() {
        include WSS_PLUGIN_DIR . 'admin/views/vitals.php';
    }

    public function page_advanced_seo() {
        include WSS_PLUGIN_DIR . 'admin/views/advanced-seo.php';
    }

    public function page_local_fonts() {
        include WSS_PLUGIN_DIR . 'admin/views/local-fonts.php';
    }

    public function page_critical_css() {
        include WSS_PLUGIN_DIR . 'admin/views/critical-css.php';
    }

    public function page_health() {
        include WSS_PLUGIN_DIR . 'admin/views/health.php';
    }
}
