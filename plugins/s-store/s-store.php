<?php
/**
 * Plugin Name: فروشگاه افزونه اس
 * Plugin URI: https://github.com/sahandse/S-Store
 * Description: فروشگاه و بروزرسان مرکزی افزونه‌های اختصاصی سهند رضوان با نصب، بروزرسانی و منوی یکپارچه.
 * Version: 1.4.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sahand Rezvan
 * Author URI: https://t.me/sahandse
 * License: GPLv2 or later
 * Text Domain: s-store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'S_STORE_VERSION', '1.4.1' );
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
        'timeout' => 12,
        'headers' => [ 'Accept' => 'application/json' ],
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

function s_store_plugin_slug_from_basename( $plugin_file ) {
    $folder = dirname( $plugin_file );
    if ( '.' === $folder ) $folder = basename( $plugin_file, '.php' );
    foreach ( s_store_manifest_plugins() as $p ) {
        if ( ! empty( $p['slug'] ) && $folder === $p['slug'] ) return $p['slug'];
    }
    return '';
}

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
            $obj->slug = $p['slug'];
            $obj->plugin = $plugin_file;
            $obj->new_version = $p['version'];
            $obj->url = $p['homepage'] ?? $p['repo'] ?? '';
            $obj->package = $p['download_url'];
            $obj->tested = $p['tested'] ?? '';
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
        'name' => $p['name'] ?? $p['slug'],
        'slug' => $p['slug'],
        'version' => $p['version'] ?? '',
        'author' => '<a href="https://t.me/sahandse">Sahand Rezvan</a>',
        'homepage' => $p['homepage'] ?? $p['repo'] ?? '',
        'requires' => $p['requires'] ?? '6.0',
        'tested' => $p['tested'] ?? '',
        'requires_php' => $p['requires_php'] ?? '7.4',
        'download_link' => $p['download_url'] ?? '',
        'sections' => [
            'description' => wp_kses_post( $p['description'] ?? '' ),
            'changelog' => wp_kses_post( $p['changelog'] ?? '' ),
        ],
    ];
}, 10, 3 );

add_action( 'admin_enqueue_scripts', function() {
    if ( empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 's-store' ) ) return;
    wp_enqueue_style( 's-store-admin', S_STORE_URL . 'assets/admin.css', [], S_STORE_VERSION );
} );

function s_store_register_submenu( $slug, $menu_title, $callback, $capability = 'manage_options', $page_title = '' ) {
    $page_title = $page_title ?: $menu_title;
    return add_submenu_page( 's-store', $page_title, $menu_title, $capability, $slug, $callback );
}

function s_store_admin_shell_start( $title, $description = '' ) {
    echo '<div class="wrap s-store-wrap" dir="rtl"><div class="s-store-head"><div><h1>' . esc_html( $title ) . '</h1>';
    if ( $description ) echo '<p>' . esc_html( $description ) . '</p>';
    echo '</div><span class="s-store-badge">S Store</span></div>';
}

function s_store_admin_shell_end() {
    echo '</div>';
}

add_action( 'admin_menu', function() {
    add_menu_page( 'S Store', 'S Store', 'manage_options', 's-store', 's_store_dashboard_page', 'dashicons-store', 58 );
    add_submenu_page( 's-store', 'فروشگاه افزونه‌ها', 'فروشگاه افزونه‌ها', 'manage_options', 's-store', 's_store_dashboard_page' );
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
            'file' => $file,
            'name' => $data['Name'] ?? $folder,
            'version' => $data['Version'] ?? '',
            'active' => is_plugin_active( $file ),
        ];
    }
    return $map;
}

function s_store_dashboard_page() {
    $plugins = s_store_manifest_plugins();
    $installed = s_store_installed_map();
    s_store_admin_shell_start( 'فروشگاه افزونه‌ها', 'مرکز نصب و بروزرسانی افزونه‌های اختصاصی شما.' );
    echo '<div class="s-store-grid">';
    foreach ( $plugins as $p ) {
        $slug = sanitize_key( $p['slug'] ?? '' );
        if ( ! $slug ) continue;
        $local = $installed[ $slug ] ?? null;
        $state = $local ? ( $local['active'] ? 'فعال' : 'نصب‌شده' ) : 'نصب نشده';
        $class = $local ? ( $local['active'] ? 'active' : 'installed' ) : 'missing';
        echo '<article class="s-store-card">';
        echo '<div class="s-store-card-top"><div class="s-store-icon">🧩</div><span class="s-store-status ' . esc_attr( $class ) . '">' . esc_html( $state ) . '</span></div>';
        echo '<h2>' . esc_html( $p['name'] ?? $slug ) . '</h2>';
        echo '<p>' . esc_html( $p['description'] ?? '' ) . '</p>';
        echo '<div class="s-store-meta"><span>نسخه ' . esc_html( $p['version'] ?? '-' ) . '</span><code>' . esc_html( $slug ) . '</code></div>';
        echo '<div class="s-store-actions">';
        if ( ! $local && ! empty( $p['available'] ) && ! empty( $p['download_url'] ) ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=s_store_install&slug=' . rawurlencode( $slug ) ), 's_store_install_' . $slug );
            echo '<a class="button button-primary" href="' . esc_url( $url ) . '">نصب</a>';
        } elseif ( $local && ! $local['active'] ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=s_store_activate&plugin=' . rawurlencode( $local['file'] ) ), 's_store_activate_' . $local['file'] );
            echo '<a class="button button-primary" href="' . esc_url( $url ) . '">فعال‌سازی</a>';
        } elseif ( $local ) {
            echo '<span class="button disabled">فعال</span>';
        } else {
            echo '<span class="button disabled">به‌زودی</span>';
        }
        if ( ! empty( $p['homepage'] ) ) echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $p['homepage'] ) . '">GitHub</a>';
        echo '</div></article>';
    }
    echo '</div>';
    s_store_admin_shell_end();
}

function s_store_installed_page() {
    $installed = s_store_installed_map();
    s_store_admin_shell_start( 'افزونه‌های نصب‌شده', 'وضعیت افزونه‌های S Store روی این وردپرس.' );
    echo '<div class="s-store-panel"><table class="widefat striped"><thead><tr><th>افزونه</th><th>نسخه</th><th>وضعیت</th></tr></thead><tbody>';
    foreach ( s_store_manifest_plugins() as $p ) {
        $slug = $p['slug'] ?? '';
        if ( empty( $installed[ $slug ] ) ) continue;
        $x = $installed[ $slug ];
        echo '<tr><td><strong>' . esc_html( $p['name'] ?? $slug ) . '</strong><br><code>' . esc_html( $slug ) . '</code></td><td>' . esc_html( $x['version'] ) . '</td><td>' . ( $x['active'] ? 'فعال' : 'غیرفعال' ) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    s_store_admin_shell_end();
}

function s_store_updates_page() {
    if ( isset( $_POST['s_store_refresh'] ) && check_admin_referer( 's_store_refresh_manifest' ) ) {
        delete_site_transient( 's_store_manifest_v1' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
        echo '<div class="notice notice-success is-dismissible"><p>اطلاعات بروزرسانی تازه‌سازی شد.</p></div>';
    }
    s_store_admin_shell_start( 'بروزرسانی‌ها', 'نسخه‌های جدید از GitHub و Manifest مرکزی بررسی می‌شوند.' );
    echo '<div class="s-store-panel"><form method="post">';
    wp_nonce_field( 's_store_refresh_manifest' );
    submit_button( 'بررسی بروزرسانی‌ها', 'primary', 's_store_refresh', false );
    echo '</form><p>هر افزونه با slug ثابت خودش بروزرسانی می‌شود؛ تغییر نام فارسی باعث نصب افزونه جدید نمی‌شود.</p></div>';
    s_store_admin_shell_end();
}

function s_store_settings_page() {
    s_store_admin_shell_start( 'تنظیمات', 'تنظیمات عمومی S Store.' );
    echo '<div class="s-store-panel"><h2>منبع بروزرسانی</h2><p><code>' . esc_html( S_STORE_MANIFEST_URL ) . '</code></p><h2>Design System مشترک</h2><p>افزونه‌ها می‌توانند از تابع <code>s_store_register_submenu()</code> برای ثبت مستقیم صفحه تنظیمات زیر S Store استفاده کنند.</p></div>';
    s_store_admin_shell_end();
}

function s_store_about_page() {
    s_store_admin_shell_start( 'درباره S Store', 'فروشگاه افزونه‌های اختصاصی سهند رضوان.' );
    echo '<div class="s-store-panel"><h2>سهند رضوان · Sahand Rezvan</h2><p>مدیریت، نصب و بروزرسانی مرکزی افزونه‌های وردپرس.</p><p><a href="https://t.me/sahandse" target="_blank" rel="noopener">t.me/sahandse</a></p><p>نسخه ' . esc_html( S_STORE_VERSION ) . '</p></div>';
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
