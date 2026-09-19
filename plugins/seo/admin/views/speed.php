<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php $opts = (array) get_option('wss_speed', []); ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-performance"></span> بهینه‌سازی سرعت</h1>
        <p>تنظیمات زیر به کاهش زمان بارگذاری سایت کمک می‌کنند.</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>یک‌کلیک فعال‌سازی پیشنهادی</h3>
            <p>همه موارد امن (Minify، Lazy Load، GZIP، Cache، WebP، حذف Emoji) به صورت خودکار فعال می‌شوند.</p>
        </div>
        <button class="button" id="wss-enable-recommended">
            <span class="dashicons dashicons-yes-alt"></span> فعال‌سازی خودکار
        </button>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_speed">
        <?php wp_nonce_field('wss_save_settings'); ?>

        <div class="wss-settings-grid">

            <!-- کد و اسکریپت -->
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-editor-code"></span> کد و اسکریپت</div>
                <div class="wss-toggle-list">
                    <?php
                    $code_toggles = [
                        'minify_html'           => ['فشرده‌سازی HTML',            'کاراکترهای اضافی HTML حذف می‌شوند',   'safe',    'فضاها، کامنت‌ها و خطوط خالی HTML حذف می‌شوند. کاملاً امن و توصیه‌شده. اگه صفحه بعد از فعال‌سازی خراب شد (نادر) خاموش کنید.'],
                        'minify_css'            => ['فشرده‌سازی CSS',             'CSS‌های inline کوچک می‌شوند',          'safe',    'CSS داخلی صفحه فشرده می‌شود. فایل‌های CSS جداگانه تغییر نمی‌کنند. امن.'],
                        'minify_js'             => ['فشرده‌سازی JS',              'JS‌های inline کوچک می‌شوند',           'caution', 'JS داخلی فشرده می‌شود. اگر JS سایت بعد از فعال‌سازی مشکل داشت، ابتدا این را خاموش کنید تا علت را پیدا کنید.'],
                        'defer_js'              => ['Defer JavaScript',           'اجرای JS تا پایان لود صفحه به تأخیر می‌افتد', 'caution', 'JS‌ها بعد از load کامل DOM اجرا می‌شوند — LCP و FCP بهتر می‌شود. اگه جاوا اسکریپت مشکل داشت، با افزودن هندل به استثناء رفع کنید. Defer و Async را همزمان فعال نکنید.'],
                        'async_js'              => ['Async JavaScript',           'جایگزین Defer — بارگذاری موازی',      'caution', 'جایگزین Defer است — فقط یکی را فعال کنید. Async برای اسکریپت‌های مستقل (analytics، chat) بهتر است.'],
                        'remove_jquery_migrate' => ['حذف jQuery Migrate',         'کتابخانه قدیمی jQuery Migrate حذف می‌شود', 'caution', 'اگه قالب یا افزونه‌ای از APIهای قدیمی jQuery استفاده کند خطا می‌دهد. ابتدا در محیط تست امتحان کنید.'],
                        'google_fonts_async'    => ['Google Fonts غیرمسدودکننده','فونت‌های Google به صورت async لود می‌شوند', 'safe', 'Render-blocking فونت‌های گوگل حذف می‌شود. برای نتیجه بهتر، از صفحه فونت‌های محلی استفاده کنید.'],
                    ];
                    foreach ($code_toggles as $key => [$label, $desc, $level, $guide]): ?>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(!empty($opts[$key])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text">
                            <strong><?php echo esc_html($label); ?> <button class="wss-guide-toggle" type="button" data-guide="g-<?php echo $key; ?>">راهنما</button></strong>
                            <span><?php echo esc_html($desc); ?></span>
                        </div>
                    </label>
                    <div class="wss-guide" id="g-<?php echo $key; ?>">
                        <div class="wss-guide-body">
                            <?php if ($level === 'safe'): ?>
                            <strong>✅ امن و توصیه‌شده</strong>
                            <?php else: ?>
                            <strong class="warn">⚠️ با احتیاط فعال کنید</strong>
                            <?php endif; ?>
                            <?php echo esc_html($guide); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- رسانه و تصاویر -->
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-format-image"></span> رسانه و تصاویر</div>
                <div class="wss-toggle-list">
                    <?php
                    $media_toggles = [
                        'lazy_images'  => ['Lazy Load تصاویر',  'تصاویر فقط هنگام رسیدن به آن‌ها لود می‌شوند', 'safe',    'attribute loading="lazy" به تصاویر اضافه می‌شود. مرورگرهای مدرن به‌طور کامل پشتیبانی می‌کنند. توصیه‌شده.'],
                        'lazy_iframes' => ['Lazy Load iFrame',   'iFrame‌ها (ویدیو، نقشه) به‌صورت تنبل لود می‌شوند', 'safe', 'صفحاتی که ویدیو یا نقشه embed دارند سریع‌تر لود می‌شوند.'],
                    ];
                    foreach ($media_toggles as $key => [$label, $desc, $level, $guide]): ?>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(!empty($opts[$key])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text">
                            <strong><?php echo esc_html($label); ?> <button class="wss-guide-toggle" type="button" data-guide="g-<?php echo $key; ?>">راهنما</button></strong>
                            <span><?php echo esc_html($desc); ?></span>
                        </div>
                    </label>
                    <div class="wss-guide" id="g-<?php echo $key; ?>">
                        <div class="wss-guide-body">
                            <strong>✅ امن و توصیه‌شده</strong>
                            <?php echo esc_html($guide); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="wss-field" style="margin-top:10px">
                    <label>Preload فونت‌ها <small>(هر URL در یک خط)</small></label>
                    <textarea name="preload_fonts" rows="3" placeholder="https://example.com/fonts/font.woff2"><?php echo esc_textarea($opts['preload_fonts'] ?? ''); ?></textarea>
                    <small class="wss-help">فونت‌های مهم را پیش‌لود کنید تا FOUT کاهش یابد.</small>
                </div>
            </div>

            <!-- شبکه و کشینگ -->
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-cloud"></span> شبکه و کشینگ</div>
                <div class="wss-toggle-list">
                    <?php
                    $net_toggles = [
                        'remove_query_str' => ['حذف Query String', 'پارامترهای ورژن از URL فایل‌های استاتیک حذف می‌شوند', 'safe', 'CDN و مرورگر فایل‌های استاتیک را بهتر کش می‌کنند. امن.'],
                        'browser_cache'    => ['Cache مرورگر',    'هدرهای Cache-Control تنظیم می‌شوند',                'safe', 'فایل‌های CSS، JS، تصویر توسط مرورگر برای مدت طولانی‌تری کش می‌شوند. توصیه‌شده.'],
                        'gzip'             => ['فشرده‌سازی GZIP', 'خروجی HTML با GZIP فشرده می‌شود',                   'safe', 'اگه سرور GZIP دارد، این گزینه را روشن نکنید. در غیر این صورت، خروجی PHP توسط ob_gzhandler فشرده می‌شود.'],
                    ];
                    foreach ($net_toggles as $key => [$label, $desc, $level, $guide]): ?>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(!empty($opts[$key])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text">
                            <strong><?php echo esc_html($label); ?> <button class="wss-guide-toggle" type="button" data-guide="g-<?php echo $key; ?>">راهنما</button></strong>
                            <span><?php echo esc_html($desc); ?></span>
                        </div>
                    </label>
                    <div class="wss-guide" id="g-<?php echo $key; ?>">
                        <div class="wss-guide-body">
                            <strong>✅ امن</strong>
                            <?php echo esc_html($guide); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="wss-field" style="margin-top:10px">
                    <label>DNS Prefetch <small>(هر دامنه در یک خط)</small></label>
                    <textarea name="dns_prefetch" rows="2" placeholder="//fonts.googleapis.com"><?php echo esc_textarea($opts['dns_prefetch'] ?? ''); ?></textarea>
                </div>
                <div class="wss-field">
                    <label>Preconnect <small>(هر URL در یک خط)</small></label>
                    <textarea name="preconnect" rows="2" placeholder="https://fonts.gstatic.com"><?php echo esc_textarea($opts['preconnect'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- پاکسازی وردپرس -->
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-wordpress"></span> پاکسازی وردپرس</div>
                <div class="wss-toggle-list">
                    <?php
                    $wp_toggles = [
                        'disable_emoji'     => ['غیرفعال کردن Emoji',        'اسکریپت‌های Emoji وردپرس حذف می‌شوند (4–8KB)', 'safe',    'اگه از emoji ها در پست‌ها استفاده می‌کنید، تصویری در مرورگرهای قدیمی نمایش داده نمی‌شود. در مرورگرهای مدرن بدون مشکل است.'],
                        'disable_embeds'    => ['غیرفعال کردن Embed',        'oEmbed برای دیگر سایت‌ها غیرفعال می‌شود',    'safe',    'صرفاً توانایی embed کردن پست‌های شما در سایت‌های دیگر غیرفعال می‌شود. ویدیوهای embed داخل پست‌ها تأثیری نمی‌بینند.'],
                        'disable_xmlrpc'    => ['غیرفعال کردن XML-RPC',      'API قدیمی وردپرس غیرفعال می‌شود',            'caution', '⚠️ اگه از JetPack، برنامه موبایل وردپرس یا هر ابزاری که به XML-RPC نیاز دارد استفاده می‌کنید، فعال نکنید.'],
                        'disable_dashicons' => ['حذف Dashicons برای مهمانان','آیکون‌های WP برای بازدیدکنندگان لود نمی‌شوند', 'safe', 'فقط برای کاربران مهمان (غیر لاگین). 30KB کاهش در هر صفحه.'],
                    ];
                    foreach ($wp_toggles as $key => [$label, $desc, $level, $guide]): ?>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(!empty($opts[$key])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text">
                            <strong><?php echo esc_html($label); ?> <button class="wss-guide-toggle" type="button" data-guide="g-<?php echo $key; ?>">راهنما</button></strong>
                            <span><?php echo esc_html($desc); ?></span>
                        </div>
                    </label>
                    <div class="wss-guide" id="g-<?php echo $key; ?>">
                        <div class="wss-guide-body">
                            <?php if ($level === 'safe'): ?>
                            <strong>✅ امن</strong>
                            <?php else: ?>
                            <strong class="warn">⚠️ با احتیاط</strong>
                            <?php endif; ?>
                            <?php echo esc_html($guide); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- .wss-settings-grid -->

        <div class="wss-submit-bar">
            <button type="submit" class="button button-primary button-large">
                <span class="dashicons dashicons-yes"></span> ذخیره تنظیمات
            </button>
            <span id="wss-recommended-msg" style="font-size:12px;color:var(--wss-success)"></span>
        </div>
    </form>
</div>

<script>
(function(){
    // Guide toggles
    document.querySelectorAll('.wss-guide-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault(); e.stopPropagation();
            var g = document.getElementById(this.dataset.guide);
            if (g) g.classList.toggle('open');
        });
    });

    // Auto-enable recommended
    document.getElementById('wss-enable-recommended').addEventListener('click', function(){
        if(!confirm('همه تنظیمات پیشنهادی (سرعت، کش، WebP، سئو پایه) فعال شوند؟')) return;
        var btn = this;
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
            } else {
                btn.disabled = false;
                btn.textContent = 'خطا — دوباره تلاش کنید';
            }
        });
    });
})();
</script>
