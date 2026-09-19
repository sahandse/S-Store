<?php
/**
 * AJAX Handlers with Strict Nonce & Capability Verification
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Admin_Ajax {

	public function __construct() {
		// Run Full Scan
		add_action( 'wp_ajax_smart_seo_ai_run_full_scan', array( $this, 'ajax_run_full_scan' ) );

		// Run AutoFix
		add_action( 'wp_ajax_smart_seo_ai_run_autofix', array( $this, 'ajax_run_autofix' ) );

		// Rollback Backup
		add_action( 'wp_ajax_smart_seo_ai_rollback', array( $this, 'ajax_rollback' ) );

		// AI Generation
		add_action( 'wp_ajax_smart_seo_ai_generate_content', array( $this, 'ajax_generate_content' ) );

		// Test AI API Key
		add_action( 'wp_ajax_smart_seo_ai_test_api_key', array( $this, 'ajax_test_api_key' ) );

		// Export CSV
		add_action( 'wp_ajax_smart_seo_ai_export_csv', array( $this, 'ajax_export_csv' ) );
	}

	/**
	 * Run Full Scan AJAX handler
	 */
	public function ajax_run_full_scan() {
		Smart_SEO_AI_Permissions::verify_ajax_request( 'smart_seo_ai_nonce_action', 'editor' );

		$loader    = Smart_SEO_AI_Loader::get_instance();
		$seo_res   = $loader->seo->analyze_site_summary();
		$sec_res   = $loader->security->run_audit();
		$perf_res  = $loader->performance->run_audit();
		$woo_res   = $loader->woocommerce->run_audit();

		$woo_score = $woo_res['is_active'] ? $woo_res['score'] : $seo_res['score'];
		$overall   = intval( round( ( $seo_res['score'] + $sec_res['score'] + $perf_res['score'] + $woo_score ) / 4 ) );

		$scan_id = Smart_SEO_AI_Database::record_scan( array(
			'scan_type'         => 'manual_full',
			'overall_score'     => $overall,
			'seo_score'         => $seo_res['score'],
			'security_score'    => $sec_res['score'],
			'performance_score' => $perf_res['score'],
			'woo_score'         => $woo_res['score'],
			'details'           => array(
				'seo'      => $seo_res,
				'security' => $sec_res,
				'perf'     => $perf_res,
				'woo'      => $woo_res,
			),
		) );

		Smart_SEO_AI_Logger::log( 'system', "Manual site scan #{$scan_id} completed (Overall: {$overall}/100)", 'success' );

		wp_send_json_success( array(
			'scan_id'           => $scan_id,
			'overall_score'     => $overall,
			'seo_score'         => $seo_res['score'],
			'security_score'    => $sec_res['score'],
			'performance_score' => $perf_res['score'],
			'woo_score'         => $woo_res['score'],
			'message'           => __( 'اسکن با موفقیت به اتمام رسید.', 'smart-seo-ai-suite-pro' ),
		) );
	}

	/**
	 * Run AutoFix AJAX handler
	 */
	public function ajax_run_autofix() {
		Smart_SEO_AI_Permissions::verify_ajax_request( 'smart_seo_ai_nonce_action', 'manage_all' );

		$loader  = Smart_SEO_AI_Loader::get_instance();
		$results = $loader->autofix->run_all_fixes();

		wp_send_json_success( array(
			'results' => $results,
			'message' => sprintf(
				__( 'رفع خودکار با موفقیت انجام شد: %d برچسب Alt تصویر، %d متای سئو و %d رکورد دیتابیس اصلاح گردید.', 'smart-seo-ai-suite-pro' ),
				$results['alt_fixed'],
				$results['meta_fixed'],
				$results['revisions_fixed'] + $results['transients_fixed']
			),
		) );
	}

	/**
	 * Rollback AJAX handler
	 */
	public function ajax_rollback() {
		Smart_SEO_AI_Permissions::verify_ajax_request( 'smart_seo_ai_nonce_action', 'manage_all' );

		$backup_id = isset( $_POST['backup_id'] ) ? intval( $_POST['backup_id'] ) : 0;
		if ( ! $backup_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه بک‌آپ نامعتبر است.', 'smart-seo-ai-suite-pro' ) ) );
		}

		$loader = Smart_SEO_AI_Loader::get_instance();
		$res    = $loader->autofix->rollback( $backup_id );

		if ( $res ) {
			wp_send_json_success( array( 'message' => __( 'تغییرات با موفقیت به حالت اولیه بازگردانده شدند.', 'smart-seo-ai-suite-pro' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'خطا در بازگردانی یا قبلا بازگردانی شده است.', 'smart-seo-ai-suite-pro' ) ) );
		}
	}

	/**
	 * AI Content Generator AJAX handler
	 */
	public function ajax_generate_content() {
		Smart_SEO_AI_Permissions::verify_ajax_request( 'smart_seo_ai_nonce_action', 'editor' );

		$task    = sanitize_key( $_POST['task'] ?? 'generate_titles' );
		$topic   = sanitize_text_field( $_POST['topic'] ?? '' );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? '' );
		$content = sanitize_textarea_field( $_POST['content'] ?? '' );

		$loader = Smart_SEO_AI_Loader::get_instance();
		$res    = array();

		switch ( $task ) {
			case 'generate_titles':
				$res = $loader->ai->generate_seo_titles( $topic, $keyword );
				break;
			case 'generate_meta_desc':
				$res = $loader->ai->generate_meta_description( $topic, $content, $keyword );
				break;
			case 'suggest_keywords':
				$res = $loader->ai->suggest_keywords( $topic ?: $keyword );
				break;
			case 'improve_content':
				$res = $loader->ai->improve_content( $content );
				break;
			case 'woo_product_desc':
				$res = $loader->ai->generate_product_description( $topic, '', $content );
				break;
			default:
				$res = $loader->ai->generate( 'SEO Assistant', "Topic: {$topic}\nKeyword: {$keyword}\nTask: {$task}" );
				break;
		}

		if ( ! empty( $res['success'] ) ) {
			wp_send_json_success( array( 'result' => $res['text'] ) );
		} else {
			wp_send_json_error( array( 'message' => $res['error'] ?: __( 'خطا در ارتباط با هوش مصنوعی', 'smart-seo-ai-suite-pro' ) ) );
		}
	}

	/**
	 * Test AI API Key
	 */
	public function ajax_test_api_key() {
		Smart_SEO_AI_Permissions::verify_ajax_request( 'smart_seo_ai_nonce_action', 'manage_all' );

		$loader = Smart_SEO_AI_Loader::get_instance();
		$test   = $loader->ai->generate( 'Test', 'Ping! Respond with: Connected successfully.' );

		if ( ! empty( $test['success'] ) ) {
			wp_send_json_success( array( 'message' => __( '✅ اتصال به هوش مصنوعی با موفقیت برقرار شد.', 'smart-seo-ai-suite-pro' ) ) );
		} else {
			wp_send_json_error( array( 'message' => '❌ ' . $test['error'] ) );
		}
	}

	/**
	 * Export CSV handler
	 */
	public function ajax_export_csv() {
		if ( ! check_admin_referer( 'smart_seo_ai_nonce_action' ) || ! Smart_SEO_AI_Permissions::can_edit_seo() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز', 'smart-seo-ai-suite-pro' ) );
		}

		$loader = Smart_SEO_AI_Loader::get_instance();
		$loader->reports->export_csv();
	}
}

new Smart_SEO_AI_Admin_Ajax();
