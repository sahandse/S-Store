<?php
/**
 * Plugin Installer and Upgrade Routines
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Installer {

	/**
	 * Activation callback
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::register_capabilities();
		self::schedule_crons();

		// Record initial log
		Smart_SEO_AI_Logger::log( 'system', 'Smart SEO AI Suite Pro activated successfully.', 'success' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'smart_seo_ai_daily_scan_cron' );
		wp_clear_scheduled_hook( 'smart_seo_ai_cleanup_logs_cron' );
		flush_rewrite_rules();
	}

	/**
	 * Create custom tables using dbDelta
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// 1. Logs Table
		$table_logs = $wpdb->prefix . 'smart_seo_ai_logs';
		$sql_logs = "CREATE TABLE {$table_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL DEFAULT 'general',
			message text NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			post_id bigint(20) unsigned DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_logs );

		// 2. Backups Table for AutoFix rollback
		$table_backups = $wpdb->prefix . 'smart_seo_ai_backups';
		$sql_backups = "CREATE TABLE {$table_backups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(50) NOT NULL,
			item_id varchar(100) NOT NULL,
			original_data longtext NOT NULL,
			modified_data longtext NOT NULL,
			action_taken text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY item_type (item_type),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_backups );

		// 3. Scans History Table
		$table_scans = $wpdb->prefix . 'smart_seo_ai_scans';
		$sql_scans = "CREATE TABLE {$table_scans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_type varchar(50) NOT NULL DEFAULT 'full',
			overall_score int(3) NOT NULL DEFAULT 0,
			seo_score int(3) NOT NULL DEFAULT 0,
			security_score int(3) NOT NULL DEFAULT 0,
			performance_score int(3) NOT NULL DEFAULT 0,
			woo_score int(3) NOT NULL DEFAULT 0,
			details_json longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_type (scan_type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_scans );

		update_option( 'smart_seo_ai_db_version', SMART_SEO_AI_DB_VERSION );
	}

	/**
	 * Populate initial default settings
	 */
	public static function set_default_options() {
		$defaults = array(
			// General
			'enable_seo_engine'         => 1,
			'enable_ai_engine'          => 1,
			'enable_woocommerce_engine' => 1,
			'enable_security_engine'    => 1,
			'enable_performance_engine' => 1,
			'enable_autofix_engine'     => 1,
			'enable_reports_engine'     => 1,
			'preserve_data_on_uninstall'=> 0,

			// AI Settings
			'ai_provider'               => 'gemini', // gemini, openai, claude
			'ai_model'                  => 'gemini-1.5-flash',
			'ai_api_key'                => '',
			'ai_temperature'            => 0.7,
			'ai_max_tokens'             => 1200,
			'ai_language'               => 'fa', // fa or en

			// SEO Settings
			'seo_title_separator'       => ' - ',
			'seo_meta_desc_max_chars'   => 160,
			'seo_auto_generate_missing' => 1,
			'seo_add_opengraph'         => 1,
			'seo_add_twitter_cards'     => 1,
			'seo_canonical_urls'        => 1,

			// Security
			'security_check_eval'       => 1,
			'security_check_admin_user' => 1,
			'security_check_debug_mode' => 1,
			'security_check_ssl'        => 1,
			'security_block_xmlrpc'     => 0,

			// Performance
			'perf_check_memory'         => 1,
			'perf_check_queries'        => 1,
			'perf_check_heavy_images'   => 1,
			'perf_image_size_threshold' => 300, // KB

			// AutoFix
			'autofix_backup_always'     => 1,
			'autofix_missing_alt'       => 1,
			'autofix_missing_meta'      => 1,
			'autofix_clean_revisions'   => 1,
		);

		if ( ! get_option( 'smart_seo_ai_settings' ) ) {
			add_option( 'smart_seo_ai_settings', $defaults );
		}
	}

	/**
	 * Set custom capabilities for Roles
	 */
	public static function register_capabilities() {
		// Admin
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_smart_seo_ai' );
			$admin->add_cap( 'manage_smart_seo_ai_shop' );
			$admin->add_cap( 'manage_smart_seo_ai_editor' );
		}

		// Shop Manager (WooCommerce)
		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager ) {
			$shop_manager->add_cap( 'manage_smart_seo_ai_shop' );
		}

		// Editor
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( 'manage_smart_seo_ai_editor' );
		}
	}

	/**
	 * Setup scheduled WP-Crons
	 */
	public static function schedule_crons() {
		if ( ! wp_next_scheduled( 'smart_seo_ai_daily_scan_cron' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'smart_seo_ai_daily_scan_cron' );
		}
	}
}
