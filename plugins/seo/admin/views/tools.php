<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-admin-tools"></span> ابزارها</h1>
        <p>ابزارهای پیشرفته برای مدیریت سایت</p>
    </div>

    <div class="wss-settings-grid">

        <!-- SEO Analyzer -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-chart-bar"></span> تحلیل سئو پست‌ها</div>
            <div class="wss-field">
                <label>انتخاب پست برای تحلیل</label>
                <?php
                $posts = get_posts(['posts_per_page' => 50, 'post_status' => 'publish']);
                ?>
                <select id="wss-analyze-post">
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($posts as $post): ?>
                    <option value="<?php echo $post->ID; ?>"><?php echo esc_html(mb_strimwidth($post->post_title, 0, 50, '...')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="button button-primary" id="wss-run-analyze">تحلیل سئو</button>
            <div id="wss-analyze-result" style="margin-top:15px"></div>
        </div>

        <!-- Database Cleanup -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-database-remove"></span> پاکسازی پایگاه داده</div>
            <?php $stats = WSS_Speed_Optimizer::instance()->get_database_stats(); ?>
            <p>موارد قابل پاکسازی:</p>
            <div class="wss-cleanup-preview">
                <div class="wss-cleanup-item <?php echo $stats['revisions'] > 0 ? 'has-data' : ''; ?>">
                    <span>ریویژن‌ها</span>
                    <span class="count"><?php echo number_format($stats['revisions']); ?></span>
                </div>
                <div class="wss-cleanup-item <?php echo $stats['auto_drafts'] > 0 ? 'has-data' : ''; ?>">
                    <span>پیش‌نویس خودکار</span>
                    <span class="count"><?php echo number_format($stats['auto_drafts']); ?></span>
                </div>
                <div class="wss-cleanup-item <?php echo $stats['trash_posts'] > 0 ? 'has-data' : ''; ?>">
                    <span>پست‌های حذف‌شده</span>
                    <span class="count"><?php echo number_format($stats['trash_posts']); ?></span>
                </div>
                <div class="wss-cleanup-item <?php echo $stats['spam_comments'] > 0 ? 'has-data' : ''; ?>">
                    <span>اسپم‌های نظرات</span>
                    <span class="count"><?php echo number_format($stats['spam_comments']); ?></span>
                </div>
                <div class="wss-cleanup-item <?php echo $stats['transients'] > 0 ? 'has-data' : ''; ?>">
                    <span>ترانزیت‌های منقضی</span>
                    <span class="count"><?php echo number_format($stats['transients']); ?></span>
                </div>
            </div>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:12px">
                <input type="hidden" name="action" value="wss_run_cleanup">
                <?php wp_nonce_field('wss_run_cleanup'); ?>
                <button type="submit" class="button button-primary" onclick="return confirm('آیا از پاکسازی پایگاه داده مطمئن هستید؟')">
                    <span class="dashicons dashicons-trash"></span> شروع پاکسازی
                </button>
                <small class="wss-help">آخرین پاکسازی: <?php echo esc_html($stats['last_cleanup']); ?></small>
            </form>
        </div>

        <!-- Robots.txt Editor -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-text-page"></span> ویرایش robots.txt</div>
            <?php
            $robots_file = ABSPATH . 'robots.txt';
            $robots_content = file_exists($robots_file) ? file_get_contents($robots_file) : '';
            $default_robots = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . home_url('/sitemap.xml');
            ?>
            <form method="post" action="" id="wss-robots-form">
                <?php wp_nonce_field('wss_save_robots', 'wss_robots_nonce'); ?>
                <textarea name="robots_content" rows="12" class="widefat wss-mono" id="wss-robots-content"><?php echo esc_textarea($robots_content ?: $default_robots); ?></textarea>
                <div style="margin-top:8px;display:flex;gap:8px">
                    <button type="submit" class="button button-primary">ذخیره robots.txt</button>
                    <button type="button" class="button" id="wss-robots-default">بازنشانی به پیش‌فرض</button>
                </div>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-admin-links"></span> لینک‌های سریع</div>
            <div class="wss-quick-links">
                <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-networking"></span> مشاهده Sitemap
                </a>
                <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-text-page"></span> مشاهده robots.txt
                </a>
                <a href="https://search.google.com/search-console" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-search"></span> Google Search Console
                </a>
                <a href="https://www.bing.com/webmasters" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-search"></span> Bing Webmaster
                </a>
                <a href="https://pagespeed.web.dev/?url=<?php echo urlencode(home_url('/')); ?>" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-performance"></span> Google PageSpeed
                </a>
                <a href="https://validator.schema.org/" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-networking"></span> Schema Validator
                </a>
                <a href="https://www.facebook.com/sharing/debugger/" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-facebook"></span> Facebook Debugger
                </a>
                <a href="https://cards-dev.twitter.com/validator" target="_blank" class="wss-quick-link">
                    <span class="dashicons dashicons-twitter"></span> Twitter Card Validator
                </a>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){

    // SEO Analyzer
    document.getElementById('wss-run-analyze').addEventListener('click', function(){
        var postId = document.getElementById('wss-analyze-post').value;
        if(!postId){ alert('لطفاً یک پست انتخاب کنید'); return; }

        this.disabled = true;
        this.textContent = 'در حال تحلیل...';
        var btn = this;

        fetch(wssAdmin.ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=wss_analyze&nonce=' + wssAdmin.nonce + '&post_id=' + postId
        })
        .then(r => r.json())
        .then(function(res){
            btn.disabled = false;
            btn.textContent = 'تحلیل سئو';
            if(res.success){
                var data = res.data;
                var html = '<div class="wss-score-display"><div class="wss-score-num ' + data.grade.toLowerCase() + '">' + data.score + '</div><div class="wss-score-grade">نمره: ' + data.grade + '</div></div>';
                html += '<div class="wss-checks-list">';
                for(var key in data.checks){
                    var c = data.checks[key];
                    var icon = c.status === 'good' ? '✓' : (c.status === 'warn' ? '!' : '✗');
                    html += '<div class="wss-check-row ' + c.status + '"><span class="wss-ci">' + icon + '</span><strong>' + c.label + '</strong><span>' + c.msg + '</span></div>';
                }
                html += '</div>';
                document.getElementById('wss-analyze-result').innerHTML = html;
            }
        })
        .catch(function(){ btn.disabled = false; btn.textContent = 'تحلیل سئو'; });
    });

    // Robots default
    document.getElementById('wss-robots-default').addEventListener('click', function(){
        document.getElementById('wss-robots-content').value = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: <?php echo esc_js(home_url('/sitemap.xml')); ?>";
    });

    // Robots save via AJAX (simple)
    document.getElementById('wss-robots-form').addEventListener('submit', function(e){
        e.preventDefault();
        var content = document.getElementById('wss-robots-content').value;
        var nonce = document.querySelector('[name="wss_robots_nonce"]').value;
        fetch(wssAdmin.ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=wss_save_robots&nonce=' + wssAdmin.nonce + '&content=' + encodeURIComponent(content)
        })
        .then(r => r.json())
        .then(function(res){
            alert(res.success ? 'robots.txt ذخیره شد.' : 'خطا در ذخیره.');
        });
    });
});
</script>
<?php
// Handle robots.txt save via AJAX
add_action('wp_ajax_wss_save_robots', function(){
    check_ajax_referer('wss_admin_nonce', 'nonce');
    if(!current_user_can('manage_options')) wp_die('Access denied');
    $content = sanitize_textarea_field(stripslashes($_POST['content'] ?? ''));
    $file = ABSPATH . 'robots.txt';
    $result = file_put_contents($file, $content);
    wp_send_json($result !== false ? ['success' => true] : ['success' => false]);
});
