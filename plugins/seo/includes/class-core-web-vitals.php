<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Core_Web_Vitals {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wss_fetch_vitals',      [ $this, 'ajax_fetch_vitals' ] );
        add_action('wp_ajax_wss_fetch_vitals_url',  [ $this, 'ajax_fetch_vitals_url' ] );
    }

    // ─── Fetch via PageSpeed Insights API ────────────────────────────────────

    public function ajax_fetch_vitals() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $url      = esc_url_raw($_POST['url'] ?? home_url('/'));
        $strategy = sanitize_key($_POST['strategy'] ?? 'mobile');

        $result = $this->fetch_pagespeed($url, $strategy);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_fetch_vitals_url() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $url      = esc_url_raw($_POST['url'] ?? '');
        $strategy = sanitize_key($_POST['strategy'] ?? 'mobile');

        if (!$url) wp_send_json_error('URL الزامی است');

        $result = $this->fetch_pagespeed($url, $strategy);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Cache result
        $cache_key = 'wss_vitals_' . md5($url . $strategy);
        set_transient($cache_key, $result, HOUR_IN_SECONDS * 2);

        wp_send_json_success($result);
    }

    public function fetch_pagespeed($url, $strategy = 'mobile') {
        $wm_opts = (array) get_option('wss_webmaster', []);
        $api_key = $wm_opts['pagespeed_key'] ?? '';

        $api_url = add_query_arg([
            'url'      => urlencode($url),
            'strategy' => $strategy,
            'category' => ['performance','seo','accessibility','best-practices'],
            'key'      => $api_key ?: '',
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');

        $response = wp_remote_get($api_url, [
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $msg  = $body['error']['message'] ?? "خطای HTTP $code";
            return new WP_Error('api_error', $msg);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data)) return new WP_Error('parse', 'پاسخ نامعتبر');

        return $this->parse_pagespeed($data, $url, $strategy);
    }

    private function parse_pagespeed($data, $url, $strategy) {
        $cats    = $data['lighthouseResult']['categories'] ?? [];
        $audits  = $data['lighthouseResult']['audits']    ?? [];
        $metrics = $data['loadingExperience']['metrics']  ?? [];

        // Category scores
        $scores = [];
        foreach ($cats as $key => $cat) {
            $scores[$key] = round(($cat['score'] ?? 0) * 100);
        }

        // Core Web Vitals from loading experience
        $cwv = [];
        $cwv_map = [
            'FIRST_CONTENTFUL_PAINT_MS'        => ['FCP', 'First Contentful Paint'],
            'LARGEST_CONTENTFUL_PAINT_MS'       => ['LCP', 'Largest Contentful Paint'],
            'FIRST_INPUT_DELAY_MS'              => ['FID', 'First Input Delay'],
            'CUMULATIVE_LAYOUT_SHIFT_SCORE'     => ['CLS', 'Cumulative Layout Shift'],
            'INTERACTION_TO_NEXT_PAINT'         => ['INP', 'Interaction to Next Paint'],
            'EXPERIMENTAL_TIME_TO_FIRST_BYTE'   => ['TTFB', 'Time to First Byte'],
        ];

        foreach ($cwv_map as $key => [$abbr, $label]) {
            if (isset($metrics[$key])) {
                $m = $metrics[$key];
                $cwv[$abbr] = [
                    'label'      => $label,
                    'percentile' => $m['percentile'] ?? null,
                    'category'   => $m['category']   ?? null,
                    'good'       => isset($m['distributions'][0]) ? round($m['distributions'][0]['proportion'] * 100) : null,
                    'ni'         => isset($m['distributions'][1]) ? round($m['distributions'][1]['proportion'] * 100) : null,
                    'poor'       => isset($m['distributions'][2]) ? round($m['distributions'][2]['proportion'] * 100) : null,
                ];
            }
        }

        // Lab data
        $lab = [];
        $lab_metrics = [
            'first-contentful-paint'    => 'FCP',
            'largest-contentful-paint'  => 'LCP',
            'total-blocking-time'       => 'TBT',
            'cumulative-layout-shift'   => 'CLS',
            'speed-index'               => 'Speed Index',
            'interactive'               => 'Time to Interactive',
            'server-response-time'      => 'TTFB',
        ];

        foreach ($lab_metrics as $audit_key => $name) {
            if (isset($audits[$audit_key])) {
                $a = $audits[$audit_key];
                $lab[$name] = [
                    'value'    => $a['displayValue'] ?? '',
                    'score'    => $a['score'] ?? null,
                    'numeric'  => $a['numericValue'] ?? null,
                ];
            }
        }

        // Top opportunities
        $opportunities = [];
        foreach ($audits as $key => $audit) {
            if (($audit['score'] ?? 1) < 0.9 && isset($audit['details']['overallSavingsMs'])) {
                $opportunities[] = [
                    'title'   => $audit['title'] ?? $key,
                    'savings' => round($audit['details']['overallSavingsMs'] ?? 0),
                    'impact'  => $audit['score'] ?? 1,
                ];
            }
        }

        usort($opportunities, fn($a,$b) => $b['savings'] - $a['savings']);
        $opportunities = array_slice($opportunities, 0, 8);

        // Screenshot
        $screenshot = $audits['final-screenshot']['details']['data'] ?? null;

        return [
            'url'           => $url,
            'strategy'      => $strategy,
            'scores'        => $scores,
            'cwv'           => $cwv,
            'lab'           => $lab,
            'opportunities' => $opportunities,
            'screenshot'    => $screenshot,
            'fetched_at'    => current_time('Y-m-d H:i:s'),
        ];
    }

    public function get_cached($url, $strategy = 'mobile') {
        $cache_key = 'wss_vitals_' . md5($url . $strategy);
        return get_transient($cache_key);
    }
}
