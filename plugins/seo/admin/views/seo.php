<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php
$seo    = (array) get_option('wss_seo',       []);
$social = (array) get_option('wss_social',    []);
$wm     = (array) get_option('wss_webmaster', []);
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-search"></span> تنظیمات سئو</h1>
        <p>تنظیمات کلی سئو، شبکه‌های اجتماعی و ابزارهای وبمستر</p>
    </div>

    <div class="wss-tab-nav">
        <button class="wss-nav-tab active" data-tab="general">عمومی</button>
        <button class="wss-nav-tab" data-tab="social">شبکه‌های اجتماعی</button>
        <button class="wss-nav-tab" data-tab="webmaster">وبمستر</button>
    </div>

    <!-- General SEO -->
    <div class="wss-tab-panel active" id="tab-general">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="wss_save_settings">
            <input type="hidden" name="wss_group" value="wss_seo">
            <?php wp_nonce_field('wss_save_settings'); ?>

            <div class="wss-settings-grid">

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-tag"></span> عنوان و توضیحات</div>
                    <div class="wss-field">
                        <label>جداکننده عنوان</label>
                        <div class="wss-sep-options">
                            <?php foreach (['-','|','•','–','—','>','»'] as $sep): ?>
                            <label class="wss-sep-option">
                                <input type="radio" name="title_separator" value="<?php echo esc_attr($sep); ?>" <?php checked(($seo['title_separator'] ?? '|'), $sep); ?>>
                                <span><?php echo esc_html($sep); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="wss-field">
                        <label>عنوان سئو صفحه اصلی</label>
                        <input type="text" name="title_home" value="<?php echo esc_attr($seo['title_home'] ?? ''); ?>" class="widefat" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    </div>
                    <div class="wss-field">
                        <label>توضیحات متا صفحه اصلی</label>
                        <textarea name="desc_home" rows="3" class="widefat"><?php echo esc_textarea($seo['desc_home'] ?? ''); ?></textarea>
                    </div>
                    <div class="wss-field">
                        <label>تصویر پیش‌فرض Open Graph</label>
                        <input type="text" name="default_og_image" id="wss-default-og" value="<?php echo esc_url($seo['default_og_image'] ?? ''); ?>" class="widefat">
                        <button type="button" class="button" id="wss-default-og-btn" style="margin-top:5px">انتخاب تصویر</button>
                    </div>
                    <div class="wss-field">
                        <label>طول خودکار توضیحات متا <small>(کاراکتر)</small></label>
                        <input type="number" name="auto_desc_length" value="<?php echo (int)($seo['auto_desc_length'] ?? 160); ?>" min="50" max="320" style="width:100px">
                    </div>
                </div>

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-share"></span> Open Graph و Twitter</div>
                    <div class="wss-toggle-list">
                        <label class="wss-toggle-item">
                            <div class="wss-toggle-switch">
                                <input type="checkbox" name="og_enabled" value="1" <?php checked(!empty($seo['og_enabled'])); ?>>
                                <span class="wss-slider"></span>
                            </div>
                            <div class="wss-toggle-text"><strong>Open Graph</strong><span>برچسب‌های og: برای فیسبوک و واتس‌اپ</span></div>
                        </label>
                        <label class="wss-toggle-item">
                            <div class="wss-toggle-switch">
                                <input type="checkbox" name="twitter_enabled" value="1" <?php checked(!empty($seo['twitter_enabled'])); ?>>
                                <span class="wss-slider"></span>
                            </div>
                            <div class="wss-toggle-text"><strong>Twitter Cards</strong><span>کارت‌های توئیتر برای اشتراک‌گذاری</span></div>
                        </label>
                    </div>
                    <div class="wss-field" style="margin-top:15px">
                        <label>نوع Twitter Card</label>
                        <select name="twitter_card">
                            <option value="summary_large_image" <?php selected(($seo['twitter_card'] ?? ''), 'summary_large_image'); ?>>Summary Large Image</option>
                            <option value="summary" <?php selected(($seo['twitter_card'] ?? ''), 'summary'); ?>>Summary</option>
                        </select>
                    </div>
                    <div class="wss-field">
                        <label>نام کاربری توئیتر سایت</label>
                        <input type="text" name="twitter_site" value="<?php echo esc_attr($seo['twitter_site'] ?? ''); ?>" placeholder="@username">
                    </div>
                </div>

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-admin-links"></span> Robots و Canonical</div>
                    <div class="wss-toggle-list">
                        <?php $toggles = [
                            'canonical_enabled'  => ['URL کانونیکال', 'از محتوای تکراری جلوگیری می‌کند'],
                            'noindex_archives'   => ['noindex آرشیوها', 'صفحات آرشیو از ایندکس خارج می‌شوند'],
                            'noindex_tags'       => ['noindex برچسب‌ها', 'صفحات برچسب از ایندکس خارج می‌شوند'],
                            'noindex_404'        => ['noindex صفحه 404', 'صفحه یافت نشد ایندکس نمی‌شود'],
                            'auto_description'   => ['توضیحات خودکار', 'اگر متا توضیحات خالی باشد، از محتوا گرفته می‌شود'],
                        ]; ?>
                        <?php foreach ($toggles as $key => [$label, $desc]): ?>
                        <label class="wss-toggle-item">
                            <div class="wss-toggle-switch">
                                <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(!empty($seo[$key])); ?>>
                                <span class="wss-slider"></span>
                            </div>
                            <div class="wss-toggle-text"><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($desc); ?></span></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-location"></span> Breadcrumb (مسیر صفحه)</div>
                    <div class="wss-toggle-list">
                        <label class="wss-toggle-item">
                            <div class="wss-toggle-switch">
                                <input type="checkbox" name="breadcrumbs" value="1" <?php checked(!empty($seo['breadcrumbs'])); ?>>
                                <span class="wss-slider"></span>
                            </div>
                            <div class="wss-toggle-text"><strong>فعال‌سازی Breadcrumb</strong><span>شورت‌کد [wss_breadcrumb] در دسترس است</span></div>
                        </label>
                        <label class="wss-toggle-item">
                            <div class="wss-toggle-switch">
                                <input type="checkbox" name="breadcrumbs_auto" value="1" <?php checked(!empty($seo['breadcrumbs_auto'])); ?>>
                                <span class="wss-slider"></span>
                            </div>
                            <div class="wss-toggle-text"><strong>درج خودکار در محتوا</strong><span>Breadcrumb به ابتدای محتوا اضافه می‌شود</span></div>
                        </label>
                    </div>
                    <div class="wss-field" style="margin-top:15px">
                        <label>متن خانه</label>
                        <input type="text" name="breadcrumb_home" value="<?php echo esc_attr($seo['breadcrumb_home'] ?? 'خانه'); ?>">
                    </div>
                    <div class="wss-info-box">
                        شورت‌کد: <code>[wss_breadcrumb]</code>
                    </div>
                </div>

            </div>

            <div class="wss-submit-bar">
                <button type="submit" class="button button-primary button-large">ذخیره تنظیمات سئو</button>
            </div>
        </form>
    </div>

    <!-- Social -->
    <div class="wss-tab-panel" id="tab-social">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="wss_save_settings">
            <input type="hidden" name="wss_group" value="wss_social">
            <?php wp_nonce_field('wss_save_settings'); ?>

            <div class="wss-card">
                <div class="wss-card-header"><span class="dashicons dashicons-share"></span> پروفایل‌های شبکه اجتماعی</div>
                <p class="wss-help">این آدرس‌ها در Schema.org (sameAs) و تگ‌های Open Graph استفاده می‌شوند.</p>
                <?php $socials = [
                    'facebook'  => ['فیسبوک', 'dashicons-facebook'],
                    'twitter'   => ['توییتر / ایکس', 'dashicons-twitter'],
                    'instagram' => ['اینستاگرام', 'dashicons-instagram'],
                    'linkedin'  => ['لینکدین', 'dashicons-linkedin'],
                    'youtube'   => ['یوتیوب', 'dashicons-video-alt3'],
                    'telegram'  => ['تلگرام', 'dashicons-email-alt'],
                    'pinterest' => ['پینترست', 'dashicons-pinterest'],
                    'whatsapp'  => ['واتساپ', 'dashicons-phone'],
                ]; ?>
                <div class="wss-social-grid">
                    <?php foreach ($socials as $key => [$label, $icon]): ?>
                    <div class="wss-social-field">
                        <label>
                            <span class="dashicons <?php echo $icon; ?>"></span>
                            <?php echo esc_html($label); ?>
                        </label>
                        <input type="url" name="<?php echo $key; ?>" value="<?php echo esc_url($social[$key] ?? ''); ?>" class="widefat" placeholder="https://">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wss-submit-bar">
                <button type="submit" class="button button-primary button-large">ذخیره شبکه‌های اجتماعی</button>
            </div>
        </form>
    </div>

    <!-- Webmaster -->
    <div class="wss-tab-panel" id="tab-webmaster">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="wss_save_settings">
            <input type="hidden" name="wss_group" value="wss_webmaster">
            <?php wp_nonce_field('wss_save_settings'); ?>

            <div class="wss-settings-grid">

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-admin-site-alt3"></span> تأییدیه وبمستر</div>
                    <?php $verifications = [
                        'google_verify' => ['Google Search Console', 'کد محتوای meta tag google-site-verification'],
                        'bing_verify'   => ['Bing Webmaster Tools', 'کد msvalidate.01'],
                        'yandex_verify' => ['Yandex Webmaster', 'کد yandex-verification'],
                    ]; ?>
                    <?php foreach ($verifications as $key => [$label, $help]): ?>
                    <div class="wss-field">
                        <label><?php echo esc_html($label); ?></label>
                        <input type="text" name="<?php echo $key; ?>" value="<?php echo esc_attr($wm[$key] ?? ''); ?>" class="widefat" placeholder="کد تأییدیه...">
                        <small class="wss-help"><?php echo esc_html($help); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="wss-card">
                    <div class="wss-card-header"><span class="dashicons dashicons-chart-area"></span> Analytics</div>
                    <div class="wss-field">
                        <label>Google Analytics (GA4 Measurement ID)</label>
                        <input type="text" name="google_analytics" value="<?php echo esc_attr($wm['google_analytics'] ?? ''); ?>" placeholder="G-XXXXXXXXXX" class="widefat">
                        <small class="wss-help">برای مثال: G-ABC123XYZ</small>
                    </div>
                    <div class="wss-field">
                        <label>Google Tag Manager (GTM ID)</label>
                        <input type="text" name="gtm_id" value="<?php echo esc_attr($wm['gtm_id'] ?? ''); ?>" placeholder="GTM-XXXXXXX" class="widefat">
                    </div>
                    <div class="wss-info-box">
                        Analytics برای کاربران لاگین‌شده اجرا نمی‌شود تا آمار دقیق‌تری داشته باشید.
                    </div>
                </div>

            </div>

            <div class="wss-submit-bar">
                <button type="submit" class="button button-primary button-large">ذخیره تنظیمات وبمستر</button>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.wss-nav-tab').forEach(function(tab){
        tab.addEventListener('click', function(){
            document.querySelectorAll('.wss-nav-tab, .wss-tab-panel').forEach(function(el){ el.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });

    // Image picker for default OG image
    var btn = document.getElementById('wss-default-og-btn');
    if(btn && typeof wp !== 'undefined' && wp.media){
        btn.addEventListener('click', function(){
            var frame = wp.media({title:'انتخاب تصویر',button:{text:'انتخاب'},multiple:false});
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                document.getElementById('wss-default-og').value = att.url;
            });
            frame.open();
        });
    }
});
</script>
