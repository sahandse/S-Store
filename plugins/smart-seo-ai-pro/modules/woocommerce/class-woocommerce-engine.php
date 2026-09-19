<?php
/**
 * WooCommerce SEO & Store Optimization Engine
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_WooCommerce_Engine {

	public function __construct() {
		// Only hook if WooCommerce is active
		if ( $this->is_woocommerce_active() ) {
			add_action( 'wp_head', array( $this, 'output_product_schema_jsonld' ), 20 );
		}
	}

	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Run comprehensive store audit
	 *
	 * @return array
	 */
	public function run_audit() {
		if ( ! $this->is_woocommerce_active() ) {
			return array(
				'is_active' => false,
				'score'     => 100,
				'message'   => __( 'فروشگاه‌ساز ووکامرس روی این سایت فعال نیست.', 'smart-seo-ai-suite-pro' ),
				'products'  => array(),
			);
		}

		$products = wc_get_products( array(
			'limit'  => 20,
			'status' => 'publish',
		) );

		$total_score = 0;
		$analyzed_items = array();
		$issues_summary = array(
			'missing_short_desc' => 0,
			'missing_gallery'    => 0,
			'missing_sku'        => 0,
			'missing_price'      => 0,
			'short_description'  => 0,
			'missing_category'   => 0,
		);

		foreach ( $products as $product ) {
			$analysis = $this->analyze_product( $product );
			$analyzed_items[] = $analysis;
			$total_score += $analysis['score'];

			if ( ! empty( $analysis['issues'] ) ) {
				foreach ( $analysis['issues'] as $issue_key ) {
					if ( isset( $issues_summary[ $issue_key ] ) ) {
						$issues_summary[ $issue_key ]++;
					}
				}
			}
		}

		$count = count( $products );
		$avg_score = $count > 0 ? intval( round( $total_score / $count ) ) : 90;

		return array(
			'is_active'       => true,
			'score'           => $avg_score,
			'total_products'  => $count,
			'issues_summary'  => $issues_summary,
			'products'        => $analyzed_items,
			'recommendations' => $this->get_store_recommendations( $issues_summary, $avg_score ),
		);
	}

	/**
	 * Analyze individual WooCommerce product
	 *
	 * @param WC_Product|int $product
	 * @return array
	 */
	public function analyze_product( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return array( 'score' => 0, 'checks' => array(), 'issues' => array() );
		}

		$score = 100;
		$checks = array();
		$issues = array();

		$id         = $product->get_id();
		$title      = $product->get_name();
		$desc       = $product->get_description();
		$short_desc = $product->get_short_description();
		$price      = $product->get_price();
		$sku        = $product->get_sku();
		$img_id     = $product->get_image_id();
		$gallery    = $product->get_gallery_image_ids();
		$cats       = wc_get_product_category_list( $id );

		// 1. Title
		$title_len = mb_strlen( $title );
		if ( $title_len < 10 ) {
			$score -= 10;
			$checks[] = array( 'title' => 'عنوان محصول', 'status' => 'warning', 'message' => 'عنوان محصول بسیار کوتاه است.' );
		} else {
			$checks[] = array( 'title' => 'عنوان محصول', 'status' => 'pass', 'message' => 'عنوان مناسب و شفاف است.' );
		}

		// 2. Full Description
		$desc_words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $desc ) ) ) );
		if ( $desc_words < 50 ) {
			$score -= 15;
			$issues[] = 'short_description';
			$checks[] = array( 'title' => 'توضیحات کامل', 'status' => 'fail', 'message' => 'توضیحات محصول کمتر از ۵۰ کلمه است (محتوای لاغر).' );
		} else {
			$checks[] = array( 'title' => 'توضیحات کامل', 'status' => 'pass', 'message' => sprintf( '%d کلمه توضیحات کامل.', $desc_words ) );
		}

		// 3. Short Description
		if ( empty( trim( $short_desc ) ) ) {
			$score -= 10;
			$issues[] = 'missing_short_desc';
			$checks[] = array( 'title' => 'توضیحات کوتاه', 'status' => 'warning', 'message' => 'توضیح کوتاه خلاصه برای بالای دکمه خرید درج نشده است.' );
		} else {
			$checks[] = array( 'title' => 'توضیحات کوتاه', 'status' => 'pass', 'message' => 'توضیحات کوتاه ثبت شده است.' );
		}

		// 4. Main Image & Gallery
		if ( empty( $img_id ) ) {
			$score -= 20;
			$checks[] = array( 'title' => 'تصویر اصلی محصول', 'status' => 'fail', 'message' => 'محصول فاقد تصویر شاخص است.' );
		} else {
			$checks[] = array( 'title' => 'تصویر اصلی', 'status' => 'pass', 'message' => 'تصویر محصول ثبت شده است.' );
		}

		if ( empty( $gallery ) ) {
			$score -= 8;
			$issues[] = 'missing_gallery';
			$checks[] = array( 'title' => 'گالری تصاویر', 'status' => 'warning', 'message' => 'گالری عکس ندارد (حداقل ۲ زاویه مختلف اضافه کنید).' );
		} else {
			$checks[] = array( 'title' => 'گالری تصاویر', 'status' => 'pass', 'message' => sprintf( '%d تصویر در گالری وجود دارد.', count( $gallery ) ) );
		}

		// 5. SKU & Price
		if ( empty( $sku ) ) {
			$score -= 5;
			$issues[] = 'missing_sku';
			$checks[] = array( 'title' => 'شناسه محصول (SKU)', 'status' => 'warning', 'message' => 'کد شناسه محصول ثبت نشده است.' );
		}

		if ( $price === '' ) {
			$score -= 15;
			$issues[] = 'missing_price';
			$checks[] = array( 'title' => 'قیمت‌گذاری', 'status' => 'fail', 'message' => 'قیمت محصول خالی است.' );
		}

		// 6. Categories
		if ( empty( $cats ) || strpos( $cats, 'دسته‌بندی نشده' ) !== false || strpos( $cats, 'Uncategorized' ) !== false ) {
			$score -= 8;
			$issues[] = 'missing_category';
			$checks[] = array( 'title' => 'دسته‌بندی', 'status' => 'warning', 'message' => 'محصول در دسته پیش‌فرض رها شده است.' );
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'id'          => $id,
			'title'       => $title,
			'score'       => $score,
			'price'       => $price,
			'sku'         => $sku,
			'stock'       => $product->get_stock_status(),
			'has_image'   => ! empty( $img_id ),
			'gallery_cnt' => count( $gallery ),
			'checks'      => $checks,
			'issues'      => $issues,
		);
	}

	private function get_store_recommendations( $issues, $avg_score ) {
		$recs = array();
		if ( $issues['missing_short_desc'] > 0 ) {
			$recs[] = sprintf( __( 'برای %d محصول توضیحات کوتاه جذاب تنظیم کنید تا نرخ تبدیل بالا رود.', 'smart-seo-ai-suite-pro' ), $issues['missing_short_desc'] );
		}
		if ( $issues['missing_gallery'] > 0 ) {
			$recs[] = sprintf( __( 'تعداد %d محصول گالری تکمیلی ندارند. تصاویر چند زاویه‌ای به جلب اعتماد مشتری کمک می‌کند.', 'smart-seo-ai-suite-pro' ), $issues['missing_gallery'] );
		}
		if ( $issues['short_description'] > 0 ) {
			$recs[] = sprintf( __( 'از هوش مصنوعی هوشمند افزونه برای بسط و توسعه توضیحات %d محصول استفاده کنید.', 'smart-seo-ai-suite-pro' ), $issues['short_description'] );
		}
		if ( empty( $recs ) ) {
			$recs[] = __( 'عالی! ساختار سئو و استانداردهای فروشگاهی محصولات در وضعیت بسیار مطلوبی قرار دارد.', 'smart-seo-ai-suite-pro' );
		}
		return $recs;
	}

	/**
	 * Output rich JSON-LD Product Schema
	 */
	public function output_product_schema_jsonld() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product ) {
			return;
		}

		$schema = array(
			'@context'    => 'https://schema.org/',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: '',
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'sku'         => $product->get_sku() ?: (string) $product->get_id(),
			'offers'      => array(
				'@type'         => 'Offer',
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => $product->get_price(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => get_permalink( $product->get_id() ),
			),
		);

		echo "\n<!-- Smart SEO AI Suite Pro Product JSON-LD Schema -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		echo "<!-- / Smart SEO AI Suite Pro Product JSON-LD Schema -->\n\n";
	}
}
