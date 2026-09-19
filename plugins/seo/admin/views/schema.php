<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php $opts = (array) get_option('wss_schema', []); ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-networking"></span> Schema.org</h1>
        <p>داده‌های ساختاریافته برای نمایش بهتر در نتایج گوگل</p>
    </div>

    <div class="wss-schema-types">
        <div class="wss-schema-badge">WebSite</div>
        <div class="wss-schema-badge active">Organization/Person</div>
        <div class="wss-schema-badge active">Article</div>
        <div class="wss-schema-badge active">BreadcrumbList</div>
        <div class="wss-schema-badge active">FAQ</div>
        <div class="wss-schema-badge active">WebPage</div>
        <div class="wss-schema-badge active">SearchAction</div>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wss_save_settings">
        <input type="hidden" name="wss_group" value="wss_schema">
        <?php wp_nonce_field('wss_save_settings'); ?>

        <div class="wss-settings-grid">

            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-building"></span> اطلاعات سازمان/شخص</div>

                <div class="wss-field">
                    <label>نوع</label>
                    <div class="wss-radio-group">
                        <label class="wss-radio-item">
                            <input type="radio" name="schema_type" value="Organization" <?php checked(($opts['type'] ?? 'Organization'), 'Organization'); ?>>
                            <span>Organization (سازمان/شرکت)</span>
                        </label>
                        <label class="wss-radio-item">
                            <input type="radio" name="schema_type" value="Person" <?php checked(($opts['type'] ?? ''), 'Person'); ?>>
                            <span>Person (شخص/بلاگر)</span>
                        </label>
                        <label class="wss-radio-item">
                            <input type="radio" name="schema_type" value="LocalBusiness" <?php checked(($opts['type'] ?? ''), 'LocalBusiness'); ?>>
                            <span>LocalBusiness (کسب‌وکار محلی)</span>
                        </label>
                    </div>
                </div>

                <div class="wss-field">
                    <label>نام سازمان/شخص</label>
                    <input type="text" name="schema_name" value="<?php echo esc_attr($opts['name'] ?? get_bloginfo('name')); ?>" class="widefat">
                </div>

                <div class="wss-field">
                    <label>لوگو</label>
                    <input type="text" name="schema_logo" id="wss-schema-logo" value="<?php echo esc_url($opts['logo'] ?? ''); ?>" class="widefat">
                    <button type="button" class="button" id="wss-logo-btn" style="margin-top:5px">انتخاب لوگو</button>
                    <?php if (!empty($opts['logo'])): ?>
                    <br><img src="<?php echo esc_url($opts['logo']); ?>" style="max-height:60px;margin-top:8px">
                    <?php endif; ?>
                </div>
            </div>

            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-phone"></span> اطلاعات تماس</div>

                <div class="wss-field">
                    <label>شماره تلفن</label>
                    <input type="text" name="schema_phone" value="<?php echo esc_attr($opts['phone'] ?? ''); ?>" placeholder="+98-21-XXXXXXXX" class="widefat">
                </div>
                <div class="wss-field">
                    <label>آدرس ایمیل</label>
                    <input type="email" name="schema_email" value="<?php echo esc_attr($opts['email'] ?? ''); ?>" class="widefat">
                </div>
                <div class="wss-field">
                    <label>آدرس</label>
                    <input type="text" name="schema_address" value="<?php echo esc_attr($opts['address'] ?? ''); ?>" class="widefat">
                </div>
                <div class="wss-field">
                    <label>شهر</label>
                    <input type="text" name="schema_city" value="<?php echo esc_attr($opts['city'] ?? ''); ?>" class="widefat">
                </div>
                <div class="wss-field">
                    <label>کد کشور</label>
                    <input type="text" name="schema_country" value="<?php echo esc_attr($opts['country'] ?? 'IR'); ?>" style="width:80px">
                </div>
                <div class="wss-toggle-list">
                    <label class="wss-toggle-item">
                        <div class="wss-toggle-switch">
                            <input type="checkbox" name="social_profiles" value="1" <?php checked(!empty($opts['social_profiles'])); ?>>
                            <span class="wss-slider"></span>
                        </div>
                        <div class="wss-toggle-text"><strong>درج پروفایل‌های اجتماعی (sameAs)</strong></div>
                    </label>
                </div>
            </div>

            <div class="wss-card wss-card-wide">
                <div class="wss-card-header"><span class="dashicons dashicons-editor-help"></span> راهنمای شورت‌کد FAQ</div>
                <p>برای ایجاد داده ساختاریافته FAQ در پست‌ها از شورت‌کد زیر استفاده کنید:</p>
                <pre class="wss-code">[wss_faq q="سوال اول؟"]
پاسخ به سوال اول در اینجا نوشته می‌شود.
[/wss_faq]

[wss_faq q="سوال دوم؟"]
پاسخ به سوال دوم در اینجا نوشته می‌شود.
[/wss_faq]</pre>
                <p>این شورت‌کد به صورت خودکار Schema.org FAQPage تولید می‌کند و شانس نمایش در Featured Snippets را افزایش می‌دهد.</p>
            </div>

        </div>

        <div class="wss-submit-bar">
            <button type="submit" class="button button-primary button-large">ذخیره Schema.org</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('wss-logo-btn');
    if(btn && typeof wp !== 'undefined' && wp.media){
        btn.addEventListener('click', function(){
            var frame = wp.media({title:'انتخاب لوگو',button:{text:'انتخاب'},multiple:false});
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                document.getElementById('wss-schema-logo').value = att.url;
            });
            frame.open();
        });
    }
});
</script>
