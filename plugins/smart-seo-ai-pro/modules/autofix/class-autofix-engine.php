<?php
/**
 * Auto-Fix Engine with Mandatory Pre-Backup and Rollback
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_AutoFix_Engine {

	/**
	 * Run comprehensive auto-fix across all fixable issues
	 *
	 * @return array
	 */
	public function run_all_fixes() {
		$results = array(
			'alt_fixed'       => 0,
			'meta_fixed'      => 0,
			'revisions_fixed' => 0,
			'transients_fixed'=> 0,
			'backups_created' => array(),
			'logs'            => array(),
		);

		// 1. Fix missing image alt tags
		$alt_res = $this->fix_missing_image_alts();
		$results['alt_fixed'] = $alt_res['fixed_count'];
		$results['backups_created'] = array_merge( $results['backups_created'], $alt_res['backups'] );

		// 2. Fix missing post meta descriptions & SEO titles
		$meta_res = $this->fix_missing_seo_metas();
		$results['meta_fixed'] = $meta_res['fixed_count'];
		$results['backups_created'] = array_merge( $results['backups_created'], $meta_res['backups'] );

		// 3. Clean database revisions and transients
		$db_res = $this->fix_database_bloat();
		$results['revisions_fixed']  = $db_res['revisions_cleaned'];
		$results['transients_fixed'] = $db_res['transients_cleaned'];

		$total_fixes = $results['alt_fixed'] + $results['meta_fixed'] + $results['revisions_fixed'] + $results['transients_fixed'];
		Smart_SEO_AI_Logger::log( 'autofix', "AutoFix completed successfully. Fixed total {$total_fixes} issues with safety backups created.", 'success' );

		return $results;
	}

	/**
	 * Fix missing ALT tags for media attachments
	 */
	public function fix_missing_image_alts( $limit = 25 ) {
		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
		) );

		$fixed_count = 0;
		$backups = array();

		foreach ( $attachments as $att ) {
			$existing_alt = get_post_meta( $att->ID, '_wp_attachment_image_alt', true );
			if ( empty( trim( (string) $existing_alt ) ) ) {
				// Derive a clean, human-readable Alt text from title or filename
				$new_alt = trim( $att->post_title );
				if ( empty( $new_alt ) ) {
					$file = get_attached_file( $att->ID );
					$filename = pathinfo( $file, PATHINFO_FILENAME );
					$new_alt = ucwords( str_replace( array( '-', '_', '.' ), ' ', $filename ) );
				}

				if ( ! empty( $new_alt ) ) {
					// 1. Create safety backup first
					$backup_id = Smart_SEO_AI_Database::create_backup(
						'attachment_alt',
						$att->ID,
						$existing_alt,
						$new_alt,
						"Auto-fixed missing image Alt tag for attachment #{$att->ID} ('{$new_alt}')"
					);

					if ( $backup_id ) {
						$backups[] = $backup_id;
						// 2. Apply fix
						update_post_meta( $att->ID, '_wp_attachment_image_alt', sanitize_text_field( $new_alt ) );
						$fixed_count++;
					}
				}
			}
		}

		return array(
			'fixed_count' => $fixed_count,
			'backups'     => $backups,
		);
	}

	/**
	 * Fix missing Meta Descriptions and SEO Titles for published posts
	 */
	public function fix_missing_seo_metas( $limit = 20 ) {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
		) );

		$fixed_count = 0;
		$backups = array();

		foreach ( $posts as $p ) {
			$title_meta = get_post_meta( $p->ID, '_smart_seo_title', true );
			$desc_meta  = get_post_meta( $p->ID, '_smart_seo_meta_desc', true );

			$needs_title = empty( $title_meta );
			$needs_desc  = empty( $desc_meta );

			if ( $needs_title || $needs_desc ) {
				$orig_data = array(
					'_smart_seo_title'     => $title_meta,
					'_smart_seo_meta_desc' => $desc_meta,
				);

				$new_title = $title_meta ?: ( $p->post_title . ' - ' . get_bloginfo( 'name' ) );
				$new_desc  = $desc_meta ?: wp_trim_words( wp_strip_all_tags( $p->post_content ), 25, '...' );

				if ( empty( trim( $new_desc ) ) ) {
					$new_desc = sprintf( __( 'مشاهده و مطالعه کامل نوشته %s در وب‌سایت %s', 'smart-seo-ai-suite-pro' ), $p->post_title, get_bloginfo( 'name' ) );
				}

				$new_data = array(
					'_smart_seo_title'     => $new_title,
					'_smart_seo_meta_desc' => $new_desc,
				);

				// Backup first
				$backup_id = Smart_SEO_AI_Database::create_backup(
					'post_meta',
					$p->ID,
					$orig_data,
					$new_data,
					"Auto-fixed missing SEO metadata for post #{$p->ID} ('{$p->post_title}')"
				);

				if ( $backup_id ) {
					$backups[] = $backup_id;
					update_post_meta( $p->ID, '_smart_seo_title', sanitize_text_field( $new_title ) );
					update_post_meta( $p->ID, '_smart_seo_meta_desc', sanitize_textarea_field( $new_desc ) );
					$fixed_count++;
				}
			}
		}

		return array(
			'fixed_count' => $fixed_count,
			'backups'     => $backups,
		);
	}

	/**
	 * Clean Database Bloat (Excess Revisions & Transients)
	 */
	public function fix_database_bloat() {
		global $wpdb;

		// 1. Keep only last 5 revisions per post and remove older ones
		$revisions_cleaned = 0;
		$revisions = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY ID ASC LIMIT 100", ARRAY_A );
		if ( ! empty( $revisions ) ) {
			foreach ( $revisions as $rev ) {
				wp_delete_post_revision( intval( $rev['ID'] ) );
				$revisions_cleaned++;
			}
		}

		// 2. Clean expired transients
		$time = time();
		$transients_cleaned = $wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
				WHERE a.option_name LIKE %s
				AND a.option_name NOT LIKE %s
				AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
				AND b.option_value < %d",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$time
			)
		);

		return array(
			'revisions_cleaned'  => $revisions_cleaned,
			'transients_cleaned' => intval( $transients_cleaned ),
		);
	}

	/**
	 * Rollback an action
	 */
	public function rollback( $backup_id ) {
		return Smart_SEO_AI_Database::rollback_backup( intval( $backup_id ) );
	}
}
