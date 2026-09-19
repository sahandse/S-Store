<?php
/**
 * Permissions & RBAC Management
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Permissions {

	/**
	 * Check if current user has administrator / full access
	 *
	 * @return bool
	 */
	public static function can_manage_all() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_smart_seo_ai' );
	}

	/**
	 * Check if user can access WooCommerce SEO & Store Reports
	 *
	 * @return bool
	 */
	public static function can_manage_shop() {
		if ( self::can_manage_all() ) {
			return true;
		}
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_smart_seo_ai_shop' );
	}

	/**
	 * Check if user can access Content SEO & AI helper
	 *
	 * @return bool
	 */
	public static function can_edit_seo() {
		if ( self::can_manage_all() || self::can_manage_shop() ) {
			return true;
		}
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_smart_seo_ai_editor' );
	}

	/**
	 * Check if user can execute Auto-Fix actions
	 *
	 * @return bool
	 */
	public static function can_run_autofix() {
		return self::can_manage_all();
	}

	/**
	 * Check if user can view security reports
	 *
	 * @return bool
	 */
	public static function can_view_security() {
		return self::can_manage_all();
	}

	/**
	 * Verify Nonce and capability helper for AJAX endpoints
	 *
	 * @param string $action Nonce action name
	 * @param string $cap Required capability check
	 * @return void Dies with JSON error if invalid
	 */
	public static function verify_ajax_request( $action = 'smart_seo_ai_nonce_action', $cap = 'manage_all' ) {
		if ( ! check_ajax_referer( $action, 'security', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed (Invalid Nonce).', 'smart-seo-ai-suite-pro' ) ), 403 );
		}

		$allowed = false;
		switch ( $cap ) {
			case 'manage_all':
				$allowed = self::can_manage_all();
				break;
			case 'shop':
				$allowed = self::can_manage_shop();
				break;
			case 'editor':
				$allowed = self::can_edit_seo();
				break;
			default:
				$allowed = current_user_can( $cap );
				break;
		}

		if ( ! $allowed ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied for this action.', 'smart-seo-ai-suite-pro' ) ), 403 );
		}
	}
}
