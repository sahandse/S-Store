<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Broken_Links {

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
        $this->table = $wpdb->prefix . 'wss_broken_links';

        add_action( 'wss_scan_links',       [ $this, 'run_scan' ] );
        add_action( 'wp_ajax_wss_scan_links_batch', [ $this, 'ajax_scan_batch' ] );
        add_action( 'wp_ajax_wss_fix_link', [ $this, 'ajax_fix_link' ] );
        add_action( 'wp_ajax_wss_unlink',   [ $this, 'ajax_unlink' ] );
    }

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'wss_broken_links';

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id          bigint(20) NOT NULL AUTO_INCREMENT,
            post_id     bigint(20) NOT NULL,
            url         varchar(2048) NOT NULL,
            anchor_text varchar(512),
            status_code smallint(6) NOT NULL DEFAULT 0,
            status      varchar(20)  NOT NULL DEFAULT 'unchecked',
            last_checked datetime,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // ─── Scan ───────────────────────────────────────────────────────────────

    public function run_scan() {
        global $wpdb;

        $posts = get_posts([
            'post_type'      => ['post','page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        // Extract links from all posts
        foreach ($posts as $post_id) {
            $post    = get_post($post_id);
            $content = $post->post_content;
            $links   = $this->extract_links($content);

            foreach ($links as $link) {
                // Check if already in DB
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE post_id=%d AND url=%s LIMIT 1",
                    $post_id, $link['url']
                ));

                if (!$exists) {
                    $wpdb->insert($this->table, [
                        'post_id'     => $post_id,
                        'url'         => $link['url'],
                        'anchor_text' => $link['text'],
                        'status'      => 'unchecked',
                    ]);
                }
            }
        }

        // Check unchecked/old links
        $to_check = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE status='unchecked' OR (last_checked < DATE_SUB(NOW(), INTERVAL 7 DAY)) LIMIT 50"
        );

        foreach ($to_check as $row) {
            $result = $this->check_url($row->url);
            $wpdb->update(
                $this->table,
                [
                    'status_code'  => $result['code'],
                    'status'       => $result['status'],
                    'last_checked' => current_time('mysql'),
                ],
                ['id' => $row->id]
            );
        }

        update_option('wss_last_link_scan', current_time('mysql'));
    }

    public function ajax_scan_batch() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        global $wpdb;

        $offset     = (int)($_POST['offset'] ?? 0);
        $batch_size = 10;
        $mode       = sanitize_key($_POST['mode'] ?? 'extract');

        if ($mode === 'extract') {
            // Extract links from posts in batches
            $posts = get_posts([
                'post_type'      => ['post','page'],
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids',
            ]);

            $found = 0;
            foreach ($posts as $post_id) {
                $post  = get_post($post_id);
                $links = $this->extract_links($post->post_content);

                // Remove old links for this post before re-inserting
                if ($offset === 0) {
                    $wpdb->delete($this->table, ['post_id' => $post_id]);
                }

                foreach ($links as $link) {
                    $wpdb->replace($this->table, [
                        'post_id'     => $post_id,
                        'url'         => $link['url'],
                        'anchor_text' => $link['text'],
                        'status'      => 'unchecked',
                    ]);
                    $found++;
                }
            }

            $total_posts = wp_count_posts('post')->publish + wp_count_posts('page')->publish;

            wp_send_json_success([
                'found'    => $found,
                'done'     => count($posts) < $batch_size,
                'next'     => $offset + $batch_size,
                'progress' => min(100, round(($offset + $batch_size) / max(1,$total_posts) * 100)),
                'mode'     => 'extract',
            ]);

        } elseif ($mode === 'check') {
            // Check URLs
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status='unchecked' LIMIT %d OFFSET %d",
                $batch_size, $offset
            ));

            $checked = 0;
            foreach ($rows as $row) {
                $result = $this->check_url($row->url);
                $wpdb->update(
                    $this->table,
                    [
                        'status_code'  => $result['code'],
                        'status'       => $result['status'],
                        'last_checked' => current_time('mysql'),
                    ],
                    ['id' => $row->id]
                );
                $checked++;
            }

            $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
            $pending = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='unchecked'");

            update_option('wss_last_link_scan', current_time('mysql'));

            wp_send_json_success([
                'checked'  => $checked,
                'done'     => $pending === 0,
                'next'     => $offset + $batch_size,
                'total'    => $total,
                'pending'  => $pending,
                'progress' => $total > 0 ? min(100, round(($total - $pending) / $total * 100)) : 100,
                'mode'     => 'check',
            ]);
        }
    }

    public function ajax_fix_link() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_die();

        global $wpdb;

        $id       = (int)($_POST['link_id'] ?? 0);
        $new_url  = esc_url_raw($_POST['new_url'] ?? '');

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id));
        if (!$row) wp_send_json_error('Not found');

        $post    = get_post($row->post_id);
        $content = str_replace(
            'href="' . $row->url . '"',
            'href="' . $new_url . '"',
            $post->post_content
        );

        wp_update_post(['ID' => $row->post_id, 'post_content' => $content]);

        $wpdb->update($this->table, [
            'url'    => $new_url,
            'status' => 'unchecked',
        ], ['id' => $id]);

        wp_send_json_success('لینک اصلاح شد.');
    }

    public function ajax_unlink() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_die();

        global $wpdb;
        $id  = (int)($_POST['link_id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id));
        if (!$row) wp_send_json_error('Not found');

        $post    = get_post($row->post_id);
        $content = preg_replace(
            '/<a[^>]+href=["\']' . preg_quote($row->url, '/') . '["\'][^>]*>(.*?)<\/a>/i',
            '$1',
            $post->post_content
        );

        wp_update_post(['ID' => $row->post_id, 'post_content' => $content]);
        $wpdb->delete($this->table, ['id' => $id]);

        wp_send_json_success('لینک حذف شد.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function extract_links($content) {
        $links = [];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $url = $m[1];
            // Skip internal anchors, javascript, mailto, tel
            if (preg_match('/^(#|javascript:|mailto:|tel:|data:)/i', $url)) continue;
            // Only HTTP(S)
            if (!preg_match('/^https?:\/\//i', $url)) {
                // Make absolute
                if (strpos($url, '/') === 0) {
                    $url = home_url($url);
                } else {
                    continue;
                }
            }

            $links[] = [
                'url'  => esc_url_raw($url),
                'text' => wp_strip_all_tags($m[2]),
            ];
        }

        return $links;
    }

    private function check_url($url) {
        $response = wp_remote_head($url, [
            'timeout'     => 10,
            'redirection' => 5,
            'user-agent'  => 'WSS Link Checker/2.0 (+' . home_url('/') . ')',
            'sslverify'   => false,
        ]);

        if (is_wp_error($response)) {
            return ['code' => 0, 'status' => 'error'];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) return ['code' => $code, 'status' => 'ok'];
        if ($code >= 300 && $code < 400) return ['code' => $code, 'status' => 'redirect'];
        if ($code >= 400)                return ['code' => $code, 'status' => 'broken'];

        return ['code' => $code, 'status' => 'unknown'];
    }

    public function get_stats() {
        global $wpdb;
        return [
            'total'    => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"),
            'broken'   => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='broken'"),
            'redirect' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='redirect'"),
            'ok'       => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='ok'"),
            'last_scan'=> get_option('wss_last_link_scan', 'هرگز'),
        ];
    }

    public function get_broken($per_page = 20, $page = 1) {
        global $wpdb;
        $offset = ($page-1)*$per_page;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT bl.*, p.post_title FROM {$this->table} bl
             LEFT JOIN {$wpdb->posts} p ON p.ID = bl.post_id
             WHERE bl.status IN ('broken','error')
             ORDER BY bl.id DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }

    public function get_all($per_page = 20, $page = 1, $status = '') {
        global $wpdb;
        $offset = ($page-1)*$per_page;
        $where  = $status ? $wpdb->prepare("WHERE bl.status=%s", $status) : '';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT bl.*, p.post_title FROM {$this->table} bl
             LEFT JOIN {$wpdb->posts} p ON p.ID = bl.post_id
             $where ORDER BY bl.status ASC, bl.id DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }
}
