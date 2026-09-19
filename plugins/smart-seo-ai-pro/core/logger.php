<?php
/**
 * Logger Class for Audit Trail
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Logger {

	/**
	 * Log an event
	 *
	 * @param string $event_type 'seo', 'ai', 'security', 'performance', 'autofix', 'system'
	 * @param string $message Log message
	 * @param string $severity 'info', 'warning', 'error', 'success'
	 * @param int $post_id Related Post ID
	 * @return int|false
	 */
	public static function log( $event_type, $message, $severity = 'info', $post_id = 0 ) {
		global $wpdb;
		$table = Smart_SEO_AI_Database::get_logs_table();

		$current_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		$insert = array(
			'event_type' => sanitize_key( $event_type ),
			'message'    => sanitize_textarea_field( $message ),
			'severity'   => sanitize_key( $severity ),
			'post_id'    => intval( $post_id ),
			'user_id'    => intval( $current_user_id ),
			'created_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert, array( '%s', '%s', '%s', '%d', '%d', '%s' ) );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve recent log entries
	 *
	 * @param int $limit
	 * @param string $event_type Filter by type
	 * @return array
	 */
	public static function get_logs( $limit = 50, $event_type = '' ) {
		global $wpdb;
		$table = Smart_SEO_AI_Database::get_logs_table();
		$limit = intval( $limit );

		if ( ! empty( $event_type ) ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE event_type = %s ORDER BY id DESC LIMIT %d", $event_type, $limit ),
				ARRAY_A
			);
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A );
	}

	/**
	 * Clear all logs
	 */
	public static function clear_logs() {
		global $wpdb;
		$table = Smart_SEO_AI_Database::get_logs_table();
		return $wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
