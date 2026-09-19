<?php
/**
 * Admin Dashboard Pages Renderer
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Admin_Dashboard {

	/**
	 * Main Dashboard view
	 */
	public static function render() {
		$loader  = Smart_SEO_AI_Loader::get_instance();
		$report  = $loader->reports->get_full_report();
		$scores  = $report['scores'];
		$backups = $report['recent_backups'];
		$logs    = $report['recent_logs'];
		?>
		<div class="wrap smart-seo-admin-wrap" id="smart-seo-dashboard-root">
			<!-- Top Brand Bar -->
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-superhero smart-icon-brand"></span>
					<div>
						<h1 class="smart-plugin-title">Smart SEO AI Suite Pro</h1>
						<p class="smart-plugin-desc"><?php esc_html_e( 'سوئیت هوشمند و یکپارچه سئو، هوش مصنوعی، ووکامرس، امنیت و بهینه‌سازی سرعت وردپرس', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
				<div class="smart-header-actions">
					<button type="button" class="button button-primary button-hero smart-btn-scan-all" id="btn-run-full-scan">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'بررسی کامل سایت', 'smart-seo-ai-suite-pro' ); ?>
					</button>
					<button type="button" class="button button-secondary button-hero smart-btn-autofix-all" id="btn-run-autofix-all">
						<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'رفع خودکار مشکلات', 'smart-seo-ai-suite-pro' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-reports' ) ); ?>" class="button button-hero">
						<span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'مشاهده گزارش', 'smart-seo-ai-suite-pro' ); ?>
					</a>
				</div>
			</div>

			<!-- Main Score Cards Row -->
			<div class="smart-scores-grid">
				<!-- Overall Score Card -->
				<div class="smart-score-card smart-card-overall">
					<div class="card-title"><?php esc_html_e( 'امتیاز کلی سلامت سایت', 'smart-seo-ai-suite-pro' ); ?></div>
					<div class="score-donut-wrapper">
						<div class="score-circle-big" style="--val: <?php echo esc_attr( $scores['overall'] ); ?>%;">
							<span class="score-number"><?php echo esc_html( $scores['overall'] ); ?></span>
							<span class="score-percent">/100</span>
						</div>
					</div>
					<div class="score-status-badge <?php echo $scores['overall'] >= 80 ? 'good' : ( $scores['overall'] >= 50 ? 'warning' : 'bad' ); ?>">
						<?php echo $scores['overall'] >= 80 ? esc_html__( 'سلامت عالی', 'smart-seo-ai-suite-pro' ) : esc_html__( 'نیازمند بهینه‌سازی', 'smart-seo-ai-suite-pro' ); ?>
					</div>
				</div>

				<!-- SEO Score Card -->
				<div class="smart-score-card">
					<div class="card-header-icon">
						<span class="dashicons dashicons-search"></span>
						<h3><?php esc_html_e( 'SEO Score', 'smart-seo-ai-suite-pro' ); ?></h3>
					</div>
					<div class="card-score-val"><?php echo esc_html( $scores['seo'] ); ?><span>/100</span></div>
					<div class="card-stat-text"><?php printf( esc_html__( '%d نوشته و برگه تحلیل شده', 'smart-seo-ai-suite-pro' ), $report['seo']['analyzed_count'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-studio' ) ); ?>" class="smart-card-link"><?php esc_html_e( 'استودیو سئو و محتوا &larr;', 'smart-seo-ai-suite-pro' ); ?></a>
				</div>

				<!-- Security Score Card -->
				<div class="smart-score-card">
					<div class="card-header-icon">
						<span class="dashicons dashicons-shield"></span>
						<h3><?php esc_html_e( 'Security Score', 'smart-seo-ai-suite-pro' ); ?></h3>
					</div>
					<div class="card-score-val"><?php echo esc_html( $scores['security'] ); ?><span>/100</span></div>
					<div class="card-stat-text"><?php echo count( $report['security']['vulnerabilities'] ) === 0 ? esc_html__( 'بدون رخنه امنیتی فعال', 'smart-seo-ai-suite-pro' ) : sprintf( esc_html__( '%d آسیب‌پذیری احتمالی', 'smart-seo-ai-suite-pro' ), count( $report['security']['vulnerabilities'] ) ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-security-speed' ) ); ?>" class="smart-card-link"><?php esc_html_e( 'اسکنر امنیت &larr;', 'smart-seo-ai-suite-pro' ); ?></a>
				</div>

				<!-- Performance Score Card -->
				<div class="smart-score-card">
					<div class="card-header-icon">
						<span class="dashicons dashicons-performance"></span>
						<h3><?php esc_html_e( 'Performance Score', 'smart-seo-ai-suite-pro' ); ?></h3>
					</div>
					<div class="card-score-val"><?php echo esc_html( $scores['performance'] ); ?><span>/100</span></div>
					<div class="card-stat-text"><?php printf( esc_html__( 'رم مصرفی: %s MB (PHP %s)', 'smart-seo-ai-suite-pro' ), $report['performance']['mem_usage_mb'], $report['performance']['php_version'] ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-security-speed' ) ); ?>" class="smart-card-link"><?php esc_html_e( 'گزارش سرعت &larr;', 'smart-seo-ai-suite-pro' ); ?></a>
				</div>

				<!-- WooCommerce Score Card -->
				<div class="smart-score-card">
					<div class="card-header-icon">
						<span class="dashicons dashicons-cart"></span>
						<h3><?php esc_html_e( 'WooCommerce Score', 'smart-seo-ai-suite-pro' ); ?></h3>
					</div>
					<div class="card-score-val"><?php echo esc_html( $scores['woocommerce'] ); ?><span>/100</span></div>
					<div class="card-stat-text"><?php echo $report['woocommerce']['is_active'] ? sprintf( esc_html__( '%d محصول بررسی شده', 'smart-seo-ai-suite-pro' ), $report['woocommerce']['total_products'] ) : esc_html__( 'ووکامرس نصب نیست', 'smart-seo-ai-suite-pro' ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-woocommerce' ) ); ?>" class="smart-card-link"><?php esc_html_e( 'سئو محصولات فروشگاه &larr;', 'smart-seo-ai-suite-pro' ); ?></a>
				</div>
			</div>

			<!-- Dashboard Main Grid -->
			<div class="smart-dash-two-cols">
				<!-- Left Column: Diagnostic Charts & Quick Action Center -->
				<div class="smart-col-main">
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'نمودار توزیع کیفیت و سلامت بخش‌ها', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<div class="smart-bar-chart-container" id="smart-dashboard-bar-chart"
								data-seo="<?php echo esc_attr( $scores['seo'] ); ?>"
								data-security="<?php echo esc_attr( $scores['security'] ); ?>"
								data-perf="<?php echo esc_attr( $scores['performance'] ); ?>"
								data-woo="<?php echo esc_attr( $scores['woocommerce'] ); ?>">
								<div class="chart-bars-wrap">
									<div class="chart-col">
										<div class="bar-fill" style="height: <?php echo esc_attr( $scores['seo'] ); ?>%;"><span><?php echo esc_html( $scores['seo'] ); ?>%</span></div>
										<div class="bar-label">سئو</div>
									</div>
									<div class="chart-col">
										<div class="bar-fill" style="height: <?php echo esc_attr( $scores['security'] ); ?>%;"><span><?php echo esc_html( $scores['security'] ); ?>%</span></div>
										<div class="bar-label">امنیت</div>
									</div>
									<div class="chart-col">
										<div class="bar-fill" style="height: <?php echo esc_attr( $scores['performance'] ); ?>%;"><span><?php echo esc_html( $scores['performance'] ); ?>%</span></div>
										<div class="bar-label">سرعت</div>
									</div>
									<div class="chart-col">
										<div class="bar-fill" style="height: <?php echo esc_attr( $scores['woocommerce'] ); ?>%;"><span><?php echo esc_html( $scores['woocommerce'] ); ?>%</span></div>
										<div class="bar-label">فروشگاه</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Priority Recommendations -->
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'پیشنهادات فوری برای ارتقای رتبه و فروش', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<ul class="smart-recommendation-list">
								<?php if ( $report['seo']['missing_meta_count'] > 0 ) : ?>
									<li class="smart-rec-item priority-high">
										<span class="dashicons dashicons-warning"></span>
										<div class="rec-content">
											<strong><?php printf( esc_html__( '%d نوشته فاقد توضیحات متا هستند', 'smart-seo-ai-suite-pro' ), $report['seo']['missing_meta_count'] ); ?></strong>
											<p><?php esc_html_e( 'توضیحات متا در جلب کلیک کاربران در نتایج گوگل حیاتی است. از دکمه AutoFix برای تولید خودکار آن‌ها استفاده کنید.', 'smart-seo-ai-suite-pro' ); ?></p>
										</div>
										<button type="button" class="button button-small button-primary smart-btn-quick-fix" data-fix="meta"><?php esc_html_e( 'اصلاح خودکار', 'smart-seo-ai-suite-pro' ); ?></button>
									</li>
								<?php endif; ?>

								<?php if ( ! empty( $report['performance']['heavy_images'] ) ) : ?>
									<li class="smart-rec-item priority-medium">
										<span class="dashicons dashicons-format-image"></span>
										<div class="rec-content">
											<strong><?php printf( esc_html__( '%d تصویر سنگین در کتابخانه رسانه یافت شد', 'smart-seo-ai-suite-pro' ), count( $report['performance']['heavy_images'] ) ); ?></strong>
											<p><?php esc_html_e( 'تصاویر بالای 300 کیلوبایت لود اولیه صفحه را کند می‌کنند.', 'smart-seo-ai-suite-pro' ); ?></p>
										</div>
									</li>
								<?php endif; ?>

								<li class="smart-rec-item priority-info">
									<span class="dashicons dashicons-superhero"></span>
									<div class="rec-content">
										<strong><?php esc_html_e( 'دستیار هوش مصنوعی آماده بهبود مقالات', 'smart-seo-ai-suite-pro' ); ?></strong>
										<p><?php esc_html_e( 'می‌توانید کلمات کلیدی، عنوان‌های جذاب و بازنویسی متون را مستقیما در استودیو هوش مصنوعی انجام دهید.', 'smart-seo-ai-suite-pro' ); ?></p>
									</div>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=smart-seo-ai-studio' ) ); ?>" class="button button-small"><?php esc_html_e( 'ورود به استودیو', 'smart-seo-ai-suite-pro' ); ?></a>
								</li>
							</ul>
						</div>
					</div>
				</div>

				<!-- Right Column: AutoFix Backups & Audit Logs -->
				<div class="smart-col-side">
					<!-- AutoFix Backups Box with One-Click Rollback -->
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'پشتیبان‌های اصلاح خودکار (AutoFix Backups)', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<?php if ( empty( $backups ) ) : ?>
								<p class="smart-empty-note"><?php esc_html_e( 'هنوز هیچ اصلاح خودکاری ثبت نشده است. سیستم قبل از هر تغییر یک بک‌آپ ایمن ایجاد می‌کند.', 'smart-seo-ai-suite-pro' ); ?></p>
							<?php else : ?>
								<div class="smart-backups-list">
									<?php foreach ( $backups as $b ) : ?>
										<div class="smart-backup-item <?php echo esc_attr( $b['status'] ); ?>">
											<div class="backup-info">
												<span class="backup-badge"><?php echo esc_html( $b['item_type'] ); ?></span>
												<strong class="backup-action"><?php echo esc_html( $b['action_taken'] ); ?></strong>
												<small class="backup-time"><?php echo esc_html( $b['created_at'] ); ?></small>
											</div>
											<div class="backup-actions">
												<?php if ( $b['status'] === 'active' ) : ?>
													<button type="button" class="button button-small smart-btn-rollback" data-backup-id="<?php echo esc_attr( $b['id'] ); ?>" title="<?php esc_attr_e( 'بازگردانی اطلاعات به حالت قبل', 'smart-seo-ai-suite-pro' ); ?>">
														<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Rollback', 'smart-seo-ai-suite-pro' ); ?>
													</button>
												<?php else : ?>
													<span class="rolled-back-tag"><?php esc_html_e( 'بازگردانی شده', 'smart-seo-ai-suite-pro' ); ?></span>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Recent Audit Logs -->
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'لاگ رویدادها و فعالیت‌ها', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<ul class="smart-log-list">
								<?php foreach ( $logs as $log ) : ?>
									<li class="log-item severity-<?php echo esc_attr( $log['severity'] ); ?>">
										<span class="log-tag"><?php echo esc_html( $log['event_type'] ); ?></span>
										<span class="log-msg"><?php echo esc_html( $log['message'] ); ?></span>
										<span class="log-time"><?php echo esc_html( substr( $log['created_at'], 11, 5 ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * SEO & AI Studio Subpage
	 */
	public static function render_studio() {
		?>
		<div class="wrap smart-seo-admin-wrap" id="smart-seo-studio-root">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-superhero smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'استودیو سئو و هوش مصنوعی محتوا (SEO & AI Studio)', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'تولید عناوین کلیک‌خور، متا دیسکریپشن، استخراج کلمات کلیدی و بهینه‌سازی مقالات با هوش مصنوعی', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
			</div>

			<div class="smart-dash-two-cols">
				<!-- Left Form Panel -->
				<div class="smart-col-main">
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><?php esc_html_e( 'دستیار تولید و بهینه‌سازی محتوا', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<div class="smart-form-group">
								<label for="ai-studio-task"><strong><?php esc_html_e( 'نوع عملیات هوش مصنوعی:', 'smart-seo-ai-suite-pro' ); ?></strong></label>
								<select id="ai-studio-task" class="widefat">
									<option value="generate_titles"><?php esc_html_e( '🎯 تولید ۵ عنوان سئو جذاب و کلیک‌خور', 'smart-seo-ai-suite-pro' ); ?></option>
									<option value="generate_meta_desc"><?php esc_html_e( '📝 تولید توضیحات متا استاندارد (۱۶۰ کاراکتر)', 'smart-seo-ai-suite-pro' ); ?></option>
									<option value="suggest_keywords"><?php esc_html_e( '🔑 پیشنهاد کلمات کلیدی و Long-tail', 'smart-seo-ai-suite-pro' ); ?></option>
									<option value="improve_content"><?php esc_html_e( '🚀 بازنویسی و بهبود لحن محتوا', 'smart-seo-ai-suite-pro' ); ?></option>
									<option value="generate_outline"><?php esc_html_e( '📑 ساختاردهی و سرفصل‌های مقاله (H2/H3)', 'smart-seo-ai-suite-pro' ); ?></option>
									<option value="woo_product_desc"><?php esc_html_e( '🛍️ تولید توضیحات فروشگاهی محصول ووکامرس', 'smart-seo-ai-suite-pro' ); ?></option>
								</select>
							</div>

							<div class="smart-form-group">
								<label for="ai-studio-topic"><strong><?php esc_html_e( 'موضوع مقاله یا عنوان محصول:', 'smart-seo-ai-suite-pro' ); ?></strong></label>
								<input type="text" id="ai-studio-topic" class="widefat" placeholder="<?php esc_attr_e( 'مثال: آموزش افزایش سرعت سایت وردپرس', 'smart-seo-ai-suite-pro' ); ?>">
							</div>

							<div class="smart-form-group">
								<label for="ai-studio-keyword"><strong><?php esc_html_e( 'کلمه کلیدی هدف (اختیاری):', 'smart-seo-ai-suite-pro' ); ?></strong></label>
								<input type="text" id="ai-studio-keyword" class="widefat" placeholder="<?php esc_attr_e( 'مثال: افزایش سرعت وردپرس', 'smart-seo-ai-suite-pro' ); ?>">
							</div>

							<div class="smart-form-group">
								<label for="ai-studio-content"><strong><?php esc_html_e( 'متن اولیه / نکات کلیدی (برای بازنویسی و بهینه‌سازی):', 'smart-seo-ai-suite-pro' ); ?></strong></label>
								<textarea id="ai-studio-content" rows="6" class="widefat" placeholder="<?php esc_attr_e( 'متن محتوا یا ویژگی‌های محصول را اینجا وارد کنید...', 'smart-seo-ai-suite-pro' ); ?>"></textarea>
							</div>

							<button type="button" class="button button-primary button-hero" id="btn-run-ai-generate">
								<span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'اجرای هوش مصنوعی و تولید خروجی', 'smart-seo-ai-suite-pro' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Right Output Panel -->
				<div class="smart-col-side">
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><?php esc_html_e( 'نتیجه هوش مصنوعی (AI Output)', 'smart-seo-ai-suite-pro' ); ?></h2>
						</div>
						<div class="smart-box-body">
							<div id="ai-studio-result-wrapper" class="smart-ai-result-box">
								<p class="placeholder-text"><?php esc_html_e( 'پس از زدن دکمه اجرا، نتیجه تولید شده توسط هوش مصنوعی در این قسمت نمایش داده می‌شود و می‌توانید با یک کلیک آن را کپی کنید.', 'smart-seo-ai-suite-pro' ); ?></p>
							</div>
							<div class="smart-result-actions" style="display:none;" id="ai-result-actions">
								<button type="button" class="button button-secondary" id="btn-copy-ai-result">
									<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'کپی در کلیپ‌بورد', 'smart-seo-ai-suite-pro' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * WooCommerce Subpage
	 */
	public static function render_woocommerce() {
		$loader = Smart_SEO_AI_Loader::get_instance();
		$audit  = $loader->woocommerce->run_audit();
		?>
		<div class="wrap smart-seo-admin-wrap">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-cart smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'سئو و بهینه‌سازی فروشگاه ووکامرس (WooCommerce Engine)', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'تحلیل عمیق سئوی محصولات، تصاویر گالری، توضیحات و تولید اسکیما استاندارد Product JSON-LD', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
			</div>

			<div class="smart-box">
				<div class="smart-box-header">
					<h2><?php printf( esc_html__( 'تحلیل کیفیت محصولات (امتیاز کلی سئو فروشگاه: %d/100)', 'smart-seo-ai-suite-pro' ), $audit['score'] ); ?></h2>
				</div>
				<div class="smart-box-body">
					<?php if ( ! $audit['is_active'] ) : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'افزونه ووکامرس فعال نیست.', 'smart-seo-ai-suite-pro' ); ?></p></div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'نام محصول', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'قیمت / SKU', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'تصویر شاخص / گالری', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'امتیاز سئو', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'عملیات هوش مصنوعی', 'smart-seo-ai-suite-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $audit['products'] ) ) : ?>
									<tr><td colspan="5"><?php esc_html_e( 'هیچ محصولی یافت نشد.', 'smart-seo-ai-suite-pro' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $audit['products'] as $p ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $p['title'] ); ?></strong></td>
											<td><?php echo esc_html( $p['price'] ?: 'بدون قیمت' ); ?> | SKU: <?php echo esc_html( $p['sku'] ?: '-' ); ?></td>
											<td><?php echo $p['has_image'] ? '✅ دارد' : '❌ بدون عکس'; ?> (گالری: <?php echo esc_html( $p['gallery_cnt'] ); ?>)</td>
											<td><span class="smart-mini-score score-<?php echo $p['score'] >= 80 ? 'good' : 'warning'; ?>"><?php echo esc_html( $p['score'] ); ?>/100</span></td>
											<td>
												<a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'ویرایش', 'smart-seo-ai-suite-pro' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Security & Speed Subpage
	 */
	public static function render_security_speed() {
		$loader = Smart_SEO_AI_Loader::get_instance();
		$sec    = $loader->security->run_audit();
		$perf   = $loader->performance->run_audit();
		?>
		<div class="wrap smart-seo-admin-wrap">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-shield smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'گزارش تخصصی امنیت و سرعت سرور (Security & Speed)', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'اسکن عمیق کدهای مشکوک، آسیب‌پذیری‌های امنیتی، تست حافظه رم و مصرف کوئری‌های دیتابیس', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
			</div>

			<div class="smart-dash-two-cols">
				<!-- Security Box -->
				<div class="smart-col-main">
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><?php printf( esc_html__( '🛡️ فاکتورهای امنیت سایت (امتیاز: %d/100)', 'smart-seo-ai-suite-pro' ), $sec['score'] ); ?></h2>
						</div>
						<div class="smart-box-body">
							<ul class="smart-audit-items">
								<?php foreach ( $sec['checks'] as $c ) : ?>
									<li class="smart-check-item status-<?php echo esc_attr( $c['status'] ); ?>">
										<span class="dashicons <?php echo $c['status'] === 'pass' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
										<div class="check-text">
											<strong><?php echo esc_html( $c['title'] ); ?>:</strong>
											<span><?php echo esc_html( $c['message'] ); ?></span>
											<?php if ( ! empty( $c['suggestion'] ) ) : ?>
												<small class="check-suggestion">💡 <?php echo esc_html( $c['suggestion'] ); ?></small>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>

				<!-- Speed & Performance Box -->
				<div class="smart-col-side">
					<div class="smart-box">
						<div class="smart-box-header">
							<h2><?php printf( esc_html__( '⚡ فاکتورهای سرعت و سرور (امتیاز: %d/100)', 'smart-seo-ai-suite-pro' ), $perf['score'] ); ?></h2>
						</div>
						<div class="smart-box-body">
							<ul class="smart-audit-items">
								<?php foreach ( $perf['checks'] as $c ) : ?>
									<li class="smart-check-item status-<?php echo esc_attr( $c['status'] ); ?>">
										<span class="dashicons <?php echo $c['status'] === 'pass' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
										<div class="check-text">
											<strong><?php echo esc_html( $c['title'] ); ?>:</strong>
											<span><?php echo esc_html( $c['message'] ); ?></span>
											<?php if ( ! empty( $c['suggestion'] ) ) : ?>
												<small class="check-suggestion">💡 <?php echo esc_html( $c['suggestion'] ); ?></small>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AutoFix & Backups Subpage
	 */
	public static function render_autofix() {
		$backups = Smart_SEO_AI_Database::get_recent_backups( 50 );
		?>
		<div class="wrap smart-seo-admin-wrap">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-admin-tools smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'سامانه رفع خودکار مشکلات و بازگردانی (Auto-Fix & Rollback)', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'اصلاح امن تگ‌های Alt تصاویر، توضیحات متا و پاکسازی دیتابیس با قابلیت بازگردانی ۱۰۰٪ تغییرات', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
				<div class="smart-header-actions">
					<button type="button" class="button button-primary button-hero smart-btn-autofix-all" id="btn-autofix-page-run">
						<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'اجرای رفع خودکار مشکلات', 'smart-seo-ai-suite-pro' ); ?>
					</button>
				</div>
			</div>

			<div class="smart-box">
				<div class="smart-box-header">
					<h2><?php esc_html_e( 'تاریخچه بک‌آپ‌ها و تغییرات اعمال شده (Restore Points)', 'smart-seo-ai-suite-pro' ); ?></h2>
				</div>
				<div class="smart-box-body">
					<?php if ( empty( $backups ) ) : ?>
						<p><?php esc_html_e( 'هیچ نسخه پشتیبانی برای بازگردانی وجود ندارد.', 'smart-seo-ai-suite-pro' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>ID</th>
									<th><?php esc_html_e( 'نوع آیتم', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'شرح تغییرات', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'وضعیت', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'تاریخ و زمان', 'smart-seo-ai-suite-pro' ); ?></th>
									<th><?php esc_html_e( 'عملیات Rollback', 'smart-seo-ai-suite-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $backups as $b ) : ?>
									<tr>
										<td>#<?php echo esc_html( $b['id'] ); ?></td>
										<td><span class="smart-tag"><?php echo esc_html( $b['item_type'] ); ?></span></td>
										<td><?php echo esc_html( $b['action_taken'] ); ?></td>
										<td>
											<span class="smart-status-badge <?php echo $b['status'] === 'active' ? 'active' : 'rolled-back'; ?>">
												<?php echo $b['status'] === 'active' ? esc_html__( 'اعمال شده', 'smart-seo-ai-suite-pro' ) : esc_html__( 'بازگردانی شده', 'smart-seo-ai-suite-pro' ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $b['created_at'] ); ?></td>
										<td>
											<?php if ( $b['status'] === 'active' ) : ?>
												<button type="button" class="button button-small smart-btn-rollback" data-backup-id="<?php echo esc_attr( $b['id'] ); ?>">
													<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Rollback', 'smart-seo-ai-suite-pro' ); ?>
												</button>
											<?php else : ?>
												<span>-</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
