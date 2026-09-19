<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php
$speed_opts  = (array) get_option('wss_speed', []);
$seo_opts    = (array) get_option('wss_seo', []);
$wm_opts     = (array) get_option('wss_webmaster', []);
$cache_opts  = (array) get_option('wss_cache', []);
$webp_opts   = (array) get_option('wss_webp', []);
$db_stats    = WSS_Speed_Optimizer::instance()->get_database_stats();
$speed_hints = WSS_Analyzer::get_page_speed_hints();

$speed_keys  = ['minify_html','minify_css','minify_js','defer_js','lazy_images','browser_cache','gzip','disable_emoji','disable_embeds','remove_query_str'];
$speed_active = array_sum(array_map(fn($k) => !empty($speed_opts[$k]) ? 1 : 0, $speed_keys));

$seo_keys    = ['og_enabled','twitter_enabled','canonical_enabled','auto_description','breadcrumbs'];
$seo_active  = array_sum(array_map(fn($k) => !empty($seo_opts[$k]) ? 1 : 0, $seo_keys));
if (!empty($wm_opts['google_analytics'])) $seo_active++;
if (!empty($wm_opts['google_verify']))    $seo_active++;
$seo_pct = round(($seo_active / 7) * 100);

$recent_posts = get_posts(['posts_per_page' => 5, 'post_status' => 'publish']);

