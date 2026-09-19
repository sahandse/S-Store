<?php
/**
 * Reports and Exports Page
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Admin_Reports {

	public static function render() {
		$loader = Smart_SEO_AI_Loader::get_instance();
		$report = $loader->reports->get_full_report();
		?>
		<div class="wrap smart-seo-admin-wrap" id="smart-seo-reports-root">
			<div class="smart-top-header">
				<div class="smart-brand">
					<span class="dashicons dashicons-chart-pie smart-icon-brand"></span>
					<div>
						<h1><?php esc_html_e( 'گزارش جامع سئو، سلامت و عملکرد (SEO & Health Audit Report)', 'smart-seo-ai-suite-pro' ); ?></h1>
						<p><?php esc_html_e( 'خروجی تفصیلی وضعیت سئو محتوا، آسیب‌پذیری‌های امنیتی، آمار فروشگاه و تاریخچه بررسی‌ها', 'smart-seo-ai-suite-pro' ); ?></p>
					</div>
				</div>
				<div class="smart-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=smart_seo_ai_export_csv&_wpnonce=' . wp_create_nonce( 'smart_seo_ai_nonce_action' ) ) ); ?>" class="button button-primary button-hero">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'دریافت خروجی اکسل (CSV)', 'smart-seo-ai-suite-pro' ); ?>
					</a>
					<button type="button" class="button button-hero" onclick="window.print();">
						<span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'چاپ یا خروجی PDF', 'smart-seo-ai-suite-pro' ); ?>
					</button>
				</div>
			</div>

			<!-- Printable Report Container -->
			<div class="smart-printable-report">
				<div class="report-meta-header">
					<div><strong>وب‌سایت:</strong> <?php echo esc_html( $report['site_name'] ); ?> (<?php echo esc_html( $report['site_url'] ); ?>)</div>
					<div><strong>تاریخ تهیه گزارش:</strong> <?php echo esc_html( $report['generated_at'] ); ?></div>
				</div>

				<!-- Summary 4 Scores -->
				<div class="smart-scores-grid report-summary-grid">
					<div class="smart-score-card">
						<div class="card-title"><?php esc_html_e( 'امتیاز کلی سلامت', 'smart-seo-ai-suite-pro' ); ?></div>
						<div class="card-score-val"><?php echo esc_html( $report['scores']['overall'] ); ?>/100</div>
					</div>
					<div class="smart-score-card">
						<div class="card-title"><?php esc_html_e( 'سئو محتوا', 'smart-seo-ai-suite-pro' ); ?></div>
						<div class="card-score-val"><?php echo esc_html( $report['scores']['seo'] ); ?>/100</div>
					</div>
					<div class="smart-score-card">
						<div class="card-title"><?php esc_html_e( 'امنیت سایت', 'smart-seo-ai-suite-pro' ); ?></div>
						<div class="card-score-val"><?php echo esc_html( $report['scores']['security'] ); ?>/100</div>
					</div>
					<div class="smart-score-card">
						<div class="card-title"><?php esc_html_e( 'سرعت و سرور', 'smart-seo-ai-suite-pro' ); ?></div>
						<div class="card-score-val"><?php echo esc_html( $report['scores']['performance'] ); ?>/100</div>
					</div>
				</div>

				<!-- Detailed Findings Table -->
				<div class="smart-box">
					<div class="smart-box-header">
						<h2><?php esc_html_e( 'فهرست فاکتورهای ارزیابی شده', 'smart-seo-ai-suite-pro' ); ?></h2>
					</div>
					<div class="smart-box-body">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th width="15%"><?php esc_html_e( 'بخش', 'smart-seo-ai-suite-pro' ); ?></th>
									<th width="25%"><?php esc_html_e( 'فاکتور', 'smart-seo-ai-suite-pro' ); ?></th>
									<th width="15%"><?php esc_html_e( 'وضعیت', 'smart-seo-ai-suite-pro' ); ?></th>
									<th width="45%"><?php esc_html_e( 'توضیحات و پیشنهاد', 'smart-seo-ai-suite-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $report['security']['checks'] as $c ) : ?>
									<tr>
										<td><strong>امنیت</strong></td>
										<td><?php echo esc_html( $c['title'] ); ?></td>
										<td><span class="smart-status-pill <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $c['status'] ); ?></span></td>
										<td><?php echo esc_html( $c['message'] ); ?> <?php if ( ! empty( $c['suggestion'] ) ) echo ' - ' . esc_html( $c['suggestion'] ); ?></td>
									</tr>
								<?php endforeach; ?>
								<?php foreach ( $report['performance']['checks'] as $c ) : ?>
									<tr>
										<td><strong>سرعت</strong></td>
										<td><?php echo esc_html( $c['title'] ); ?></td>
										<td><span class="smart-status-pill <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $c['status'] ); ?></span></td>
										<td><?php echo esc_html( $c['message'] ); ?> <?php if ( ! empty( $c['suggestion'] ) ) echo ' - ' . esc_html( $c['suggestion'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Scan History -->
				<div class="smart-box">
					<div class="smart-box-header">
						<h2><?php esc_html_e( 'تاریخچه آخرین اسکن‌های دوره‌ای (Scan History)', 'smart-seo-ai-suite-pro' ); ?></h2>
					</div>
					<div class="smart-box-body">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>ID</th>
									<th>نوع اسکن</th>
									<th>امتیاز کل</th>
									<th>سئو</th>
									<th>امنیت</th>
									<th>سرعت</th>
									<th>فروشگاه</th>
									<th>تاریخ</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $report['recent_scans'] ) ) : ?>
									<tr><td colspan="8"><?php esc_html_e( 'تاریخچه‌ای ثبت نشده است.', 'smart-seo-ai-suite-pro' ); ?></td></tr>
								<?php else : ?>
									<?php foreach ( $report['recent_scans'] as $s ) : ?>
										<tr>
											<td>#<?php echo esc_html( $s['id'] ); ?></td>
											<td><?php echo esc_html( $s['scan_type'] ); ?></td>
											<td><strong><?php echo esc_html( $s['overall_score'] ); ?>%</strong></td>
											<td><?php echo esc_html( $s['seo_score'] ); ?>%</td>
											<td><?php echo esc_html( $s['security_score'] ); ?>%</td>
											<td><?php echo esc_html( $s['performance_score'] ); ?>%</td>
											<td><?php echo esc_html( $s['woo_score'] ); ?>%</td>
											<td><?php echo esc_html( $s['created_at'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
