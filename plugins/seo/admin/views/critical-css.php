<?php if (!defined('ABSPATH')) exit; ?>
<?php
$opts  = (array) get_option('wss_critical_css', []);
$types = WSS_Critical_CSS::instance()->get_types();
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-editor-code"></span> Critical CSS</h1>
        <p>تعریف CSS ضروری برای بارگذاری سریع‌تر Render — حذف CSS مسدودکننده</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>Critical CSS چیست؟</h3>
            <p>CSS ضروری (above-the-fold) را inline کنید تا FCP و LCP بهبود یابد — بدون Render Blocking.</p>
        </div>
        <button class="button" id="wss-critical-guide-toggle">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
        </button>
    </div>

    <div class="wss-card" id="wss-critical-guide" style="margin-bottom:16px;display:none">
        <div class="wss-card-header"><span class="dashicons dashicons-info"></span> راهنمای استخراج و اعمال Critical CSS</div>
        <div style="padding:18px">
            <div class="wss-steps">
                <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>DevTools را باز کنید</strong><span>در Chrome: F12 → تب Coverage (Ctrl+Shift+P → Coverage) → کلیک روی دایره ضبط → رفرش صفحه</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>CSS استفاده‌شده را کپی کنید</strong><span>فایل CSS اصلی قالب را انتخاب کنید — بخش آبی = used. CSS‌های used را کپی کنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>در ویرایشگر پایین قرار دهید</strong><span>تب Global برای همه صفحات، تب Home برای صفحه اصلی. دکمه ذخیره را بزنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>فعال کنید و تست کنید</strong><span>تیک "فعال‌سازی Critical CSS" را بزنید → PageSpeed Insights را مجدداً اجرا کنید.</span></div></div>
            </div>
            <div class="wss-info-box" style="margin-top:12px">
                <strong>ابزارهای آنلاین:</strong>
                <a href="https://penthouse.aisle.one/" target="_blank">Penthouse</a> &nbsp;|&nbsp;
                <a href="https://criticalcss.com/" target="_blank">CriticalCSS.com</a> — Critical CSS را به صورت خودکار استخراج می‌کنند.
                اندازه Critical CSS باید کمتر از <strong>14KB</strong> باشد.
            </div>
        </div>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_critical_css">
        <?php wp_nonce_field('wss_save_settings'); ?>
        <div class="wss-card" style="margin-bottom:20px">
            <div class="wss-card-header"><span class="dashicons dashicons-controls-play"></span> فعال‌سازی</div>
            <div class="wss-toggle-list">
                <label class="wss-toggle-item">
                    <div class="wss-toggle-switch">
                        <input type="checkbox" name="enabled" value="1" <?php checked(!empty($opts['enabled'])); ?>>
                        <span class="wss-slider"></span>
                    </div>
                    <div class="wss-toggle-text"><strong>فعال‌سازی Critical CSS</strong><span>استایل‌های ضروری inline و بقیه deferred می‌شوند</span></div>
                </label>
            </div>
            <div class="wss-field" style="padding:12px 18px 18px">
                <label>هندل‌های CSS حیاتی (از deferred خارج شوند) <small>(با کاما جدا کنید)</small></label>
                <input type="text" name="critical_handles" value="<?php echo esc_attr($opts['critical_handles'] ?? ''); ?>" class="widefat" placeholder="my-theme-style, woocommerce-general">
            </div>
            <div class="wss-submit-bar" style="margin:0;border:none;border-top:1px solid #e2e8f0;border-radius:0">
                <button type="submit" class="button button-primary">ذخیره</button>
            </div>
        </div>
    </form>

    <div class="wss-card">
        <div class="wss-card-header"><span class="dashicons dashicons-edit"></span> ویرایش Critical CSS</div>

        <div class="wss-tab-nav" style="margin:0;border-bottom:1px solid #e2e8f0">
            <?php foreach ($types as $type => [$label, $css]): ?>
            <button class="wss-nav-tab <?php echo $type === 'global' ? 'active' : ''; ?>" data-tab="css-<?php echo $type; ?>"><?php echo esc_html($label); ?></button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($types as $type => [$label, $css]): ?>
        <div class="wss-tab-panel <?php echo $type === 'global' ? 'active' : ''; ?>" id="tab-css-<?php echo $type; ?>" style="padding:18px">
            <div class="wss-field">
                <label>Critical CSS برای: <strong><?php echo esc_html($label); ?></strong></label>
                <textarea class="wss-mono widefat wss-css-editor" rows="15" data-type="<?php echo $type; ?>"><?php echo esc_textarea($css); ?></textarea>
                <small class="wss-help">اگر خالی بشد، CSS عمومی اعمال می‌شود. CSS را از DevTools مرورگر استخراج کنید.</small>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <button class="button button-primary wss-save-critical" data-type="<?php echo $type; ?>">ذخیره</button>
                <button class="button wss-delete-critical" data-type="<?php echo $type; ?>">پاک کردن</button>
                <button class="button wss-beautify-css" data-type="<?php echo $type; ?>">قالب‌بندی</button>
                <span class="wss-char-counter" id="wss-cnt-<?php echo $type; ?>" style="margin-right:auto;color:#64748b;font-size:12px"><?php echo mb_strlen($css); ?> کاراکتر</span>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <div class="wss-card" style="margin-top:20px">
        <div class="wss-card-header"><span class="dashicons dashicons-lightbulb"></span> نکات</div>
        <div style="padding:18px">
            <ul style="list-style:disc;padding-right:18px;font-size:13px;color:#444;line-height:2">
                <li>Critical CSS باید شامل استایل‌های قابل مشاهده در viewport اولیه (above-the-fold) باشد</li>
                <li>از DevTools Chrome: Coverage tab → انتخاب CSS فایل‌ها → استایل‌های استفاده‌شده</li>
                <li>ابزارهای آنلاین: <a href="https://penthouse.aisle.one/" target="_blank">Penthouse</a>, <a href="https://criticalcss.com/" target="_blank">CriticalCSS.com</a></li>
                <li>اندازه Critical CSS باید کمتر از 14KB باشد</li>
                <li>پس از اعمال، PageSpeed Insights را مجدداً اجرا کنید</li>
            </ul>
        </div>
    </div>
