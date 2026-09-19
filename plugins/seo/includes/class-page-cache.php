<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Page_Cache {

    private static $instance = null;
    private $opts     = [];
    private $cache_dir;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/wss-cache';
        $this->opts      = (array) get_option( 'wss_cache', [] );

        if ( $this->opt('enabled') ) {
            $this->hooks();
        }

        // Always register management hooks
        add_action( 'save_post',           [ $this, 'clear_post_cache' ] );
        add_action( 'deleted_post',        [ $this, 'clear_post_cache' ] );
        add_action( 'switch_theme',        [ $this, 'clear_all' ] );
        add_action( 'upgrader_process_complete', [ $this, 'clear_all' ] );
        add_action( 'wss_cache_cleanup',   [ $this, 'clear_expired' ] );
        add_action( 'comment_post',        [ $this, 'handle_comment' ] );
        add_action( 'wp_trash_comment',    [ $this, 'handle_comment' ] );
    }

    private function opt( $key, $default = 0 ) {
        return $this->opts[ $key ] ?? $default;
    }

    private function hooks() {
        add_action( 'template_redirect', [ $this, 'start_cache' ], 0 );
    }

    // ─── Cache serve / write ────────────────────────────────────────────────

    public function start_cache() {
        if ( $this->should_skip() ) return;

        $file = $this->get_cache_file();

        if ( file_exists($file) ) {
            $ttl     = (int) $this->opt('ttl', 3600);
            $age     = time() - filemtime($file);
            if ( $age < $ttl ) {
                $this->serve_cache($file);
                return;
            }
            unlink($file);
        }

        ob_start( [ $this, 'write_cache' ] );
    }

    public function write_cache( $buffer ) {
        if ( empty(trim($buffer)) || is_404() || is_search() ) return $buffer;
        if ( strlen($buffer) < 1024 ) return $buffer; // skip tiny pages

        $file = $this->get_cache_file();
        $dir  = dirname($file);

        if ( ! is_dir($dir) ) {
            wp_mkdir_p($dir);
        }

        $comment = "\n<!-- WSS Page Cache: " . gmdate('Y-m-d H:i:s') . ' UTC -->';
        file_put_contents( $file, $buffer . $comment, LOCK_EX );

        return $buffer;
    }

    private function serve_cache( $file ) {
        header( 'X-WSS-Cache: HIT' );
        header( 'Cache-Control: public, max-age=' . (int)$this->opt('ttl', 3600) );

        if ( $this->opt('gzip_cache') && function_exists('gzencode') ) {
            $accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
            if ( strpos($accept, 'gzip') !== false ) {
                header('Content-Encoding: gzip');
                echo gzencode( file_get_contents($file), 6 );
                exit;
            }
        }

        readfile($file);
        exit;
    }

    // ─── Should skip? ───────────────────────────────────────────────────────

    private function should_skip() {
        // Logged-in users
        if ( is_user_logged_in() && ! $this->opt('cache_logged_in') ) return true;

        // Admin pages
        if ( is_admin() ) return true;

        // Only GET requests
        if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) return true;

        // Query strings (except UTM)
        $query = $_SERVER['QUERY_STRING'] ?? '';
        if ( $query ) {
            parse_str($query, $params);
            $skip_params = array_diff_key($params, array_flip(['utm_source','utm_medium','utm_campaign','utm_term','utm_content']));
            if ( $skip_params ) return true;
        }

        // WooCommerce cart/checkout
        if ( function_exists('is_cart') && is_cart() ) return true;
        if ( function_exists('is_checkout') && is_checkout() ) return true;
        if ( function_exists('is_account_page') && is_account_page() ) return true;

        // Cookies indicating session
        $skip_cookies = ['woocommerce_cart_hash', 'woocommerce_items_in_cart', 'wp_woocommerce_session_'];
        foreach ( $skip_cookies as $cookie ) {
            foreach ( $_COOKIE as $name => $val ) {
                if ( strpos($name, $cookie) === 0 ) return true;
            }
        }

        // Excluded pages
        $excluded = array_filter( array_map('trim', explode("\n", $this->opt('exclude_urls', '') ) ) );
        $request  = $_SERVER['REQUEST_URI'] ?? '/';
        foreach ( $excluded as $rule ) {
            if ( fnmatch($rule, $request) ) return true;
        }

        return false;
    }

    // ─── Cache file path ────────────────────────────────────────────────────

    private function get_cache_file() {
        $url  = (is_ssl() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        $hash = md5($url);
        $path = $this->cache_dir . '/' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
        $path = rtrim($path, '/') . '/index_' . $hash . '.html';
        return $path;
    }

    // ─── Cache clearing ─────────────────────────────────────────────────────

    public function clear_all() {
        $this->rmdir_recursive( $this->cache_dir );
        wp_mkdir_p( $this->cache_dir );
        update_option('wss_cache_cleared', current_time('mysql'));
    }

    public function clear_post_cache( $post_id ) {
        if ( wp_is_post_revision($post_id) ) return;

        $url  = get_permalink($post_id);
        if ( ! $url ) return;

        $hash = md5($url);
        $path = $this->cache_dir . '/' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
        $file = rtrim($path, '/') . '/index_' . $hash . '.html';

        if ( file_exists($file) ) unlink($file);

        // Also clear home and archives
        $this->clear_url(home_url('/'));
    }

    public function clear_url( $url ) {
        $hash = md5($url);
        $path = $this->cache_dir . '/' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
        $file = rtrim($path, '/') . '/index_' . $hash . '.html';
        if ( file_exists($file) ) unlink($file);
    }

    public function handle_comment( $comment_id ) {
        $comment = get_comment($comment_id);
        if ( $comment ) {
            $this->clear_post_cache($comment->comment_post_ID);
        }
    }

    public function clear_expired() {
        $ttl = (int) $this->opt('ttl', 3600);
        $this->clear_expired_recursive($this->cache_dir, $ttl);
    }

    private function clear_expired_recursive($dir, $ttl) {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*') as $item) {
            if (is_dir($item)) {
                $this->clear_expired_recursive($item, $ttl);
            } elseif (is_file($item) && (time() - filemtime($item)) > $ttl) {
                unlink($item);
            }
        }
    }

    private function rmdir_recursive($dir) {
        if (!is_dir($dir)) return;
        $items = glob($dir . '/{,.}*', GLOB_BRACE);
        foreach ($items as $item) {
            if ($item === $dir . '/.' || $item === $dir . '/..') continue;
            is_dir($item) ? $this->rmdir_recursive($item) : unlink($item);
        }
    }

    // ─── Stats ──────────────────────────────────────────────────────────────

    public function get_stats() {
        $count = 0;
        $size  = 0;
        if ( is_dir($this->cache_dir) ) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->cache_dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'html') {
                    $count++;
                    $size += $file->getSize();
                }
            }
        }
        return [
            'count'   => $count,
            'size'    => $this->format_bytes($size),
            'cleared' => get_option('wss_cache_cleared', 'هرگز'),
        ];
    }

    private function format_bytes($bytes) {
        if ($bytes > 1048576) return round($bytes/1048576, 2) . ' MB';
        if ($bytes > 1024)    return round($bytes/1024, 2)    . ' KB';
        return $bytes . ' B';
    }
}
