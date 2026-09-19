<?php
/**
 * Reports & Data Analytics Engine
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Reports_Engine {

	/**
	 * Compile full diagnostic report data
	 *
	 * @return array
	 */
	public function get_full_report() {
		$loader = Smart_SEO_AI_Loader::get_instance();

		$seo_data      = $loader->seo->analyze_site_summary();
		$security_data = $loader->security->run_audit();
		$perf_data     = $loader->performance->run_audit();
		$woo_data      = $loader->woocommerce->run_audit();

		$woo_score = $woo_data['is_active'] ? $woo_data['score'] : $seo_data['score'];
		$overall   = intval( round( ( $seo_data['score'] + $security_data['score'] + $perf_data['score'] + $woo_score ) / 4 ) );

		$recent_scans   = Smart_SEO_AI_Database::get_recent_scans( 7 );
		$recent_backups = Smart_SEO_AI_Database::get_recent_backups( 10 );
		$recent_logs    = Smart_SEO_AI_Logger::get_logs( 15 );

		return array(
			'generated_at'   => current_time( 'mysql' ),
			'site_url'       => get_site_url(),
			'site_name'      => get_bloginfo( 'name' ),
			'scores'         => array(
				'overall'     => $overall,
				'seo'         => $seo_data['score'],
				'security'    => $security_data['score'],
				'performance' => $perf_data['score'],
				'woocommerce' => $woo_data['score'],
			),
			'seo'            => $seo_data,
			'security'       => $security_data,
			'performance'    => $perf_data,
			'woocommerce'    => $woo_data,
			'recent_scans'   => $recent_scans,
			'recent_backups' => $recent_backups,
			'recent_logs'    => $recent_logs,
		);
	}

	/**
	 * Export full report to downloadable CSV
	 */
	public function export_csv() {
		$report = $this->get_full_report();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=smart-seo-ai-report-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		// UTF-8 BOM for Excel Persian/Arabic compatibility
		fputs( $output, "\xEF\xBB\xBF" );

		// Header
		fputcsv( $output, array( 'بخش بررسی', 'عنوان فاکتور', 'وضعیت', 'پیام و جزئیات', 'پیشنهاد اصلاح' ) );

		// Scores Row
		fputcsv( $output, array( 'خلاصه امتیازات', 'امتیاز کلی سلامت سایت', $report['scores']['overall'] . '/100', 'میانگین سئو، امنیت، سرعت و فروشگاه', '-' ) );
		fputcsv( $output, array( 'خلاصه امتیازات', 'امتیاز سئو محتوا', $report['scores']['seo'] . '/100', '', '-' ) );
		fputcsv( $output, array( 'خلاصه امتیازات', 'امتیاز امنیت سایت', $report['scores']['security'] . '/100', '', '-' ) );
		fputcsv( $output, array( 'خلاصه امتیازات', 'امتیاز سرعت و عملکرد', $report['scores']['performance'] . '/100', '', '-' ) );
		fputcsv( $output, array( 'خلاصه امتیازات', 'امتیاز ووکامرس', $report['scores']['woocommerce'] . '/100', '', '-' ) );

		// Security Checks
		foreach ( $report['security']['checks'] as $c ) {
			fputcsv( $output, array( 'امنیت', $c['title'], $c['status'], $c['message'], $c['suggestion'] ?? '-' ) );
		}

		// Performance Checks
		foreach ( $report['performance']['checks'] as $c ) {
			fputcsv( $output, array( 'عملکرد و سرعت', $c['title'], $c['status'], $c['message'], $c['suggestion'] ?? '-' ) );
		}

		fclose( $output );
		exit;
	}
}
