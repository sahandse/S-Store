<?php
/**
 * Settings Management Class
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Settings {

	/**
	 * Get a setting value
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( $key, $default = false ) {
		$settings = get_option( 'smart_seo_ai_settings', array() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get_all() {
		return get_option( 'smart_seo_ai_settings', array() );
	}

	/**
	 * Update single or multiple settings
	 *
	 * @param array $new_settings
	 * @return bool
	 */
	public static function update_all( $new_settings ) {
		$current = self::get_all();
		$sanitized = self::sanitize( array_merge( $current, $new_settings ) );
		return update_option( 'smart_seo_ai_settings', $sanitized );
	}

	/**
	 * Sanitize raw settings input
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$sanitized = array();

		// Checkboxes / Booleans
		$bool_keys = array(
			'enable_seo_engine',
			'enable_ai_engine',
			'enable_woocommerce_engine',
			'enable_security_engine',
			'enable_performance_engine',
			'enable_autofix_engine',
			'enable_reports_engine',
			'preserve_data_on_uninstall',
			'seo_auto_generate_missing',
			'seo_add_opengraph',
			'seo_add_twitter_cards',
			'seo_canonical_urls',
			'security_check_eval',
			'security_check_admin_user',
			'security_check_debug_mode',
			'security_check_ssl',
			'security_block_xmlrpc',
			'perf_check_memory',
			'perf_check_queries',
			'perf_check_heavy_images',
			'autofix_backup_always',
			'autofix_missing_alt',
			'autofix_missing_meta',
			'autofix_clean_revisions',
		);

		foreach ( $bool_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		// Text & select fields
		$sanitized['ai_provider'] = sanitize_key( $input['ai_provider'] ?? 'gemini' );
		$sanitized['ai_model']    = sanitize_text_field( $input['ai_model'] ?? 'gemini-1.5-flash' );
		$sanitized['ai_api_key']  = sanitize_text_field( trim( $input['ai_api_key'] ?? '' ) );
		$sanitized['ai_language'] = sanitize_key( $input['ai_language'] ?? 'fa' );

		$sanitized['seo_title_separator'] = sanitize_text_field( $input['seo_title_separator'] ?? ' - ' );
		$sanitized['seo_meta_desc_max_chars'] = intval( $input['seo_meta_desc_max_chars'] ?? 160 );
		$sanitized['perf_image_size_threshold'] = intval( $input['perf_image_size_threshold'] ?? 300 );
		$sanitized['ai_max_tokens'] = intval( $input['ai_max_tokens'] ?? 1200 );
		$sanitized['ai_temperature'] = floatval( $input['ai_temperature'] ?? 0.7 );

		return $sanitized;
	}
}
