<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Analyzer {

    public static function analyze_post( $post_id ) {
        $post       = get_post( $post_id );
        $content    = wp_strip_all_tags( $post->post_content );
        $title      = get_the_title( $post_id );
        $seo_title  = get_post_meta( $post_id, '_wss_seo_title', true );
        $seo_desc   = get_post_meta( $post_id, '_wss_seo_description', true );
        $focus_kw   = get_post_meta( $post_id, '_wss_focus_keyword', true );
        $word_count = str_word_count( $content );
        $has_image  = has_post_thumbnail( $post_id );

        $checks = [];

        // Title length
        $tlen = mb_strlen( $seo_title ?: $title );
        $checks['title_length'] = [
            'label'  => 'طول عنوان سئو',
            'status' => $tlen >= 30 && $tlen <= 60 ? 'good' : ($tlen > 0 ? 'warn' : 'bad'),
            'msg'    => "$tlen کاراکتر (توصیه: 30-60)",
        ];

        // Description
        $dlen = mb_strlen( $seo_desc );
        $checks['desc_length'] = [
            'label'  => 'طول توضیحات متا',
            'status' => $dlen >= 100 && $dlen <= 160 ? 'good' : ($dlen > 0 ? 'warn' : 'bad'),
            'msg'    => $dlen ? "$dlen کاراکتر (توصیه: 100-160)" : 'توضیحات متا تنظیم نشده',
        ];

        // Content length
        $checks['content_length'] = [
            'label'  => 'طول محتوا',
            'status' => $word_count >= 300 ? 'good' : ($word_count >= 100 ? 'warn' : 'bad'),
            'msg'    => "$word_count کلمه (توصیه: بیش از 300)",
        ];

        // Featured image
        $checks['featured_image'] = [
            'label'  => 'تصویر شاخص',
            'status' => $has_image ? 'good' : 'bad',
            'msg'    => $has_image ? 'تصویر شاخص دارد' : 'تصویر شاخص ندارد',
        ];

        // Focus keyword checks
        if ( $focus_kw ) {
            $kw_lower      = mb_strtolower( $focus_kw );
            $title_lower   = mb_strtolower( $seo_title ?: $title );
            $desc_lower    = mb_strtolower( $seo_desc );
            $content_lower = mb_strtolower( $content );

            $checks['kw_in_title'] = [
                'label'  => 'کلمه کلیدی در عنوان',
                'status' => strpos($title_lower, $kw_lower) !== false ? 'good' : 'bad',
                'msg'    => strpos($title_lower, $kw_lower) !== false ? 'کلمه کلیدی در عنوان وجود دارد' : 'کلمه کلیدی در عنوان نیست',
            ];

            $checks['kw_in_desc'] = [
                'label'  => 'کلمه کلیدی در توضیحات',
                'status' => strpos($desc_lower, $kw_lower) !== false ? 'good' : 'bad',
                'msg'    => strpos($desc_lower, $kw_lower) !== false ? 'کلمه کلیدی در توضیحات وجود دارد' : 'کلمه کلیدی در توضیحات نیست',
            ];

            $kw_count = substr_count($content_lower, $kw_lower);
            $density  = $word_count > 0 ? round(($kw_count / $word_count) * 100, 2) : 0;
            $checks['kw_density'] = [
                'label'  => 'تراکم کلمه کلیدی',
                'status' => $density >= 0.5 && $density <= 3 ? 'good' : 'warn',
                'msg'    => "$density% (توصیه: 0.5%-3%)",
            ];
        }

        // Internal links
        preg_match_all( '/<a[^>]+href=["\']' . preg_quote(home_url(), '/') . '[^"\']*["\'][^>]*>/i', $post->post_content, $int_links );
        $int_count = count($int_links[0]);
        $checks['internal_links'] = [
            'label'  => 'لینک‌های داخلی',
            'status' => $int_count >= 2 ? 'good' : ($int_count >= 1 ? 'warn' : 'bad'),
            'msg'    => "$int_count لینک داخلی (توصیه: حداقل 2)",
        ];

        // Images with alt
        preg_match_all( '/<img[^>]+>/i', $post->post_content, $imgs );
        $total_imgs  = count($imgs[0]);
        $imgs_no_alt = 0;
        foreach ( $imgs[0] as $img ) {
            if ( ! preg_match('/alt=["\'][^"\']+["\']/', $img) ) {
                $imgs_no_alt++;
            }
        }
        if ( $total_imgs > 0 ) {
            $checks['img_alt'] = [
                'label'  => 'تگ alt تصاویر',
                'status' => $imgs_no_alt === 0 ? 'good' : ($imgs_no_alt < $total_imgs ? 'warn' : 'bad'),
                'msg'    => $imgs_no_alt === 0 ? "همه $total_imgs تصویر دارای alt" : "$imgs_no_alt از $total_imgs تصویر بدون alt",
            ];
        }

        // H1
        preg_match_all( '/<h1[^>]*>/i', $post->post_content, $h1s );
        $h1_count = count($h1s[0]);
        $checks['h1'] = [
            'label'  => 'تگ H1',
            'status' => $h1_count === 0 ? 'good' : ($h1_count === 1 ? 'warn' : 'bad'),
            'msg'    => $h1_count === 0 ? 'محتوا H1 ندارد (H1 از عنوان پست است)' : "$h1_count تگ H1 در محتوا",
        ];

        $score = self::calculate_score($checks);

        return [
            'checks' => $checks,
            'score'  => $score,
            'grade'  => self::get_grade($score),
        ];
    }

    private static function calculate_score( $checks ) {
        if ( empty($checks) ) return 0;
        $total = count($checks);
        $good  = 0;
        $warn  = 0;

        foreach ( $checks as $check ) {
            if ( $check['status'] === 'good' ) $good++;
            if ( $check['status'] === 'warn' ) $warn += 0.5;
        }

        return (int) round( ( ($good + $warn) / $total ) * 100 );
    }

    private static function get_grade( $score ) {
        if ( $score >= 80 ) return 'A';
        if ( $score >= 60 ) return 'B';
        if ( $score >= 40 ) return 'C';
        if ( $score >= 20 ) return 'D';
        return 'F';
    }

    public static function get_page_speed_hints() {
        $speed  = (array) get_option('wss_speed', []);
        $issues = [];

        if ( empty($speed['minify_html']) )     $issues[] = 'فشرده‌سازی HTML غیرفعال است';
        if ( empty($speed['defer_js']) )        $issues[] = 'Defer JS غیرفعال است';
        if ( empty($speed['lazy_images']) )     $issues[] = 'Lazy Load تصاویر غیرفعال است';
        if ( empty($speed['remove_query_str'])) $issues[] = 'Query strings از فایل‌های استاتیک حذف نشده';
        if ( empty($speed['disable_emoji']) )   $issues[] = 'اسکریپت‌های Emoji غیرفعال نشده';
        if ( empty($speed['browser_cache']) )   $issues[] = 'Cache مرورگر فعال نیست';
        if ( empty($speed['gzip']) )            $issues[] = 'فشرده‌سازی GZIP فعال نیست';

        return $issues;
    }
}
