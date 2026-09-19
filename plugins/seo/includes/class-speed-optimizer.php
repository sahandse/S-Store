<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Speed_Optimizer {

    private static $instance = null;
    private $opts = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->opts = (array) get_option( 'wss_speed', [] );
        $this->hooks();
    }

    private function opt( $key, $default = 0 ) {
        return isset( $this->opts[ $key ] ) ? $this->opts[ $key ] : $default;
    }

    private function hooks() {
        // HTML Minify
        if ( $this->opt('minify_html') ) {
            add_action( 'template_redirect', [ $this, 'start_html_minify' ], 0 );
        }

        // Remove emoji
        if ( $this->opt('disable_emoji') ) {
            remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
            remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            remove_action( 'wp_print_styles', 'print_emoji_styles' );
            remove_action( 'admin_print_styles', 'print_emoji_styles' );
            remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
            remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
            remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
            add_filter( 'tiny_mce_plugins', [ $this, 'remove_tinymce_emoji' ] );
            add_filter( 'wp_resource_hints', [ $this, 'remove_emoji_dns_prefetch' ], 10, 2 );
        }

        // Remove embeds
        if ( $this->opt('disable_embeds') ) {
            add_action( 'init', [ $this, 'disable_embeds' ], 9999 );
        }

        // Disable XML-RPC
        if ( $this->opt('disable_xmlrpc') ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
            add_filter( 'wp_headers', [ $this, 'remove_x_pingback' ] );
            remove_action( 'wp_head', 'rsd_link' );
            remove_action( 'wp_head', 'wlwmanifest_link' );
        }

        // Disable dashicons for guests
        if ( $this->opt('disable_dashicons') && ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'disable_dashicons' ] );
        }

        // Remove jQuery migrate
        if ( $this->opt('remove_jquery_migrate') ) {
            add_action( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ] );
        }

        // Defer JS
        if ( $this->opt('defer_js') || $this->opt('async_js') ) {
            add_filter( 'script_loader_tag', [ $this, 'add_script_attributes' ], 10, 3 );
        }

        // Remove query strings
        if ( $this->opt('remove_query_str') ) {
            add_filter( 'style_loader_src',  [ $this, 'remove_query_strings' ] );
            add_filter( 'script_loader_src', [ $this, 'remove_query_strings' ] );
        }

        // Lazy load
        if ( $this->opt('lazy_images') || $this->opt('lazy_iframes') ) {
            add_filter( 'the_content',           [ $this, 'add_lazy_loading' ] );
            add_filter( 'post_thumbnail_html',   [ $this, 'add_lazy_loading' ] );
            add_filter( 'get_avatar',            [ $this, 'add_lazy_loading' ] );
            add_action( 'wp_head',               [ $this, 'lazy_load_noscript_style' ] );
        }

        // DNS Prefetch
        $dns = $this->opt('dns_prefetch', '');
        if ( $dns ) {
            add_action( 'wp_head', [ $this, 'output_dns_prefetch' ], 1 );
        }

        // Preconnect
        $preconnect = $this->opt('preconnect', '');
        if ( $preconnect ) {
            add_action( 'wp_head', [ $this, 'output_preconnect' ], 1 );
        }

        // Google Fonts async
        if ( $this->opt('google_fonts_async') ) {
            add_filter( 'style_loader_tag', [ $this, 'async_google_fonts' ], 10, 2 );
        }

        // Preload fonts
        $preload = $this->opt('preload_fonts', '');
        if ( $preload ) {
            add_action( 'wp_head', [ $this, 'output_preload_fonts' ], 1 );
        }

        // Database cleanup cron
        add_action( 'wss_db_cleanup', [ $this, 'cleanup_database' ] );

        // Browser cache headers
        if ( $this->opt('browser_cache') && ! is_admin() ) {
            add_action( 'send_headers', [ $this, 'set_cache_headers' ] );
        }

        // GZIP via ob_gzhandler if not already enabled
        if ( $this->opt('gzip') && ! is_admin() && ! ini_get('zlib.output_compression') ) {
            if ( extension_loaded('zlib') ) {
                add_action( 'init', [ $this, 'enable_gzip' ], -1 );
            }
        }

        // Remove unneeded head links
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'feed_links',          2 );
        remove_action( 'wp_head', 'feed_links_extra',    3 );
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
    }

    // ─── HTML Minify ───────────────────────────────────────────────────────────

    public function start_html_minify() {
        // Skip on WooCommerce pages — inline scripts (nonces, variation data) can break
        if ( ! is_admin() && ! is_feed() && ! $this->is_woocommerce_page() ) {
            ob_start( [ $this, 'minify_html_output' ] );
        }
    }

    public function minify_html_output( $html ) {
        if ( empty( trim( $html ) ) ) return $html;

        // Preserve pre/script/style/textarea blocks
        $preserved = [];
        $counter   = 0;

        $patterns = [
            '/<pre[^>]*>.*?<\/pre>/is',
            '/<script[^>]*>.*?<\/script>/is',
            '/<style[^>]*>.*?<\/style>/is',
            '/<textarea[^>]*>.*?<\/textarea>/is',
        ];

        foreach ( $patterns as $pattern ) {
            $html = preg_replace_callback( $pattern, function( $m ) use ( &$preserved, &$counter ) {
                $placeholder = "<!--WSS_PRESERVE_{$counter}-->";
                $preserved[ $placeholder ] = $m[0];
                $counter++;
                return $placeholder;
            }, $html );
        }

        // Minify HTML
        $html = preg_replace( '/<!--(?!\[if)(?!WSS_PRESERVE).*?-->/s', '', $html ); // remove comments
        $html = preg_replace( '/\s+/', ' ', $html );
        $html = preg_replace( '/>\s+</', '><', $html );
        $html = str_replace( [ ' />', '/>' ], '>', $html );

        // Inline CSS minify
        if ( $this->opt('minify_css') ) {
            $html = preg_replace_callback( '/<style[^>]*>(.*?)<\/style>/is', function( $m ) {
                return str_replace( $m[1], $this->minify_css( $m[1] ), $m[0] );
            }, $html );
        }

        // Inline JS minify
        if ( $this->opt('minify_js') ) {
            $html = preg_replace_callback( '/<script(?![^>]*src)[^>]*>(.*?)<\/script>/is', function( $m ) {
                $content = trim( $m[1] );
                if ( ! empty( $content ) ) {
                    $content = preg_replace( '/\/\/[^\n]*\n/', "\n", $content );
                    $content = preg_replace( '/\/\*.*?\*\//s', '', $content );
                    $content = preg_replace( '/\s+/', ' ', $content );
                    $content = trim( $content );
                    return str_replace( $m[1], $content, $m[0] );
                }
                return $m[0];
            }, $html );
        }

        // Restore preserved blocks
        foreach ( $preserved as $placeholder => $block ) {
            $html = str_replace( $placeholder, $block, $html );
        }

        return trim( $html );
    }

    private function minify_css( $css ) {
        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
        $css = preg_replace( '/\s+/', ' ', $css );
        $css = str_replace( [ ': ', ' :', ' {', '{ ', ' }', '} ', '; ', ' ;' ], [ ':', ':', '{', '{', '}', '}', ';', ';' ], $css );
        return trim( $css );
    }

    // ─── WooCommerce compatibility ────────────────────────────────────────────

    private function is_woocommerce_page() {
        if ( ! function_exists('is_woocommerce') ) return false;
        return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
    }

    // ─── Script attributes ─────────────────────────────────────────────────────

    private $no_defer_scripts = [
        // jQuery core
        'jquery', 'jquery-core', 'jquery-ui-core', 'jquery-migrate',
        // WooCommerce — never defer these or variation/cart/checkout breaks
        'woocommerce', 'wc-add-to-cart', 'wc-add-to-cart-variation',
        'wc-cart-fragments', 'wc-checkout', 'wc-country-select',
        'wc-address-i18n', 'wc-credit-card-form', 'wc-password-strength-meter',
        'wc-single-product', 'wc-cart', 'wc-jquery-payment', 'wc-price-slider',
        'wc-account', 'accounting', 'jquery-blockui', 'prettyPhoto',
        'wc-blocks-checkout', 'wc-blocks-cart', 'wc-blocks-middleware',
        'wp-hooks', 'wp-element', 'wp-i18n', 'wp-blocks',
    ];

    public function add_script_attributes( $tag, $handle, $src ) {
        if ( is_admin() ) return $tag;

        // Skip entirely on WooCommerce pages — any defer/async can break variation
        // pickers, add-to-cart AJAX, cart fragments, and checkout form submission
        if ( $this->is_woocommerce_page() ) return $tag;

        if ( in_array( $handle, $this->no_defer_scripts, true ) ) return $tag;

        // Skip scripts from WooCommerce plugin directory
        if ( strpos( $src, '/woocommerce/' ) !== false ) return $tag;

        if ( strpos( $tag, 'defer' ) !== false || strpos( $tag, 'async' ) !== false ) {
            return $tag;
        }

        if ( $this->opt('defer_js') ) {
            return str_replace( ' src', ' defer src', $tag );
        }
        if ( $this->opt('async_js') ) {
            return str_replace( ' src', ' async src', $tag );
        }
        return $tag;
    }

    // ─── Remove query strings ──────────────────────────────────────────────────

    public function remove_query_strings( $src ) {
        if ( strpos( $src, '?' ) !== false ) {
            $src = strtok( $src, '?' );
        }
        return $src;
    }

    // ─── Lazy loading ──────────────────────────────────────────────────────────

    public function add_lazy_loading( $content ) {
        if ( $this->opt('lazy_images') ) {
            $content = preg_replace_callback( '/<img([^>]+)>/i', function( $matches ) {
                $attrs = $matches[1];
                if ( strpos( $attrs, 'loading=' ) === false ) {
                    $attrs .= ' loading="lazy"';
                }
                if ( strpos( $attrs, 'decoding=' ) === false ) {
                    $attrs .= ' decoding="async"';
                }
                return '<img' . $attrs . '>';
            }, $content );
        }

        if ( $this->opt('lazy_iframes') ) {
            $content = preg_replace_callback( '/<iframe([^>]+)>/i', function( $matches ) {
                $attrs = $matches[1];
                if ( strpos( $attrs, 'loading=' ) === false ) {
                    $attrs .= ' loading="lazy"';
                }
                return '<iframe' . $attrs . '>';
            }, $content );
        }

        return $content;
    }

    public function lazy_load_noscript_style() {
        echo '<noscript><style>img[loading="lazy"],iframe[loading="lazy"]{display:block}</style></noscript>' . "\n";
    }

    // ─── Emoji ─────────────────────────────────────────────────────────────────

    public function remove_tinymce_emoji( $plugins ) {
        return array_diff( $plugins, [ 'wpemoji' ] );
    }

    public function remove_emoji_dns_prefetch( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, function( $url ) {
                return false === strpos( $url, 'twemoji' ) && false === strpos( $url, 's.w.org' );
            });
        }
        return $urls;
    }

    // ─── Embeds ────────────────────────────────────────────────────────────────

    public function disable_embeds() {
        global $wp;
        $wp->public_query_vars = array_diff( $wp->public_query_vars, [ 'embed' ] );
        remove_action( 'rest_api_init',       'wp_oembed_register_route' );
        remove_filter( 'oembed_dataparse',    'wp_filter_oembed_result' );
        remove_action( 'wp_head',             'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head',             'wp_oembed_add_host_js' );
        remove_action( 'embed_head',          'enqueue_embed_scripts',    9 );
        remove_filter( 'pre_oembed_result',   'wp_filter_pre_oembed_result' );
        wp_deregister_script( 'wp-embed' );
    }

    // ─── Dashicons ─────────────────────────────────────────────────────────────

    public function disable_dashicons() {
        if ( ! is_user_logged_in() ) {
            wp_deregister_style( 'dashicons' );
        }
    }

    // ─── jQuery migrate ────────────────────────────────────────────────────────

    public function remove_jquery_migrate( $scripts ) {
        // Never remove jQuery migrate when WooCommerce is active — it depends on it
        if ( class_exists('WooCommerce') ) return;
        if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
            $deps = $scripts->registered['jquery']->deps;
            $scripts->registered['jquery']->deps = array_diff( $deps, [ 'jquery-migrate' ] );
        }
    }

    // ─── DNS Prefetch ──────────────────────────────────────────────────────────

    public function output_dns_prefetch() {
        $domains = array_filter( array_map( 'trim', explode( "\n", $this->opt('dns_prefetch', '') ) ) );
        foreach ( $domains as $domain ) {
            printf( '<link rel="dns-prefetch" href="%s">' . "\n", esc_url( $domain ) );
        }
    }

    // ─── Preconnect ────────────────────────────────────────────────────────────

    public function output_preconnect() {
        $origins = array_filter( array_map( 'trim', explode( "\n", $this->opt('preconnect', '') ) ) );
        foreach ( $origins as $origin ) {
            printf( '<link rel="preconnect" href="%s" crossorigin>' . "\n", esc_url( $origin ) );
        }
    }

    // ─── Google Fonts async ────────────────────────────────────────────────────

    public function async_google_fonts( $tag, $handle ) {
        if ( strpos( $tag, 'fonts.googleapis.com' ) !== false ) {
            $tag = str_replace( "rel='stylesheet'", "rel='preload' as='style' onload=\"this.rel='stylesheet'\"", $tag );
            $tag .= '<noscript>' . str_replace( "rel='preload' as='style' onload=\"this.rel='stylesheet'\"", "rel='stylesheet'", $tag ) . '</noscript>';
        }
        return $tag;
    }

    // ─── Preload fonts ─────────────────────────────────────────────────────────

    public function output_preload_fonts() {
        $fonts = array_filter( array_map( 'trim', explode( "\n", $this->opt('preload_fonts', '') ) ) );
        foreach ( $fonts as $font ) {
            $ext = strtolower( pathinfo( $font, PATHINFO_EXTENSION ) );
            $type_map = [ 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf' ];
            $type = $type_map[ $ext ] ?? 'font/woff2';
            printf( '<link rel="preload" href="%s" as="font" type="%s" crossorigin>' . "\n", esc_url( $font ), $type );
        }
    }

    // ─── Remove X-Pingback ─────────────────────────────────────────────────────

    public function remove_x_pingback( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    // ─── Cache headers ─────────────────────────────────────────────────────────

    public function set_cache_headers() {
        if ( is_user_logged_in() || is_admin() ) return;

        $max_age = 2592000; // 30 days
        header( 'Cache-Control: public, max-age=' . $max_age );
        header( 'Vary: Accept-Encoding' );
        header( 'Pragma: public' );
    }

    // ─── GZIP ──────────────────────────────────────────────────────────────────

    public function enable_gzip() {
        if ( ! ob_get_level() ) {
            ob_start( 'ob_gzhandler' );
        }
    }

    // ─── Database cleanup ──────────────────────────────────────────────────────

    public function cleanup_database() {
        global $wpdb;

        // Post revisions
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'inherit' AND post_type = 'revision'" );

        // Auto-drafts
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

        // Trashed posts
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );

        // Orphan post meta
        $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );

        // Spam comments
        $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
        $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

        // Orphan comment meta
        $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" );

        // Expired transients
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()" );

        // Optimize tables
        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
        foreach ( $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE `{$table}`" );
        }

        update_option( 'wss_last_cleanup', current_time('mysql') );
    }

    public function get_database_stats() {
        global $wpdb;

        return [
            'revisions'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'" ),
            'auto_drafts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='auto-draft'" ),
            'trash_posts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='trash'" ),
            'spam_comments'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'" ),
            'transients'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ),
            'last_cleanup'   => get_option('wss_last_cleanup', 'هرگز'),
        ];
    }
}
