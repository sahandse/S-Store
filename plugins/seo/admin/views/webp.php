<?php if (!defined('ABSPATH')) exit; ?>
<?php
$opts  = (array) get_option('wss_webp', []);
$stats = WSS_WebP_Converter::instance()->get_stats();
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-format-image"></span> WebP Converter</h1>
        <p>تبدیل خودکار تصاویر به فرمت WebP — 30-80% کوچکتر بدون افت کیفیت</p>
    </div>

    <?php if (!$stats['supported']): ?>
    <div class="notice notice-error"><p>PHP GD یا Imagick با پشتیبانی از WebP نصب نشده است. لطفاً با هاستینگ خود تماس بگیرید.</p></div>
    <?php else: ?>

    <?php if (empty($opts['enabled'])): ?>
    <div class="wss-action-hero">
        <div>
            <h3>WebP غیرفعال است</h3>
            <p>تبدیل خودکار هنگام آپلود و سرو WebP را فعال کنید — ۳۰-۸۰٪ کاهش حجم تصاویر.</p>
        </div>
        <button class="button" id="wss-auto-webp">
            <span class="dashicons dashicons-yes-alt"></span> فعال‌سازی خودکار
        </button>
    </div>
    <?php endif; ?>

    <div class="wss-card" style="margin-bottom:16px">
        <div class="wss-card-header" style="cursor:pointer" onclick="this.nextElementSibling.classList.toggle('open')">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
            <span style="margin-right:auto;font-size:11px;color:var(--wss-muted)">▼</span>
        </div>
        <div class="wss-guide open" style="margin:0;border:none;border-radius:0">
            <div class="wss-guide-body" style="padding:0">
                <div class="wss-steps">
                    <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>تبدیل خودکار را فعال کنید</strong><span>تصاویر جدید هنگام آپلود به WebP تبدیل می‌شوند.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>تبدیل انبوه اجرا کنید</strong><span>تصاویر قبلی را با دکمه "شروع تبدیل انبوه" WebP کنید.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>کیفیت را تنظیم کنید</strong><span>۸۲ تعادل خوبی بین کیفیت و حجم است. برای عکاسی ۸۵-۹۰ توصیه می‌شود.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>نتیجه را بررسی کنید</strong><span>در Core Web Vitals صفحه سایت را تست کنید و بهبود LCP را مشاهده کنید.</span></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="wss-stats-bar">
        <div class="wss-stat-box">
            <div class="val"><?php echo $stats['total_imgs']; ?></div>
            <div class="lbl">کل تصاویر</div>
        </div>
        <div class="wss-stat-box good">
            <div class="val"><?php echo $stats['webp_files']; ?></div>
            <div class="lbl">فایل WebP</div>
        </div>
        <div class="wss-stat-box">
            <div class="val"><?php echo $stats['webp_size']; ?></div>
            <div class="lbl">حجم WebP</div>
        </div>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_webp">
        <?php wp_nonce_field('wss_save_settings'); ?>

        <div class="wss-settings-grid">
            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-admin-settings"></span> تنظیمات</div>
                <div class="wss-toggle-list">
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="enabled" value="1" <?php checked(!empty($opts['enabled'])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text"><strong>تبدیل خودکار هنگام آپلود</strong><span>تصاویر جدید بلافاصله WebP می‌شوند</span></div>
                    </label>
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="serve_webp" value="1" <?php checked(!empty($opts['serve_webp'])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text"><strong>سرو WebP برای مرورگرهای پشتیبان</strong><span>Chrome، Firefox، Edge</span></div>
                    </label>
                </div>
                <div class="wss-field" style="padding:12px 18px 18px">
                    <label>کیفیت WebP <small>(0-100)</small></label>
                    <input type="range" name="quality" min="50" max="100" value="<?php echo (int)($opts['quality'] ?? 82); ?>"
                           oninput="document.getElementById('wss-q-val').textContent=this.value" style="width:200px">
                    <span id="wss-q-val" style="margin-right:8px;font-weight:bold"><?php echo (int)($opts['quality'] ?? 82); ?></span>
                </div>
            </div>

            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-update"></span> تبدیل انبوه</div>
                <div style="padding:18px">
                    <p>تصاویر موجود در کتابخانه رسانه را به WebP تبدیل کنید:</p>
                    <div class="wss-progress-bar" id="wss-webp-progress" style="display:none">
                        <div class="wss-progress-fill" id="wss-webp-fill" style="width:0%"></div>
                        <span id="wss-webp-pct">0%</span>
                    </div>
                    <div id="wss-webp-status" style="margin:10px 0;font-size:13px;color:#555"></div>
                    <button type="button" class="button button-primary" id="wss-bulk-convert">
                        <span class="dashicons dashicons-update"></span> شروع تبدیل انبوه
                    </button>
                </div>
            </div>
        </div>

        <div class="wss-submit-bar">
            <button type="submit" class="button button-primary button-large">ذخیره تنظیمات WebP</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function($){
    var offset = 0;
    var running = false;

    $('#wss-bulk-convert').on('click', function(){
        if(running) return;
        running = true;
        offset  = 0;
        $(this).prop('disabled', true).text('در حال تبدیل...');
        $('#wss-webp-progress').show();
        doConvert();
    });

    function doConvert(){
        $.post(wssAdmin.ajaxUrl, {
            action: 'wss_convert_batch',
            nonce:  wssAdmin.nonce,
            offset: offset
        }, function(res){
            if(!res.success){ finish('خطا'); return; }
            var d = res.data;
            var pct = d.progress + '%';
            $('#wss-webp-fill').css('width', pct);
            $('#wss-webp-pct').text(pct);
            $('#wss-webp-status').text('تبدیل‌شده: ' + d.converted + ' | رد‌شده: ' + d.skipped + ' | خطا: ' + d.errors);

            if(d.done){
                finish('✓ تبدیل کامل شد!');
            } else {
                offset = d.next;
                setTimeout(doConvert, 200);
            }
        });
    }

    function finish(msg){
        running = false;
        $('#wss-bulk-convert').prop('disabled', false).text('شروع تبدیل انبوه');
        $('#wss-webp-status').html('<strong>' + msg + '</strong>');
    }
    // Auto-enable webp
    var autoBtn = document.getElementById('wss-auto-webp');
    if(autoBtn) autoBtn.addEventListener('click', function(){
        autoBtn.disabled=true; autoBtn.innerHTML='<span class="dashicons dashicons-update"></span> در حال فعال‌سازی...';
        fetch(wssAdmin.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=wss_enable_recommended&nonce='+wssAdmin.nonce})
        .then(r=>r.json()).then(function(res){ if(res.success){ autoBtn.innerHTML='✓ فعال شد — رفرش...'; setTimeout(()=>location.reload(),1400); } });
    });
})(jQuery);
</script>

<style>
.wss-stats-bar{display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap}
.wss-stat-box{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 24px;text-align:center;min-width:120px}
.wss-stat-box .val{font-size:28px;font-weight:700;color:#1e293b}
.wss-stat-box.good .val{color:#46b450}
.wss-stat-box .lbl{font-size:12px;color:#64748b;margin-top:4px}
.wss-stat-box .val.small{font-size:16px}
.wss-progress-bar{background:#e2e8f0;border-radius:20px;height:24px;position:relative;overflow:hidden;margin-bottom:10px}
.wss-progress-fill{background:linear-gradient(90deg,#0073aa,#00a0d2);height:100%;border-radius:20px;transition:width 0.3s}
.wss-progress-bar span{position:absolute;right:50%;top:50%;transform:translate(50%,-50%);font-size:12px;font-weight:bold;color:#fff}
</style>
