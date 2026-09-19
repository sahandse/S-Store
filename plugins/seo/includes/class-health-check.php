<?php
if ( ! defined('ABSPATH') ) exit;

class WSS_Health_Check {

    private static $instance = null;

    public static function instance() {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public function run_all() {
        return [
            'server'      => [ 'label' => 'سرور و PHP',       'icon' => 'dashicons-admin-tools',    'checks' => $this->check_server() ],
            'wordpress'   => [ 'label' => 'وردپرس',           'icon' => 'dashicons-wordpress-alt',  'checks' => $this->check_wordpress() ],
            'performance' => [ 'label' => 'عملکرد و سرعت',    'icon' => 'dashicons-performance',    'checks' => $this->check_performance() ],
            'seo'         => [ 'label' => 'سئو',               'icon' => 'dashicons-search',         'checks' => $this->check_seo() ],
            'security'    => [ 'label' => 'امنیت',             'icon' => 'dashicons-shield',         'checks' => $this->check_security() ],
            'content'     => [ 'label' => 'محتوا و رسانه',    'icon' => 'dashicons-admin-media',    'checks' => $this->check_content() ],
        ];
    }

    // ─── Server ───────────────────────────────────────────────────────────────

    private function check_server() {
        $checks = [];

        // PHP version
        $php_ver = phpversion();
        $php_ok  = version_compare($php_ver, '8.0', '>=');
        $php_ok2 = version_compare($php_ver, '7.4', '>=');
        $checks[] = [
            'title'  => 'نسخه PHP',
            'status' => $php_ok ? 'good' : ($php_ok2 ? 'warning' : 'error'),
            'value'  => 'PHP ' . $php_ver,
            'desc'   => $php_ok ? 'نسخه PHP به‌روز و پشتیبانی‌شده است.' : ( $php_ok2 ? 'PHP 7.4 هنوز کار می‌کند اما به‌روزرسانی توصیه می‌شود.' : 'PHP شما قدیمی و آسیب‌پذیر است.' ),
            'fix'    => 'از هاستینگ خود بخواهید PHP را به نسخه ۸.۱ یا بالاتر ارتقاء دهند. در کنترل پنل cPanel/DirectAdmin معمولاً از بخش PHP Version Selector قابل تغییر است.',
            'link'   => '',
        ];

        // Memory limit
        $memory    = ini_get('memory_limit');
        $memory_mb = $this->parse_size_mb($memory);
        $checks[] = [
            'title'  => 'حافظه PHP (Memory Limit)',
            'status' => $memory_mb >= 256 ? 'good' : ($memory_mb >= 128 ? 'warning' : 'error'),
            'value'  => $memory,
            'desc'   => $memory_mb >= 256 ? 'حافظه کافی است.' : ( $memory_mb >= 128 ? 'حافظه کم است — با افزونه‌های سنگین ممکن است کرش شود.' : 'حافظه بسیار کم است — خطاهای Fatal Error محتمل است.' ),
            'fix'    => "در wp-config.php اضافه کنید:\ndefine('WP_MEMORY_LIMIT', '256M');\n\nاگر اثر نداشت، از هاست بخواهید memory_limit را در php.ini افزایش دهند.",
            'link'   => '',
        ];

        // Max execution time
        $max_exec = (int) ini_get('max_execution_time');
        $checks[] = [
            'title'  => 'Max Execution Time',
            'status' => ($max_exec === 0 || $max_exec >= 60) ? 'good' : ($max_exec >= 30 ? 'warning' : 'error'),
            'value'  => $max_exec === 0 ? 'نامحدود' : $max_exec . ' ثانیه',
            'desc'   => ($max_exec === 0 || $max_exec >= 60) ? 'زمان اجرا کافی است.' : 'زمان اجرا کوتاه است — عملیات سنگین (import، پشتیبان‌گیری) ممکن است قطع شوند.',
            'fix'    => "در wp-config.php اضافه کنید:\n@ini_set('max_execution_time', 120);\n\nیا از هاست بخواهید max_execution_time را در php.ini افزایش دهند.",
            'link'   => '',
        ];

        // GD / Imagick
        $has_gd      = extension_loaded('gd') && function_exists('imagecreatetruecolor');
        $has_imagick = extension_loaded('imagick');
        $has_webp    = $has_gd && function_exists('imagewebp');
        $checks[] = [
            'title'  => 'پردازش تصویر (GD / Imagick)',
            'status' => ($has_gd || $has_imagick) ? 'good' : 'error',
            'value'  => implode(' + ', array_filter([$has_gd ? 'GD' : '', $has_imagick ? 'Imagick' : ''])) ?: 'ندارد',
            'desc'   => ($has_gd || $has_imagick) ? 'پردازش تصویر فعال است.' : 'GD یا Imagick نصب نیست — WebP، تغییر اندازه تصویر و thumbnail کار نمی‌کند.',
            'fix'    => "از هاست بخواهید php-gd یا php-imagick را نصب کنند.\n\nدر سرورهای Ubuntu/Debian:\napt install php8.1-gd php8.1-imagick",
            'link'   => '',
        ];

        // WebP support
        $checks[] = [
            'title'  => 'پشتیبانی WebP',
            'status' => ($has_webp || $has_imagick) ? 'good' : 'error',
            'value'  => $has_webp ? 'GD + WebP ✓' : ($has_imagick ? 'Imagick ✓' : 'ندارد ✗'),
            'desc'   => ($has_webp || $has_imagick) ? 'تبدیل تصاویر به WebP امکان‌پذیر است.' : 'PHP بدون پشتیبانی WebP کامپایل شده — تبدیل WebP کار نمی‌کند.',
            'fix'    => "از هاست بخواهید PHP را با پشتیبانی WebP کامپایل کنند.\nدر Ubuntu: apt install libwebp-dev\nسپس PHP را با --with-webp بازسازی کنند.",
            'link'   => admin_url('admin.php?page=wss-webp'),
        ];

        // cURL
        $has_curl = extension_loaded('curl');
        $checks[] = [
            'title'  => 'cURL',
            'status' => $has_curl ? 'good' : 'error',
            'value'  => $has_curl ? 'نصب‌شده ' . (curl_version()['version'] ?? '') : 'ندارد',
            'desc'   => $has_curl ? 'cURL فعال است — درخواست‌های HTTP خارجی کار می‌کنند.' : 'cURL نصب نیست — بسیاری از API‌ها و HTTP requests کار نمی‌کنند.',
            'fix'    => "از هاست بخواهید php-curl را نصب کنند.\nدر Ubuntu: apt install php8.1-curl",
            'link'   => '',
        ];

        // HTTPS
        $is_https = is_ssl();
        $checks[] = [
            'title'  => 'HTTPS / SSL',
            'status' => $is_https ? 'good' : 'error',
            'value'  => $is_https ? 'فعال ✓' : 'غیرفعال ✗',
            'desc'   => $is_https ? 'سایت روی HTTPS امن اجرا می‌شود.' : 'سایت از HTTP استفاده می‌کند — گوگل سایت‌های HTTP را پایین‌تر رتبه می‌دهد و مرورگرها آن را "ناامن" نشان می‌دهند.',
            'fix'    => "۱. از کنترل پنل هاست، SSL رایگان Let's Encrypt را فعال کنید.\n۲. در wp-config.php اضافه کنید:\ndefine('FORCE_SSL_ADMIN', true);\n۳. در .htaccess ریدایرکت HTTP→HTTPS اضافه کنید.",
            'link'   => '',
        ];

        // MySQL version
        global $wpdb;
        $mysql_ver = $wpdb->db_version();
        $checks[] = [
            'title'  => 'نسخه MySQL / MariaDB',
            'status' => version_compare($mysql_ver, '5.7', '>=') ? 'good' : 'warning',
            'value'  => $mysql_ver,
            'desc'   => version_compare($mysql_ver, '5.7', '>=') ? 'نسخه پایگاه داده به‌روز است.' : 'نسخه قدیمی MySQL — ممکن است مشکل کارایی و امنیت داشته باشد.',
            'fix'    => 'از هاست بخواهید MySQL را به نسخه ۸.۰ یا MariaDB ۱۰.۶ ارتقاء دهند.',
            'link'   => '',
        ];

        // mbstring
        $checks[] = [
            'title'  => 'mbstring (چند‌بایتی)',
            'status' => extension_loaded('mbstring') ? 'good' : 'error',
            'value'  => extension_loaded('mbstring') ? 'فعال' : 'ندارد',
            'desc'   => extension_loaded('mbstring') ? 'mbstring فعال است — پردازش متون فارسی/عربی درست کار می‌کند.' : 'mbstring نصب نیست — متون فارسی و UTF-8 ممکن است مشکل داشته باشند.',
            'fix'    => "از هاست بخواهید php-mbstring را نصب کنند.\nدر Ubuntu: apt install php8.1-mbstring",
            'link'   => '',
        ];

        return $checks;
    }

    // ─── WordPress ────────────────────────────────────────────────────────────

    private function check_wordpress() {
        $checks = [];
        global $wp_version;

        // WP version
        $latest_core = get_site_transient('update_core');
        $is_latest   = ! $latest_core || empty($latest_core->updates) || $latest_core->updates[0]->version === $wp_version;
        $checks[] = [
            'title'  => 'نسخه وردپرس',
            'status' => $is_latest ? 'good' : 'warning',
            'value'  => 'WordPress ' . $wp_version,
            'desc'   => $is_latest ? 'وردپرس به‌روز است.' : 'نسخه جدیدتری از وردپرس موجود است — باگ‌ها و آسیب‌پذیری‌های امنیتی برطرف شده‌اند.',
            'fix'    => 'از داشبورد وردپرس → به‌روزرسانی‌ها، وردپرس را آپدیت کنید.',
            'link'   => admin_url('update-core.php'),
        ];

        // Debug mode
        $debug_on = defined('WP_DEBUG') && WP_DEBUG;
        $checks[] = [
            'title'  => 'حالت Debug',
            'status' => $debug_on ? 'error' : 'good',
            'value'  => $debug_on ? 'فعال ⚠️' : 'غیرفعال ✓',
            'desc'   => $debug_on ? 'WP_DEBUG روشن است — اطلاعات حساس سیستم و مسیر فایل‌ها برای بازدیدکنندگان نمایش داده می‌شود.' : 'Debug mode خاموش است.',
            'fix'    => "در wp-config.php این خط را پیدا کنید:\ndefine('WP_DEBUG', true);\n\nو تغییر دهید به:\ndefine('WP_DEBUG', false);",
            'link'   => '',
        ];

        // File editing
        $disallow_edit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $checks[] = [
            'title'  => 'ویرایش فایل از داشبورد',
            'status' => $disallow_edit ? 'good' : 'warning',
            'value'  => $disallow_edit ? 'غیرفعال ✓' : 'فعال',
            'desc'   => $disallow_edit ? 'ویرایش مستقیم فایل‌ها از داشبورد غیرفعال است.' : 'ادمین‌ها می‌توانند مستقیماً کد PHP قالب و افزونه‌ها را از داشبورد ویرایش کنند — ریسک امنیتی.',
            'fix'    => "در wp-config.php اضافه کنید:\ndefine('DISALLOW_FILE_EDIT', true);",
            'link'   => '',
        ];

        // WP Cron
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $checks[] = [
            'title'  => 'WP-Cron',
            'status' => ! $cron_disabled ? 'good' : 'warning',
            'value'  => $cron_disabled ? 'غیرفعال' : 'فعال',
            'desc'   => ! $cron_disabled ? 'WP-Cron فعال است — کارهای زمان‌بندی‌شده (ایمیل، پشتیبان‌گیری) اجرا می‌شوند.' : 'WP-Cron غیرفعال است — ممکن است ایمیل‌ها و زمان‌بندی‌ها کار نکنند.',
            'fix'    => "اگر DISABLE_WP_CRON را خودتان تنظیم کرده‌اید، باید یک cron job واقعی در سرور تنظیم کنید:\n*/5 * * * * curl -s " . home_url('/wp-cron.php?doing_wp_cron') . " > /dev/null",
            'link'   => '',
        ];

        // Permalink structure
        $structure = get_option('permalink_structure');
        $checks[] = [
            'title'  => 'ساختار URL (Permalink)',
            'status' => $structure ? 'good' : 'error',
            'value'  => $structure ?: '?p=123 (پیش‌فرض)',
            'desc'   => $structure ? 'ساختار URL مناسب برای سئو است.' : 'Permalink به صورت پیش‌فرض است — URL‌ها برای سئو و کاربر مناسب نیستند.',
            'fix'    => 'از وردپرس → تنظیمات → پیوندهای یکتا، گزینه "نام نوشته" را انتخاب و ذخیره کنید.',
            'link'   => admin_url('options-permalink.php'),
        ];

        // Active plugin count
        $active_plugins = get_option('active_plugins', []);
        $plugin_count   = count($active_plugins);
        $checks[] = [
            'title'  => 'تعداد افزونه‌های فعال',
            'status' => $plugin_count <= 15 ? 'good' : ($plugin_count <= 25 ? 'warning' : 'error'),
            'value'  => $plugin_count . ' افزونه',
            'desc'   => $plugin_count <= 15 ? 'تعداد افزونه‌ها معقول است.' : ( $plugin_count <= 25 ? 'تعداد افزونه‌ها زیاد است — ممکن است سرعت را کاهش دهد.' : 'افزونه‌های خیلی زیاد — احتمالاً چندین تا غیرضروری هستند.' ),
            'fix'    => 'افزونه‌های غیرضروری را غیرفعال و حذف کنید. هر افزونه‌ای که بیش از ۲ ثانیه به زمان لود اضافه کند باید بررسی شود.',
            'link'   => admin_url('plugins.php'),
        ];

        // Update check for plugins
        $plugin_updates = get_site_transient('update_plugins');
        $pending        = $plugin_updates ? count($plugin_updates->response ?? []) : 0;
        $checks[] = [
            'title'  => 'به‌روزرسانی افزونه‌ها',
            'status' => $pending === 0 ? 'good' : ($pending <= 3 ? 'warning' : 'error'),
            'value'  => $pending === 0 ? 'همه به‌روز' : $pending . ' آپدیت در انتظار',
            'desc'   => $pending === 0 ? 'همه افزونه‌ها به‌روز هستند.' : $pending . ' افزونه نیاز به آپدیت دارد — آپدیت‌ها شامل وصله‌های امنیتی هستند.',
            'fix'    => 'از داشبورد → افزونه‌ها، افزونه‌های دارای آپدیت را به‌روزرسانی کنید.',
            'link'   => admin_url('update-core.php'),
        ];

        return $checks;
    }

    // ─── Performance ──────────────────────────────────────────────────────────

    private function check_performance() {
        $checks       = [];
        $speed_opts   = (array) get_option('wss_speed', []);
        $cache_opts   = (array) get_option('wss_cache', []);
        $webp_opts    = (array) get_option('wss_webp', []);

        $checks[] = [
            'title'  => 'کش صفحات',
            'status' => ! empty($cache_opts['enabled']) ? 'good' : 'warning',
            'value'  => ! empty($cache_opts['enabled']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($cache_opts['enabled']) ? 'کش صفحات فعال است — بارگذاری تا ۱۰ برابر سریع‌تر.' : 'کش غیرفعال است — هر بازدید صفحه از صفر رندر می‌شود.',
            'fix'    => 'از منو کش صفحات، دکمه "فعال‌سازی خودکار" را کلیک کنید.',
            'link'   => admin_url('admin.php?page=wss-cache'),
        ];

        $checks[] = [
            'title'  => 'فشرده‌سازی GZIP',
            'status' => ! empty($speed_opts['gzip']) ? 'good' : 'warning',
            'value'  => ! empty($speed_opts['gzip']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($speed_opts['gzip']) ? 'GZIP فعال است — حجم انتقال داده تا ۷۰٪ کمتر.' : 'GZIP غیرفعال است — سایت داده‌های فشرده‌نشده ارسال می‌کند.',
            'fix'    => 'از منو سرعت → Network/Caching → GZIP Compression را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        $checks[] = [
            'title'  => 'Minify CSS و JavaScript',
            'status' => (! empty($speed_opts['minify_css']) && ! empty($speed_opts['minify_js'])) ? 'good' : 'warning',
            'value'  => (! empty($speed_opts['minify_css']) ? 'CSS ✓' : 'CSS ✗') . '  ' . (! empty($speed_opts['minify_js']) ? 'JS ✓' : 'JS ✗'),
            'desc'   => (! empty($speed_opts['minify_css']) && ! empty($speed_opts['minify_js'])) ? 'CSS و JS فشرده می‌شوند.' : 'Minify فعال نیست — فضاهای خالی و کامنت‌ها حجم فایل را بالا می‌برند.',
            'fix'    => 'از منو سرعت → Code/Scripts، تیک Minify CSS و Minify JS را بزنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        $checks[] = [
            'title'  => 'Defer JavaScript',
            'status' => ! empty($speed_opts['defer_js']) ? 'good' : 'warning',
            'value'  => ! empty($speed_opts['defer_js']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($speed_opts['defer_js']) ? 'JS به صورت Deferred بارگذاری می‌شود — render block نیست.' : 'JS مسدودکننده render است — FCP و LCP پایین‌تر.',
            'fix'    => 'از منو سرعت → Code/Scripts → Defer JavaScript را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        $checks[] = [
            'title'  => 'Lazy Load تصاویر',
            'status' => ! empty($speed_opts['lazy_images']) ? 'good' : 'warning',
            'value'  => ! empty($speed_opts['lazy_images']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($speed_opts['lazy_images']) ? 'Lazy load فعال است — تصاویر خارج از دید بارگذاری نمی‌شوند.' : 'تصاویر بدون lazy load بارگذاری می‌شوند — لود اولیه سنگین‌تر.',
            'fix'    => 'از منو سرعت → Media → Lazy Load Images را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        $checks[] = [
            'title'  => 'Browser Cache',
            'status' => ! empty($speed_opts['browser_cache']) ? 'good' : 'warning',
            'value'  => ! empty($speed_opts['browser_cache']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($speed_opts['browser_cache']) ? 'Browser Cache فعال است — بازدیدکنندگان بازگشتی سریع‌تر لود می‌شوند.' : 'فایل‌های استاتیک کش نمی‌شوند.',
            'fix'    => 'از منو سرعت → Network → Browser Cache (Expires Headers) را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        $checks[] = [
            'title'  => 'تبدیل WebP تصاویر',
            'status' => ! empty($webp_opts['enabled']) ? 'good' : 'warning',
            'value'  => ! empty($webp_opts['enabled']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($webp_opts['enabled']) ? 'تبدیل خودکار WebP فعال است.' : 'تصاویر به WebP تبدیل نمی‌شوند — حجم تصاویر ۳۰-۸۰٪ بیشتر از حد لازم است.',
            'fix'    => 'از منو WebP Converter → فعال‌سازی خودکار را کلیک کنید.',
            'link'   => admin_url('admin.php?page=wss-webp'),
        ];

        $checks[] = [
            'title'  => 'حذف Emoji WordPress',
            'status' => ! empty($speed_opts['disable_emoji']) ? 'good' : 'warning',
            'value'  => ! empty($speed_opts['disable_emoji']) ? 'حذف‌شده ✓' : 'بارگذاری می‌شود',
            'desc'   => ! empty($speed_opts['disable_emoji']) ? 'اسکریپت emoji حذف شده — یک درخواست HTTP کمتر.' : 'وردپرس یک فایل JS اضافی برای emoji بارگذاری می‌کند.',
            'fix'    => 'از منو سرعت → WordPress Cleanup → Disable Emoji را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        return $checks;
    }

    // ─── SEO ─────────────────────────────────────────────────────────────────

    private function check_seo() {
        $checks   = [];
        $seo_opts = (array) get_option('wss_seo', []);
        $wm_opts  = (array) get_option('wss_webmaster', []);

        $checks[] = [
            'title'  => 'Open Graph (اشتراک‌گذاری)',
            'status' => ! empty($seo_opts['og_enabled']) ? 'good' : 'warning',
            'value'  => ! empty($seo_opts['og_enabled']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($seo_opts['og_enabled']) ? 'تگ‌های Open Graph فعال هستند — اشتراک‌گذاری در شبکه اجتماعی با تصویر و عنوان نمایش داده می‌شود.' : 'بدون Open Graph، اشتراک‌گذاری در تلگرام/واتساپ/توییتر تصویر و عنوان ندارد.',
            'fix'    => 'از منو سئو، تیک Open Graph را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        $checks[] = [
            'title'  => 'Canonical URL',
            'status' => ! empty($seo_opts['canonical_enabled']) ? 'good' : 'warning',
            'value'  => ! empty($seo_opts['canonical_enabled']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($seo_opts['canonical_enabled']) ? 'Canonical URL فعال است — محتوای تکراری مشکل ایجاد نمی‌کند.' : 'بدون Canonical، URL‌های مختلف یک صفحه با هم رقیب می‌شوند.',
            'fix'    => 'از منو سئو → Canonical URL را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        $checks[] = [
            'title'  => 'توضیحات متا خودکار',
            'status' => ! empty($seo_opts['auto_description']) ? 'good' : 'warning',
            'value'  => ! empty($seo_opts['auto_description']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($seo_opts['auto_description']) ? 'برای پست‌های بدون meta description، خودکار از محتوا ساخته می‌شود.' : 'پست‌های بدون meta description در نتایج گوگل توضیح ندارند.',
            'fix'    => 'از منو سئو → Auto Description را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        // Sitemap check
        $sitemap_url = home_url('/sitemap.xml');
        $response    = wp_remote_get($sitemap_url, ['timeout' => 5, 'sslverify' => false]);
        $sitemap_ok  = ! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        $checks[] = [
            'title'  => 'XML Sitemap',
            'status' => $sitemap_ok ? 'good' : 'error',
            'value'  => $sitemap_ok ? 'در دسترس ✓' : 'در دسترس نیست ✗',
            'desc'   => $sitemap_ok ? 'Sitemap در آدرس /sitemap.xml در دسترس است.' : 'Sitemap باز نمی‌شود — گوگل‌بات نمی‌تواند محتوا را پیدا کند.',
            'fix'    => "۱. از منو سرعت → تنظیمات را ذخیره کنید تا Rewrite rules بازسازی شود.\n۲. از منو نقشه سایت → «بازسازی نقشه سایت» را کلیک کنید.\n۳. اطمینان حاصل کنید که wp-content/uploads/ قابل نوشتن است.",
            'link'   => admin_url('admin.php?page=wss-sitemap'),
        ];

        $checks[] = [
            'title'  => 'Google Analytics',
            'status' => ! empty($wm_opts['google_analytics']) ? 'good' : 'warning',
            'value'  => ! empty($wm_opts['google_analytics']) ? 'متصل ✓' : 'وصل نیست',
            'desc'   => ! empty($wm_opts['google_analytics']) ? 'Google Analytics متصل است — ترافیک سایت تحلیل می‌شود.' : 'بدون Analytics ترافیک سایت اندازه‌گیری نمی‌شود.',
            'fix'    => 'از منو سئو → تب وبمستر، کد Google Analytics (G-XXXXXXXXXX) را وارد کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        $checks[] = [
            'title'  => 'Google Search Console',
            'status' => ! empty($wm_opts['google_verify']) ? 'good' : 'warning',
            'value'  => ! empty($wm_opts['google_verify']) ? 'تأیید شده ✓' : 'تأیید نشده',
            'desc'   => ! empty($wm_opts['google_verify']) ? 'Search Console تأیید شده است — رتبه و مشکلات را می‌توانید ببینید.' : 'بدون Search Console نمی‌توانید خطاهای Crawl و رتبه کلمات کلیدی را ببینید.',
            'fix'    => 'از Google Search Console یک کد تأیید دریافت کنید و در منو سئو → وبمستر وارد کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        // Breadcrumbs
        $checks[] = [
            'title'  => 'Breadcrumbs (مسیر صفحه)',
            'status' => ! empty($seo_opts['breadcrumbs']) ? 'good' : 'warning',
            'value'  => ! empty($seo_opts['breadcrumbs']) ? 'فعال ✓' : 'غیرفعال',
            'desc'   => ! empty($seo_opts['breadcrumbs']) ? 'Breadcrumbs فعال است — schema BreadcrumbList در نتایج گوگل نمایش داده می‌شود.' : 'Breadcrumbs غیرفعال است — این schema در رتبه‌بندی تأثیر مثبت دارد.',
            'fix'    => 'از منو سئو → Breadcrumbs را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-seo'),
        ];

        // Broken links count
        if ( class_exists('WSS_Broken_Links') ) {
            $stats = WSS_Broken_Links::instance()->get_stats();
            $checks[] = [
                'title'  => 'لینک‌های شکسته (۴۰۴)',
                'status' => $stats['broken'] === 0 ? 'good' : ($stats['broken'] < 5 ? 'warning' : 'error'),
                'value'  => $stats['total'] > 0 ? $stats['broken'] . ' از ' . $stats['total'] . ' لینک' : 'اسکن نشده',
                'desc'   => $stats['broken'] === 0 ? 'لینک شکسته‌ای یافت نشد.' : $stats['broken'] . ' لینک ۴۰۴ پیدا شد — لینک‌های شکسته به سئو آسیب می‌زنند.',
                'fix'    => 'از منو لینک‌های شکسته، اسکن را اجرا کنید و لینک‌های شکسته را رفع کنید.',
                'link'   => admin_url('admin.php?page=wss-broken-links'),
            ];
        }

        // Posts without meta description
        $no_desc = $this->count_posts_without_meta('_wss_seo_description');
        $checks[] = [
            'title'  => 'پست‌های بدون Meta Description',
            'status' => $no_desc === 0 ? 'good' : ($no_desc < 5 ? 'warning' : 'error'),
            'value'  => $no_desc === 0 ? 'همه پست‌ها دارند' : $no_desc . ' پست',
            'desc'   => $no_desc === 0 ? 'همه پست‌های منتشرشده توضیحات متا دارند.' : $no_desc . ' پست بدون Meta Description — نرخ کلیک (CTR) در نتایج گوگل پایین‌تر می‌شود.',
            'fix'    => 'در ویرایشگر هر پست، در باکس سئو زیر، توضیحات متا را پر کنید.',
            'link'   => admin_url('edit.php'),
        ];

        return $checks;
    }

    // ─── Security ─────────────────────────────────────────────────────────────

    private function check_security() {
        $checks = [];

        // Debug log exposed
        $debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $checks[] = [
            'title'  => 'Debug Log',
            'status' => ! $debug_log ? 'good' : 'warning',
            'value'  => $debug_log ? 'فعال' : 'غیرفعال ✓',
            'desc'   => ! $debug_log ? 'Debug log غیرفعال است.' : 'WP_DEBUG_LOG فعال است — فایل debug.log ممکن است اطلاعات حساس داشته باشد.',
            'fix'    => "در wp-config.php:\ndefine('WP_DEBUG_LOG', false);",
            'link'   => '',
        ];

        // wp-config.php writable
        $wpconfig   = ABSPATH . 'wp-config.php';
        $wc_writable = file_exists($wpconfig) && is_writable($wpconfig);
        $checks[] = [
            'title'  => 'مجوز wp-config.php',
            'status' => $wc_writable ? 'warning' : 'good',
            'value'  => $wc_writable ? 'قابل نوشتن ⚠️' : '644 / 400 ✓',
            'desc'   => $wc_writable ? 'wp-config.php قابل نوشتن است — ریسک امنیتی در صورت نفوذ.' : 'wp-config.php مجوز درست دارد.',
            'fix'    => "مجوز فایل را محدود کنید:\nchmod 444 wp-config.php\n\nیا از File Manager هاست مجوز را به 444 تغییر دهید.",
            'link'   => '',
        ];

        // Default "admin" username
        $admin_user = get_user_by('login', 'admin');
        $checks[] = [
            'title'  => 'نام کاربری "admin"',
            'status' => ! $admin_user ? 'good' : 'warning',
            'value'  => $admin_user ? 'وجود دارد ⚠️' : 'وجود ندارد ✓',
            'desc'   => ! $admin_user ? 'نام کاربری "admin" وجود ندارد.' : 'نام کاربری "admin" وجود دارد — هدف اول حملات Brute Force است.',
            'fix'    => "۱. یک کاربر جدید با نام متفاوت و سطح ادمین بسازید.\n۲. تمام پست‌ها را به کاربر جدید انتقال دهید.\n۳. کاربر 'admin' را حذف کنید.",
            'link'   => admin_url('users.php'),
        ];

        // Uploads directory listing protection
        $uploads      = wp_upload_dir();
        $index_file   = trailingslashit($uploads['basedir']) . 'index.php';
        $protected    = file_exists($index_file);
        $checks[] = [
            'title'  => 'محافظت پوشه Uploads',
            'status' => $protected ? 'good' : 'warning',
            'value'  => $protected ? 'محافظت‌شده ✓' : 'بدون محافظت',
            'desc'   => $protected ? 'پوشه uploads دارای فایل index.php محافظ است.' : 'پوشه uploads ممکن است فهرست فایل‌ها را نمایش دهد.',
            'fix'    => "یک فایل index.php خالی در پوشه uploads بسازید:\n" . $uploads['basedir'] . "/index.php\n\nمحتوای فایل:\n<?php // Silence is golden.",
            'link'   => '',
        ];

        // XMLRPC
        $xmlrpc_disabled = ! empty(get_option('wss_speed', [])['disable_xmlrpc']);
        $checks[] = [
            'title'  => 'XML-RPC',
            'status' => $xmlrpc_disabled ? 'good' : 'warning',
            'value'  => $xmlrpc_disabled ? 'غیرفعال ✓' : 'فعال',
            'desc'   => $xmlrpc_disabled ? 'XML-RPC غیرفعال است — بردار حملات DDoS و Brute Force حذف شده.' : 'XML-RPC فعال است — مگر که از Jetpack یا اپ وردپرس استفاده می‌کنید، بهتر است غیرفعال باشد.',
            'fix'    => 'از منو سرعت → WordPress Cleanup → Disable XML-RPC را فعال کنید.',
            'link'   => admin_url('admin.php?page=wss-speed'),
        ];

        // Check if site uses HTTPS in siteurl
        $siteurl = get_option('siteurl');
        $home    = get_option('home');
        $https_config = (strpos($siteurl, 'https://') === 0 && strpos($home, 'https://') === 0);
        $checks[] = [
            'title'  => 'تنظیم HTTPS در وردپرس',
            'status' => $https_config ? 'good' : 'error',
            'value'  => $https_config ? 'https:// ✓' : 'http://',
            'desc'   => $https_config ? 'آدرس سایت در وردپرس روی HTTPS تنظیم شده.' : 'آدرس سایت هنوز HTTP است — تغییر دهید تا mixed content و redirect loop رفع شود.',
            'fix'    => "از وردپرس → تنظیمات → عمومی:\nآدرس وردپرس و آدرس سایت را از http:// به https:// تغییر دهید.\n\nیا در wp-config.php:\ndefine('WP_HOME', 'https://yoursite.com');\ndefine('WP_SITEURL', 'https://yoursite.com');",
            'link'   => admin_url('options-general.php'),
        ];

        return $checks;
    }

    // ─── Content ──────────────────────────────────────────────────────────────

    private function check_content() {
        $checks = [];
        global $wpdb;

        // Images without alt
        $no_alt = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'
               AND p.post_status = 'inherit'
               AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );
        $checks[] = [
            'title'  => 'تصاویر بدون Alt Text',
            'status' => $no_alt === 0 ? 'good' : ($no_alt < 10 ? 'warning' : 'error'),
            'value'  => $no_alt === 0 ? 'همه دارند ✓' : $no_alt . ' تصویر',
            'desc'   => $no_alt === 0 ? 'همه تصاویر دارای Alt Text هستند.' : $no_alt . ' تصویر بدون Alt Text — سئو تصویر و دسترسی‌پذیری ضعیف است.',
            'fix'    => 'از منو سئو پیشرفته → تب "تگ Alt"، تصاویر را بررسی و Alt اضافه کنید.',
            'link'   => admin_url('admin.php?page=wss-advanced-seo'),
        ];

        // Posts without focus keyword
        $no_kw = $this->count_posts_without_meta('_wss_focus_keyword');
        $checks[] = [
            'title'  => 'پست‌های بدون کلمه کلیدی',
            'status' => $no_kw === 0 ? 'good' : ($no_kw < 10 ? 'warning' : 'error'),
            'value'  => $no_kw === 0 ? 'همه دارند ✓' : $no_kw . ' پست',
            'desc'   => $no_kw === 0 ? 'همه پست‌ها کلمه کلیدی focus دارند.' : $no_kw . ' پست بدون کلمه کلیدی — گوگل نمی‌داند هر پست برای چه عبارتی بهینه شده.',
            'fix'    => 'در ویرایشگر هر پست، در باکس سئو، کلمه کلیدی اصلی (Focus Keyword) را وارد کنید.',
            'link'   => admin_url('edit.php'),
        ];

        // Posts without SEO title
        $no_title = $this->count_posts_without_meta('_wss_seo_title');
        $checks[] = [
            'title'  => 'پست‌های بدون عنوان سئو',
            'status' => $no_title === 0 ? 'good' : ($no_title < 10 ? 'warning' : 'error'),
            'value'  => $no_title === 0 ? 'همه دارند ✓' : $no_title . ' پست',
            'desc'   => $no_title === 0 ? 'همه پست‌ها عنوان سئو دارند.' : $no_title . ' پست بدون عنوان سئو — عنوان پیش‌فرض پست در نتایج گوگل نمایش داده می‌شود.',
            'fix'    => 'در ویرایشگر هر پست، عنوان سئو را در باکس سئو تنظیم کنید.',
            'link'   => admin_url('edit.php'),
        ];

        // WebP conversion status
        if ( class_exists('WSS_WebP_Converter') ) {
            $webp_stats  = WSS_WebP_Converter::instance()->get_stats();
            $total       = (int) $webp_stats['total_imgs'];
            $converted   = (int) $webp_stats['webp_files'];
            $unconverted = max(0, $total - $converted);
            $pct         = $total > 0 ? round($converted / $total * 100) : 100;
            $checks[] = [
                'title'  => 'تبدیل WebP تصاویر',
                'status' => $unconverted === 0 ? 'good' : ($pct >= 50 ? 'warning' : 'error'),
                'value'  => $total > 0 ? $converted . ' از ' . $total . ' (' . $pct . '%)' : 'بدون تصویر',
                'desc'   => $unconverted === 0 ? 'همه تصاویر به WebP تبدیل شده‌اند.' : $unconverted . ' تصویر هنوز WebP نشده — حجم بیشتر و LCP پایین‌تر.',
                'fix'    => 'از منو WebP Converter → شروع تبدیل انبوه را کلیک کنید.',
                'link'   => admin_url('admin.php?page=wss-webp'),
            ];
        }

        // Database revisions
        $revisions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $checks[] = [
            'title'  => 'ریویژن‌های پایگاه داده',
            'status' => $revisions < 200 ? 'good' : ($revisions < 1000 ? 'warning' : 'error'),
            'value'  => number_format($revisions) . ' ریویژن',
            'desc'   => $revisions < 200 ? 'تعداد ریویژن‌ها معقول است.' : $revisions . ' ریویژن — پایگاه داده حجیم شده و کوئری‌ها کندتر می‌شوند.',
            'fix'    => 'از داشبورد → پاکسازی خودکار را اجرا کنید تا ریویژن‌های قدیمی حذف شوند.',
            'link'   => admin_url('admin.php?page=wss-dashboard'),
        ];

        // Trash posts
        $trash = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'");
        $checks[] = [
            'title'  => 'پست‌های سطل زباله',
            'status' => $trash < 20 ? 'good' : 'warning',
            'value'  => $trash . ' پست',
            'desc'   => $trash < 20 ? 'سطل زباله تمیز است.' : $trash . ' پست در سطل زباله — فضا اشغال می‌کنند.',
            'fix'    => 'از داشبورد → پاکسازی خودکار را اجرا کنید تا سطل زباله خالی شود.',
            'link'   => admin_url('edit.php?post_status=trash'),
        ];

        return $checks;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function count_posts_without_meta( $meta_key ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_type = 'post' AND p.post_status = 'publish'
               AND (pm.meta_value IS NULL OR pm.meta_value = '')",
            $meta_key
        ));
    }

    private function parse_size_mb( $size ) {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $val  = (int) $size;
        if ( $unit === 'G' ) return $val * 1024;
        if ( $unit === 'M' ) return $val;
        if ( $unit === 'K' ) return intdiv($val, 1024);
        return intdiv($val, 1024 * 1024);
    }
}
