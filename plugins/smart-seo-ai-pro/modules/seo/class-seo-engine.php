<?php
/**
 * SEO Analysis & Metadata Engine
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_SEO_Engine {

	public function __construct() {
		// Register Meta Boxes on edit screens
		add_action( 'add_meta_boxes', array( $this, 'register_seo_metabox' ) );
		add_action( 'save_post', array( $this, 'save_seo_metabox' ) );
	}

	/**
	 * Register SEO Metabox on Posts, Pages, and Custom Post Types (including products)
	 */
	public function register_seo_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'smart_seo_ai_metabox',
				__( '⚡ Smart SEO AI Suite Pro - تحلیل سئو و هوش مصنوعی', 'smart-seo-ai-suite-pro' ),
				array( $this, 'render_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render Metabox HTML
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'smart_seo_ai_save_meta', 'smart_seo_ai_meta_nonce' );

		$seo_title       = get_post_meta( $post->ID, '_smart_seo_title', true );
		$meta_desc       = get_post_meta( $post->ID, '_smart_seo_meta_desc', true );
		$focus_keyword   = get_post_meta( $post->ID, '_smart_seo_focus_keyword', true );
		$canonical_url   = get_post_meta( $post->ID, '_smart_seo_canonical', true );
		$noindex         = get_post_meta( $post->ID, '_smart_seo_noindex', true );

		// Perform real-time analysis of the current content
		$analysis = $this->analyze_single_post( $post->ID, $post->post_title, $post->post_content, $focus_keyword, $seo_title, $meta_desc );
		?>
		<div class="smart-seo-metabox-wrapper" id="smart-seo-metabox-app" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<!-- Header Score Bar -->
			<div class="smart-seo-meta-header">
				<div class="smart-score-badge <?php echo esc_attr( $this->get_score_badge_class( $analysis['score'] ) ); ?>">
					<span class="score-num"><?php echo esc_html( $analysis['score'] ); ?></span>
					<span class="score-total">/100</span>
					<span class="score-label"><?php echo esc_html( $this->get_score_label( $analysis['score'] ) ); ?></span>
				</div>
				<div class="smart-seo-actions-top">
					<button type="button" class="button button-primary smart-btn-ai-auto" id="smart-btn-ai-fill-meta">
						<span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'تولید خودکار متا با هوش مصنوعی', 'smart-seo-ai-suite-pro' ); ?>
					</button>
				</div>
			</div>

			<!-- Meta Fields Form -->
			<div class="smart-seo-fields-grid">
				<div class="smart-form-group">
					<label for="smart_seo_focus_keyword">
						<strong><?php esc_html_e( 'کلمه کلیدی کانونی (Focus Keyword):', 'smart-seo-ai-suite-pro' ); ?></strong>
					</label>
					<input type="text" name="smart_seo_focus_keyword" id="smart_seo_focus_keyword" class="widefat" value="<?php echo esc_attr( $focus_keyword ); ?>" placeholder="<?php esc_attr_e( 'مثال: افزونه سئو وردپرس', 'smart-seo-ai-suite-pro' ); ?>">
					<p class="description"><?php esc_html_e( 'کلمه کلیدی اصلی که می‌خواهید این صفحه برای آن رتبه بگیرد.', 'smart-seo-ai-suite-pro' ); ?></p>
				</div>

				<div class="smart-form-group">
					<div class="smart-label-row">
						<label for="smart_seo_title"><strong><?php esc_html_e( 'عنوان سئو (SEO Title):', 'smart-seo-ai-suite-pro' ); ?></strong></label>
						<span class="smart-char-counter" id="smart_seo_title_count"><?php echo mb_strlen( $seo_title ?: $post->post_title ); ?> / 60</span>
					</div>
					<div class="smart-input-with-btn">
						<input type="text" name="smart_seo_title" id="smart_seo_title" class="widefat" value="<?php echo esc_attr( $seo_title ); ?>" placeholder="<?php echo esc_attr( $post->post_title . ' - ' . get_bloginfo( 'name' ) ); ?>">
						<button type="button" class="button smart-btn-ai-mini" data-action="generate_title" title="<?php esc_attr_e( 'تولید عنوان با هوش مصنوعی', 'smart-seo-ai-suite-pro' ); ?>">✨ AI</button>
					</div>
				</div>

				<div class="smart-form-group">
					<div class="smart-label-row">
						<label for="smart_seo_meta_desc"><strong><?php esc_html_e( 'توضیحات متا (Meta Description):', 'smart-seo-ai-suite-pro' ); ?></strong></label>
						<span class="smart-char-counter" id="smart_seo_meta_desc_count"><?php echo mb_strlen( $meta_desc ); ?> / 160</span>
					</div>
					<div class="smart-input-with-btn">
						<textarea name="smart_seo_meta_desc" id="smart_seo_meta_desc" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'خلاصه جذاب محتوا برای نمایش در نتایج گوگل (150 تا 160 کاراکتر)...', 'smart-seo-ai-suite-pro' ); ?>"><?php echo esc_textarea( $meta_desc ); ?></textarea>
						<button type="button" class="button smart-btn-ai-mini" data-action="generate_meta_desc" title="<?php esc_attr_e( 'تولید توضیحات با هوش مصنوعی', 'smart-seo-ai-suite-pro' ); ?>">✨ AI</button>
					</div>
				</div>

				<!-- Google Search Snippet Preview -->
				<div class="smart-google-preview-box">
					<div class="preview-title"><?php esc_html_e( 'پیش‌نمایش در گوگل (Google SERP Preview):', 'smart-seo-ai-suite-pro' ); ?></div>
					<div class="serp-card">
						<div class="serp-url"><?php echo esc_html( get_permalink( $post->ID ) ?: get_site_url() . '/sample-url' ); ?></div>
						<div class="serp-title" id="serp-title-preview"><?php echo esc_html( $seo_title ?: ( $post->post_title . ' - ' . get_bloginfo( 'name' ) ) ); ?></div>
						<div class="serp-desc" id="serp-desc-preview"><?php echo esc_html( $meta_desc ?: 'توضیحات متا هنوز مشخص نشده است. گوگل به صورت پیش‌فرض بخشی از متن نوشته را نمایش خواهد داد.' ); ?></div>
					</div>
				</div>

				<!-- Live Audit Checkpoints Checklist -->
				<div class="smart-audit-checklist">
					<h4><?php esc_html_e( 'چک‌لیست بررسی فنی و محتوایی سئو:', 'smart-seo-ai-suite-pro' ); ?></h4>
					<ul class="smart-audit-items">
						<?php foreach ( $analysis['checks'] as $check ) : ?>
							<li class="smart-check-item status-<?php echo esc_attr( $check['status'] ); ?>">
								<span class="dashicons <?php echo $check['status'] === 'pass' ? 'dashicons-yes-alt' : ( $check['status'] === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss' ); ?>"></span>
								<div class="check-text">
									<strong><?php echo esc_html( $check['title'] ); ?>:</strong>
									<span><?php echo esc_html( $check['message'] ); ?></span>
									<?php if ( ! empty( $check['suggestion'] ) ) : ?>
										<small class="check-suggestion">💡 <?php echo esc_html( $check['suggestion'] ); ?></small>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Metabox Data
	 */
	public function save_seo_metabox( $post_id ) {
		if ( ! isset( $_POST['smart_seo_ai_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smart_seo_ai_meta_nonce'] ) ), 'smart_seo_ai_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['smart_seo_title'] ) ) {
			update_post_meta( $post_id, '_smart_seo_title', sanitize_text_field( wp_unslash( $_POST['smart_seo_title'] ) ) );
		}

		if ( isset( $_POST['smart_seo_meta_desc'] ) ) {
			update_post_meta( $post_id, '_smart_seo_meta_desc', sanitize_textarea_field( wp_unslash( $_POST['smart_seo_meta_desc'] ) ) );
		}

		if ( isset( $_POST['smart_seo_focus_keyword'] ) ) {
			update_post_meta( $post_id, '_smart_seo_focus_keyword', sanitize_text_field( wp_unslash( $_POST['smart_seo_focus_keyword'] ) ) );
		}

		if ( isset( $_POST['smart_seo_canonical'] ) ) {
			update_post_meta( $post_id, '_smart_seo_canonical', esc_url_raw( wp_unslash( $_POST['smart_seo_canonical'] ) ) );
		}
	}

	/**
	 * Detailed single post SEO analyzer algorithm
	 */
	public function analyze_single_post( $post_id, $title, $content, $focus_keyword = '', $seo_title = '', $meta_desc = '' ) {
		$score = 100;
		$checks = array();
		$title = trim( $title );
		$content_clean = wp_strip_all_tags( $content );
		$word_count = mb_strlen( $content_clean ) > 0 ? count( preg_split( '/\s+/u', trim( $content_clean ) ) ) : 0;
		$focus_keyword = trim( (string) $focus_keyword );

		$final_title = ! empty( $seo_title ) ? $seo_title : $title;
		$final_desc  = ! empty( $meta_desc ) ? $meta_desc : '';

		// 1. Title Length
		$title_len = mb_strlen( $final_title );
		if ( empty( $title_len ) ) {
			$score -= 20;
			$checks[] = array(
				'title'      => __( 'عنوان سئو', 'smart-seo-ai-suite-pro' ),
				'status'     => 'fail',
				'message'    => __( 'عنوان برای صفحه ثبت نشده است.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'یک عنوان بین 40 تا 60 کاراکتر وارد کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $title_len < 30 ) {
			$score -= 10;
			$checks[] = array(
				'title'      => __( 'طول عنوان سئو', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => sprintf( __( 'عنوان کوتاه است (%d کاراکتر).', 'smart-seo-ai-suite-pro' ), $title_len ),
				'suggestion' => __( 'طول ایده‌آل عنوان سئو بین 40 تا 60 کاراکتر است.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $title_len > 65 ) {
			$score -= 8;
			$checks[] = array(
				'title'      => __( 'طول عنوان سئو', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => sprintf( __( 'عنوان طولانی است (%d کاراکتر) و ممکن است در گوگل بریده شود.', 'smart-seo-ai-suite-pro' ), $title_len ),
				'suggestion' => __( 'عنوان را کمتر از 60 کاراکتر نگه دارید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'      => __( 'طول عنوان سئو', 'smart-seo-ai-suite-pro' ),
				'status'     => 'pass',
				'message'    => sprintf( __( 'طول عنوان ایده‌آل است (%d کاراکتر).', 'smart-seo-ai-suite-pro' ), $title_len ),
			);
		}

		// 2. Meta Description Check
		$desc_len = mb_strlen( $final_desc );
		if ( empty( $desc_len ) ) {
			$score -= 15;
			$checks[] = array(
				'title'      => __( 'توضیحات متا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'fail',
				'message'    => __( 'توضیحات متا خالی است.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'یک توضیحات متای جذاب بین 120 تا 160 کاراکتر بنویسید یا از هوش مصنوعی کمک بگیرید.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $desc_len < 100 ) {
			$score -= 6;
			$checks[] = array(
				'title'      => __( 'طول توضیحات متا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => sprintf( __( 'توضیحات متا کوتاه است (%d کاراکتر).', 'smart-seo-ai-suite-pro' ), $desc_len ),
				'suggestion' => __( 'توضیحات را به 120 تا 160 کاراکتر افزایش دهید.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $desc_len > 165 ) {
			$score -= 5;
			$checks[] = array(
				'title'      => __( 'طول توضیحات متا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => sprintf( __( 'توضیحات متا بیش از حد مجاز است (%d کاراکتر).', 'smart-seo-ai-suite-pro' ), $desc_len ),
				'suggestion' => __( 'توضیحات را در حداکثر 160 کاراکتر خلاصه کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'      => __( 'توضیحات متا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'pass',
				'message'    => sprintf( __( 'توضیحات متا با طول ایده‌آل (%d کاراکتر) ثبت شده است.', 'smart-seo-ai-suite-pro' ), $desc_len ),
			);
		}

		// 3. Content Length
		if ( $word_count < 300 ) {
			$score -= 15;
			$checks[] = array(
				'title'      => __( 'حجم محتوا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'fail',
				'message'    => sprintf( __( 'محتوا بسیار کوتاه است (%d کلمه). محتوای لاغر (Thin Content) به سئو آسیب می‌زند.', 'smart-seo-ai-suite-pro' ), $word_count ),
				'suggestion' => __( 'حداقل 600 تا 1200 کلمه محتوای جامع تولید کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $word_count < 600 ) {
			$score -= 5;
			$checks[] = array(
				'title'      => __( 'حجم محتوا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => sprintf( __( 'حجم محتوا متوسط است (%d کلمه).', 'smart-seo-ai-suite-pro' ), $word_count ),
				'suggestion' => __( 'برای رتبه‌گیری بهتر در کلمات رقابتی، محتوا را به بالای 800 کلمه برسانید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'      => __( 'حجم محتوا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'pass',
				'message'    => sprintf( __( 'حجم محتوای عالی (%d کلمه).', 'smart-seo-ai-suite-pro' ), $word_count ),
			);
		}

		// 4. Focus Keyword analysis
		if ( empty( $focus_keyword ) ) {
			$score -= 15;
			$checks[] = array(
				'title'      => __( 'کلمه کلیدی کانونی', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => __( 'کلمه کلیدی کانونی مشخص نشده است.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'یک کلمه کلیدی هدف تعریف کنید تا انطباق دقیق محتوا با آن بررسی شود.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			// In title?
			if ( mb_stripos( $final_title, $focus_keyword ) !== false ) {
				$checks[] = array(
					'title'   => __( 'کلمه کلیدی در عنوان', 'smart-seo-ai-suite-pro' ),
					'status'  => 'pass',
					'message' => __( 'کلمه کلیدی کانونی در عنوان سئو وجود دارد.', 'smart-seo-ai-suite-pro' ),
				);
			} else {
				$score -= 8;
				$checks[] = array(
					'title'      => __( 'کلمه کلیدی در عنوان', 'smart-seo-ai-suite-pro' ),
					'status'     => 'fail',
					'message'    => __( 'کلمه کلیدی کانونی در عنوان سئو یافت نشد.', 'smart-seo-ai-suite-pro' ),
					'suggestion' => __( 'کلمه کلیدی را در ابتدای عنوان سئو قرار دهید.', 'smart-seo-ai-suite-pro' ),
				);
			}

			// In meta desc?
			if ( ! empty( $final_desc ) && mb_stripos( $final_desc, $focus_keyword ) !== false ) {
				$checks[] = array(
					'title'   => __( 'کلمه کلیدی در توضیحات متا', 'smart-seo-ai-suite-pro' ),
					'status'  => 'pass',
					'message' => __( 'کلمه کلیدی در توضیحات متا به کار رفته است.', 'smart-seo-ai-suite-pro' ),
				);
			} else {
				$score -= 5;
				$checks[] = array(
					'title'      => __( 'کلمه کلیدی در توضیحات متا', 'smart-seo-ai-suite-pro' ),
					'status'     => 'warning',
					'message'    => __( 'کلمه کلیدی کانونی در توضیحات متا وجود ندارد.', 'smart-seo-ai-suite-pro' ),
					'suggestion' => __( 'کلمه کلیدی را به شکل طبیعی در توضیحات متا بگنجانید.', 'smart-seo-ai-suite-pro' ),
				);
			}

			// Density in content
			$keyword_count = mb_substr_count( mb_strtolower( $content_clean ), mb_strtolower( $focus_keyword ) );
			$density = $word_count > 0 ? round( ( $keyword_count / $word_count ) * 100, 2 ) : 0;
			if ( $keyword_count === 0 ) {
				$score -= 8;
				$checks[] = array(
					'title'      => __( 'تکرار کلمه کلیدی در محتوا', 'smart-seo-ai-suite-pro' ),
					'status'     => 'fail',
					'message'    => __( 'کلمه کلیدی هیچ بار در متن محتوا تکرار نشده است!', 'smart-seo-ai-suite-pro' ),
					'suggestion' => __( 'کلمه کلیدی را در پاراگراف اول و در بخش‌های مختلف متن تکرار کنید.', 'smart-seo-ai-suite-pro' ),
				);
			} elseif ( $density > 3.5 ) {
				$score -= 6;
				$checks[] = array(
					'title'      => __( 'چگالی کلمه کلیدی', 'smart-seo-ai-suite-pro' ),
					'status'     => 'warning',
					'message'    => sprintf( __( 'چگالی کلمه کلیدی بالا است (%s%% - خطر Keyword Stuffing).', 'smart-seo-ai-suite-pro' ), $density ),
					'suggestion' => __( 'چگالی کلمه کلیدی را بین 1%% تا 2.5%% نگه دارید.', 'smart-seo-ai-suite-pro' ),
				);
			} else {
				$checks[] = array(
					'title'   => __( 'چگالی کلمه کلیدی', 'smart-seo-ai-suite-pro' ),
					'status'  => 'pass',
					'message' => sprintf( __( 'چگالی کلمه کلیدی متناسب است (%d بار تکرار، %s%%).', 'smart-seo-ai-suite-pro' ), $keyword_count, $density ),
				);
			}
		}

		// 5. Headings Analysis (H1, H2, H3)
		preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/si', $content, $h1_matches );
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/si', $content, $h2_matches );
		preg_match_all( '/<h3[^>]*>(.*?)<\/h3>/si', $content, $h3_matches );

		$h1_count = count( $h1_matches[0] );
		$h2_count = count( $h2_matches[0] );
		$h3_count = count( $h3_matches[0] );

		if ( $h1_count > 0 ) {
			// In WordPress theme template, the post title is usually the only H1
			$checks[] = array(
				'title'      => __( 'تگ H1 در محتوا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => __( 'یک تگ H1 داخل محتوا یافت شد. عنوان اصلی معمولا توسط قالب H1 می‌شود.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'پیشنهاد می‌شود داخل بدنه نوشته به جای H1 از تگ‌های H2 و H3 استفاده کنید.', 'smart-seo-ai-suite-pro' ),
			);
		}

		if ( $h2_count === 0 ) {
			$score -= 6;
			$checks[] = array(
				'title'      => __( 'ساختار سرفصل‌ها (H2)', 'smart-seo-ai-suite-pro' ),
				'status'     => 'fail',
				'message'    => __( 'هیچ تگ H2 برای بخش‌بندی محتوا یافت نشد.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'محتوا را با حداقل 2 تا 4 سرفصل H2 بخش‌بندی و ساختاردهی کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'   => __( 'ساختار سرفصل‌ها (H2/H3)', 'smart-seo-ai-suite-pro' ),
				'status'  => 'pass',
				'message' => sprintf( __( 'ساختاربندی با %d عنوان H2 و %d عنوان H3 انجام شده است.', 'smart-seo-ai-suite-pro' ), $h2_count, $h3_count ),
			);
		}

		// 6. Links Analysis (Internal & External)
		$site_url = get_site_url();
		preg_match_all( '/<a\s+[^>]*href=([\'"])(.*?)\1[^>]*>(.*?)<\/a>/si', $content, $links );
		$internal_links = 0;
		$external_links = 0;

		if ( ! empty( $links[2] ) ) {
			foreach ( $links[2] as $href ) {
				if ( strpos( $href, $site_url ) !== false || strpos( $href, '/' ) === 0 || strpos( $href, '#' ) === 0 ) {
					$internal_links++;
				} else {
					$external_links++;
				}
			}
		}

		if ( $internal_links === 0 ) {
			$score -= 5;
			$checks[] = array(
				'title'      => __( 'لینک‌های داخلی', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => __( 'هیچ لینک داخلی به سایر صفحات سایت یافت نشد.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'به 2 تا 5 نوشته یا محصول مرتبط در سایت خود لینک دهید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'   => __( 'لینک‌های داخلی', 'smart-seo-ai-suite-pro' ),
				'status'  => 'pass',
				'message' => sprintf( __( '%d لینک داخلی در محتوا وجود دارد.', 'smart-seo-ai-suite-pro' ), $internal_links ),
			);
		}

		if ( $external_links === 0 ) {
			$checks[] = array(
				'title'      => __( 'لینک‌های خارجی', 'smart-seo-ai-suite-pro' ),
				'status'     => 'info',
				'message'    => __( 'لینک خارجی معتبر به منابع مرجع یافت نشد.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'لینک دادن به 1 یا 2 منبع معتبر باعث افزایش اعتبار محتوا از دید گوگل می‌شود.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'   => __( 'لینک‌های خارجی', 'smart-seo-ai-suite-pro' ),
				'status'  => 'pass',
				'message' => sprintf( __( '%d لینک به منابع خارجی وجود دارد.', 'smart-seo-ai-suite-pro' ), $external_links ),
			);
		}

		// 7. Images & Alt Tags Check
		preg_match_all( '/<img\s+[^>]*src=([\'"])(.*?)\1[^>]*>/si', $content, $img_tags );
		$img_count = count( $img_tags[0] );
		$missing_alt_count = 0;

		if ( $img_count > 0 ) {
			foreach ( $img_tags[0] as $tag ) {
				if ( ! preg_match( '/alt=([\'"])(.*?)\1/si', $tag, $alt_match ) || empty( trim( $alt_match[2] ) ) ) {
					$missing_alt_count++;
				}
			}
		}

		$has_featured_img = has_post_thumbnail( $post_id );
		if ( ! $has_featured_img ) {
			$score -= 6;
			$checks[] = array(
				'title'      => __( 'تصویر شاخص (Featured Image)', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => __( 'برای این نوشته تصویر شاخص تنظیم نشده است.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'یک تصویر شاخص با ابعاد استاندارد (1200x630) اضافه کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'   => __( 'تصویر شاخص', 'smart-seo-ai-suite-pro' ),
				'status'  => 'pass',
				'message' => __( 'تصویر شاخص با موفقیت ثبت شده است.', 'smart-seo-ai-suite-pro' ),
			);
		}

		if ( $img_count === 0 && ! $has_featured_img ) {
			$score -= 5;
			$checks[] = array(
				'title'      => __( 'تصاویر در محتوا', 'smart-seo-ai-suite-pro' ),
				'status'     => 'warning',
				'message'    => __( 'هیچ تصویری در محتوا وجود ندارد.', 'smart-seo-ai-suite-pro' ),
				'suggestion' => __( 'تصاویر و اینفوگرافیک‌ها باعث افزایش زمان ماندگاری کاربر در سایت می‌شوند.', 'smart-seo-ai-suite-pro' ),
			);
		} elseif ( $missing_alt_count > 0 ) {
			$score -= ( $missing_alt_count * 3 );
			$checks[] = array(
				'title'      => __( 'متن جایگزین تصاویر (Alt Tag)', 'smart-seo-ai-suite-pro' ),
				'status'     => 'fail',
				'message'    => sprintf( __( '%d تصویر در محتوا فاقد متن Alt هستند.', 'smart-seo-ai-suite-pro' ), $missing_alt_count ),
				'suggestion' => __( 'از بخش AutoFix افزونه برای اصلاح خودکار تگ‌های Alt استفاده کنید.', 'smart-seo-ai-suite-pro' ),
			);
		} else {
			$checks[] = array(
				'title'   => __( 'متن جایگزین تصاویر (Alt)', 'smart-seo-ai-suite-pro' ),
				'status'  => 'pass',
				'message' => sprintf( __( 'تمام %d تصویر موجود در نوشته دارای متن Alt معتبر هستند.', 'smart-seo-ai-suite-pro' ), $img_count ),
			);
		}

		// Ensure score is bounded between 0 and 100
		$score = max( 0, min( 100, $score ) );

		return array(
			'post_id'        => $post_id,
			'score'          => $score,
			'checks'         => $checks,
			'word_count'     => $word_count,
			'h2_count'       => $h2_count,
			'h3_count'       => $h3_count,
			'internal_links' => $internal_links,
			'external_links' => $external_links,
			'images_count'   => $img_count,
			'missing_alt'    => $missing_alt_count,
		);
	}

	/**
	 * Analyze entire site summary for dashboard
	 */
	public function analyze_site_summary() {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		) );

		$total_score = 0;
		$analyzed_count = 0;
		$missing_meta_count = 0;
		$missing_keyword_count = 0;
		$short_content_count = 0;

		foreach ( $posts as $p ) {
			$seo_title = get_post_meta( $p->ID, '_smart_seo_title', true );
			$meta_desc = get_post_meta( $p->ID, '_smart_seo_meta_desc', true );
			$keyword   = get_post_meta( $p->ID, '_smart_seo_focus_keyword', true );

			if ( empty( $meta_desc ) ) {
				$missing_meta_count++;
			}
			if ( empty( $keyword ) ) {
				$missing_keyword_count++;
			}

			$res = $this->analyze_single_post( $p->ID, $p->post_title, $p->post_content, $keyword, $seo_title, $meta_desc );
			$total_score += $res['score'];
			$analyzed_count++;

			if ( $res['word_count'] < 300 ) {
				$short_content_count++;
			}
		}

		$avg_score = $analyzed_count > 0 ? intval( round( $total_score / $analyzed_count ) ) : 85;

		return array(
			'score'                 => $avg_score,
			'analyzed_count'        => $analyzed_count,
			'missing_meta_count'    => $missing_meta_count,
			'missing_keyword_count' => $missing_keyword_count,
			'short_content_count'   => $short_content_count,
			'posts_sample'          => $posts,
		);
	}

	/**
	 * Output SEO Meta Tags, Canonical & OpenGraph in wp_head
	 */
	public function output_frontend_seo_tags() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$seo_title = get_post_meta( $post->ID, '_smart_seo_title', true );
		$meta_desc = get_post_meta( $post->ID, '_smart_seo_meta_desc', true );
		$canonical = get_post_meta( $post->ID, '_smart_seo_canonical', true );

		if ( empty( $seo_title ) ) {
			$seo_title = $post->post_title . ' - ' . get_bloginfo( 'name' );
		}

		if ( empty( $meta_desc ) ) {
			// Auto extract first 155 chars of content
			$meta_desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' );
		}

		$url = ! empty( $canonical ) ? $canonical : get_permalink( $post->ID );
		$thumb = get_the_post_thumbnail_url( $post->ID, 'large' );

		echo "\n<!-- Smart SEO AI Suite Pro Meta Tags -->\n";
		echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";

		// OpenGraph
		echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		if ( $thumb ) {
			echo '<meta property="og:image" content="' . esc_url( $thumb ) . '" />' . "\n";
		}

		// Twitter Cards
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $seo_title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
		if ( $thumb ) {
			echo '<meta name="twitter:image" content="' . esc_url( $thumb ) . '" />' . "\n";
		}
		echo "<!-- / Smart SEO AI Suite Pro Meta Tags -->\n\n";
	}

	public function get_score_badge_class( $score ) {
		if ( $score >= 80 ) {
			return 'score-good';
		}
		if ( $score >= 50 ) {
			return 'score-warning';
		}
		return 'score-bad';
	}

	public function get_score_label( $score ) {
		if ( $score >= 80 ) {
			return __( 'عالی', 'smart-seo-ai-suite-pro' );
		}
		if ( $score >= 50 ) {
			return __( 'نیاز به بهبود', 'smart-seo-ai-suite-pro' );
		}
		return __( 'ضعیف', 'smart-seo-ai-suite-pro' );
	}
}
