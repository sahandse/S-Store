<?php if (!defined('ABSPATH')) exit; ?>
<?php
$downloaded = WSS_Local_Fonts::instance()->get_downloaded_fonts();
$common     = WSS_Local_Fonts::get_common_fonts();
$opts       = (array) get_option('wss_local_fonts', []);
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-editor-textcolor"></span> فونت‌های محلی</h1>
        <p>دانلود و میزبانی فونت‌های Google Fonts روی سرور خود — بدون درخواست به سرورهای خارجی</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>فونت‌های Google را محلی کنید</h3>
            <p>درخواست به fonts.googleapis.com حذف می‌شود — FCP بهتر، GDPR رعایت می‌شود.</p>
        </div>
        <button class="button" id="wss-fonts-guide-toggle">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
        </button>
    </div>

    <div class="wss-card" id="wss-fonts-guide" style="margin-bottom:16px;display:none">
        <div class="wss-card-header"><span class="dashicons dashicons-info"></span> راهنمای دانلود و میزبانی فونت</div>
        <div style="padding:18px">
            <div class="wss-steps">
                <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>فونت را از Google Fonts انتخاب کنید</strong><span>به fonts.google.com بروید، فونت موردنظر را انتخاب و لینک CSS را کپی کنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>لینک را در فرم زیر وارد کنید</strong><span>URL کامل CSS فونت مثل: https://fonts.googleapis.com/css2?family=Vazirmatn</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>دکمه "دانلود و میزبانی" را بزنید</strong><span>فایل‌های فونت و CSS به سرور شما منتقل می‌شوند.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>جایگزینی خودکار را فعال کنید</strong><span>تیک "جایگزینی خودکار Google Fonts" را بزنید و ذخیره کنید.</span></div></div>
            </div>
        </div>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_local_fonts">
        <?php wp_nonce_field('wss_save_settings'); ?>
        <div class="wss-card" style="margin-bottom:20px">
            <div class="wss-card-header"><span class="dashicons dashicons-admin-settings"></span> تنظیمات</div>
            <div class="wss-toggle-list">
                <label class="wss-toggle-item">
                    <div class="wss-toggle-switch">
                        <input type="checkbox" name="enabled" value="1" <?php checked(!empty($opts['enabled'])); ?>>
                        <span class="wss-slider"></span>
                    </div>
                    <div class="wss-toggle-text"><strong>جایگزینی خودکار Google Fonts با نسخه محلی</strong><span>فونت‌های دانلودشده به جای Google Fonts بارگذاری می‌شوند</span></div>
                </label>
            </div>
            <div class="wss-submit-bar" style="margin:0;border:none;border-top:1px solid #e2e8f0;border-radius:0">
                <button type="submit" class="button button-primary">ذخیره</button>
            </div>
        </div>
    </form>

    <div class="wss-settings-grid">

        <!-- Download -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-download"></span> دانلود فونت</div>
            <div style="padding:18px">
                <div class="wss-field">
                    <label>فونت‌های پرکاربرد</label>
                    <select id="wss-font-presets">
                        <option value="">-- انتخاب سریع --</option>
                        <?php foreach ($common as $name => $url): ?>
                        <?php if ($url): ?>
                        <option value="<?php echo esc_attr($url); ?>" data-name="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wss-field">
                    <label>نام فونت</label>
                    <input type="text" id="wss-font-name" class="widefat" placeholder="Vazirmatn">
                </div>
                <div class="wss-field">
                    <label>Google Fonts URL</label>
                    <input type="url" id="wss-font-url" class="widefat" placeholder="https://fonts.googleapis.com/css2?family=...">
                    <small class="wss-help">آدرس را از Google Fonts کپی کنید</small>
                </div>
                <div id="wss-font-preview" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;font-size:11px;font-family:monospace;direction:ltr;margin-bottom:10px;max-height:100px;overflow:auto"></div>
                <div style="display:flex;gap:8px">
                    <button class="button" id="wss-preview-font">پیش‌نمایش CSS</button>
                    <button class="button button-primary" id="wss-download-font">
                        <span class="dashicons dashicons-download"></span> دانلود و میزبانی
                    </button>
                </div>
                <div id="wss-font-status" style="margin-top:10px;font-size:13px"></div>
            </div>
        </div>

        <!-- Downloaded fonts -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-list-view"></span> فونت‌های دانلودشده</div>
            <?php if ($downloaded): ?>
            <table class="wss-table wss-table-full">
                <thead><tr><th>نام</th><th>فایل‌ها</th><th>تاریخ</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($downloaded as $hash => $font): ?>
                <tr id="font-row-<?php echo esc_attr($hash); ?>">
                    <td><strong><?php echo esc_html($font['family']); ?></strong></td>
                    <td><?php echo (int)$font['downloaded']; ?> فایل</td>
                    <td style="font-size:11px"><?php echo esc_html($font['date']); ?></td>
                    <td>
                        <button class="button button-small wss-delete-font" data-hash="<?php echo esc_attr($hash); ?>">حذف</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="wss-empty-state"><span class="dashicons dashicons-editor-textcolor"></span><p>هنوز فونتی دانلود نشده.</p></div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
(function($){
    // Guide toggle
    $('#wss-fonts-guide-toggle').on('click', function(){
        var guide = document.getElementById('wss-fonts-guide');
        guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
    });

    // Preset selector
    $('#wss-font-presets').on('change', function(){
        var url=$(this).val(), name=$(this).find(':selected').data('name');
        $('#wss-font-url').val(url);
        if(name) $('#wss-font-name').val(name);
    });

    // Preview CSS
    $('#wss-preview-font').on('click', function(){
        var url=$('#wss-font-url').val();
        if(!url){alert('URL را وارد کنید');return;}
        $.post(wssAdmin.ajaxUrl,{action:'wss_preview_font_css',nonce:wssAdmin.nonce,font_url:url},function(res){
            if(res.success) $('#wss-font-preview').text(res.data.css.substring(0,600)+'...').show();
        });
    });

    // Download
    $('#wss-download-font').on('click', function(){
        var url=$('#wss-font-url').val(), name=$('#wss-font-name').val()||'Font';
        if(!url){alert('URL را وارد کنید');return;}
        var btn=$(this).prop('disabled',true).text('در حال دانلود...');
        $('#wss-font-status').text('').hide();
        $.post(wssAdmin.ajaxUrl,{action:'wss_download_font',nonce:wssAdmin.nonce,font_url:url,family:name},function(res){
            btn.prop('disabled',false).html('<span class="dashicons dashicons-download"></span> دانلود و میزبانی');
            if(res.success){
                $('#wss-font-status').html('<strong style="color:#46b450">✓ دانلود کامل شد! '+res.data.downloaded+' فایل ذخیره شد.</strong>').show();
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                $('#wss-font-status').html('<strong style="color:#dc3232">✗ خطا: '+res.data+'</strong>').show();
            }
        });
    });

    // Delete
    $(document).on('click','.wss-delete-font',function(){
        if(!confirm('این فونت حذف شود؟')) return;
        var hash=$(this).data('hash');
        $.post(wssAdmin.ajaxUrl,{action:'wss_delete_font',nonce:wssAdmin.nonce,hash:hash},function(res){
            if(res.success) $('#font-row-'+hash).fadeOut(300,function(){$(this).remove();});
        });
    });
})(jQuery);
</script>