// Check what's not set up yet
$todos = [];
if (empty($cache_opts['enabled']))           $todos[] = ['کش صفحات غیرفعال است',   'wss-cache'];
if (empty($webp_opts['enabled']))            $todos[] = ['WebP فعال نیست',           'wss-webp'];
if (empty($wm_opts['google_verify']))        $todos[] = ['Google Search Console تأیید نشده', 'wss-seo'];
if (empty($wm_opts['google_analytics']))     $todos[] = ['Google Analytics وصل نیست', 'wss-seo'];
if ($speed_active < 7)                       $todos[] = ['چند ویژگی سرعت غیرفعال است', 'wss-speed'];
?>
<div class="wss-wrap">
    <div class="wss-header">
        <div class="wss-header-inner">
            <div class="wss-logo">
                <span class="dashicons dashicons-chart-line"></span>
                <div>
                    <h1>Speed & SEO Optimizer</h1>
                    <p>بهینه‌سازی جامع سرعت و سئو وردپرس</p>
                </div>
            </div>
            <div class="wss-header-meta">نسخه <?php echo WSS_VERSION; ?> &nbsp;|&nbsp; <?php echo esc_html(get_bloginfo('name')); ?></div>
        </div>
    </div>

    <?php if ($todos): ?>
    <!-- Quick Setup -->
    <div class="wss-action-hero" style="margin-bottom:20px">
        <div>
            <h3>راه‌اندازی سریع — <?php echo count($todos); ?> مورد ناقص</h3>
            <p><?php echo esc_html(implode(' | ', array_column($todos, 0))); ?></p>
        </div>
        <button class="button" id="wss-quick-setup">
            <span class="dashicons dashicons-yes-alt"></span> فعال‌سازی خودکار همه
        </button>
    </div>
    <?php endif; ?>

    <div class="wss-dashboard-grid">

        <!-- Speed Score -->
        <div class="wss-card wss-card-score">
            <div class="wss-card-header"><span class="dashicons dashicons-performance"></span> سرعت</div>
            <div class="wss-score-circle speed">
                <svg viewBox="0 0 100 100">
                    <circle class="bg" cx="50" cy="50" r="42"/>
                    <circle class="fill" cx="50" cy="50" r="42" style="stroke-dashoffset:<?php echo 264 - (264 * $speed_active / 10); ?>"/>
                </svg>
                <div class="score-value"><?php echo $speed_active * 10; ?>%</div>
            </div>
            <p class="wss-score-label"><?php echo $speed_active; ?>/10 ویژگی فعال</p>
            <a href="<?php echo admin_url('admin.php?page=wss-speed'); ?>" class="button button-primary">تنظیم سرعت</a>
        </div>

        <!-- SEO Score -->
        <div class="wss-card wss-card-score">
            <div class="wss-card-header"><span class="dashicons dashicons-search"></span> سئو</div>
            <div class="wss-score-circle seo">
                <svg viewBox="0 0 100 100">
                    <circle class="bg" cx="50" cy="50" r="42"/>
                    <circle class="fill" cx="50" cy="50" r="42" style="stroke-dashoffset:<?php echo 264 - (264 * $seo_pct / 100); ?>"/>
                </svg>
                <div class="score-value"><?php echo $seo_pct; ?>%</div>
            </div>
            <p class="wss-score-label"><?php echo $seo_active; ?>/7 ویژگی فعال</p>
            <a href="<?php echo admin_url('admin.php?page=wss-seo'); ?>" class="button button-primary">تنظیم سئو</a>
        </div>

        <!-- Module Status -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-list-view"></span> وضعیت ماژول‌ها</div>
            <div class="wss-stats-list">
                <?php
                $modules = [
                    ['کش صفحات',        !empty($cache_opts['enabled']),      'wss-cache'],
                    ['تبدیل WebP',       !empty($webp_opts['enabled']),       'wss-webp'],
                    ['Critical CSS',     !empty(get_option('wss_critical_css', [])['enabled']), 'wss-critical-css'],
                    ['فونت‌های محلی',    !empty(get_option('wss_local_fonts', [])['enabled']), 'wss-local-fonts'],
                    ['Open Graph',       !empty($seo_opts['og_enabled']),     'wss-seo'],
                    ['Breadcrumbs',      !empty($seo_opts['breadcrumbs']),    'wss-seo'],
                    ['Google Analytics', !empty($wm_opts['google_analytics']),'wss-seo'],
                ];
                foreach ($modules as [$name, $active, $page]): ?>
                <div class="wss-stat-item">
                    <span class="wss-stat-label"><?php echo $name; ?></span>
                    <?php if ($active): ?>
                    <span class="wss-badge wss-badge-good">فعال ✓</span>
                    <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page='.$page); ?>" class="wss-badge wss-badge-bad">غیرفعال — تنظیم</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- DB Stats -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-database"></span> پایگاه داده</div>
            <div class="wss-stats-list">
                <div class="wss-stat-item"><span class="wss-stat-label">ریویژن‌ها</span><span class="wss-stat-val <?php echo $db_stats['revisions']>50?'warn':''; ?>"><?php echo number_format($db_stats['revisions']); ?></span></div>
                <div class="wss-stat-item"><span class="wss-stat-label">پیش‌نویس خودکار</span><span class="wss-stat-val"><?php echo number_format($db_stats['auto_drafts']); ?></span></div>
                <div class="wss-stat-item"><span class="wss-stat-label">پست‌های سطل زباله</span><span class="wss-stat-val"><?php echo number_format($db_stats['trash_posts']); ?></span></div>
                <div class="wss-stat-item"><span class="wss-stat-label">اسپم</span><span class="wss-stat-val"><?php echo number_format($db_stats['spam_comments']); ?></span></div>
                <div class="wss-stat-item"><span class="wss-stat-label">ترانزیت‌های منقضی</span><span class="wss-stat-val"><?php echo number_format($db_stats['transients']); ?></span></div>
            </div>
            <div style="padding:10px 16px">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wss_run_cleanup">
                    <?php wp_nonce_field('wss_run_cleanup'); ?>
                    <button type="submit" class="button" onclick="return confirm('پاکسازی پایگاه داده؟')"><span class="dashicons dashicons-trash"></span> پاکسازی خودکار</button>
                </form>
            </div>
        </div>

        <!-- Speed Hints -->
        <?php if ($speed_hints): ?>
        <div class="wss-card">
            <div class="wss-card-header" style="background:#fffbeb;color:#92400e"><span class="dashicons dashicons-warning"></span> پیشنهادات سرعت</div>
            <ul class="wss-hints-list">
                <?php foreach ($speed_hints as $hint): ?>
                <li><span class="dashicons dashicons-dismiss"></span> <?php echo esc_html($hint); ?></li>
                <?php endforeach; ?>
            </ul>
            <div style="padding:10px 16px">
                <a href="<?php echo admin_url('admin.php?page=wss-speed'); ?>" class="button">رفع مشکلات</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-admin-links"></span> ابزارهای سریع</div>
            <div class="wss-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wss-health'); ?>" class="wss-quick-link"><span class="dashicons dashicons-heart"></span> سلامت سایت</a>
                <a href="<?php echo admin_url('admin.php?page=wss-vitals'); ?>" class="wss-quick-link"><span class="dashicons dashicons-performance"></span> Core Web Vitals</a>
                <a href="<?php echo admin_url('admin.php?page=wss-rank-tracker'); ?>" class="wss-quick-link"><span class="dashicons dashicons-chart-line"></span> ردیاب رتبه</a>
                <a href="<?php echo admin_url('admin.php?page=wss-broken-links'); ?>" class="wss-quick-link"><span class="dashicons dashicons-warning"></span> لینک‌های شکسته</a>
                <a href="<?php echo admin_url('admin.php?page=wss-advanced-seo'); ?>" class="wss-quick-link"><span class="dashicons dashicons-editor-expand"></span> سئو پیشرفته</a>
                <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" class="wss-quick-link"><span class="dashicons dashicons-networking"></span> Sitemap</a>
                <a href="https://search.google.com/search-console" target="_blank" class="wss-quick-link"><span class="dashicons dashicons-search"></span> Search Console</a>
            </div>
        </div>

        <!-- Recent posts SEO -->
        <div class="wss-card wss-card-wide">
            <div class="wss-card-header"><span class="dashicons dashicons-list-view"></span> وضعیت سئو آخرین پست‌ها</div>
            <table class="wss-table wss-table-full">
                <thead>
                    <tr><th>عنوان پست</th><th>عنوان سئو</th><th>توضیحات متا</th><th>کلمه کلیدی</th><th>تصویر</th><th>نمره</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent_posts as $post):
                    $seo_title = get_post_meta($post->ID, '_wss_seo_title', true);
                    $seo_desc  = get_post_meta($post->ID, '_wss_seo_description', true);
                    $focus_kw  = get_post_meta($post->ID, '_wss_focus_keyword', true);
                    $has_thumb = has_post_thumbnail($post->ID);
                    $score     = ($seo_title?25:0) + ($seo_desc?25:0) + ($focus_kw?25:0) + ($has_thumb?25:0);
                    $gc = $score >= 75 ? 'good' : ($score >= 50 ? 'warn' : 'bad');
                ?>
                <tr>
                    <td><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html(mb_strimwidth($post->post_title, 0, 45, '...')); ?></a></td>
                    <td class="<?php echo $seo_title ? 'td-good' : 'td-bad'; ?>"><?php echo $seo_title ? '✓' : '✗'; ?></td>
                    <td class="<?php echo $seo_desc  ? 'td-good' : 'td-bad'; ?>"><?php echo $seo_desc  ? '✓' : '✗'; ?></td>
                    <td class="<?php echo $focus_kw  ? 'td-good' : 'td-bad'; ?>"><?php echo $focus_kw  ? esc_html($focus_kw) : '✗'; ?></td>
                    <td class="<?php echo $has_thumb ? 'td-good' : 'td-bad'; ?>"><?php echo $has_thumb ? '✓' : '✗'; ?></td>
                    <td><span class="wss-badge wss-badge-<?php echo $gc; ?>"><?php echo $score; ?>%</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('wss-quick-setup');
    if (!btn) return;
    btn.addEventListener('click', function(){
        if(!confirm('همه تنظیمات پیشنهادی (سرعت، کش، WebP، سئو پایه) فعال شوند؟')) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update"></span> در حال اعمال...';
        fetch(wssAdmin.ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=wss_enable_recommended&nonce=' + wssAdmin.nonce
        }).then(r=>r.json()).then(function(res){
            if(res.success){
                btn.innerHTML = '✓ انجام شد — در حال رفرش...';
                setTimeout(function(){ location.reload(); }, 1400);
            }
        });
    });
})();
</script>