</div>

<script>
(function($){
    $('.wss-nav-tab').on('click', function(){
        var tab=$(this).data('tab');
        $('.wss-nav-tab,.wss-tab-panel').removeClass('active');
        $(this).addClass('active');
        $('#tab-'+tab).addClass('active');
    });

    // Character counter
    $('.wss-css-editor').on('input', function(){
        var type=$(this).data('type');
        $('#wss-cnt-'+type).text($(this).val().length+' کاراکتر');
    });

    // Save
    $('.wss-save-critical').on('click', function(){
        var type=$(this).data('type');
        var css=$('.wss-css-editor[data-type='+type+']').val();
        var btn=$(this).prop('disabled',true).text('در حال ذخیره...');
        $.post(wssAdmin.ajaxUrl,{action:'wss_save_critical',nonce:wssAdmin.nonce,type:type,css:css},function(res){
            btn.prop('disabled',false).text('ذخیره');
            if(res.success) alert('✓ ذخیره شد');
        });
    });

    // Delete
    $('.wss-delete-critical').on('click', function(){
        if(!confirm('پاک شود؟')) return;
        var type=$(this).data('type');
        $.post(wssAdmin.ajaxUrl,{action:'wss_delete_critical',nonce:wssAdmin.nonce,type:type},function(res){
            if(res.success){
                $('.wss-css-editor[data-type='+type+']').val('');
                $('#wss-cnt-'+type).text('0 کاراکتر');
            }
        });
    });

    // Guide toggle
    $('#wss-critical-guide-toggle').on('click', function(){
        var guide = document.getElementById('wss-critical-guide');
        guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
    });

    // Beautify
    $('.wss-beautify-css').on('click', function(){
        var ta=$('.wss-css-editor[data-type='+$(this).data('type')+']');
        var css=ta.val();
        // Simple CSS formatter
        css=css.replace(/\{/g,' {\n    ').replace(/;/g,';\n    ').replace(/\}/g,'\n}\n').replace(/    \n\}/g,'\n}');
        ta.val(css.trim());
    });
})(jQuery);
</script>
