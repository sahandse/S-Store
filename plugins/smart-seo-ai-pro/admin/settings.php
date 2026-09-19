<?php
/**
 * Plugin Settings Page
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Admin_Settings {

	public static function render() {
		if ( ! Smart_SEO_AI_Permissions::can_manage_all() ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای ویرایش تنظیمات را ندارید.', 'smart-seo-ai-suite-pro' ) );
		}

		$settings = Smart_SEO_AI_Settings::get_all();
		$message  = '';

		// Handle Form Submit
		if ( isset( $_POST['smart_seo_ai_save_settings'] ) ) {
			check_admin_referer( 'smart_seo_ai_settings_verify', 'smart_seo_ai_settings_nonce' );

			$raw_input = $_POST['settings'] ?? array();
			Smart_SEO_AI_Settings::update_all( $raw_input );
			$settings = Smart_SEO_AI_Settings::get_all();
			$message  = __( 'تنظیمات با موفقیت ذخیره شد.', 'smart-seo-ai-suite-pro' );
			Smart_SEO_AI_Logger::log( 'system', 'Plugin settings updated by administrator.', 'success' );
		}
		?>
		<div class="wrap smart-seo-admin-wrap">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-admin-generic smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'تنظیمات افزونه Smart SEO AI Suite Pro', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'مدیریت کلیدهای اتصال هوش مصنوعی، فاکتورهای سئو، پیکربندی اسکنر امنیت و اصلاح خودکار', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $message ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'smart_seo_ai_settings_verify', 'smart_seo_ai_settings_nonce' ); ?>

				<!-- AI Engine Settings -->
				<div class="smart-box">
					<div class="smart-box-header">
						<h2><span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'تنظیمات موتور هوش مصنوعی (AI Engine)', 'smart-seo-ai-suite-pro' ); ?></h2>
					</div>
					<div class="smart-box-body">
						<table class="form-table">
							<tr>
								<th scope="row"><label for="ai_provider"><?php esc_html_e( 'سرویس‌دهنده هوش مصنوعی:', 'smart-seo-ai-suite-pro' ); ?></label></th>
								<td>
									<select name="settings[ai_provider]" id="ai_provider">
										<option value="gemini" <?php selected( $settings['ai_provider'] ?? 'gemini', 'gemini' ); ?>>Google Gemini (پیش‌فرض پیشنهادی)</option>
										<option value="openai" <?php selected( $settings['ai_provider'] ?? '', 'openai' ); ?>>OpenAI (ChatGPT / GPT-4o)</option>
										<option value="claude" <?php selected( $settings['ai_provider'] ?? '', 'claude' ); ?>>Anthropic Claude</option>
									</select>
									<p class="description"><?php esc_html_e( 'سرویس ارائه‌دهنده مدل زبانی را انتخاب کنید.', 'smart-seo-ai-suite-pro' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ai_model"><?php esc_html_e( 'نام مدل (AI Model):', 'smart-seo-ai-suite-pro' ); ?></label></th>
								<td>
									<input type="text" name="settings[ai_model]" id="ai_model" class="regular-text" value="<?php echo esc_attr( $settings['ai_model'] ?? 'gemini-1.5-flash' ); ?>">
									<p class="description"><?php esc_html_e( 'مثال: gemini-1.5-flash یا gemini-2.0-flash یا gpt-4o', 'smart-seo-ai-suite-pro' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ai_api_key"><?php esc_html_e( 'کلید دسترسی (API Key):', 'smart-seo-ai-suite-pro' ); ?></label></th>
								<td>
									<input type="password" name="settings[ai_api_key]" id="ai_api_key" class="regular-text" value="<?php echo esc_attr( $settings['ai_api_key'] ?? '' ); ?>">
									<button type="button" class="button" id="btn-test-ai-key"><?php esc_html_e( 'تست اتصال API', 'smart-seo-ai-suite-pro' ); ?></button>
									<span id="ai-key-test-result"></span>
									<p class="description"><?php esc_html_e( 'در صورت خالی بودن، موتور هوش مصنوعی به صورت خودکار از الگوریتم بومی داخلی استفاده می‌کند.', 'smart-seo-ai-suite-pro' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ai_language"><?php esc_html_e( 'زبان پیش‌فرض خروجی‌ها:', 'smart-seo-ai-suite-pro' ); ?></label></th>
								<td>
									<select name="settings[ai_language]" id="ai_language">
										<option value="fa" <?php selected( $settings['ai_language'] ?? 'fa', 'fa' ); ?>>فارسی (Persian)</option>
										<option value="en" <?php selected( $settings['ai_language'] ?? '', 'en' ); ?>>English</option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- SEO Settings -->
				<div class="smart-box">
					<div class="smart-box-header">
						<h2><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'تنظیمات سئو تکنیکال و محتوا (SEO Engine)', 'smart-seo-ai-suite-pro' ); ?></h2>
					</div>
					<div class="smart-box-body">
						<table class="form-table">
							<tr>
								<th scope="row"><label for="seo_title_separator"><?php esc_html_e( 'جداکننده عنوان سئو:', 'smart-seo-ai-suite-pro' ); ?></label></th>
								<td>
									<input type="text" name="settings[seo_title_separator]" id="seo_title_separator" class="small-text" value="<?php echo esc_attr( $settings['seo_title_separator'] ?? ' - ' ); ?>">
									<p class="description"><?php esc_html_e( 'جداکننده بین عنوان نوشته و نام سایت (مثال: - یا | )', 'smart-seo-ai-suite-pro' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'قابلیت‌های خودکار سئو:', 'smart-seo-ai-suite-pro' ); ?></th>
								<td>
									<label><input type="checkbox" name="settings[seo_auto_generate_missing]" value="1" <?php checked( $settings['seo_auto_generate_missing'] ?? 1, 1 ); ?>> <?php esc_html_e( 'تولید خودکار متای خالی از محتوا در صورت نبود متای دستی', 'smart-seo-ai-suite-pro' ); ?></label><br>
									<label><input type="checkbox" name="settings[seo_add_opengraph]" value="1" <?php checked( $settings['seo_add_opengraph'] ?? 1, 1 ); ?>> <?php esc_html_e( 'افزودن برچسب‌های شبکه‌های اجتماعی OpenGraph (فیسبوک، تلگرام، واتساپ)', 'smart-seo-ai-suite-pro' ); ?></label><br>
									<label><input type="checkbox" name="settings[seo_add_twitter_cards]" value="1" <?php checked( $settings['seo_add_twitter_cards'] ?? 1, 1 ); ?>> <?php esc_html_e( 'افزودن کارت‌های توییتر (Twitter Cards)', 'smart-seo-ai-suite-pro' ); ?></label><br>
									<label><input type="checkbox" name="settings[seo_canonical_urls]" value="1" <?php checked( $settings['seo_canonical_urls'] ?? 1, 1 ); ?>> <?php esc_html_e( 'افزودن تگ Canonical استاندارد برای جلوگیری از محتوای تکراری', 'smart-seo-ai-suite-pro' ); ?></label>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- AutoFix & Security -->
				<div class="smart-box">
					<div class="smart-box-header">
						<h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'تنظیمات سامانه اصلاح خودکار (AutoFix & Safety)', 'smart-seo-ai-suite-pro' ); ?></h2>
					</div>
					<div class="smart-box-body">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'امنیت و بازگردانی:', 'smart-seo-ai-suite-pro' ); ?></th>
								<td>
									<label><input type="checkbox" name="settings[autofix_backup_always]" value="1" <?php checked( $settings['autofix_backup_always'] ?? 1, 1 ); ?>> <strong><?php esc_html_e( 'تهیه نسخه پشتیبان (Backup) قبل از هر تغییر خودکار (الزامی و امن)', 'smart-seo-ai-suite-pro' ); ?></strong></label><br>
									<label><input type="checkbox" name="settings[autofix_missing_alt]" value="1" <?php checked( $settings['autofix_missing_alt'] ?? 1, 1 ); ?>> <?php esc_html_e( 'اصلاح خودکار متن جایگزین (Alt) تصاویر بر اساس نام تصویر و عنوان', 'smart-seo-ai-suite-pro' ); ?></label><br>
									<label><input type="checkbox" name="settings[autofix_missing_meta]" value="1" <?php checked( $settings['autofix_missing_meta'] ?? 1, 1 ); ?>> <?php esc_html_e( 'اصلاح خودکار متای مقالات ناقص', 'smart-seo-ai-suite-pro' ); ?></label><br>
									<label><input type="checkbox" name="settings[autofix_clean_revisions]" value="1" <?php checked( $settings['autofix_clean_revisions'] ?? 1, 1 ); ?>> <?php esc_html_e( 'پاکسازی رونوشت‌های اضافی دیتابیس برای بهینه‌سازی سرعت', 'smart-seo-ai-suite-pro' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'هنگام حذف افزونه:', 'smart-seo-ai-suite-pro' ); ?></th>
								<td>
									<label><input type="checkbox" name="settings[preserve_data_on_uninstall]" value="1" <?php checked( $settings['preserve_data_on_uninstall'] ?? 0, 1 ); ?>> <?php esc_html_e( 'حفظ جداول و تاریخچه اسکن‌ها هنگام حذف افزونه (Uninstall)', 'smart-seo-ai-suite-pro' ); ?></label>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<p class="submit">
					<button type="submit" name="smart_seo_ai_save_settings" class="button button-primary button-hero">
						<?php esc_html_e( 'ذخیره تمام تنظیمات', 'smart-seo-ai-suite-pro' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
