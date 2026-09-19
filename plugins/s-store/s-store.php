<?php
/**
 * Plugin Name: فروشگاه افزونه اس
 * Plugin URI: https://github.com/sahandse/S-Store
 * Description: فروشگاه و بروزرسان مرکزی افزونه‌های اختصاصی سهند رضوان با نصب، بروزرسانی، جزئیات افزونه و منوی یکپارچه.
 * Version: 1.9.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sahand Rezvan
 * Author URI: https://t.me/sahandse
 * License: GPLv2 or later
 * Text Domain: s-store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'S_STORE_VERSION', '1.9.0' );
define( 'S_STORE_FILE', __FILE__ );
define( 'S_STORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'S_STORE_URL', plugin_dir_url( __FILE__ ) );
define( 'S_STORE_MANIFEST_URL', 'https://raw.githubusercontent.com/sahandse/S-Store/main/manifest/plugins.json' );

function s_store_get_manifest( $force = false ) {
    $key = 's_store_manifest_v1';
    if ( ! $force ) {
        $cached = get_site_transient( $key );
        if ( is_array( $cached ) ) return $cached;
    }

    $res = wp_remote_get( S_STORE_MANIFEST_URL, [
        'timeout'    => 12,
        'headers'    => [ 'Accept' => 'application/json' ],
        'user-agent' => 'S-Store/' . S_STORE_VERSION . '; ' . home_url( '/' ),
    ] );

    if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
        return is_array( get_site_transient( $key ) ) ? get_site_transient( $key ) : [];
    }

    $data = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( ! is_array( $data ) || empty( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) return [];

    set_site_transient( $key, $data, 15 * MINUTE_IN_SECONDS );
    return $data;
}

function s_store_manifest_plugins() {
    $m = s_store_get_manifest();
    return isset( $m['plugins'] ) && is_array( $m['plugins'] ) ? $m['plugins'] : [];
}

function s_store_plugin_by_slug( $slug ) {
    foreach ( s_store_manifest_plugins() as $p ) {
        if ( isset( $p['slug'] ) && $p['slug'] === $slug ) return $p;
    }
    return null;
}

function s_store_plugin_category( $p ) {
    if ( ! empty( $p['category'] ) ) return sanitize_key( $p['category'] );
    $slug = $p['slug'] ?? '';
    if ( false !== strpos( $slug, 'seo' ) ) return 'seo';
    if ( false !== strpos( $slug, 'sms' ) || false !== strpos( $slug, 'bale' ) ) return 'marketing';
    if ( false !== strpos( $slug, 'woo' ) || false !== strpos( $slug, 'price' ) || false !== strpos( $slug, 'cash' ) || false !== strpos( $slug, 'card' ) ) return 'commerce';
    if ( false !== strpos( $slug, 'delivery' ) || false !== strpos( $slug, 'appointment' ) ) return 'tools';
    if ( false !== strpos( $slug, 'media' ) || false !== strpos( $slug, 'video' ) ) return 'media';
    return 'tools';
}

function s_store_category_label( $category ) {
    $labels = [
        'all'       => 'همه دسته‌ها',
        'commerce'  => 'فروش و ووکامرس',
        'marketing' => 'مارکتینگ و پیامک',
        'seo'       => 'سئو و بهینه‌سازی',
        'media'     => 'رسانه و محتوا',
        'tools'     => 'ابزارهای سایت',
    ];
    return $labels[ $category ] ?? 'ابزارهای سایت';
}

function s_store_icon_for_slug( $slug ) {
    $map = [
        'appointment-booking-pro'       => 'calendar-alt',
        'cardyar'                       => 'money-alt',
        'cash-installment-price'        => 'tag',
        'delivery-calendar'             => 'calendar',
        'domarhaleii'                   => 'shield-alt',
        'gheymatbar'                    => 'chart-line',
        'login-sms-bale'                => 'smartphone',
        'lucky-wheel-pro'               => 'tickets-alt',
        'media-optimizer'               => 'format-image',
        'price-compare-assistant'       => 'search',
        'product-video-reels'           => 'video-alt3',
        'seo'                           => 'chart-area',
        'smart-delivery-for-woocommerce'=> 'location-alt',
        'support-button'                => 'sos',
        'support-button-'               => 'sos',
        'wc-market-sync'                => 'update',
        'woo-cashback-wallet'           => 'money',
        'woo-mobile-app-shell'          => 'smartphone',
        'woocommerce-sms-orders'        => 'email-alt',
        'smart-seo-ai-pro'              => 'superhero-alt',
    ];
    return $map[ $slug ] ?? 'admin-plugins';
}

function s_store_plugin_icon_url( $slug ) {
    $slug = sanitize_key( $slug );
    $path = S_STORE_DIR . 'assets/icons/' . $slug . '.svg';
    if ( file_exists( $path ) ) {
        return S_STORE_URL . 'assets/icons/' . $slug . '.svg';
    }
    return '';
}

function s_store_render_plugin_icon( $slug, $extra_class = '' ) {
    $url = s_store_plugin_icon_url( $slug );
    $class = 's-store-plugin-icon ' . trim( $extra_class );
    if ( $url ) {
        echo '<span class="' . esc_attr( $class . ' has-image' ) . '"><img src="' . esc_url( $url ) . '" alt="" loading="lazy"></span>';
        return;
    }
    echo '<span class="' . esc_attr( $class . ' tone-' . ( abs( crc32( $slug ) ) % 6 ) ) . '"><span class="dashicons dashicons-' . esc_attr( s_store_icon_for_slug( $slug ) ) . '"></span></span>';
}

function s_store_upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
    if ( ! is_a( $upgrader, 'Plugin_Upgrader' ) ) return $source;

    $slug = '';
    if ( ! empty( $GLOBALS['s_store_install_slug'] ) ) {
        $slug = sanitize_key( $GLOBALS['s_store_install_slug'] );
    } elseif ( ! empty( $hook_extra['plugin'] ) ) {
        $folder = dirname( $hook_extra['plugin'] );
        $slug = ( '.' === $folder ) ? basename( $hook_extra['plugin'], '.php' ) : $folder;
    }

    if ( ! $slug ) return $source;
    $p = s_store_plugin_by_slug( $slug );
    if ( ! $p ) return $source;

    $nested = $source;
    if ( ! empty( $p['source_subdir'] ) ) {
        $nested = trailingslashit( $source ) . trim( $p['source_subdir'], '/' );
        if ( ! is_dir( $nested ) ) {
            return new WP_Error(
                's_store_source_missing',
                sprintf( 'مسیر افزونه %s داخل بسته دانلودی پیدا نشد.', esc_html( $p['name'] ?? $slug ) )
            );
        }
    }

    global $wp_filesystem;
    $target = trailingslashit( $remote_source ) . $slug;

    if ( trailingslashit( $nested ) === trailingslashit( $target ) ) return $nested;

    if ( $wp_filesystem && $wp_filesystem->exists( $target ) ) {
        $wp_filesystem->delete( $target, true );
    }

    if ( ! $wp_filesystem || ! $wp_filesystem->move( $nested, $target, true ) ) {
        return new WP_Error( 's_store_source_move_failed', 'آماده‌سازی بسته افزونه برای نصب ناموفق بود.' );
    }

    return trailingslashit( $target );
}
add_filter( 'upgrader_source_selection', 's_store_upgrader_source_selection', 10, 4 );

add_filter( 'pre_set_site_transient_update_plugins', function( $transient ) {
    if ( ! is_object( $transient ) || empty( $transient->checked ) ) return $transient;
    $manifest = s_store_manifest_plugins();
    if ( ! $manifest ) return $transient;

    foreach ( $transient->checked as $plugin_file => $installed_version ) {
        $folder = dirname( $plugin_file );
        if ( '.' === $folder ) $folder = basename( $plugin_file, '.php' );

        foreach ( $manifest as $p ) {
            if ( empty( $p['slug'] ) || $p['slug'] !== $folder ) continue;
            if ( empty( $p['version'] ) || version_compare( $installed_version, $p['version'], '>=' ) ) continue;
            if ( empty( $p['download_url'] ) ) continue;

            $obj = new stdClass();
            $obj->slug         = $p['slug'];
            $obj->plugin       = $plugin_file;
            $obj->new_version  = $p['version'];
            $obj->url          = $p['homepage'] ?? $p['repo'] ?? '';
            $obj->package      = $p['download_url'];
            $obj->tested       = $p['tested'] ?? '';
            $obj->requires_php = $p['requires_php'] ?? '';
            $transient->response[ $plugin_file ] = $obj;
        }
    }

    return $transient;
} );

add_filter( 'plugins_api', function( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || empty( $args->slug ) ) return $result;
    $p = s_store_plugin_by_slug( sanitize_key( $args->slug ) );
    if ( ! $p ) return $result;

    return (object) [
        'name'          => $p['name'] ?? $p['slug'],
        'slug'          => $p['slug'],
        'version'       => $p['version'] ?? '',
        'author'        => '<a href="https://t.me/sahandse">Sahand Rezvan</a>',
        'homepage'      => $p['homepage'] ?? $p['repo'] ?? '',
        'requires'      => $p['requires'] ?? '6.0',
        'tested'        => $p['tested'] ?? '',
        'requires_php'  => $p['requires_php'] ?? '7.4',
        'download_link' => $p['download_url'] ?? '',
        'sections'      => [
            'description' => wp_kses_post( $p['description'] ?? '' ),
            'changelog'   => wp_kses_post( $p['changelog'] ?? '' ),
        ],
    ];
}, 10, 3 );

function s_store_managed_page_slugs() {
    $slugs = [
        's-store','s-store-all','s-store-installed','s-store-updates','s-store-health','s-store-settings','s-store-about',
        'appointment-booking-pro','cardyar','cash-installment-price','delivery-calendar','gheymatbar',
        'login-sms-bale','lucky-wheel-pro','media-optimizer','price-compare-assistant','product-video-reels',
        'wc-market-sync','woo-cashback-wallet','woo-mobile-app-shell','woocommerce-sms-orders',
        'smart-delivery-for-woocommerce','support-button','support-button-conversations','support-button-settings',
        'do-marhalei','wss-dashboard','wss-speed','wss-seo','wss-schema','wss-sitemap','wss-redirects',
        'wss-cache','wss-webp','wss-broken-links','wss-rank-tracker','wss-vitals','wss-advanced-seo',
        'wss-local-fonts','wss-critical-css','wss-health','wss-tools',
        'smart-seo-ai-dashboard','smart-seo-ai-studio','smart-seo-ai-woocommerce',
        'smart-seo-ai-security-speed','smart-seo-ai-autofix','smart-seo-ai-reports','smart-seo-ai-settings'
    ];

    foreach ( s_store_manifest_plugins() as $plugin ) {
        if ( ! empty( $plugin['slug'] ) ) {
            $slugs[] = sanitize_key( $plugin['slug'] );
        }
    }

    return array_values( array_unique( array_filter( $slugs ) ) );
}

function s_store_is_managed_admin_page() {
    if ( ! empty( $_GET['post_type'] ) ) {
        $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
        if ( in_array( $post_type, [ 'cardyar_payment' ], true ) ) return true;
    }

    if ( empty( $_GET['page'] ) ) return false;
    $page = sanitize_key( wp_unslash( $_GET['page'] ) );
    if ( in_array( $page, s_store_managed_page_slugs(), true ) ) return true;

    return (
        0 === strpos( $page, 'wss-' ) ||
        0 === strpos( $page, 'smart-seo-ai-' ) ||
        0 === strpos( $page, 'support-button' ) ||
        0 === strpos( $page, 's-store' )
    );
}

add_filter( 'admin_body_class', function( $classes ) {
    if ( s_store_is_managed_admin_page() ) {
        $classes .= ' s-store-managed-page';
    }
    if ( ! empty( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 's-store' ) ) {
        $classes .= ' s-store-admin-page';
    }
    return $classes;
} );

function s_store_current_managed_context() {
    $page = ! empty( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $map = [
        'appointment-booking-pro' => [ 'slug' => 'appointment-booking-pro', 'title' => 'نوبت‌دهی حرفه‌ای' ],
        'cardyar' => [ 'slug' => 'cardyar', 'title' => 'کارت‌یار' ],
        'cash-installment-price' => [ 'slug' => 'cash-installment-price', 'title' => 'قیمت نقدی و اقساطی' ],
        'delivery-calendar' => [ 'slug' => 'delivery-calendar', 'title' => 'تقویم ارسال' ],
        'gheymatbar' => [ 'slug' => 'gheymatbar', 'title' => 'قیمت‌بار' ],
        'login-sms-bale' => [ 'slug' => 'login-sms-bale', 'title' => 'ورود با پیامک و بله' ],
        'lucky-wheel-pro' => [ 'slug' => 'lucky-wheel-pro', 'title' => 'گردونه شانس' ],
        'media-optimizer' => [ 'slug' => 'media-optimizer', 'title' => 'بهینه‌ساز رسانه' ],
        'price-compare-assistant' => [ 'slug' => 'price-compare-assistant', 'title' => 'دستیار مقایسه قیمت' ],
        'product-video-reels' => [ 'slug' => 'product-video-reels', 'title' => 'ویدئوی محصول و ریلز' ],
        'wc-market-sync' => [ 'slug' => 'wc-market-sync', 'title' => 'همگام‌سازی بازار' ],
        'woo-cashback-wallet' => [ 'slug' => 'woo-cashback-wallet', 'title' => 'کش‌بک و کیف پول' ],
        'woo-mobile-app-shell' => [ 'slug' => 'woo-mobile-app-shell', 'title' => 'اپ موبایل ووکامرس' ],
        'woocommerce-sms-orders' => [ 'slug' => 'woocommerce-sms-orders', 'title' => 'پیامک سفارشات' ],
        'smart-delivery-for-woocommerce' => [ 'slug' => 'smart-delivery-for-woocommerce', 'title' => 'ارسال هوشمند ووکامرس' ],
        'support-button' => [ 'slug' => 'support-button', 'title' => 'پشتیبانی' ],
        'support-button-conversations' => [ 'slug' => 'support-button', 'title' => 'گفتگوهای پشتیبانی' ],
        'support-button-settings' => [ 'slug' => 'support-button', 'title' => 'تنظیمات پشتیبانی' ],
        'do-marhalei' => [ 'slug' => 'domarhaleii', 'title' => 'تأیید هویت دو مرحله‌ای' ],
        'wss-dashboard' => [ 'slug' => 'seo', 'title' => 'سئو سهند' ],
        'smart-seo-ai-dashboard' => [ 'slug' => 'smart-seo-ai-pro', 'title' => 'سئو هوشمند AI' ],
    ];

    if ( 0 === strpos( $page, 'wss-' ) ) {
        return [ 'slug' => 'seo', 'title' => 'سئو سهند' ];
    }
    if ( 0 === strpos( $page, 'smart-seo-ai-' ) ) {
        return [ 'slug' => 'smart-seo-ai-pro', 'title' => 'سئو هوشمند AI' ];
    }
    if ( isset( $map[ $page ] ) ) return $map[ $page ];

    if ( ! empty( $_GET['post_type'] ) && 'cardyar_payment' === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) {
        return [ 'slug' => 'cardyar', 'title' => 'پرداخت‌های کارت‌یار' ];
    }

    return [ 'slug' => '', 'title' => '' ];
}

add_action( 'admin_enqueue_scripts', function() {
    if ( ! s_store_is_managed_admin_page() ) return;

    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 's-store-design-system', S_STORE_URL . 'assets/design-system.css', [], S_STORE_VERSION );

    $page = ! empty( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 0 === strpos( $page, 's-store' ) ) {
        wp_enqueue_style( 's-store-admin', S_STORE_URL . 'assets/admin.css', [ 's-store-design-system' ], S_STORE_VERSION );
        wp_enqueue_script( 's-store-admin', S_STORE_URL . 'assets/admin.js', [], S_STORE_VERSION, true );
        return;
    }

    wp_enqueue_script( 's-store-managed-ui', S_STORE_URL . 'assets/managed-ui.js', [], S_STORE_VERSION, true );
    $context = s_store_current_managed_context();
    $plugin  = ! empty( $context['slug'] ) ? s_store_plugin_by_slug( $context['slug'] ) : null;
    wp_localize_script( 's-store-managed-ui', 'SStoreManagedUI', [
        'slug'      => $context['slug'] ?? '',
        'title'     => $context['title'] ?? '',
        'version'   => is_array( $plugin ) ? ( $plugin['version'] ?? '' ) : '',
        'storeUrl'  => admin_url( 'admin.php?page=s-store' ),
        'detailUrl' => ! empty( $context['slug'] )
            ? add_query_arg( [ 'page' => 's-store', 'view' => 'plugin', 'slug' => $context['slug'] ], admin_url( 'admin.php' ) )
            : '',
    ] );
} );

add_action( 'admin_notices', function() {
    if ( ! s_store_is_managed_admin_page() ) return;
    if ( empty( $_GET['page'] ) ) return;
    $page = sanitize_key( wp_unslash( $_GET['page'] ) );
    if ( 0 === strpos( $page, 's-store' ) ) return;

    echo '<div class="s-store-managed-strip">';
    echo '<strong><span class="dashicons dashicons-store"></span> مدیریت‌شده توسط S Store</strong>';
    echo '<span>طراحی و بروزرسانی یکپارچه افزونه‌های Sahand Rezvan</span>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=s-store' ) ) . '">بازگشت به S Store</a>';
    echo '</div>';
} );

function s_store_register_submenu( $slug, $menu_title, $callback, $capability = 'manage_options', $page_title = '' ) {
    $page_title = $page_title ?: $menu_title;
    return add_submenu_page( 's-store', $page_title, $menu_title, $capability, $slug, $callback );
}

add_action( 'admin_init', function() {
    register_setting( 's_store_settings', 's_store_auto_updates', [
        'type'              => 'boolean',
        'sanitize_callback' => function( $v ) { return (bool) $v; },
        'default'           => false,
    ] );
} );

add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( ! get_option( 's_store_auto_updates', false ) || empty( $item->plugin ) ) return $update;
    $folder = dirname( $item->plugin );
    if ( '.' === $folder ) $folder = basename( $item->plugin, '.php' );
    foreach ( s_store_manifest_plugins() as $p ) {
        if ( ! empty( $p['slug'] ) && $p['slug'] === $folder ) return true;
    }
    return $update;
}, 10, 2 );

function s_store_admin_shell_start( $title, $description = '' ) {
    echo '<div class="wrap s-store-wrap" dir="rtl">';
    echo '<div class="s-store-topbar">';
    echo '<div class="s-store-brand-mini"><span class="s-store-brand-mark">S</span><strong>S Store</strong><span>v' . esc_html( S_STORE_VERSION ) . '</span></div>';
    echo '<div class="s-store-top-actions"><a href="' . esc_url( admin_url( 'admin.php?page=s-store-updates' ) ) . '"><span class="dashicons dashicons-update"></span> بروزرسانی‌ها</a><a href="' . esc_url( admin_url( 'admin.php?page=s-store-settings' ) ) . '"><span class="dashicons dashicons-admin-generic"></span> تنظیمات</a></div>';
    echo '</div>';
    echo '<div class="s-store-head"><div><h1>' . esc_html( $title ) . '</h1>';
    if ( $description ) echo '<p>' . esc_html( $description ) . '</p>';
    echo '</div><div class="s-store-head-badge"><span class="dashicons dashicons-store"></span><span>مرکز افزونه‌های شما</span></div></div>';
}

function s_store_admin_shell_end() {
    echo '<div class="s-store-footer"><strong>S Store</strong><span>قدرت، امنیت و سادگی برای وردپرس فارسی</span><a href="https://t.me/sahandse" target="_blank" rel="noopener">پشتیبانی</a></div></div>';
}

add_action( 'admin_menu', function() {
    add_menu_page( 'S Store', 'S Store', 'manage_options', 's-store', 's_store_dashboard_page', 'dashicons-store', 58 );
    add_submenu_page( 's-store', 'فروشگاه افزونه‌ها', 'خانه', 'manage_options', 's-store', 's_store_dashboard_page' );
    add_submenu_page( 's-store', 'همه افزونه‌ها', 'همه افزونه‌ها', 'manage_options', 's-store-all', 's_store_all_page' );
    add_submenu_page( 's-store', 'افزونه‌های نصب‌شده', 'نصب‌شده‌ها', 'manage_options', 's-store-installed', 's_store_installed_page' );
    add_submenu_page( 's-store', 'بروزرسانی‌ها', 'بروزرسانی‌ها', 'update_plugins', 's-store-updates', 's_store_updates_page' );
    add_submenu_page( 's-store', 'مرکز سلامت', 'مرکز سلامت', 'manage_options', 's-store-health', 's_store_health_center_page' );
    add_submenu_page( 's-store', 'تنظیمات', 'تنظیمات', 'manage_options', 's-store-settings', 's_store_settings_page' );
    add_submenu_page( 's-store', 'درباره', 'درباره', 'manage_options', 's-store-about', 's_store_about_page' );
    do_action( 's_store_admin_menu' );
}, 20 );

function s_store_installed_map() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $all = get_plugins();
    $map = [];

    foreach ( $all as $file => $data ) {
        $folder = dirname( $file );
        if ( '.' === $folder ) $folder = basename( $file, '.php' );
        $map[ $folder ] = [
            'file'    => $file,
            'name'    => $data['Name'] ?? $folder,
            'version' => $data['Version'] ?? '',
            'active'  => is_plugin_active( $file ),
        ];
    }

    return $map;
}

function s_store_stats( $plugins, $installed ) {
    $stats = [ 'total' => count( $plugins ), 'installed' => 0, 'active' => 0, 'updates' => 0 ];
    foreach ( $plugins as $p ) {
        $slug = $p['slug'] ?? '';
        if ( ! $slug || empty( $installed[ $slug ] ) ) continue;
        $stats['installed']++;
        if ( $installed[ $slug ]['active'] ) $stats['active']++;
        if ( ! empty( $p['version'] ) && version_compare( $installed[ $slug ]['version'], $p['version'], '<' ) ) $stats['updates']++;
    }
    return $stats;
}

function s_store_health_status_rank( $status ) {
    $map = [ 'ok' => 0, 'info' => 1, 'off' => 1, 'warn' => 2, 'error' => 3 ];
    return $map[ $status ] ?? 1;
}

function s_store_health_add_check( &$checks, $status, $title, $detail, $group = 'general' ) {
    $checks[] = [
        'status' => $status,
        'title'  => $title,
        'detail' => $detail,
        'group'  => $group,
    ];
}

function s_store_health_recent_error( $slug, $days = 7 ) {
    $logs = s_store_activity_for_plugin( $slug, 30 );
    $min  = current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS );
    foreach ( $logs as $row ) {
        if ( ( $row['status'] ?? '' ) !== 'error' ) continue;
        if ( ! empty( $row['time'] ) && (int) $row['time'] >= $min ) return $row;
    }
    return null;
}

function s_store_health_check_plugin( $p, $installed ) {
    $slug   = sanitize_key( $p['slug'] ?? '' );
    $local  = $installed[ $slug ] ?? null;
    $checks = [];

    if ( ! $local ) {
        s_store_health_add_check( $checks, 'off', 'وضعیت نصب', 'این افزونه روی سایت نصب نشده است.', 'install' );
        return [
            'slug'    => $slug,
            'name'    => $p['name'] ?? $slug,
            'status'  => 'off',
            'checks'  => $checks,
            'local'   => null,
            'plugin'  => $p,
        ];
    }

    if ( ! empty( $local['active'] ) ) {
        s_store_health_add_check( $checks, 'ok', 'وضعیت افزونه', 'افزونه فعال است.', 'install' );
    } else {
        s_store_health_add_check( $checks, 'warn', 'وضعیت افزونه', 'افزونه نصب شده اما غیرفعال است.', 'install' );
    }

    if ( ! empty( $p['version'] ) && version_compare( $local['version'], $p['version'], '<' ) ) {
        s_store_health_add_check( $checks, 'warn', 'نسخه قدیمی', 'نسخه ' . $local['version'] . ' نصب است و نسخه ' . $p['version'] . ' موجود است.', 'version' );
    } else {
        s_store_health_add_check( $checks, 'ok', 'نسخه افزونه', 'نسخه نصب‌شده بروز است.', 'version' );
    }

    $required_php = $p['requires_php'] ?? '';
    if ( $required_php ) {
        if ( version_compare( PHP_VERSION, $required_php, '<' ) ) {
            s_store_health_add_check( $checks, 'error', 'نسخه PHP', 'PHP ' . PHP_VERSION . ' نصب است؛ حداقل ' . $required_php . ' لازم است.', 'compatibility' );
        } else {
            s_store_health_add_check( $checks, 'ok', 'نسخه PHP', 'PHP ' . PHP_VERSION . ' سازگار است.', 'compatibility' );
        }
    }

    $required_wp = $p['requires'] ?? '';
    if ( $required_wp ) {
        $wp_version = get_bloginfo( 'version' );
        if ( version_compare( $wp_version, $required_wp, '<' ) ) {
            s_store_health_add_check( $checks, 'error', 'نسخه WordPress', 'WordPress ' . $wp_version . ' نصب است؛ حداقل ' . $required_wp . ' لازم است.', 'compatibility' );
        } else {
            s_store_health_add_check( $checks, 'ok', 'نسخه WordPress', 'WordPress ' . $wp_version . ' سازگار است.', 'compatibility' );
        }
    }

    $woocommerce_slugs = [
        'cash-installment-price','delivery-calendar','price-compare-assistant','product-video-reels',
        'wc-market-sync','woo-cashback-wallet','woo-mobile-app-shell','woocommerce-sms-orders',
        'smart-delivery-for-woocommerce'
    ];
    if ( in_array( $slug, $woocommerce_slugs, true ) && ! empty( $local['active'] ) ) {
        if ( class_exists( 'WooCommerce' ) ) {
            s_store_health_add_check( $checks, 'ok', 'WooCommerce', 'وابستگی WooCommerce فعال است.', 'dependency' );
        } else {
            s_store_health_add_check( $checks, 'error', 'WooCommerce', 'این افزونه فعال است اما WooCommerce در دسترس نیست.', 'dependency' );
        }
    }

    $presence = s_store_option_presence( $slug );
    if ( $presence['groups'] > 0 ) {
        s_store_health_add_check( $checks, 'ok', 'تنظیمات افزونه', sprintf( '%d گروه تنظیمات و %d مقدار قابل تشخیص ذخیره شده است.', $presence['groups'], $presence['items'] ), 'settings' );
    } else {
        s_store_health_add_check( $checks, 'info', 'تنظیمات افزونه', 'هنوز تنظیمات قابل تشخیص برای این افزونه ذخیره نشده است.', 'settings' );
    }

    if ( 'login-sms-bale' === $slug ) {
        $s = (array) get_option( 'lsb_settings', [] );
        if ( ( $s['enabled_sms'] ?? 'no' ) === 'yes' ) {
            if ( empty( $s['sms_provider'] ) || 'none' === $s['sms_provider'] ) {
                s_store_health_add_check( $checks, 'error', 'ورود پیامکی', 'ارسال SMS فعال است اما Provider انتخاب نشده است.', 'service' );
            } else {
                s_store_health_add_check( $checks, 'ok', 'Provider پیامک', 'Provider انتخاب‌شده: ' . sanitize_text_field( $s['sms_provider'] ), 'service' );
            }
        }
        if ( ( $s['enabled_bale'] ?? 'no' ) === 'yes' ) {
            if ( empty( $s['bale_bot_token'] ) ) {
                s_store_health_add_check( $checks, 'error', 'بله', 'ورود با بله فعال است اما توکن ربات وارد نشده است.', 'service' );
            } else {
                s_store_health_add_check( $checks, 'ok', 'بله', 'توکن ربات بله ثبت شده است.', 'service' );
            }
        }
    }

    if ( 'woocommerce-sms-orders' === $slug ) {
        $s = (array) get_option( 'wso_settings', [] );
        $sending = ( $s['send_to_customer'] ?? 'no' ) === 'yes' || ( $s['send_to_admin'] ?? 'no' ) === 'yes';
        if ( $sending ) {
            if ( empty( $s['provider'] ) || 'none' === $s['provider'] ) {
                s_store_health_add_check( $checks, 'error', 'پیامک سفارشات', 'ارسال پیامک فعال است اما Provider انتخاب نشده است.', 'service' );
            } else {
                $has_credentials = ! empty( $s['api_key'] ) || ! empty( $s['username'] ) || ! empty( $s['password'] );
                s_store_health_add_check(
                    $checks,
                    $has_credentials ? 'ok' : 'warn',
                    'تنظیمات پنل پیامک',
                    $has_credentials ? 'اطلاعات اتصال برای Provider ثبت شده است.' : 'Provider انتخاب شده اما Credential قابل تشخیص وارد نشده است.',
                    'service'
                );
            }
        }
    }

    if ( 'wc-market-sync' === $slug ) {
        $s = (array) get_option( 'wcms_settings', [] );
        if ( ( $s['enabled_basalam'] ?? 'no' ) === 'yes' ) {
            s_store_health_add_check( $checks, empty( $s['basalam_token'] ) ? 'error' : 'ok', 'باسلام', empty( $s['basalam_token'] ) ? 'همگام‌سازی باسلام فعال است اما Token ثبت نشده است.' : 'Token باسلام ثبت شده است.', 'service' );
        }
        if ( ( $s['enabled_torob'] ?? 'no' ) === 'yes' ) {
            s_store_health_add_check( $checks, empty( $s['torob_key'] ) ? 'error' : 'ok', 'ترب', empty( $s['torob_key'] ) ? 'همگام‌سازی ترب فعال است اما Key ثبت نشده است.' : 'Key ترب ثبت شده است.', 'service' );
        }
    }

    if ( 'support-button' === $slug ) {
        $s = (array) get_option( 'sb_settings', [] );
        if ( ! empty( $s['bale_enabled'] ) ) {
            s_store_health_add_check( $checks, empty( $s['bale_bot_token'] ) ? 'error' : 'ok', 'اتصال بله', empty( $s['bale_bot_token'] ) ? 'اتصال بله فعال است اما Bot Token ثبت نشده است.' : 'Bot Token بله ثبت شده است.', 'service' );
        }
        if ( ! empty( $local['active'] ) ) {
            s_store_health_add_check( $checks, wp_next_scheduled( 'sb_cleanup_event' ) ? 'ok' : 'warn', 'Cron پاکسازی', wp_next_scheduled( 'sb_cleanup_event' ) ? 'Cron روزانه پاکسازی زمان‌بندی شده است.' : 'رویداد sb_cleanup_event زمان‌بندی نشده است.', 'cron' );
        }
    }

    if ( 'smart-seo-ai-pro' === $slug && ! empty( $local['active'] ) ) {
        $ai = (array) get_option( 'smart_seo_ai_settings', [] );
        if ( ! empty( $ai['enable_ai_engine'] ) ) {
            s_store_health_add_check( $checks, empty( $ai['ai_api_key'] ) ? 'warn' : 'ok', 'AI API', empty( $ai['ai_api_key'] ) ? 'موتور AI فعال است اما API Key ثبت نشده است.' : 'API Key موتور AI ثبت شده است.', 'service' );
        }
        s_store_health_add_check( $checks, wp_next_scheduled( 'smart_seo_ai_daily_scan_cron' ) ? 'ok' : 'warn', 'Cron اسکن روزانه', wp_next_scheduled( 'smart_seo_ai_daily_scan_cron' ) ? 'اسکن روزانه زمان‌بندی شده است.' : 'Cron اسکن روزانه پیدا نشد.', 'cron' );
    }

    if ( 'media-optimizer' === $slug ) {
        $s = (array) get_option( 'mo_settings', [] );
        if ( ( $s['scheduled'] ?? 'no' ) === 'yes' && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            s_store_health_add_check( $checks, 'warn', 'WP-Cron', 'بهینه‌سازی زمان‌بندی‌شده فعال است اما WP-Cron غیرفعال شده است.', 'cron' );
        }
    }

    $recent_error = s_store_health_recent_error( $slug );
    if ( $recent_error ) {
        s_store_health_add_check( $checks, 'error', 'خطای اخیر S Store', $recent_error['message'] ?: 'یک خطای اخیر برای این افزونه ثبت شده است.', 'error' );
    }

    $status = 'ok';
    foreach ( $checks as $check ) {
        if ( s_store_health_status_rank( $check['status'] ) > s_store_health_status_rank( $status ) ) {
            $status = $check['status'];
        }
    }

    return [
        'slug'    => $slug,
        'name'    => $p['name'] ?? $slug,
        'status'  => $status,
        'checks'  => $checks,
        'local'   => $local,
        'plugin'  => $p,
    ];
}

function s_store_health_report() {
    $installed = s_store_installed_map();
    $items = [];
    foreach ( s_store_manifest_plugins() as $p ) {
        if ( empty( $p['slug'] ) || 's-store' === $p['slug'] ) continue;
        $items[] = s_store_health_check_plugin( $p, $installed );
    }
    return $items;
}

function s_store_health_summary( $report = null ) {
    $report = is_array( $report ) ? $report : s_store_health_report();
    $summary = [ 'ok' => 0, 'warn' => 0, 'error' => 0, 'off' => 0, 'info' => 0 ];
    foreach ( $report as $item ) {
        $status = $item['status'] ?? 'info';
        if ( ! isset( $summary[ $status ] ) ) $status = 'info';
        $summary[ $status ]++;
    }
    return $summary;
}

function s_store_health_center_page() {
    $report  = s_store_health_report();
    $summary = s_store_health_summary( $report );

    s_store_admin_shell_start( 'مرکز سلامت S Store', 'بررسی یکپارچه وضعیت افزونه‌ها، وابستگی‌ها، نسخه‌ها، Cron، APIها و خطاهای اخیر.' );

    echo '<section class="s-store-health-hero">';
    echo '<div><span class="dashicons dashicons-heart"></span><div><h2>Health Center</h2><p>این صفحه فقط از داده‌های واقعی همین وردپرس استفاده می‌کند.</p></div></div>';
    echo '<a class="s-store-btn ghost" href="' . esc_url( admin_url( 'admin.php?page=s-store-health' ) ) . '"><span class="dashicons dashicons-update"></span>بررسی دوباره</a>';
    echo '</section>';

    echo '<section class="s-store-health-summary">';
    $cards = [
        [ 'سالم', $summary['ok'], 'ok', 'yes-alt' ],
        [ 'نیازمند توجه', $summary['warn'], 'warn', 'warning' ],
        [ 'خطا', $summary['error'], 'error', 'dismiss' ],
        [ 'نصب‌نشده', $summary['off'], 'off', 'minus' ],
    ];
    foreach ( $cards as $card ) {
        echo '<div class="s-store-health-stat ' . esc_attr( $card[2] ) . '"><span class="dashicons dashicons-' . esc_attr( $card[3] ) . '"></span><div><small>' . esc_html( $card[0] ) . '</small><strong>' . esc_html( $card[1] ) . '</strong></div></div>';
    }
    echo '</section>';

    $global_cron = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
    echo '<section class="s-store-global-health">';
    echo '<div class="' . ( $global_cron ? 'ok' : 'warn' ) . '"><span class="dashicons dashicons-clock"></span><strong>WP-Cron</strong><small>' . ( $global_cron ? 'فعال' : 'غیرفعال' ) . '</small></div>';
    echo '<div class="ok"><span class="dashicons dashicons-wordpress"></span><strong>WordPress</strong><small>' . esc_html( get_bloginfo( 'version' ) ) . '</small></div>';
    echo '<div class="ok"><span class="dashicons dashicons-editor-code"></span><strong>PHP</strong><small>' . esc_html( PHP_VERSION ) . '</small></div>';
    echo '<div class="' . ( class_exists( 'WooCommerce' ) ? 'ok' : 'info' ) . '"><span class="dashicons dashicons-cart"></span><strong>WooCommerce</strong><small>' . ( class_exists( 'WooCommerce' ) ? esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'فعال' ) : 'غیرفعال' ) . '</small></div>';
    echo '</section>';

    echo '<div class="s-store-health-list">';
    foreach ( $report as $item ) {
        $status = $item['status'] ?? 'info';
        $slug   = $item['slug'];
        echo '<article class="s-store-health-plugin ' . esc_attr( $status ) . '">';
        echo '<div class="s-store-health-plugin-head">';
        s_store_render_plugin_icon( $slug );
        echo '<div><h3>' . esc_html( $item['name'] ) . '</h3><span>' . esc_html( $slug ) . '</span></div>';
        echo '<span class="s-store-health-pill ' . esc_attr( $status ) . '">' . esc_html( [ 'ok'=>'سالم','warn'=>'نیازمند توجه','error'=>'خطا','off'=>'نصب‌نشده','info'=>'اطلاعات' ][ $status ] ?? 'اطلاعات' ) . '</span>';
        echo '<a class="s-store-btn ghost" href="' . esc_url( add_query_arg( [ 'page'=>'s-store','view'=>'plugin','slug'=>$slug ], admin_url( 'admin.php' ) ) ) . '">جزئیات</a>';
        echo '</div><div class="s-store-health-checks">';

        foreach ( $item['checks'] as $check ) {
            echo '<div class="s-store-health-check ' . esc_attr( $check['status'] ) . '"><i></i><div><strong>' . esc_html( $check['title'] ) . '</strong><small>' . esc_html( $check['detail'] ) . '</small></div></div>';
        }
        echo '</div></article>';
    }
    echo '</div>';

    s_store_admin_shell_end();
}

function s_store_action_button( $p, $local, $compact = false ) {
    $slug = sanitize_key( $p['slug'] ?? '' );
    if ( ! $slug ) return;

    if ( ! $local && ! empty( $p['available'] ) && ! empty( $p['download_url'] ) ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=s_store_install&slug=' . rawurlencode( $slug ) ), 's_store_install_' . $slug );
        echo '<a class="s-store-btn primary" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-download"></span>نصب</a>';
    } elseif ( $local && ! $local['active'] ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=s_store_activate&plugin=' . rawurlencode( $local['file'] ) ), 's_store_activate_' . $local['file'] );
        echo '<a class="s-store-btn primary" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-controls-play"></span>فعال‌سازی</a>';
    } elseif ( $local && ! empty( $p['version'] ) && version_compare( $local['version'], $p['version'], '<' ) ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=s_store_update&slug=' . rawurlencode( $slug ) ), 's_store_update_' . $slug );
        echo '<a class="s-store-btn primary" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-update"></span>بروزرسانی</a>';
    } elseif ( $local ) {
        echo '<span class="s-store-btn success"><span class="dashicons dashicons-yes-alt"></span>فعال</span>';
    } else {
        echo '<span class="s-store-btn muted">به‌زودی</span>';
    }

    if ( ! $compact ) {
        $detail = add_query_arg( [ 'page' => 's-store', 'view' => 'plugin', 'slug' => $slug ], admin_url( 'admin.php' ) );
        echo '<a class="s-store-btn ghost" href="' . esc_url( $detail ) . '">جزئیات</a>';
    }
}

function s_store_render_plugin_card( $p, $installed ) {
    $slug = sanitize_key( $p['slug'] ?? '' );
    if ( ! $slug ) return;
    $local = $installed[ $slug ] ?? null;
    $category = s_store_plugin_category( $p );
    $state = $local ? ( $local['active'] ? 'فعال' : 'نصب‌شده' ) : 'نصب نشده';
    $class = $local ? ( $local['active'] ? 'active' : 'installed' ) : 'missing';
    $search = strtolower( ( $p['name'] ?? '' ) . ' ' . ( $p['description'] ?? '' ) . ' ' . $slug );

    echo '<article class="s-store-plugin-card" data-s-store-plugin data-category="' . esc_attr( $category ) . '" data-search="' . esc_attr( $search ) . '">';
    echo '<a class="s-store-plugin-main" href="' . esc_url( add_query_arg( [ 'page' => 's-store', 'view' => 'plugin', 'slug' => $slug ], admin_url( 'admin.php' ) ) ) . '">';
    s_store_render_plugin_icon( $slug );
    echo '<span class="s-store-plugin-copy"><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><span>' . esc_html( wp_trim_words( $p['description'] ?? 'افزونه حرفه‌ای وردپرس', 12 ) ) . '</span></span></a>';
    echo '<div class="s-store-plugin-meta"><span>v' . esc_html( $p['version'] ?? '-' ) . '</span><span class="s-store-status ' . esc_attr( $class ) . '">' . esc_html( $state ) . '</span></div>';
    echo '<div class="s-store-plugin-actions">';
    s_store_action_button( $p, $local );
    echo '</div></article>';
}

function s_store_dashboard_page() {
    if ( ! empty( $_GET['view'] ) && 'plugin' === sanitize_key( wp_unslash( $_GET['view'] ) ) ) {
        s_store_plugin_detail_page();
        return;
    }

    $plugins = s_store_manifest_plugins();
    $installed = s_store_installed_map();
    $stats = s_store_stats( $plugins, $installed );

    s_store_admin_shell_start( 'فروشگاه افزونه‌های S Store', 'همه افزونه‌های ضروری وردپرس را از یکجا نصب، مدیریت و بروزرسانی کنید.' );

    echo '<section class="s-store-hero">';
    echo '<div class="s-store-hero-copy"><span class="s-store-eyebrow">S STORE · WORDPRESS TOOLKIT</span><h2>سایتی سریع‌تر، امن‌تر و حرفه‌ای‌تر</h2><p>افزونه‌های اختصاصی شما با نصب سریع، بروزرسانی مرکزی و رابط مدیریتی یکپارچه.</p><div class="s-store-hero-points"><span>✓ بروزرسانی امن</span><span>✓ نسخه ثابت هر افزونه</span><span>✓ مدیریت یکجا</span></div></div>';
    echo '<div class="s-store-hero-art"><span class="s-store-shield"><span class="dashicons dashicons-wordpress"></span></span><i></i><b></b></div>';
    echo '</section>';

    echo '<section class="s-store-toolbar"><div class="s-store-search"><span class="dashicons dashicons-search"></span><input id="s-store-search" type="search" placeholder="نام افزونه، قابلیت یا دسته را جستجو کنید…"></div>';
    echo '<div class="s-store-filters">';
    foreach ( [ 'all', 'commerce', 'marketing', 'seo', 'media', 'tools' ] as $cat ) {
        echo '<button type="button" class="s-store-filter ' . ( 'all' === $cat ? 'is-active' : '' ) . '" data-category="' . esc_attr( $cat ) . '">' . esc_html( s_store_category_label( $cat ) ) . '</button>';
    }
    echo '</div></section>';

    echo '<section class="s-store-stats">';
    $cards = [
        [ 'کل افزونه‌ها', $stats['total'], 'admin-plugins', 'purple' ],
        [ 'افزونه‌های نصب‌شده', $stats['installed'], 'archive', 'blue' ],
        [ 'موجودی بروزرسانی', $stats['updates'], 'update', 'orange' ],
        [ 'افزونه‌های فعال', $stats['active'], 'yes-alt', 'green' ],
    ];
    foreach ( $cards as $c ) {
        echo '<div class="s-store-stat ' . esc_attr( $c[3] ) . '"><span class="dashicons dashicons-' . esc_attr( $c[2] ) . '"></span><div><small>' . esc_html( $c[0] ) . '</small><strong>' . esc_html( $c[1] ) . '</strong><i><b style="width:' . esc_attr( min( 100, max( 18, $c[1] * 4 ) ) ) . '%"></b></i></div></div>';
    }
    echo '</section>';

    $health_report = s_store_health_report();
    $health_summary = s_store_health_summary( $health_report );
    $health_state = $health_summary['error'] ? 'error' : ( $health_summary['warn'] ? 'warn' : 'ok' );
    echo '<section class="s-store-health-banner ' . esc_attr( $health_state ) . '">';
    echo '<div class="s-store-health-banner-icon"><span class="dashicons dashicons-heart"></span></div>';
    echo '<div class="s-store-health-banner-copy"><small>HEALTH CENTER</small><h2>سلامت مجموعه افزونه‌ها</h2><p>';
    if ( $health_summary['error'] ) {
        echo esc_html( $health_summary['error'] ) . ' افزونه دارای خطا و ' . esc_html( $health_summary['warn'] ) . ' مورد نیازمند توجه است.';
    } elseif ( $health_summary['warn'] ) {
        echo esc_html( $health_summary['warn'] ) . ' افزونه نیازمند بررسی است و خطای بحرانی ثبت نشده.';
    } else {
        echo 'افزونه‌های نصب‌شده بدون خطای شناسایی‌شده کار می‌کنند.';
    }
    echo '</p></div>';
    echo '<div class="s-store-health-banner-counts"><span class="ok">' . esc_html( $health_summary['ok'] ) . ' سالم</span><span class="warn">' . esc_html( $health_summary['warn'] ) . ' هشدار</span><span class="error">' . esc_html( $health_summary['error'] ) . ' خطا</span></div>';
    echo '<a class="s-store-btn primary" href="' . esc_url( admin_url( 'admin.php?page=s-store-health' ) ) . '">باز کردن مرکز سلامت</a>';
    echo '</section>';

    echo '<section class="s-store-section"><div class="s-store-section-head"><div><span class="dashicons dashicons-star-filled"></span><h2>افزونه‌های پیشنهادی</h2></div><a href="' . esc_url( admin_url( 'admin.php?page=s-store-all' ) ) . '">مشاهده همه</a></div><div class="s-store-plugin-grid">';
    foreach ( array_slice( $plugins, 0, 8 ) as $p ) s_store_render_plugin_card( $p, $installed );
    echo '</div><div class="s-store-empty" hidden>افزونه‌ای با این جستجو پیدا نشد.</div></section>';

    if ( $stats['updates'] ) {
        echo '<section class="s-store-section"><div class="s-store-section-head"><div><span class="dashicons dashicons-update"></span><h2>بروزرسانی‌های موجود</h2></div><a href="' . esc_url( admin_url( 'admin.php?page=s-store-updates' ) ) . '">مشاهده همه</a></div><div class="s-store-update-grid">';
        foreach ( $plugins as $p ) {
            $slug = $p['slug'] ?? '';
            if ( empty( $installed[ $slug ] ) || empty( $p['version'] ) || ! version_compare( $installed[ $slug ]['version'], $p['version'], '<' ) ) continue;
            echo '<div class="s-store-update-card">'; s_store_render_plugin_icon( $slug ); echo '<div><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><small>v' . esc_html( $installed[ $slug ]['version'] ) . ' ← v' . esc_html( $p['version'] ) . '</small></div>';
            s_store_action_button( $p, $installed[ $slug ], true );
            echo '</div>';
        }
        echo '</div></section>';
    }

    echo '<section class="s-store-section"><div class="s-store-section-head"><div><span class="dashicons dashicons-admin-generic"></span><h2>امکانات فروشگاه S Store</h2></div></div><div class="s-store-feature-grid">';
    $features = [
        [ 'نصب سریع', 'نصب افزونه‌ها با یک کلیک', 'superhero', admin_url( 'admin.php?page=s-store-all' ) ],
        [ 'بروزرسانی یک‌کلیک', 'نسخه‌های جدید از GitHub', 'update', admin_url( 'admin.php?page=s-store-updates' ) ],
        [ 'مدیریت مرکزی', 'همه افزونه‌ها در یک مکان', 'screenoptions', admin_url( 'admin.php?page=s-store-installed' ) ],
        [ 'آپدیت خودکار', 'اختیاری و قابل کنترل', 'backup', admin_url( 'admin.php?page=s-store-settings' ) ],
        [ 'سازگاری وردپرس', 'ساختار استاندارد و پایدار', 'wordpress', admin_url( 'admin.php?page=s-store-about' ) ],
        [ 'پشتیبانی', 'ارتباط مستقیم با توسعه‌دهنده', 'sos', 'https://t.me/sahandse' ],
    ];
    foreach ( $features as $f ) {
        echo '<a class="s-store-feature" href="' . esc_url( $f[3] ) . '"><span class="dashicons dashicons-' . esc_attr( $f[2] ) . '"></span><strong>' . esc_html( $f[0] ) . '</strong><small>' . esc_html( $f[1] ) . '</small><em>مشاهده ←</em></a>';
    }
    echo '</div></section>';

    s_store_admin_shell_end();
}

function s_store_all_page() {
    $plugins = s_store_manifest_plugins();
    $installed = s_store_installed_map();
    s_store_admin_shell_start( 'همه افزونه‌ها', 'جستجو و مدیریت همه افزونه‌های موجود در S Store.' );

    echo '<section class="s-store-toolbar compact"><div class="s-store-search"><span class="dashicons dashicons-search"></span><input id="s-store-search" type="search" placeholder="جستجو در همه افزونه‌ها…"></div><div class="s-store-filters">';
    foreach ( [ 'all', 'commerce', 'marketing', 'seo', 'media', 'tools' ] as $cat ) {
        echo '<button type="button" class="s-store-filter ' . ( 'all' === $cat ? 'is-active' : '' ) . '" data-category="' . esc_attr( $cat ) . '">' . esc_html( s_store_category_label( $cat ) ) . '</button>';
    }
    echo '</div></section><div class="s-store-plugin-grid all">';
    foreach ( $plugins as $p ) s_store_render_plugin_card( $p, $installed );
    echo '</div><div class="s-store-empty" hidden>افزونه‌ای با این جستجو پیدا نشد.</div>';
    s_store_admin_shell_end();
}

function s_store_activity_log( $slug, $action, $status = 'success', $message = '' ) {
    $slug = sanitize_key( $slug );
    $action = sanitize_key( $action );
    $status = in_array( $status, [ 'success', 'warning', 'error', 'info' ], true ) ? $status : 'info';
    $logs = get_option( 's_store_activity_log', [] );
    if ( ! is_array( $logs ) ) $logs = [];
    array_unshift( $logs, [
        'slug'    => $slug,
        'action'  => $action,
        'status'  => $status,
        'message' => sanitize_text_field( $message ),
        'time'    => current_time( 'timestamp' ),
        'user'    => get_current_user_id(),
    ] );
    update_option( 's_store_activity_log', array_slice( $logs, 0, 120 ), false );
}

function s_store_activity_for_plugin( $slug, $limit = 6 ) {
    $logs = get_option( 's_store_activity_log', [] );
    if ( ! is_array( $logs ) ) return [];
    $out = [];
    foreach ( $logs as $row ) {
        if ( empty( $row['slug'] ) || $row['slug'] !== $slug ) continue;
        $out[] = $row;
        if ( count( $out ) >= $limit ) break;
    }
    return $out;
}

function s_store_option_presence( $slug ) {
    $map = [
        'appointment-booking-pro'        => [ 'abp_settings' ],
        'cardyar'                        => [ 'cardyar_settings' ],
        'cash-installment-price'         => [ 'cip_settings' ],
        'delivery-calendar'              => [ 'dc_settings', 'delivery_calendar_settings' ],
        'gheymatbar'                     => [ 'gheymatbar_settings', 'gb_settings' ],
        'login-sms-bale'                 => [ 'lsb_settings', 'login_sms_bale_settings' ],
        'lucky-wheel-pro'                => [ 'lwp_settings' ],
        'media-optimizer'                => [ 'mo_settings' ],
        'price-compare-assistant'        => [ 'pca_settings' ],
        'product-video-reels'            => [ 'pvr_settings' ],
        'wc-market-sync'                 => [ 'wcms_settings' ],
        'woo-cashback-wallet'            => [ 'wcw_settings', 'woo_cashback_wallet_settings' ],
        'woo-mobile-app-shell'           => [ 'wmas_settings' ],
        'woocommerce-sms-orders'         => [ 'wso_settings', 'woocommerce_sms_orders_settings' ],
        'smart-delivery-for-woocommerce' => [ 'sdw_settings', 'smart_delivery_settings' ],
        'support-button'                 => [ 'sb_settings' ],
        'domarhaleii'                    => [ 's2fa_settings' ],
        'seo'                            => [ 'wss_speed', 'wss_seo', 'wss_schema' ],
        'smart-seo-ai-pro'               => [ 'smart_seo_ai_settings' ],
    ];

    $keys = $map[ $slug ] ?? [];
    $found = 0;
    $items = 0;
    foreach ( $keys as $key ) {
        $value = get_option( $key, null );
        if ( null === $value ) continue;
        $found++;
        if ( is_array( $value ) ) $items += count( $value );
        elseif ( '' !== $value ) $items++;
    }
    return [ 'groups' => $found, 'items' => $items ];
}

function s_store_plugin_services( $slug, $local ) {
    $services = [];

    $services[] = [
        'label'  => 'هسته افزونه',
        'status' => $local && ! empty( $local['active'] ) ? 'ok' : ( $local ? 'warn' : 'off' ),
        'text'   => $local && ! empty( $local['active'] ) ? 'فعال و در حال اجرا' : ( $local ? 'نصب‌شده اما غیرفعال' : 'نصب نشده' ),
    ];

    $woocommerce_slugs = [
        'cash-installment-price','delivery-calendar','price-compare-assistant','product-video-reels',
        'wc-market-sync','woo-cashback-wallet','woo-mobile-app-shell','woocommerce-sms-orders',
        'smart-delivery-for-woocommerce'
    ];
    if ( in_array( $slug, $woocommerce_slugs, true ) ) {
        $woo = class_exists( 'WooCommerce' );
        $services[] = [
            'label'  => 'WooCommerce',
            'status' => $woo ? 'ok' : 'error',
            'text'   => $woo ? 'در دسترس' : 'وابستگی پیدا نشد',
        ];
    }

    if ( in_array( $slug, [ 'login-sms-bale','woocommerce-sms-orders','appointment-booking-pro','support-button' ], true ) ) {
        $services[] = [
            'label'  => 'سرویس ارتباطی',
            'status' => 'info',
            'text'   => 'وضعیت از تنظیمات افزونه خوانده می‌شود',
        ];
    }

    $presence = s_store_option_presence( $slug );
    $services[] = [
        'label'  => 'تنظیمات ذخیره‌شده',
        'status' => $presence['groups'] > 0 ? 'ok' : 'info',
        'text'   => $presence['groups'] > 0
            ? sprintf( '%d گروه / %d مقدار', $presence['groups'], $presence['items'] )
            : 'هنوز تنظیمات قابل تشخیص ذخیره نشده',
    ];

    return apply_filters( 's_store_plugin_services', $services, $slug, $local );
}

function s_store_plugin_real_metrics( $slug, $p, $local ) {
    global $wpdb;

    $presence = s_store_option_presence( $slug );
    $metrics = [
        [
            'label' => 'نسخه نصب‌شده',
            'value' => $local ? ( $local['version'] ?: '—' ) : '—',
            'hint'  => $local ? 'روی این سایت' : 'نصب نشده',
            'icon'  => 'archive',
        ],
        [
            'label' => 'نسخه جدید',
            'value' => $p['version'] ?? '—',
            'hint'  => ( $local && ! empty( $p['version'] ) && version_compare( $local['version'], $p['version'], '<' ) ) ? 'آپدیت موجود' : 'آخرین نسخه',
            'icon'  => 'update',
        ],
        [
            'label' => 'تنظیمات',
            'value' => (string) $presence['items'],
            'hint'  => $presence['groups'] ? 'مقدار ذخیره‌شده' : 'داده قابل تشخیص',
            'icon'  => 'admin-generic',
        ],
    ];

    if ( 'cardyar' === $slug && post_type_exists( 'cardyar_payment' ) ) {
        $counts = wp_count_posts( 'cardyar_payment' );
        $total = 0;
        if ( is_object( $counts ) ) {
            foreach ( get_object_vars( $counts ) as $count ) $total += (int) $count;
        }
        $metrics[] = [
            'label' => 'پرداخت‌ها',
            'value' => number_format_i18n( $total ),
            'hint'  => 'رکورد واقعی کارت‌یار',
            'icon'  => 'money-alt',
        ];
    } elseif ( 'media-optimizer' === $slug ) {
        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mo_optimizer_pending'" );
        $metrics[] = [
            'label' => 'صف بهینه‌سازی',
            'value' => number_format_i18n( $pending ),
            'hint'  => 'تصویر در انتظار پردازش',
            'icon'  => 'format-image',
        ];
    } elseif ( 'woo-cashback-wallet' === $slug ) {
        $wallet_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE '%wallet%balance%'" );
        $metrics[] = [
            'label' => 'کیف پول‌ها',
            'value' => number_format_i18n( $wallet_rows ),
            'hint'  => 'رکورد موجودی ثبت‌شده',
            'icon'  => 'money',
        ];
    } elseif ( 'woocommerce-sms-orders' === $slug ) {
        $log = get_option( 'wso_logs', get_option( 'woocommerce_sms_orders_logs', [] ) );
        $metrics[] = [
            'label' => 'لاگ پیامک',
            'value' => is_array( $log ) ? number_format_i18n( count( $log ) ) : '0',
            'hint'  => 'رکورد ذخیره‌شده',
            'icon'  => 'email-alt',
        ];
    } elseif ( 'lucky-wheel-pro' === $slug ) {
        $settings = get_option( 'lwp_settings', [] );
        $prizes = is_array( $settings ) && ! empty( $settings['prizes'] ) && is_array( $settings['prizes'] ) ? count( $settings['prizes'] ) : 0;
        $metrics[] = [
            'label' => 'جوایز',
            'value' => number_format_i18n( $prizes ),
            'hint'  => 'جایزه تعریف‌شده',
            'icon'  => 'tickets-alt',
        ];
    } else {
        $logs = s_store_activity_for_plugin( $slug, 50 );
        $metrics[] = [
            'label' => 'عملیات ثبت‌شده',
            'value' => number_format_i18n( count( $logs ) ),
            'hint'  => 'در Activity Log',
            'icon'  => 'list-view',
        ];
    }

    return apply_filters( 's_store_plugin_metrics', $metrics, $slug, $p, $local );
}

function s_store_plugin_quick_actions( $slug, $p, $local ) {
    $actions = [];
    $settings_pages = [
        'domarhaleii'       => 'do-marhalei',
        'seo'               => 'wss-dashboard',
        'smart-seo-ai-pro'  => 'smart-seo-ai-dashboard',
        'support-button'    => 'support-button',
    ];
    $settings_page = $settings_pages[ $slug ] ?? $slug;

    if ( $local && ! empty( $local['active'] ) ) {
        $actions[] = [
            'label' => 'تنظیمات',
            'icon'  => 'admin-generic',
            'url'   => admin_url( 'admin.php?page=' . $settings_page ),
            'class' => 'primary',
        ];
    }
    if ( $local && ! empty( $p['version'] ) && version_compare( $local['version'], $p['version'], '<' ) ) {
        $actions[] = [
            'label' => 'بروزرسانی',
            'icon'  => 'update',
            'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=s_store_update&slug=' . rawurlencode( $slug ) ), 's_store_update_' . $slug ),
            'class' => 'primary',
        ];
    }
    if ( ! $local && ! empty( $p['available'] ) && ! empty( $p['download_url'] ) ) {
        $actions[] = [
            'label' => 'نصب',
            'icon'  => 'download',
            'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=s_store_install&slug=' . rawurlencode( $slug ) ), 's_store_install_' . $slug ),
            'class' => 'primary',
        ];
    }
    if ( ! empty( $p['homepage'] ) ) {
        $actions[] = [
            'label' => 'GitHub',
            'icon'  => 'external',
            'url'   => $p['homepage'],
            'class' => 'ghost',
            'external' => true,
        ];
    }

    return apply_filters( 's_store_plugin_quick_actions', $actions, $slug, $p, $local );
}

function s_store_render_plugin_dashboard( $slug, $p, $local ) {
    $metrics  = s_store_plugin_real_metrics( $slug, $p, $local );
    $services = s_store_plugin_services( $slug, $local );
    $logs     = s_store_activity_for_plugin( $slug, 6 );
    $actions  = s_store_plugin_quick_actions( $slug, $p, $local );

    echo '<section class="s-store-plugin-dashboard" id="dashboard">';
    echo '<div class="s-store-dashboard-head"><div><span class="dashicons dashicons-dashboard"></span><h3>داشبورد اختصاصی افزونه</h3></div><span>داده‌های واقعی همین سایت</span></div>';

    echo '<div class="s-store-metric-grid">';
    foreach ( $metrics as $metric ) {
        echo '<article class="s-store-metric-card"><span class="dashicons dashicons-' . esc_attr( $metric['icon'] ?? 'chart-bar' ) . '"></span><div><small>' . esc_html( $metric['label'] ?? '' ) . '</small><strong>' . esc_html( $metric['value'] ?? '—' ) . '</strong><em>' . esc_html( $metric['hint'] ?? '' ) . '</em></div></article>';
    }
    echo '</div>';

    echo '<div class="s-store-dashboard-grid">';
    echo '<section class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-heart"></span><h3>وضعیت سرویس‌ها</h3></div><div class="s-store-service-list">';
    foreach ( $services as $service ) {
        $status = sanitize_key( $service['status'] ?? 'info' );
        echo '<div class="s-store-service-row"><i class="' . esc_attr( $status ) . '"></i><div><strong>' . esc_html( $service['label'] ?? '' ) . '</strong><small>' . esc_html( $service['text'] ?? '' ) . '</small></div><span>' . esc_html( strtoupper( $status ) ) . '</span></div>';
    }
    echo '</div></section>';

    echo '<section class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-controls-repeat"></span><h3>آخرین عملیات</h3></div><div class="s-store-activity-list">';
    if ( ! $logs ) {
        echo '<div class="s-store-activity-empty">هنوز عملیات S Store برای این افزونه ثبت نشده است.</div>';
    } else {
        foreach ( $logs as $row ) {
            $time = ! empty( $row['time'] ) ? human_time_diff( (int) $row['time'], current_time( 'timestamp' ) ) . ' پیش' : '—';
            echo '<div class="s-store-activity-row"><i class="' . esc_attr( $row['status'] ?? 'info' ) . '"></i><div><strong>' . esc_html( $row['message'] ?: $row['action'] ) . '</strong><small>' . esc_html( $time ) . '</small></div></div>';
        }
    }
    echo '</div></section>';

    echo '<section class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-performance"></span><h3>Quick Actions</h3></div><div class="s-store-quick-actions">';
    foreach ( $actions as $action ) {
        $external = ! empty( $action['external'] ) ? ' target="_blank" rel="noopener"' : '';
        echo '<a class="s-store-quick-action ' . esc_attr( $action['class'] ?? 'ghost' ) . '" href="' . esc_url( $action['url'] ?? '#' ) . '"' . $external . '><span class="dashicons dashicons-' . esc_attr( $action['icon'] ?? 'admin-links' ) . '"></span><strong>' . esc_html( $action['label'] ?? '' ) . '</strong></a>';
    }
    echo '</div></section>';
    echo '</div></section>';
}

function s_store_plugin_detail_page() {
    $slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
    $p = s_store_plugin_by_slug( $slug );
    if ( ! $p ) {
        s_store_admin_shell_start( 'افزونه پیدا نشد' );
        echo '<div class="s-store-panel"><p>اطلاعات این افزونه در Manifest موجود نیست.</p></div>';
        s_store_admin_shell_end();
        return;
    }

    $installed = s_store_installed_map();
    $local = $installed[ $slug ] ?? null;
    $is_update = $local && ! empty( $p['version'] ) && version_compare( $local['version'], $p['version'], '<' );

    s_store_admin_shell_start( 'جزئیات افزونه', 'اطلاعات، امکانات و مدیریت افزونه انتخاب‌شده.' );

    echo '<a class="s-store-back" href="' . esc_url( admin_url( 'admin.php?page=s-store' ) ) . '">← بازگشت به فروشگاه</a>';
    echo '<section class="s-store-detail-hero">';
    s_store_render_plugin_icon( $slug, 'detail' );
    echo '<div class="s-store-detail-copy"><div class="s-store-detail-title"><h2>' . esc_html( $p['name'] ?? $slug ) . '</h2>';
    if ( $local ) echo '<span class="s-store-status ' . ( $local['active'] ? 'active' : 'installed' ) . '">' . ( $local['active'] ? 'فعال' : 'نصب‌شده' ) . '</span>';
    echo '</div><p>' . esc_html( $p['description'] ?? 'افزونه حرفه‌ای وردپرس از مجموعه S Store.' ) . '</p><div class="s-store-detail-meta"><span>نسخه ' . esc_html( $p['version'] ?? '-' ) . '</span><span>WordPress ' . esc_html( $p['requires'] ?? '6.0+' ) . '</span><span>PHP ' . esc_html( $p['requires_php'] ?? '7.4+' ) . '</span></div></div>';
    echo '<div class="s-store-detail-actions">';
    s_store_action_button( $p, $local, true );
    if ( $local && $local['active'] ) echo '<a class="s-store-btn ghost" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons dashicons-admin-generic"></span>تنظیمات</a>';
    if ( ! empty( $p['homepage'] ) ) echo '<a class="s-store-btn ghost" target="_blank" rel="noopener" href="' . esc_url( $p['homepage'] ) . '"><span class="dashicons dashicons-external"></span>GitHub</a>';
    echo '</div></section>';

    echo '<nav class="s-store-detail-tabs"><a class="is-active" href="#dashboard">داشبورد</a><a href="#overview">نمای کلی</a><a href="#features">ویژگی‌ها</a><a href="#changelog">تغییرات نسخه</a><a href="#compatibility">سازگاری</a></nav>';

    s_store_render_plugin_dashboard( $slug, $p, $local );

    echo '<div class="s-store-detail-layout"><main>';
    echo '<section id="overview" class="s-store-panel rich"><div class="s-store-panel-title"><span class="dashicons dashicons-images-alt2"></span><h3>نمای افزونه</h3></div>';
    echo '<div class="s-store-preview"><div class="s-store-preview-side"><span></span><span class="active"></span><span></span><span></span></div><div class="s-store-preview-main"><div class="s-store-preview-bar"></div><div class="s-store-preview-cards"><i></i><i></i><i></i></div><div class="s-store-preview-table"><b></b><b></b><b></b><b></b></div></div></div></section>';

    echo '<section id="changelog" class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-backup"></span><h3>تغییرات نسخه</h3></div><div class="s-store-changelog">';
    echo '<div><strong>v' . esc_html( $p['version'] ?? S_STORE_VERSION ) . '</strong><span class="s-store-chip green">نسخه جدید</span><p>' . esc_html( $p['changelog'] ?? 'بهبود رابط کاربری، سازگاری بیشتر و رفع مشکلات گزارش‌شده.' ) . '</p></div>';
    if ( $local && $is_update ) echo '<div><strong>v' . esc_html( $local['version'] ) . '</strong><span class="s-store-chip">نسخه نصب‌شده</span><p>نسخه فعلی روی وب‌سایت شما.</p></div>';
    echo '</div></section>';
    echo '</main><aside>';

    echo '<section id="features" class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-star-filled"></span><h3>ویژگی‌های کلیدی</h3></div><div class="s-store-key-features">';
    $generic = [
        [ 'مدیریت یکپارچه', 'همه تنظیمات در محیطی ساده', 'screenoptions' ],
        [ 'رابط فارسی', 'طراحی راست‌چین و کاربرپسند', 'translation' ],
        [ 'بروزرسانی مرکزی', 'دریافت نسخه جدید از S Store', 'update' ],
        [ 'ساختار امن', 'Nonce و سطح دسترسی استاندارد', 'shield' ],
        [ 'سازگاری', 'طراحی‌شده برای وردپرس جدید', 'yes-alt' ],
    ];
    foreach ( $generic as $f ) echo '<div><span class="dashicons dashicons-' . esc_attr( $f[2] ) . '"></span><p><strong>' . esc_html( $f[0] ) . '</strong><small>' . esc_html( $f[1] ) . '</small></p></div>';
    echo '</div></section>';

    echo '<section id="compatibility" class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-admin-links"></span><h3>سازگاری و نیازمندی‌ها</h3></div><div class="s-store-compat">';
    $compat = [
        [ 'وردپرس', $p['requires'] ?? '6.0+' ],
        [ 'نسخه تست‌شده', $p['tested'] ?? 'جدیدترین نسخه' ],
        [ 'PHP', $p['requires_php'] ?? '7.4+' ],
        [ 'زبان', 'فارسی / انگلیسی' ],
        [ 'وضعیت', 'سازگار' ],
    ];
    foreach ( $compat as $c ) echo '<div><span>' . esc_html( $c[0] ) . '</span><strong>' . esc_html( $c[1] ) . '</strong></div>';
    echo '</div></section>';

    echo '</aside></div>';
    s_store_admin_shell_end();
}

function s_store_installed_page() {
    $installed = s_store_installed_map();
    s_store_admin_shell_start( 'افزونه‌های نصب‌شده', 'وضعیت افزونه‌های S Store روی این وردپرس.' );
    echo '<div class="s-store-panel"><div class="s-store-table"><div class="head"><span>افزونه</span><span>نسخه</span><span>وضعیت</span></div>';
    foreach ( s_store_manifest_plugins() as $p ) {
        $slug = $p['slug'] ?? '';
        if ( empty( $installed[ $slug ] ) ) continue;
        $x = $installed[ $slug ];
        echo '<div class="row"><span><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><small>' . esc_html( $slug ) . '</small></span><span>v' . esc_html( $x['version'] ) . '</span><span class="s-store-status ' . ( $x['active'] ? 'active' : 'installed' ) . '">' . ( $x['active'] ? 'فعال' : 'غیرفعال' ) . '</span></div>';
    }
    echo '</div></div>';
    s_store_admin_shell_end();
}

function s_store_updates_page() {
    if ( isset( $_POST['s_store_refresh'] ) && check_admin_referer( 's_store_refresh_manifest' ) ) {
        delete_site_transient( 's_store_manifest_v1' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
        echo '<div class="notice notice-success is-dismissible"><p>اطلاعات بروزرسانی تازه‌سازی شد.</p></div>';
    }

    $plugins = s_store_manifest_plugins();
    $installed = s_store_installed_map();
    $stats = s_store_stats( $plugins, $installed );

    s_store_admin_shell_start( 'بروزرسانی‌ها', 'نسخه‌های جدید از GitHub و Manifest مرکزی بررسی می‌شوند.' );
    echo '<div class="s-store-update-head"><div><strong>' . esc_html( $stats['updates'] ) . '</strong><span>بروزرسانی موجود</span></div><form method="post">';
    wp_nonce_field( 's_store_refresh_manifest' );
    submit_button( 'بررسی دوباره', 'primary', 's_store_refresh', false );
    echo '</form></div><div class="s-store-update-grid full">';

    foreach ( $plugins as $p ) {
        $slug = $p['slug'] ?? '';
        if ( empty( $installed[ $slug ] ) || empty( $p['version'] ) || ! version_compare( $installed[ $slug ]['version'], $p['version'], '<' ) ) continue;
        echo '<div class="s-store-update-card"><span class="s-store-plugin-icon tone-' . esc_attr( abs( crc32( $slug ) ) % 6 ) . '"><span class="dashicons dashicons-' . esc_attr( s_store_icon_for_slug( $slug ) ) . '"></span></span><div><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><small>v' . esc_html( $installed[ $slug ]['version'] ) . ' ← v' . esc_html( $p['version'] ) . '</small></div>';
        s_store_action_button( $p, $installed[ $slug ], true );
        echo '</div>';
    }

    if ( ! $stats['updates'] ) echo '<div class="s-store-all-good"><span class="dashicons dashicons-yes-alt"></span><strong>همه افزونه‌ها بروز هستند</strong><p>در حال حاضر نسخه جدیدی برای افزونه‌های S Store وجود ندارد.</p></div>';
    echo '</div>';
    s_store_admin_shell_end();
}

function s_store_settings_page() {
    s_store_admin_shell_start( 'تنظیمات S Store', 'رفتار بروزرسانی و امکانات عمومی فروشگاه.' );
    echo '<form method="post" action="options.php" class="s-store-settings-form">';
    settings_fields( 's_store_settings' );
    echo '<section class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-update"></span><h3>بروزرسانی خودکار</h3></div><label class="s-store-toggle-row"><div><strong>آپدیت خودکار افزونه‌های S Store</strong><small>نسخه‌های جدید افزونه‌های موجود در Manifest به‌صورت خودکار نصب شوند.</small></div><input type="checkbox" name="s_store_auto_updates" value="1" ' . checked( (bool) get_option( 's_store_auto_updates', false ), true, false ) . '><i></i></label></section>';
    echo '<section class="s-store-panel"><div class="s-store-panel-title"><span class="dashicons dashicons-cloud"></span><h3>منبع بروزرسانی</h3></div><code class="s-store-code">' . esc_html( S_STORE_MANIFEST_URL ) . '</code><p>نسخه، لینک ZIP و اطلاعات افزونه‌ها از Manifest مرکزی S Store خوانده می‌شوند.</p></section>';
    submit_button( 'ذخیره تنظیمات', 'primary s-store-save' );
    echo '</form>';
    s_store_admin_shell_end();
}

function s_store_about_page() {
    s_store_admin_shell_start( 'درباره S Store', 'مرکز مدیریت افزونه‌های اختصاصی Sahand Rezvan.' );
    echo '<div class="s-store-about-grid"><section class="s-store-panel"><span class="s-store-about-logo">S</span><h2>S Store</h2><p>نصب، مدیریت و بروزرسانی مرکزی افزونه‌های وردپرس با طراحی یکپارچه و slug ثابت.</p><div class="s-store-detail-meta"><span>نسخه ' . esc_html( S_STORE_VERSION ) . '</span><span>GPLv2+</span></div></section><section class="s-store-panel"><h3>توسعه‌دهنده</h3><p><strong>سهند رضوان · Sahand Rezvan</strong></p><p><a href="https://t.me/sahandse" target="_blank" rel="noopener">t.me/sahandse</a></p><p><a href="https://github.com/sahandse/S-Store" target="_blank" rel="noopener">GitHub / S-Store</a></p></section></div>';
    s_store_admin_shell_end();
}

add_action( 'admin_post_s_store_install', function() {
    if ( ! current_user_can( 'install_plugins' ) ) wp_die( 'دسترسی غیرمجاز.' );
    $slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
    check_admin_referer( 's_store_install_' . $slug );
    $p = s_store_plugin_by_slug( $slug );
    if ( ! $p || empty( $p['available'] ) || empty( $p['download_url'] ) ) wp_die( 'این افزونه هنوز بسته نصب آماده ندارد.' );

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader( $skin );
    $GLOBALS['s_store_install_slug'] = $slug;
    $result = $upgrader->install( esc_url_raw( $p['download_url'] ) );
    unset( $GLOBALS['s_store_install_slug'] );

    if ( is_wp_error( $result ) || ! $result ) {
        $message = is_wp_error( $result ) ? $result->get_error_message() : 'نصب افزونه ناموفق بود.';
        s_store_activity_log( $slug, 'install', 'error', $message );
        wp_die( esc_html( $message ) );
    }
    s_store_activity_log( $slug, 'install', 'success', 'افزونه با موفقیت نصب شد.' );
    wp_safe_redirect( admin_url( 'admin.php?page=s-store-installed' ) );
    exit;
} );

add_action( 'admin_post_s_store_update', function() {
    if ( ! current_user_can( 'update_plugins' ) ) wp_die( 'دسترسی غیرمجاز.' );
    $slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
    check_admin_referer( 's_store_update_' . $slug );

    $installed = s_store_installed_map();
    if ( empty( $installed[ $slug ]['file'] ) ) wp_die( 'افزونه نصب‌شده پیدا نشد.' );

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';

    delete_site_transient( 'update_plugins' );
    wp_update_plugins();

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader( $skin );
    $result = $upgrader->upgrade( $installed[ $slug ]['file'] );

    if ( is_wp_error( $result ) || ! $result ) {
        $message = is_wp_error( $result ) ? $result->get_error_message() : 'بروزرسانی افزونه ناموفق بود.';
        s_store_activity_log( $slug, 'update', 'error', $message );
        wp_die( esc_html( $message ) );
    }
    s_store_activity_log( $slug, 'update', 'success', 'افزونه با موفقیت بروزرسانی شد.' );
    wp_safe_redirect( admin_url( 'admin.php?page=s-store-updates' ) );
    exit;
} );

add_action( 'admin_post_s_store_activate', function() {
    if ( ! current_user_can( 'activate_plugins' ) ) wp_die( 'دسترسی غیرمجاز.' );
    $plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
    check_admin_referer( 's_store_activate_' . $plugin );

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $result = activate_plugin( $plugin );

    if ( is_wp_error( $result ) ) {
        $folder = dirname( $plugin );
        if ( '.' === $folder ) $folder = basename( $plugin, '.php' );
        s_store_activity_log( $folder, 'activate', 'error', $result->get_error_message() );
        wp_die( esc_html( $result->get_error_message() ) );
    }
    $folder = dirname( $plugin );
    if ( '.' === $folder ) $folder = basename( $plugin, '.php' );
    s_store_activity_log( $folder, 'activate', 'success', 'افزونه فعال شد.' );
    wp_safe_redirect( admin_url( 'admin.php?page=s-store-installed' ) );
    exit;
} );
