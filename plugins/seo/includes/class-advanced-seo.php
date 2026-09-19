<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Advanced_SEO {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wss_internal_links', [ $this, 'ajax_internal_links' ] );
        add_action('wp_ajax_wss_readability',    [ $this, 'ajax_readability' ] );
        add_action('wp_ajax_wss_competitor',     [ $this, 'ajax_competitor' ] );
        add_action('wp_ajax_wss_fix_alt',        [ $this, 'ajax_fix_alt' ] );
        add_action('wp_ajax_wss_alt_report',     [ $this, 'ajax_alt_report' ] );
    }

    // ─── Internal Linking Suggestions ────────────────────────────────────────

    public function ajax_internal_links() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_die();

        $post_id   = (int)($_POST['post_id'] ?? 0);
        $post      = get_post($post_id);
        if (!$post) wp_send_json_error();

        $focus_kw  = get_post_meta($post_id, '_wss_focus_keyword', true);
        $content   = wp_strip_all_tags($post->post_content);
        $words     = preg_split('/\s+/', strtolower($content), -1, PREG_SPLIT_NO_EMPTY);

        // Get top words from content (TF-IDF simplified)
        $stopwords = ['و','در','به','از','که','این','با','است','را','برای','آن','یک','هم','تا',
                      'ما','شما','آنها','نیز','هر','خود','کرد','می','شد','بود','اما'];
        $freq      = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 3) continue;
            if (in_array($w, $stopwords)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $top_words = array_slice(array_keys($freq), 0, 15);

        if ($focus_kw) array_unshift($top_words, strtolower($focus_kw));

        // Find posts that could be linked
        $suggestions = [];
        foreach ($top_words as $word) {
            if (mb_strlen($word) < 3) continue;

            $related = get_posts([
                'post_type'      => ['post','page'],
                'post_status'    => 'publish',
                'posts_per_page' => 3,
                'post__not_in'   => [$post_id],
                's'              => $word,
            ]);

            foreach ($related as $r) {
                $key = $r->ID;
                if (isset($suggestions[$key])) continue;

                // Check if already linked
                if (strpos($post->post_content, get_permalink($r->ID)) !== false) continue;

                $suggestions[$key] = [
                    'id'        => $r->ID,
                    'title'     => $r->post_title,
                    'url'       => get_permalink($r->ID),
                    'keyword'   => $word,
                    'relevance' => $freq[$word] ?? 1,
                ];
            }

            if (count($suggestions) >= 10) break;
        }

        usort($suggestions, fn($a,$b) => $b['relevance'] - $a['relevance']);

        wp_send_json_success(array_values($suggestions));
    }

    // ─── Readability Analysis ────────────────────────────────────────────────

    public function ajax_readability() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_die();

        $post_id  = (int)($_POST['post_id'] ?? 0);
        $post     = get_post($post_id);
        if (!$post) wp_send_json_error();

        $content  = wp_strip_all_tags($post->post_content);
        $html     = $post->post_content;

        $result   = $this->analyze_readability($content, $html);
        wp_send_json_success($result);
    }

    public function analyze_readability($content, $html = '') {
        $sentences = preg_split('/[.!?؟]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences), fn($s) => mb_strlen($s) > 5);
        $words     = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);

        $sentence_count = count($sentences);
        $word_count     = count($words);
        $char_count     = mb_strlen($content);
        $para_count     = substr_count($html, '<p') ?: 1;

        // Average sentence length
        $avg_sentence_len = $sentence_count > 0 ? round($word_count / $sentence_count) : 0;

        // Long sentences (>20 words)
        $long_sentences = 0;
        foreach ($sentences as $s) {
            $sw = count(preg_split('/\s+/', trim($s)));
            if ($sw > 20) $long_sentences++;
        }

        // Passive voice detection (simplified Persian/English)
        $passive_patterns = ['/شده است/u', '/شده بود/u', '/می‌شود/u', '/was\s+\w+ed/i', '/were\s+\w+ed/i', '/been\s+\w+ed/i'];
        $passive_count = 0;
        foreach ($passive_patterns as $p) {
            $passive_count += preg_match_all($p, $content);
        }

        // Heading structure
        $h2_count = substr_count($html, '<h2');
        $h3_count = substr_count($html, '<h3');
        $h4_count = substr_count($html, '<h4');

        // Paragraph length
        $paragraphs = preg_split('/<\/p>/i', $html);
        $long_paras = 0;
        foreach ($paragraphs as $p) {
            $pw = count(preg_split('/\s+/', trim(wp_strip_all_tags($p))));
            if ($pw > 100) $long_paras++;
        }

        // Reading time (200 wpm average for Persian)
        $reading_time = max(1, ceil($word_count / 200));

        // Score
        $score = 100;
        if ($avg_sentence_len > 20)  $score -= 15;
        if ($avg_sentence_len > 25)  $score -= 10;
        if ($long_sentences / max($sentence_count, 1) > 0.3) $score -= 10;
        if ($passive_count > 3)      $score -= 10;
        if ($h2_count === 0 && $word_count > 300) $score -= 15;
        if ($long_paras > 2)         $score -= 10;
        if ($para_count < 3)         $score -= 5;

        $score = max(0, min(100, $score));

        return [
            'word_count'        => $word_count,
            'sentence_count'    => $sentence_count,
            'char_count'        => $char_count,
            'avg_sentence_len'  => $avg_sentence_len,
            'long_sentences'    => $long_sentences,
            'passive_count'     => $passive_count,
            'h2_count'          => $h2_count,
            'h3_count'          => $h3_count,
            'long_paras'        => $long_paras,
            'reading_time'      => $reading_time,
            'score'             => $score,
            'grade'             => $score >= 80 ? 'A' : ($score >= 60 ? 'B' : ($score >= 40 ? 'C' : 'D')),
            'checks'            => [
                ['label' => 'طول جملات (' . $avg_sentence_len . ' کلمه)', 'status' => $avg_sentence_len <= 20 ? 'good' : ($avg_sentence_len <= 25 ? 'warn' : 'bad')],
                ['label' => 'جملات طولانی: ' . $long_sentences, 'status' => $long_sentences === 0 ? 'good' : ($long_sentences < 3 ? 'warn' : 'bad')],
                ['label' => 'جملات منفعل: ' . $passive_count, 'status' => $passive_count === 0 ? 'good' : ($passive_count <= 2 ? 'warn' : 'bad')],
                ['label' => 'هدینگ H2: ' . $h2_count, 'status' => $h2_count >= 2 ? 'good' : ($h2_count === 1 ? 'warn' : 'bad')],
                ['label' => 'پاراگراف‌های طولانی: ' . $long_paras, 'status' => $long_paras === 0 ? 'good' : ($long_paras <= 1 ? 'warn' : 'bad')],
                ['label' => 'زمان خواندن: ' . $reading_time . ' دقیقه', 'status' => 'good'],
            ],
        ];
    }

    // ─── Competitor Analysis ─────────────────────────────────────────────────

    public function ajax_competitor() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('URL الزامی است');

        $result = $this->analyze_competitor($url);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    private function analyze_competitor($url) {
        $response = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; WSS Analyzer/2.0)',
            'sslverify'  => false,
        ]);

        if (is_wp_error($response)) return $response;

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) return new WP_Error('empty', 'محتوا دریافت نشد');

        $data = [];

        // Title
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m);
        $data['title'] = isset($m[1]) ? trim(html_entity_decode($m[1])) : '';
        $data['title_length'] = mb_strlen($data['title']);

        // Meta description
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $html, $m);
        $data['description'] = isset($m[1]) ? trim($m[1]) : '';
        $data['desc_length'] = mb_strlen($data['description']);

        // H1
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m);
        $data['h1'] = array_map(fn($t) => trim(wp_strip_all_tags($t)), $m[1] ?? []);

        // H2s
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $m);
        $data['h2'] = array_slice(array_map(fn($t) => trim(wp_strip_all_tags($t)), $m[1] ?? []), 0, 10);

        // Word count
        $text = wp_strip_all_tags($html);
        $data['word_count'] = str_word_count($text);

        // Images
        preg_match_all('/<img[^>]+>/i', $html, $imgs);
        $no_alt = 0;
        foreach ($imgs[0] as $img) {
            if (!preg_match('/alt=["\'][^"\']+["\']/', $img)) $no_alt++;
        }
        $data['images_total']   = count($imgs[0]);
        $data['images_no_alt']  = $no_alt;

        // Links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $links);
        $internal = 0;
        $external = 0;
        $comp_host = parse_url($url, PHP_URL_HOST);
        foreach ($links[1] as $link) {
            $link_host = parse_url($link, PHP_URL_HOST);
            if (!$link_host || $link_host === $comp_host) $internal++;
            elseif (strpos($link, 'http') === 0)          $external++;
        }
        $data['links_internal'] = $internal;
        $data['links_external'] = $external;

        // Schema
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $schemas);
        $schema_types = [];
        foreach ($schemas[1] as $schema) {
            $decoded = json_decode($schema, true);
            if ($decoded && isset($decoded['@type'])) $schema_types[] = $decoded['@type'];
        }
        $data['schema_types'] = $schema_types;

        // OG
        preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m);
        $data['og_image'] = $m[1] ?? '';

        // Canonical
        preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)/i', $html, $m);
        $data['canonical'] = $m[1] ?? '';

        // Keywords extraction (top 10 words)
        $words    = preg_split('/\s+/', strtolower(wp_strip_all_tags($html)));
        $freq     = [];
        $stopwords = ['the','a','an','and','or','but','in','on','at','to','for','of','with',
                      'is','are','was','were','be','been','by','this','that','it','its',
                      'و','در','به','از','که','این','با','است','را','برای','آن','یک'];
        foreach ($words as $w) {
            $w = preg_replace('/[^a-zآ-ی\-]/u', '', $w);
            if (mb_strlen($w) < 3 || in_array($w, $stopwords)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $data['top_keywords'] = array_slice(array_keys($freq), 0, 10);
        $data['url']          = $url;
        $data['analyzed_at']  = current_time('Y-m-d H:i');

        return $data;
    }

    // ─── Alt text fixer ──────────────────────────────────────────────────────

    public function ajax_fix_alt() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_die();

        $attachment_id = (int)($_POST['attachment_id'] ?? 0);
        $alt           = sanitize_text_field($_POST['alt'] ?? '');

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        wp_send_json_success('Alt text ذخیره شد.');
    }

    public function ajax_alt_report() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_die();

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $report = [];
        foreach ($images as $img) {
            $alt = get_post_meta($img->ID, '_wp_attachment_image_alt', true);
            if (!$alt) {
                $src = wp_get_attachment_image_src($img->ID, 'thumbnail');
                $report[] = [
                    'id'    => $img->ID,
                    'title' => $img->post_title,
                    'thumb' => $src ? $src[0] : '',
                    'alt'   => $alt,
                ];
            }
        }

        wp_send_json_success($report);
    }

    // ─── Heading keyword check ───────────────────────────────────────────────

    public static function check_headings($content, $focus_kw) {
        if (!$focus_kw) return null;

        $kw = mb_strtolower($focus_kw);
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER);

        $results = [];
        foreach ($matches as $m) {
            $text     = mb_strtolower(wp_strip_all_tags($m[2]));
            $has_kw   = strpos($text, $kw) !== false;
            $results[] = [
                'level'  => 'H' . $m[1],
                'text'   => wp_strip_all_tags($m[2]),
                'has_kw' => $has_kw,
            ];
        }

        return $results;
    }
}
