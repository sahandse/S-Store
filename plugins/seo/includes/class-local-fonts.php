<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Local_Fonts {

    private static $instance = null;
    private $font_dir;
    private $font_url;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $upload          = wp_get_upload_dir();
        $this->font_dir  = $upload['basedir'] . '/wss-fonts';
        $this->font_url  = $upload['baseurl'] . '/wss-fonts';

        if (!is_dir($this->font_dir)) {
            wp_mkdir_p($this->font_dir);
        }

        $opts = (array) get_option('wss_local_fonts', []);

        if (!empty($opts['enabled'])) {
            add_filter('style_loader_tag', [ $this, 'intercept_google_fonts' ], 5, 4 );
        }

        add_action('wp_ajax_wss_download_font',   [ $this, 'ajax_download_font' ] );
        add_action('wp_ajax_wss_delete_font',     [ $this, 'ajax_delete_font' ] );
        add_action('wp_ajax_wss_list_fonts',      [ $this, 'ajax_list_fonts' ] );
        add_action('wp_ajax_wss_preview_font_css',[ $this, 'ajax_preview_font_css' ] );
    }

    // ─── Intercept Google Fonts ──────────────────────────────────────────────

    public function intercept_google_fonts($html, $handle, $href, $media) {
        if (strpos($href, 'fonts.googleapis.com') === false) return $html;

        $local_css = $this->get_local_css_for_url($href);
        if (!$local_css) return $html;

        // Replace with local version
        return "<style id='wss-local-{$handle}'>{$local_css}</style>\n";
    }

    private function get_local_css_for_url($google_url) {
        $hash    = md5($google_url);
        $css_file = $this->font_dir . "/font-{$hash}.css";

        if (file_exists($css_file)) {
            return file_get_contents($css_file);
        }

        return false;
    }

    // ─── Download font from Google ───────────────────────────────────────────

    public function ajax_download_font() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $font_url = esc_url_raw($_POST['font_url'] ?? '');
        $family   = sanitize_text_field($_POST['family'] ?? '');

        if (!$font_url || strpos($font_url, 'fonts.googleapis.com') === false) {
            wp_send_json_error('URL نامعتبر است. باید از fonts.googleapis.com باشد.');
        }

        $result = $this->download_and_host($font_url, $family);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function download_and_host($google_url, $family = '') {
        // Fetch CSS with modern user-agent to get WOFF2
        $response = wp_remote_get($google_url, [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
            'timeout'    => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $css = wp_remote_retrieve_body($response);
        if (empty($css)) return new WP_Error('empty', 'پاسخ CSS خالی است');

        $hash     = md5($google_url);
        $css_file = $this->font_dir . "/font-{$hash}.css";
        $font_subdir = $this->font_dir . "/files/{$hash}";

        if (!is_dir($font_subdir)) wp_mkdir_p($font_subdir);

        // Download each font file referenced in CSS
        preg_match_all('/url\(([^)]+)\)/i', $css, $url_matches);

        $downloaded = 0;
        foreach ($url_matches[1] as $font_file_url) {
            $font_file_url = trim($font_file_url, '"\'');
            if (strpos($font_file_url, 'fonts.gstatic.com') === false) continue;

            $ext      = pathinfo(parse_url($font_file_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'woff2';
            $filename = md5($font_file_url) . '.' . $ext;
            $local    = $font_subdir . '/' . $filename;

            if (!file_exists($local)) {
                $font_response = wp_remote_get($font_file_url, ['timeout' => 30]);
                if (!is_wp_error($font_response)) {
                    file_put_contents($local, wp_remote_retrieve_body($font_response));
                    $downloaded++;
                }
            }

            // Replace URL in CSS
            $local_url = $this->font_url . "/files/{$hash}/{$filename}";
            $css        = str_replace($font_file_url, $local_url, $css);
        }

        // Save local CSS
        file_put_contents($css_file, $css);

        // Save to options for display
        $fonts = (array) get_option('wss_downloaded_fonts', []);
        $fonts[$hash] = [
            'family'     => $family ?: 'Unknown',
            'source_url' => $google_url,
            'css_file'   => $css_file,
            'downloaded' => $downloaded,
            'date'       => current_time('Y-m-d H:i'),
        ];
        update_option('wss_downloaded_fonts', $fonts);

        return [
            'hash'       => $hash,
            'downloaded' => $downloaded,
            'css'        => mb_strimwidth($css, 0, 200, '...'),
        ];
    }

    public function ajax_delete_font() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $hash  = sanitize_key($_POST['hash'] ?? '');
        $fonts = (array) get_option('wss_downloaded_fonts', []);

        if (isset($fonts[$hash])) {
            // Delete files
            $css_file   = $this->font_dir . "/font-{$hash}.css";
            $font_subdir = $this->font_dir . "/files/{$hash}";

            if (file_exists($css_file)) unlink($css_file);
            if (is_dir($font_subdir)) {
                foreach (glob($font_subdir . '/*') as $f) unlink($f);
                rmdir($font_subdir);
            }

            unset($fonts[$hash]);
            update_option('wss_downloaded_fonts', $fonts);
        }

        wp_send_json_success();
    }

    public function ajax_list_fonts() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        $fonts = (array) get_option('wss_downloaded_fonts', []);
        wp_send_json_success($fonts);
    }

    public function ajax_preview_font_css() {
        check_ajax_referer('wss_admin_nonce', 'nonce');

        $font_url = esc_url_raw($_POST['font_url'] ?? '');
        $response = wp_remote_get($font_url, [
            'user-agent' => 'Mozilla/5.0 Chrome/120 Safari/537.36',
            'timeout'    => 10,
        ]);

        if (is_wp_error($response)) wp_send_json_error('خطا در دریافت');

        wp_send_json_success(['css' => wp_remote_retrieve_body($response)]);
    }

    public function get_downloaded_fonts() {
        return (array) get_option('wss_downloaded_fonts', []);
    }

    public static function get_common_fonts() {
        return [
            'Vazirmatn'  => 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;300;400;500;700;900&display=swap',
            'IRANSans'   => '',
            'Roboto'     => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
            'Open Sans'  => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap',
            'Lato'       => 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
            'Montserrat' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
            'Poppins'    => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
        ];
    }
}
