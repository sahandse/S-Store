<?php if (!defined('ABSPATH')) exit; ?>
<?php
$opts  = (array) get_option('wss_cache', []);
$stats = WSS_Page_Cache::instance()->get_stats();
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-archive"></span> کش صفحات</h1>
        <p>ذخیره HTML استاتیک صفحات — سرعت بارگذاری تا ۱۰ برابر بهبود می‌یابد.</p>
    </div>

    <?php if (empty($opts['enabled'])): ?>
    <div class="wss-action-hero">
        <div>
            <h3>کش صفحات غیرفعال است</h3>
            <p>با یک کلیک فعال کنید — تنظیمات پیشنهادی به صورت خودکار اعمال می‌شوند.</p>
        </div>
        <button class="button" id="wss-auto-cache">
            <span class="dashicons dashicons-yes-alt"></span> فعال‌سازی خودکار
        </button>
    </div>
    <?php endif; ?>

    <div class="wss-stats-bar">
        <div class="wss-stat-box <?php echo $stats['count']>0?'good':''; ?>">
            <div class="val"><?php echo $stats['count']; ?></div><div class="lbl">صفحه کش‌شده</div>
        </div>
        <div class="wss-stat-box"><div class="val"><?php echo $stats['size']; ?></div><div class="lbl">حجم کش</div></div>
        <div class="wss-stat-box <?php echo !empty($opts['enabled'])?'good':''; ?>">
            <div class="val"><?php echo !empty($opts['enabled'])?'فعال':'غیرفعال'; ?></div><div class="lbl">وضعیت</div>
        </div>
    </div>

    <!-- Guide -->
    <div class="wss-card" style="margin-bottom:16px">
        <div class="wss-card-header" style="cursor:pointer" onclick="this.nextElementSibling.classList.toggle('open')">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
            <span style="margin-right:auto;font-size:11px;color:var(--wss-muted)">▼</span>
        </div>
        <div id="wss-cache-guide" class="wss-guide open" style="margin:0;border:none;border-radius:0">
            <div class="wss-guide-body" style="padding:0">
                <div class="wss-steps">
                    <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>کش را فعال کنید</strong><span>تیک "فعال‌سازی کش" را بزنید یا دکمه بالا را کلیک کنید.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>TTL تنظیم کنید</strong><span>سایت‌های پرتغییر: ۳۰۰ ثانیه | سایت‌های ثابت: ۸۶۴۰۰ ثانیه (۱ روز)</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>بعد از تغییر محتوا، کش پاک کنید</strong><span>ذخیره پست به صورت خودکار کش آن صفحه را پاک می‌کند.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>استثناها را تنظیم کنید</strong><span>cart، checkout، my-account به صورت خودکار از کش خارج هستند.</span></div></div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_cache">
        <?php wp_nonce_field('wss_save_settings'); ?>
        <div class="wss-settings-grid">
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-admin-settings"></span> تنظیمات</div>
                <div class="wss-toggle-list">
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch"><input type="checkbox" name="enabled" value="1" <?php checked(!empty($opts['enabled'])); ?>><span class="wss-slider"></span></div>
                        <div class="wss-toggle-text"><strong>فعال‌سازی کش صفحات</strong><span>HTML استاتیک تولید و سرویس داده می‌شود</span></div>
                    </label>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch"><input type="checkbox" name="gzip_cache" value="1" <?php checked(!empty($opts['gzip_cache'])); ?>><span class="wss-slider"></span></div>
                        <div class="wss-toggle-text"><strong>فشرده‌سازی GZIP کش</strong><span>فایل‌های کش با GZIP ذخیره — فضا و سرعت بهتر</span></div>
                    </label>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch"><input type="checkbox" name="cache_logged_in" value="1" <?php checked(!empty($opts['cache_logged_in'])); ?>><span class="wss-slider"></span></div>
                        <div class="wss-toggle-text"><strong>کش برای کاربران لاگین</strong><span>⚠️ توصیه نمی‌شود — محتوای شخصی ممکن است اشتباه نمایش داده شود</span></div>
                    </label>
                </div>
                <div class="wss-field" style="margin-top:10px">
                    <label>مدت زمان کش — ثانیه (TTL)</label>
                    <input type="number" name="ttl" value="<?php echo (int)($opts['ttl'] ?? 3600); ?>" min="60" max="604800">
                    <small class="wss-help">۳۶۰۰ = ۱ ساعت &nbsp;|&nbsp; ۸۶۴۰۰ = ۱ روز &nbsp;|&nbsp; ۶۰۴۸۰۰ = ۱ هفته</small>
                </div>
                <div class="wss-field">
                    <label>URL‌های استثنا <small>(هر خط یک URL)</small></label>
                    <textarea name="exclude_urls" rows="3" placeholder="/cart&#10;/checkout&#10;/my-account"><?php echo esc_textarea($opts['exclude_urls'] ?? ''); ?></textarea>
                </div>
                <div class="wss-submit-bar"><button type="submit" class="button button-primary">ذخیره تنظیمات</button></div>
            </div>

            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-trash"></span> مدیریت کش</div>
                <div style="padding:16px">
                    <p style="font-size:12px;color:var(--wss-muted);margin:0 0 12px"><?php echo $stats['count']; ?> صفحه &nbsp;|&nbsp; <?php echo $stats['size']; ?></p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wss_clear_cache">
                        <?php wp_nonce_field('wss_clear_cache'); ?>
                        <button type="submit" class="button button-primary"><span class="dashicons dashicons-trash"></span> پاک کردن همه کش</button>
                    </form>
                    <div class="wss-info-box" style="margin-top:12px">
                        کش با ذخیره پست، نصب افزونه یا تغییر قالب به صورت خودکار پاک می‌شود.
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    var btn = document.getElementById('wss-auto-cache');
    if (!btn) return;
    btn.addEventListener('click', function(){
        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update"></span> در حال فعال‌سازی...';
        fetch(wssAdmin.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=wss_enable_recommended&nonce='+wssAdmin.nonce})
        .then(r=>r.json()).then(function(res){
            if(res.success){ btn.innerHTML='✓ فعال شد — رفرش...'; setTimeout(()=>location.reload(),1400); }
        });
    });
})();
</script>
