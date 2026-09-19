<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-networking"></span> نقشه سایت (Sitemap)</h1>
        <p>نقشه‌های سایت به موتورهای جستجو کمک می‌کنند صفحات شما را سریع‌تر پیدا کنند.</p>
    </div>

    <div class="wss-settings-grid">

        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-list-view"></span> نقشه‌های سایت موجود</div>
            <table class="wss-table">
                <thead><tr><th>نوع</th><th>URL</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php
                $sitemaps = [
                    'Index (اصلی)'   => home_url('/sitemap.xml'),
                    'پست‌ها'          => home_url('/sitemap-posts.xml'),
                    'صفحات'           => home_url('/sitemap-pages.xml'),
                    'دسته‌بندی‌ها'    => home_url('/sitemap-terms.xml'),
                    'تصاویر'          => home_url('/sitemap-images.xml'),
                    'Google News'     => home_url('/news-sitemap.xml'),
                    'ویدیو'           => home_url('/video-sitemap.xml'),
                ];
                foreach ($sitemaps as $name => $url): ?>
                <tr>
                    <td><?php echo esc_html($name); ?></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank" class="wss-link"><?php echo esc_url($url); ?></a></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-small">مشاهده</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:15px">
                <button class="button button-primary" id="wss-flush-sitemap">
                    <span class="dashicons dashicons-update"></span> بازسازی نقشه‌های سایت
                </button>
                <span id="wss-sitemap-msg" style="margin-right:10px;color:#46b450"></span>
            </div>
        </div>

        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-search"></span> ارسال به موتورهای جستجو</div>
            <p>آدرس نقشه سایت را در ابزارهای وبمستر ثبت کنید:</p>
            <ul class="wss-hint-list">
                <li>
                    <strong>Google Search Console:</strong><br>
                    <a href="https://search.google.com/search-console" target="_blank" class="button button-secondary">باز کردن Search Console</a>
                    <code><?php echo esc_url(home_url('/sitemap.xml')); ?></code>
                </li>
                <li style="margin-top:10px">
                    <strong>Bing Webmaster Tools:</strong><br>
                    <a href="https://www.bing.com/webmasters" target="_blank" class="button button-secondary">باز کردن Bing Webmaster</a>
                </li>
            </ul>
            <div class="wss-info-box" style="margin-top:15px">
                با ذخیره هر پست، سایت‌مپ به صورت خودکار به Google و Bing Ping ارسال می‌شود.
            </div>
        </div>

        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-info"></span> آمار سایت</div>
            <?php
            $post_count = wp_count_posts('post');
            $page_count = wp_count_posts('page');
            $cat_count  = wp_count_terms(['taxonomy' => 'category']);
            $tag_count  = wp_count_terms(['taxonomy' => 'post_tag']);
            ?>
            <div class="wss-stats-list">
                <div class="wss-stat-item">
                    <span class="wss-stat-label">پست‌های منتشرشده</span>
                    <span class="wss-stat-val"><?php echo number_format($post_count->publish ?? 0); ?></span>
                </div>
                <div class="wss-stat-item">
                    <span class="wss-stat-label">صفحات منتشرشده</span>
                    <span class="wss-stat-val"><?php echo number_format($page_count->publish ?? 0); ?></span>
                </div>
                <div class="wss-stat-item">
                    <span class="wss-stat-label">دسته‌بندی‌ها</span>
                    <span class="wss-stat-val"><?php echo number_format(is_wp_error($cat_count) ? 0 : $cat_count); ?></span>
                </div>
                <div class="wss-stat-item">
                    <span class="wss-stat-label">برچسب‌ها</span>
                    <span class="wss-stat-val"><?php echo number_format(is_wp_error($tag_count) ? 0 : $tag_count); ?></span>
                </div>
            </div>
        </div>

    </div>
</div>
