<?php
/**
 * Security Audit & Vulnerability Scanner Engine
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Security_Engine {

	/**
	 * Run full security audit
	 *
	 * @return array
	 */
	public function run_audit() {
		$score = 100;
		$checks = array();
		$vulnerabilities = array();

		// 1. Check SSL / HTTPS
		$is_ssl = is_ssl();
		if ( ! $is_ssl ) {
			$score -= 25;
			$checks[] = array(
				'title'      => 'گواهی امنیتی SSL (HTTPS)',
				'status'     => 'fail',
				'message'    => 'سایت از پروتکل امن HTTPS استفاده نمی‌کند.',
				'suggestion' => 'گواهی SSL را از طریق پنل هاست خود فعال کنید.',
			);
			$vulnerabilities[] = 'no_ssl';
		} else {
			$checks[] = array(
				'title'   => 'گواهی امنیتی SSL',
				'status'  => 'pass',
				'message' => 'اتصال امن HTTPS فعال است.',
			);
		}

		// 2. Check for default 'admin' user
		$admin_user = get_user_by( 'login', 'admin' );
		if ( $admin_user && user_can( $admin_user, 'manage_options' ) ) {
			$score -= 20;
			$checks[] = array(
				'title'      => 'نام کاربری پیش‌فرض مدیر (admin)',
				'status'     => 'fail',
				'message'    => 'یک حساب مدیر با نام کاربری پیش‌فرض "admin" وجود دارد (هدف حملات Brute Force).',
				'suggestion' => 'نام کاربری را تغییر داده یا حساب کاربری جدید با نام اختصاصی بسازید.',
			);
			$vulnerabilities[] = 'admin_user_exists';
		} else {
			$checks[] = array(
				'title'   => 'نام کاربری مدیر',
				'status'  => 'pass',
				'message' => 'نام کاربری پیش‌فرض admin یافت نشد.',
			);
		}

		// 3. Check WP_DEBUG & WP_DEBUG_DISPLAY in Production
		$debug_active  = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;

		if ( $debug_active && $debug_display ) {
			$score -= 15;
			$checks[] = array(
				'title'      => 'وضعیت دیباگ وردپرس (WP_DEBUG)',
				'status'     => 'warning',
				'message'    => 'نمایش خطاهای دیباگ در سایت عمومی فعال است (نشت اطلاعات سرور و مسیرها).',
				'suggestion' => 'مقدار WP_DEBUG_DISPLAY را در wp-config.php برابر false قرار دهید.',
			);
			$vulnerabilities[] = 'debug_display_on';
		} else {
			$checks[] = array(
				'title'   => 'وضعیت دیباگ وردپرس',
				'status'  => 'pass',
				'message' => 'نمایش خطاهای حساس سرور خاموش است.',
			);
		}

		// 4. Check File Permissions (wp-config.php and uploads)
		$wp_config = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config ) ) {
			$perms = substr( sprintf( '%o', fileperms( $wp_config ) ), -4 );
			if ( $perms === '0777' || $perms === '0666' ) {
				$score -= 15;
				$checks[] = array(
					'title'      => 'سطح دسترسی فایل wp-config.php',
					'status'     => 'fail',
					'message'    => sprintf( 'سطح دسترسی فایل حساس (%s) بسیار باز و ناامن است.', $perms ),
					'suggestion' => 'سطح دسترسی wp-config.php را روی 0440 یا 0600 تنظیم کنید.',
				);
			} else {
				$checks[] = array(
					'title'   => 'سطح دسترسی wp-config.php',
					'status'  => 'pass',
					'message' => sprintf( 'دسترسی امن است (%s).', $perms ),
				);
			}
		}

		// 5. Check Dangerous Function Signatures in active plugins / themes
		$dangerous_findings = $this->scan_files_for_dangerous_patterns();
		if ( ! empty( $dangerous_findings ) ) {
			$score -= min( 25, count( $dangerous_findings ) * 10 );
			$checks[] = array(
				'title'      => 'کدهای مشکوک در فایل‌های PHP',
				'status'     => 'warning',
				'message'    => sprintf( 'تعداد %d مورد استفاده از توابع پرخطر (eval, base64_decode, shell_exec) شناسایی شد.', count( $dangerous_findings ) ),
				'suggestion' => 'فایل‌های گزارش شده را بررسی کنید تا از عدم وجود بدافزار مطمئن شوید.',
			);
			$vulnerabilities[] = 'suspicious_php_patterns';
		} else {
			$checks[] = array(
				'title'   => 'کدهای مشکوک و توابع پرخطر',
				'status'  => 'pass',
				'message' => 'هیچ الگوی مخرب یا شل PHP یافت نشد.',
			);
		}

		// 6. Check WordPress Core Version Freshness
		global $wp_version;
		$checks[] = array(
			'title'   => 'نسخه هسته وردپرس',
			'status'  => 'pass',
			'message' => sprintf( 'نسخه فعلی: %s', $wp_version ),
		);

		$score = max( 0, min( 100, $score ) );

		return array(
			'score'           => $score,
			'checks'          => $checks,
			'vulnerabilities' => $vulnerabilities,
			'findings'        => $dangerous_findings,
			'last_scanned'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Scan theme / plugin files for dangerous signatures
	 */
	public function scan_files_for_dangerous_patterns() {
		$findings = array();
		$dangerous_functions = array(
			'shell_exec'     => 'اجرای دستورات خط فرمان سیستم',
			'passthru'       => 'اجرای مستقیم شل با خروجی خام',
			'gzinflate'      => 'کدگشایی رشته‌های متراکم (شایع در بدافزارها)',
			'base64_decode'  => 'کدگشایی Base64 (مشکوک در صورت ترکیب با eval)',
			'eval'           => 'اجرای پویای کد خام PHP (خطرناک)',
		);

		// Scan theme directory as a sample target
		$theme_dir = get_template_directory();
		if ( ! is_dir( $theme_dir ) ) {
			return $findings;
		}

		$files = glob( $theme_dir . '/*.php' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_readable( $file ) ) {
					$content = @file_get_contents( $file );
					if ( $content ) {
						foreach ( $dangerous_functions as $func => $label ) {
							if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/i', $content ) ) {
								// Avoid false positive if it's our own plugin
								if ( strpos( $file, 'smart-seo-ai-suite-pro' ) === false ) {
									$findings[] = array(
										'file'     => basename( $file ),
										'path'     => $file,
										'function' => $func,
										'label'    => $label,
									);
								}
							}
						}
					}
				}
			}
		}

		return array_slice( $findings, 0, 10 );
	}
}
