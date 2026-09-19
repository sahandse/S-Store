<?php
/**
 * Plugin Name: فروشگاه افزونه اس
 * Plugin URI: https://github.com/sahandse/S-Store
 * Description: فروشگاه و بروزرسان مرکزی افزونه‌های اختصاصی سهند رضوان با نصب، بروزرسانی، جزئیات افزونه و منوی یکپارچه.
 * Version: 1.5.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sahand Rezvan
 * Author URI: https://t.me/sahandse
 * License: GPLv2 or later
 * Text Domain: s-store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'S_STORE_VERSION', '1.5.0' );
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
    if ( ! $p || empty( $p['source_subdir'] ) ) return $source;

    $nested = trailingslashit( $source ) . trim( $p['source_subdir'], '/' );
    if ( ! is_dir( $nested ) ) {
        return new WP_Error(
            's_store_source_missing',
            sprintf( 'مسیر افزونه %s داخل بسته دانلودی پیدا نشد.', esc_html( $p['name'] ?? $slug ) )
        );
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

add_filter( 'admin_body_class', function( $classes ) {
    if ( ! empty( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 's-store' ) ) {
        $classes .= ' s-store-admin-page';
    }
    return $classes;
} );

add_action( 'admin_enqueue_scripts', function() {
    if ( empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 's-store' ) ) return;
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 's-store-admin', S_STORE_URL . 'assets/admin.css', [], S_STORE_VERSION );
    wp_enqueue_script( 's-store-admin', S_STORE_URL . 'assets/admin.js', [], S_STORE_VERSION, true );
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
    echo '<span class="s-store-plugin-icon tone-' . esc_attr( abs( crc32( $slug ) ) % 6 ) . '"><span class="dashicons dashicons-' . esc_attr( s_store_icon_for_slug( $slug ) ) . '"></span></span>';
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

    echo '<section class="s-store-section"><div class="s-store-section-head"><div><span class="dashicons dashicons-star-filled"></span><h2>افزونه‌های پیشنهادی</h2></div><a href="' . esc_url( admin_url( 'admin.php?page=s-store-all' ) ) . '">مشاهده همه</a></div><div class="s-store-plugin-grid">';
    foreach ( array_slice( $plugins, 0, 8 ) as $p ) s_store_render_plugin_card( $p, $installed );
    echo '</div><div class="s-store-empty" hidden>افزونه‌ای با این جستجو پیدا نشد.</div></section>';

    if ( $stats['updates'] ) {
        echo '<section class="s-store-section"><div class="s-store-section-head"><div><span class="dashicons dashicons-update"></span><h2>بروزرسانی‌های موجود</h2></div><a href="' . esc_url( admin_url( 'admin.php?page=s-store-updates' ) ) . '">مشاهده همه</a></div><div class="s-store-update-grid">';
        foreach ( $plugins as $p ) {
            $slug = $p['slug'] ?? '';
            if ( empty( $installed[ $slug ] ) || empty( $p['version'] ) || ! version_compare( $installed[ $slug ]['version'], $p['version'], '<' ) ) continue;
            echo '<div class="s-store-update-card"><span class="s-store-plugin-icon tone-' . esc_attr( abs( crc32( $slug ) ) % 6 ) . '"><span class="dashicons dashicons-' . esc_attr( s_store_icon_for_slug( $slug ) ) . '"></span></span><div><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><small>v' . esc_html( $installed[ $slug ]['version'] ) . ' ← v' . esc_html( $p['version'] ) . '</small></div>';
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
    echo '<span class="s-store-plugin-icon detail tone-' . esc_attr( abs( crc32( $slug ) ) % 6 ) . '"><span class="dashicons dashicons-' . esc_attr( s_store_icon_for_slug( $slug ) ) . '"></span></span>';
    echo '<div class="s-store-detail-copy"><div class="s-store-detail-title"><h2>' . esc_html( $p['name'] ?? $slug ) . '</h2>';
    if ( $local ) echo '<span class="s-store-status ' . ( $local['active'] ? 'active' : 'installed' ) . '">' . ( $local['active'] ? 'فعال' : 'نصب‌شده' ) . '</span>';
    echo '</div><p>' . esc_html( $p['description'] ?? 'افزونه حرفه‌ای وردپرس از مجموعه S Store.' ) . '</p><div class="s-store-detail-meta"><span>نسخه ' . esc_html( $p['version'] ?? '-' ) . '</span><span>WordPress ' . esc_html( $p['requires'] ?? '6.0+' ) . '</span><span>PHP ' . esc_html( $p['requires_php'] ?? '7.4+' ) . '</span></div></div>';
    echo '<div class="s-store-detail-actions">';
    s_store_action_button( $p, $local, true );
    if ( $local && $local['active'] ) echo '<a class="s-store-btn ghost" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons dashicons-admin-generic"></span>تنظیمات</a>';
    if ( ! empty( $p['homepage'] ) ) echo '<a class="s-store-btn ghost" target="_blank" rel="noopener" href="' . esc_url( $p['homepage'] ) . '"><span class="dashicons dashicons-external"></span>GitHub</a>';
    echo '</div></section>';

    echo '<nav class="s-store-detail-tabs"><a class="is-active" href="#overview">نمای کلی</a><a href="#features">ویژگی‌ها</a><a href="#changelog">تغییرات نسخه</a><a href="#compatibility">سازگاری</a></nav>';

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

    if ( is_wp_error( $result ) || ! $result ) wp_die( 'نصب افزونه ناموفق بود.' );
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

    if ( is_wp_error( $result ) || ! $result ) wp_die( 'بروزرسانی افزونه ناموفق بود.' );
    wp_safe_redirect( admin_url( 'admin.php?page=s-store-updates' ) );
    exit;
} );

add_action( 'admin_post_s_store_activate', function() {
    if ( ! current_user_can( 'activate_plugins' ) ) wp_die( 'دسترسی غیرمجاز.' );
    $plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
    check_admin_referer( 's_store_activate_' . $plugin );

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $result = activate_plugin( $plugin );

    if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
    wp_safe_redirect( admin_url( 'admin.php?page=s-store-installed' ) );
    exit;
} );
