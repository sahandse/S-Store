<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Check if user chose to preserve data on uninstall
$settings = get_option( 'smart_seo_ai_settings', array() );
$preserve_data = ! empty( $settings['preserve_data_on_uninstall'] );

if ( ! $preserve_data ) {
	// 1. Delete custom database tables
	$tables = array(
		$wpdb->prefix . 'smart_seo_ai_logs',
		$wpdb->prefix . 'smart_seo_ai_backups',
		$wpdb->prefix . 'smart_seo_ai_scans',
		$wpdb->prefix . 'smart_seo_ai_keywords',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// 2. Delete plugin options
	delete_option( 'smart_seo_ai_settings' );
	delete_option( 'smart_seo_ai_db_version' );
	delete_option( 'smart_seo_ai_last_scan' );
	delete_option( 'smart_seo_ai_stats' );
	delete_option( 'smart_seo_ai_license' );

	// 3. Delete transients
	delete_transient( 'smart_seo_ai_dashboard_cache' );
	delete_transient( 'smart_seo_ai_security_status' );
	delete_transient( 'smart_seo_ai_perf_status' );

	// 4. Clear scheduled crons
	wp_clear_scheduled_hook( 'smart_seo_ai_daily_scan_cron' );
	wp_clear_scheduled_hook( 'smart_seo_ai_cleanup_logs_cron' );
}
