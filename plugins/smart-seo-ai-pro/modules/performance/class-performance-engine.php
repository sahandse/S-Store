<?php
/**
 * Performance & Speed Audit Engine
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Performance_Engine {

	/**
	 * Run performance diagnosis
	 *
	 * @return array
	 */
	public function run_audit() {
		global $wpdb;
		$score = 100;
		$checks = array();
		$optimizations = array();

		// 1. PHP Version
		$php_version = phpversion();
		if ( version_compare( $php_version, '8.1', '>=' ) ) {
			$checks[] = array(
				'title'   => 'نسخه PHP سرور',
				'status'  => 'pass',
				'message' => sprintf( 'نسخه مدرن و پرسرعت: PHP %s', $php_version ),
			);
		} elseif ( version_compare( $php_version, '7.4', '>=' ) ) {
			$score -= 8;
			$checks[] = array(
				'title'      => 'نسخه PHP سرور',
				'status'     => 'warning',
				'message'    => sprintf( 'نسخه فعلی: PHP %s. ارتقا به PHP 8.2 یا 8.3 تا 30%% سرعت را بیشتر می‌کند.', $php_version ),
				'suggestion' => 'از طریق cPanel/DirectAdmin نسخه PHP را به 8.2 یا بالاتر ارتقا دهید.',
			);
		} else {
			$score -= 20;
			$checks[] = array(
				'title'      => 'نسخه PHP سرور',
				'status'     => 'fail',
				'message'    => sprintf( 'نسخه بسیار قدیمی و منسوخ: PHP %s', $php_version ),
				'suggestion' => 'فورا نسخه PHP را ارتقا دهید.',
			);
		}

		// 2. Memory Limit & Consumption
		$mem_limit = ini_get( 'memory_limit' );
		$mem_usage = memory_get_peak_usage( true );
		$mem_usage_mb = round( $mem_usage / 1024 / 1024, 1 );

		$mem_limit_num = intval( $mem_limit );
		if ( strpos( $mem_limit, 'G' ) !== false ) {
			$mem_limit_num *= 1024;
		}

		if ( $mem_limit_num < 128 && $mem_limit_num > 0 ) {
			$score -= 15;
			$checks[] = array(
				'title'      => 'محدودیت حافظه رم (Memory Limit)',
				'status'     => 'fail',
				'message'    => sprintf( 'محدودیت حافظه %s برای سایت‌های وردپرسی کم است (مصرف فعلی: %s MB).', $mem_limit, $mem_usage_mb ),
				'suggestion' => 'مقدار WP_MEMORY_LIMIT را در wp-config.php به 256M افزایش دهید.',
			);
		} else {
			$checks[] = array(
				'title'   => 'محدودیت حافظه رم (Memory Limit)',
				'status'  => 'pass',
				'message' => sprintf( 'محدودیت حافظه: %s (مصرف در این لحظه: %s MB)', $mem_limit, $mem_usage_mb ),
			);
		}

		// 3. Database Revisions Count
		$revisions_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		$revisions_count = intval( $revisions_count );

		if ( $revisions_count > 100 ) {
			$score -= 10;
			$checks[] = array(
				'title'      => 'رونوشت‌های ذخیره شده در دیتابیس',
				'status'     => 'warning',
				'message'    => sprintf( 'تعداد %d رونوشت قدیمی در جدول posts انباشته شده و دیتابیس را کند کرده است.', $revisions_count ),
				'suggestion' => 'از بخش AutoFix افزونه برای پاکسازی رونوشت‌های اضافی استفاده کنید.',
			);
			$optimizations[] = 'clean_revisions';
		} else {
			$checks[] = array(
				'title'   => 'رونوشت‌های دیتابیس',
				'status'  => 'pass',
				'message' => sprintf( 'تعداد رونوشت‌ها کنترل شده است (%d مورد).', $revisions_count ),
			);
		}

		// 4. Autoload Options Size
		$autoload_size = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'" );
		$autoload_kb = round( ( intval( $autoload_size ) / 1024 ), 1 );

		if ( $autoload_kb > 800 ) {
			$score -= 10;
			$checks[] = array(
				'title'      => 'حجم داده‌های لود خودکار (Autoload Options)',
				'status'     => 'warning',
				'message'    => sprintf( 'حجم آپشن‌های لود اولیه بالا است (%s KB).', $autoload_kb ),
				'suggestion' => 'آپشن‌های منسوخ پلاگین‌های حذف شده را از دیتابیس پاک کنید.',
			);
		} else {
			$checks[] = array(
				'title'   => 'حجم دیتابیس لود خودکار',
				'status'  => 'pass',
				'message' => sprintf( 'حجم ایده‌آل است (%s KB).', $autoload_kb ),
			);
		}

		// 5. Caching Detection
		$has_caching = wp_using_ext_object_cache() || defined( 'WP_CACHE' ) && WP_CACHE;
		if ( ! $has_caching ) {
			$score -= 12;
			$checks[] = array(
				'title'      => 'سیستم کش صفحه و آبجکت (Page/Object Caching)',
				'status'     => 'warning',
				'message'    => 'افزونه کش فعال شناسایی نشد (کش صفحه زمان پاسخ‌دهی سرور را تا 80% کاهش می‌دهد).',
				'suggestion' => 'یک افزونه کش مانند LiteSpeed Cache یا WP Rocket فعال کنید.',
			);
		} else {
			$checks[] = array(
				'title'   => 'سیستم کش',
				'status'  => 'pass',
				'message' => 'سیستم کش سرور یا افزونه فعال است.',
			);
		}

		// 6. Heavy Images in Media Library (> 300KB)
		$heavy_images = $this->detect_heavy_images();
		if ( ! empty( $heavy_images ) ) {
			$score -= min( 15, count( $heavy_images ) * 4 );
			$checks[] = array(
				'title'      => 'تصاویر حجیم بدون فشرده‌سازی',
				'status'     => 'warning',
				'message'    => sprintf( 'تعداد %d تصویر با حجم بالاتر از 300KB در کتابخانه رسانه یافت شد.', count( $heavy_images ) ),
				'suggestion' => 'تصاویر را به فرمت WebP تبدیل کرده یا فشرده‌سازی کنید.',
			);
		} else {
			$checks[] = array(
				'title'   => 'بهینه‌سازی تصاویر',
				'status'  => 'pass',
				'message' => 'تصاویر بررسی شده در وضعیت فشرده و بهینه قرار دارند.',
			);
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'score'           => $score,
			'checks'          => $checks,
			'mem_usage_mb'    => $mem_usage_mb,
			'mem_limit'       => $mem_limit,
			'php_version'     => $php_version,
			'revisions_count' => $revisions_count,
			'autoload_kb'     => $autoload_kb,
			'heavy_images'    => $heavy_images,
		);
	}

	/**
	 * Detect heavy images in media library
	 */
	public function detect_heavy_images( $limit = 6 ) {
		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
		) );

		$heavy = array();
		foreach ( $attachments as $att ) {
			$file_path = get_attached_file( $att->ID );
			if ( $file_path && file_exists( $file_path ) ) {
				$filesize = filesize( $file_path );
				$size_kb  = round( $filesize / 1024 );
				if ( $size_kb > 300 ) {
					$heavy[] = array(
						'id'       => $att->ID,
						'title'    => $att->post_title,
						'filename' => basename( $file_path ),
						'size_kb'  => $size_kb,
						'url'      => wp_get_attachment_url( $att->ID ),
					);
					if ( count( $heavy ) >= $limit ) {
						break;
					}
				}
			}
		}

		return $heavy;
	}
}
