<?php
/**
 * Database Management Class
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Database {

	/**
	 * Log table name
	 */
	public static function get_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'smart_seo_ai_logs';
	}

	/**
	 * Backup table name
	 */
	public static function get_backups_table() {
		global $wpdb;
		return $wpdb->prefix . 'smart_seo_ai_backups';
	}

	/**
	 * Scans table name
	 */
	public static function get_scans_table() {
		global $wpdb;
		return $wpdb->prefix . 'smart_seo_ai_scans';
	}

	/**
	 * Record a scan run
	 *
	 * @param array $data Scan summary and details
	 * @return int|false Insert ID
	 */
	public static function record_scan( $data ) {
		global $wpdb;
		$table = self::get_scans_table();

		$insert = array(
			'scan_type'         => sanitize_text_field( $data['scan_type'] ?? 'full' ),
			'overall_score'     => intval( $data['overall_score'] ?? 0 ),
			'seo_score'         => intval( $data['seo_score'] ?? 0 ),
			'security_score'    => intval( $data['security_score'] ?? 0 ),
			'performance_score' => intval( $data['performance_score'] ?? 0 ),
			'woo_score'         => intval( $data['woo_score'] ?? 0 ),
			'details_json'      => wp_json_encode( $data['details'] ?? array() ),
			'created_at'        => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert, array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );

		if ( $result ) {
			update_option( 'smart_seo_ai_last_scan', current_time( 'mysql' ) );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Create a backup before an automated or manual fix
	 *
	 * @param string $item_type e.g., 'post_meta', 'attachment_meta', 'option', 'post_content'
	 * @param int|string $item_id Post ID or identifier
	 * @param mixed $original_data Original state
	 * @param mixed $modified_data Target state
	 * @param string $action_taken Action description
	 * @return int|false Backup ID
	 */
	public static function create_backup( $item_type, $item_id, $original_data, $modified_data, $action_taken ) {
		global $wpdb;
		$table = self::get_backups_table();

		$insert = array(
			'item_type'     => sanitize_text_field( $item_type ),
			'item_id'       => sanitize_text_field( (string) $item_id ),
			'original_data' => maybe_serialize( $original_data ),
			'modified_data' => maybe_serialize( $modified_data ),
			'action_taken'  => sanitize_text_field( $action_taken ),
			'status'        => 'active',
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $insert, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Rollback a specific backup entry
	 *
	 * @param int $backup_id
	 * @return bool
	 */
	public static function rollback_backup( $backup_id ) {
		global $wpdb;
		$table = self::get_backups_table();

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $backup_id ) );
		if ( ! $row || $row->status === 'rolled_back' ) {
			return false;
		}

		$original_data = maybe_unserialize( $row->original_data );
		$success = false;

		switch ( $row->item_type ) {
			case 'post_meta':
				if ( is_array( $original_data ) ) {
					foreach ( $original_data as $meta_key => $meta_val ) {
						update_post_meta( intval( $row->item_id ), $meta_key, $meta_val );
					}
					$success = true;
				}
				break;

			case 'attachment_alt':
				update_post_meta( intval( $row->item_id ), '_wp_attachment_image_alt', sanitize_text_field( $original_data ) );
				$success = true;
				break;

			case 'post_content':
				$post_arr = array(
					'ID'           => intval( $row->item_id ),
					'post_content' => $original_data,
				);
				wp_update_post( $post_arr );
				$success = true;
				break;

			case 'option':
				update_option( $row->item_id, $original_data );
				$success = true;
				break;
		}

		if ( $success ) {
			$wpdb->update(
				$table,
				array( 'status' => 'rolled_back' ),
				array( 'id' => $backup_id ),
				array( '%s' ),
				array( '%d' )
			);

			Smart_SEO_AI_Logger::log( 'autofix', "Rolled back backup #{$backup_id} for {$row->item_type} ({$row->item_id})", 'info' );
		}

		return $success;
	}

	/**
	 * Get recent scan history
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_scans( $limit = 10 ) {
		global $wpdb;
		$table = self::get_scans_table();
		$limit = intval( $limit );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A );
	}

	/**
	 * Get recent backups
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_backups( $limit = 20 ) {
		global $wpdb;
		$table = self::get_backups_table();
		$limit = intval( $limit );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A );
	}
}
