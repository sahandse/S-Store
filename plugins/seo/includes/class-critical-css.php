<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Critical_CSS {

    private static $instance = null;
    private $opts = [];
    private $cache_dir;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/wss-critical-css';
        $this->opts      = (array) get_option('wss_critical_css', []);

        if ($this->opt('enabled')) {
            add_action('wp_head',   [ $this, 'inject_critical_css' ], 1 );
            add_filter('style_loader_tag', [ $this, 'defer_non_critical' ], 10, 4 );
        }

        add_action('wp_ajax_wss_save_critical',   [ $this, 'ajax_save' ] );
        add_action('wp_ajax_wss_delete_critical', [ $this, 'ajax_delete' ] );
    }

    private function opt($key, $default = '') {
        return $this->opts[$key] ?? $default;
    }

    // ─── Inject critical CSS inline ──────────────────────────────────────────

    public function inject_critical_css() {
        $css = $this->get_critical_for_current_page();
        if (!$css) return;

        echo '<style id="wss-critical-css">' . $css . '</style>' . "\n";
    }

    private function get_critical_for_current_page() {
        // Check for page-specific critical CSS
        if (is_singular()) {
            $post_id  = get_the_ID();
            $specific = get_post_meta($post_id, '_wss_critical_css', true);
            if ($specific) return $specific;
        }

        // Check for template-based critical CSS
        if (is_front_page()) {
            $css = get_option('wss_critical_css_home');
            if ($css) return $css;
        }

        if (is_single()) {
            $css = get_option('wss_critical_css_single');
            if ($css) return $css;
        }

        if (is_page()) {
            $css = get_option('wss_critical_css_page');
            if ($css) return $css;
        }

        // Global critical CSS
        return get_option('wss_critical_css_global', '');
    }

    // ─── Defer non-critical stylesheets ──────────────────────────────────────

    public function defer_non_critical($html, $handle, $href, $media) {
        if (is_admin()) return $html;

        // Skip if explicitly marked as critical
        $critical_handles = array_filter(array_map('trim', explode(',', $this->opt('critical_handles', ''))));
        if (in_array($handle, $critical_handles)) return $html;

        // Add preload trick for deferred loading
        $noscript = str_replace("rel='stylesheet'", "rel='stylesheet'", $html);
        $preload  = str_replace(
            ["rel='stylesheet'", 'rel="stylesheet"'],
            ["rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
             'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"'],
            $html
        );

        return $preload . '<noscript>' . $noscript . '</noscript>';
    }

    // ─── Auto-extract (basic heuristic) ─────────────────────────────────────

    public function extract_from_html($html, $viewport_height = 800) {
        // Extract all inline styles
        $css_parts = [];

        // Get all <style> blocks
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $style_matches);
        foreach ($style_matches[1] as $style) {
            $css_parts[] = $style;
        }

        // Extract critical rules (simplified - above-the-fold rules)
        $combined = implode("\n", $css_parts);

        // Keep: resets, typography, layout, header, nav, hero, body, html
        $critical_selectors = ['html','body','*','header','nav','.nav','.header',
                                'h1','h2','h3','p','a','img','.hero','.banner',
                                '.wrapper','.container','main','article','.site'];

        $rules      = $this->parse_css_rules($combined);
        $critical   = [];

        foreach ($rules as $selector => $props) {
            foreach ($critical_selectors as $critical_sel) {
                if (strpos(strtolower($selector), $critical_sel) !== false) {
                    $critical[$selector] = $props;
                    break;
                }
            }
        }

        $output = '';
        foreach ($critical as $sel => $props) {
            $output .= $sel . '{' . $props . '}';
        }

        return $this->minify($output);
    }

    private function parse_css_rules($css) {
        $rules  = [];
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        // Remove @media, @keyframes blocks (simplified)
        $css = preg_replace('/@(?:media|keyframes|font-face)[^{]*\{(?:[^{}]*|\{[^{}]*\})*\}/s', '', $css);

        preg_match_all('/([^{]+)\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $rules[trim($m[1])] = trim($m[2]);
        }

        return $rules;
    }

    private function minify($css) {
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace([': ', ' {', '} '], [':', '{', '}'], $css);
        return trim($css);
    }

    // ─── AJAX ────────────────────────────────────────────────────────────────

    public function ajax_save() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $type    = sanitize_key($_POST['type'] ?? 'global');
        $css     = wp_strip_all_tags($_POST['css'] ?? '');
        $post_id = (int)($_POST['post_id'] ?? 0);

        if ($post_id && $type === 'post') {
            update_post_meta($post_id, '_wss_critical_css', $css);
        } else {
            update_option("wss_critical_css_{$type}", $css);
        }

        wp_send_json_success('Critical CSS ذخیره شد.');
    }

    public function ajax_delete() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $type = sanitize_key($_POST['type'] ?? 'global');
        delete_option("wss_critical_css_{$type}");
        wp_send_json_success();
    }

    public function get_types() {
        return [
            'global' => ['صفحات عمومی', get_option('wss_critical_css_global','')],
            'home'   => ['صفحه اصلی',   get_option('wss_critical_css_home','')],
            'single' => ['پست‌های مفرد', get_option('wss_critical_css_single','')],
            'page'   => ['صفحات',        get_option('wss_critical_css_page','')],
        ];
    }
}
