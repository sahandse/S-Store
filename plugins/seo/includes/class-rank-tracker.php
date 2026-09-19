<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Rank_Tracker {

    private static $instance = null;
    private $table;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wss_keywords';

        add_action( 'wp_ajax_wss_add_keyword',      [ $this, 'ajax_add_keyword' ] );
        add_action( 'wp_ajax_wss_delete_keyword',   [ $this, 'ajax_delete_keyword' ] );
        add_action( 'wp_ajax_wss_update_rank',      [ $this, 'ajax_update_rank' ] );
        add_action( 'wp_ajax_wss_check_serp',       [ $this, 'ajax_check_serp' ] );
        add_action( 'wss_rank_check',               [ $this, 'auto_check_all' ] );
    }

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'wss_keywords';

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id           bigint(20) NOT NULL AUTO_INCREMENT,
            keyword      varchar(512) NOT NULL,
            url          varchar(1024),
            current_rank smallint(6) DEFAULT NULL,
            prev_rank    smallint(6) DEFAULT NULL,
            best_rank    smallint(6) DEFAULT NULL,
            search_engine varchar(20) DEFAULT 'google',
            country      varchar(10) DEFAULT 'ir',
            last_checked datetime,
            history      longtext,
            notes        text,
            created_at   datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // ─── AJAX handlers ──────────────────────────────────────────────────────

    public function ajax_add_keyword() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        global $wpdb;

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $url     = esc_url_raw($_POST['url'] ?? '');
        $engine  = sanitize_key($_POST['engine'] ?? 'google');
        $country = sanitize_key($_POST['country'] ?? 'ir');
        $notes   = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$keyword) wp_send_json_error('کلمه کلیدی الزامی است');

        $wpdb->insert($this->table, [
            'keyword'       => $keyword,
            'url'           => $url,
            'search_engine' => $engine,
            'country'       => $country,
            'notes'         => $notes,
            'history'       => json_encode([]),
        ]);

        wp_send_json_success(['id' => $wpdb->insert_id]);
    }

    public function ajax_delete_keyword() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        global $wpdb;
        $id = (int)($_POST['id'] ?? 0);
        $wpdb->delete($this->table, ['id' => $id]);
        wp_send_json_success();
    }

    public function ajax_update_rank() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        global $wpdb;

        $id   = (int)($_POST['id'] ?? 0);
        $rank = (int)($_POST['rank'] ?? 0);

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id));
        if (!$row) wp_send_json_error();

        $history = json_decode($row->history ?: '[]', true);
        $history[] = [
            'date' => current_time('Y-m-d'),
            'rank' => $rank,
        ];

        // Keep last 90 days
        if (count($history) > 90) array_shift($history);

        $best = $row->best_rank ? min($row->best_rank, $rank) : $rank;

        $wpdb->update($this->table, [
            'prev_rank'    => $row->current_rank,
            'current_rank' => $rank,
            'best_rank'    => $best,
            'last_checked' => current_time('mysql'),
            'history'      => json_encode($history),
        ], ['id' => $id]);

        wp_send_json_success([
            'change' => $row->current_rank ? $row->current_rank - $rank : 0,
        ]);
    }

    public function ajax_check_serp() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $id = (int)($_POST['id'] ?? 0);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id));
        if (!$row) wp_send_json_error();

        $rank = $this->fetch_rank($row->keyword, $row->url, $row->search_engine, $row->country);

        if ($rank !== null) {
            $history = json_decode($row->history ?: '[]', true);
            $history[] = ['date' => current_time('Y-m-d'), 'rank' => $rank];
            if (count($history) > 90) array_shift($history);

            $best = $row->best_rank ? min($row->best_rank, $rank) : $rank;

            $wpdb->update($this->table, [
                'prev_rank'    => $row->current_rank,
                'current_rank' => $rank,
                'best_rank'    => $best,
                'last_checked' => current_time('mysql'),
                'history'      => json_encode($history),
            ], ['id' => $id]);
        }

        wp_send_json_success(['rank' => $rank ?? 'N/A']);
    }

    // ─── SERP check ─────────────────────────────────────────────────────────

    private function fetch_rank($keyword, $target_url, $engine = 'google', $country = 'ir') {
        if (!$target_url) return null;

        $domain = parse_url($target_url, PHP_URL_HOST) ?: parse_url(home_url('/'), PHP_URL_HOST);

        // Use ValueSERP or similar free API if configured
        $api_key = get_option('wss_serp_api_key');

        if ($api_key) {
            return $this->fetch_via_api($keyword, $domain, $api_key, $engine, $country);
        }

        // Fallback: simple Google scrape (limited, may be blocked)
        return $this->fetch_via_scrape($keyword, $domain, $country);
    }

    private function fetch_via_api($keyword, $domain, $api_key, $engine, $country) {
        $params = http_build_query([
            'api_key'  => $api_key,
            'q'        => $keyword,
            'gl'       => strtoupper($country),
            'hl'       => 'fa',
            'num'      => 100,
            'engine'   => $engine,
        ]);

        $response = wp_remote_get("https://api.valueserp.com/search?{$params}", [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return null;

        $data    = json_decode(wp_remote_retrieve_body($response), true);
        $results = $data['organic_results'] ?? [];

        foreach ($results as $i => $r) {
            $result_domain = parse_url($r['link'] ?? '', PHP_URL_HOST);
            if ($result_domain && (strpos($result_domain, $domain) !== false || strpos($domain, $result_domain) !== false)) {
                return $i + 1;
            }
        }

        return 100; // Not found in top 100
    }

    private function fetch_via_scrape($keyword, $domain, $country) {
        // Simple DuckDuckGo check as Google blocks scraping
        $url      = 'https://html.duckduckgo.com/html/?q=' . urlencode($keyword);
        $response = wp_remote_get($url, [
            'timeout'    => 10,
            'user-agent' => 'Mozilla/5.0',
        ]);

        if (is_wp_error($response)) return null;

        $body = wp_remote_retrieve_body($response);
        preg_match_all('/<a[^>]+class="result__url"[^>]*>([^<]+)<\/a>/i', $body, $matches);

        foreach ($matches[1] as $i => $result_domain) {
            $result_domain = trim($result_domain);
            if (strpos($result_domain, $domain) !== false) {
                return $i + 1;
            }
        }

        return null;
    }

    public function auto_check_all() {
        global $wpdb;
        $keywords = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY last_checked ASC LIMIT 20");
        foreach ($keywords as $row) {
            $rank = $this->fetch_rank($row->keyword, $row->url, $row->search_engine, $row->country);
            if ($rank !== null) {
                $history   = json_decode($row->history ?: '[]', true);
                $history[] = ['date' => current_time('Y-m-d'), 'rank' => $rank];
                if (count($history) > 90) array_shift($history);
                $best = $row->best_rank ? min($row->best_rank, $rank) : $rank;
                $wpdb->update($this->table, [
                    'prev_rank'    => $row->current_rank,
                    'current_rank' => $rank,
                    'best_rank'    => $best,
                    'last_checked' => current_time('mysql'),
                    'history'      => json_encode($history),
                ], ['id' => $row->id]);
            }
        }
    }

    public function get_all() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY current_rank ASC, id DESC");
    }

    public function get_stats() {
        global $wpdb;
        return [
            'total'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"),
            'top10'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE current_rank <= 10 AND current_rank > 0"),
            'top3'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE current_rank <= 3 AND current_rank > 0"),
            'improved'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE current_rank < prev_rank AND prev_rank IS NOT NULL"),
        ];
    }
}
