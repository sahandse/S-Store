<?php
/**
 * Main Plugin Loader
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Loader {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Modules container
	 */
	public $seo = null;
	public $ai = null;
	public $woocommerce = null;
	public $security = null;
	public $performance = null;
	public $autofix = null;
	public $reports = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		$this->init_modules();
		$this->init_hooks();
	}

	/**
	 * Include core and module files
	 */
	private function includes() {
		require_once SMART_SEO_AI_PATH . 'core/database.php';
		require_once SMART_SEO_AI_PATH . 'core/installer.php';
		require_once SMART_SEO_AI_PATH . 'core/logger.php';
		require_once SMART_SEO_AI_PATH . 'core/permissions.php';
		require_once SMART_SEO_AI_PATH . 'core/settings.php';

		// Modules
		require_once SMART_SEO_AI_PATH . 'modules/seo/class-seo-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/ai/class-ai-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/woocommerce/class-woocommerce-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/security/class-security-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/performance/class-performance-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/autofix/class-autofix-engine.php';
		require_once SMART_SEO_AI_PATH . 'modules/reports/class-reports-engine.php';

		// Admin Pages & AJAX
		if ( is_admin() ) {
			require_once SMART_SEO_AI_PATH . 'admin/dashboard.php';
			require_once SMART_SEO_AI_PATH . 'admin/settings.php';
			require_once SMART_SEO_AI_PATH . 'admin/reports.php';
			require_once SMART_SEO_AI_PATH . 'admin/ajax.php';
			require_once SMART_SEO_AI_PATH . 'admin/charts.php';
		}
	}

	/**
	 * Initialize sub-engines
	 */
	private function init_modules() {
		$this->seo         = new Smart_SEO_AI_SEO_Engine();
		$this->ai          = new Smart_SEO_AI_AI_Engine();
		$this->woocommerce = new Smart_SEO_AI_WooCommerce_Engine();
		$this->security    = new Smart_SEO_AI_Security_Engine();
		$this->performance = new Smart_SEO_AI_Performance_Engine();
		$this->autofix     = new Smart_SEO_AI_AutoFix_Engine();
		$this->reports     = new Smart_SEO_AI_Reports_Engine();
	}

	/**
	 * Hook registrations
	 */
	private function init_hooks() {
		// Translation Domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin Menu & Assets
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Frontend SEO Output
		add_action( 'wp_head', array( $this->seo, 'output_frontend_seo_tags' ), 1 );

		// Cron Schedule Handler
		add_action( 'smart_seo_ai_daily_scan_cron', array( $this, 'run_daily_cron_scan' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'smart-seo-ai-suite-pro', false, dirname( SMART_SEO_AI_BASENAME ) . '/languages' );
	}

	/**
	 * Register WordPress Admin Menus
	 */
	public function register_admin_menus() {
		if ( ! Smart_SEO_AI_Permissions::can_edit_seo() ) {
			return;
		}

		// Main Menu
		if ( function_exists( 's_store_register_submenu' ) ) {
			s_store_register_submenu(
				'smart-seo-ai-dashboard',
				__( 'سئو هوشمند AI', 'smart-seo-ai-suite-pro' ),
				'Smart_SEO_AI_Admin_Dashboard::render',
				'edit_posts',
				__( 'Smart SEO AI Suite', 'smart-seo-ai-suite-pro' )
			);
			$parent_slug = 's-store';
		} else {
			add_menu_page(
				__( 'Smart SEO AI Suite', 'smart-seo-ai-suite-pro' ),
				__( 'Smart SEO AI', 'smart-seo-ai-suite-pro' ),
				'edit_posts',
				'smart-seo-ai-dashboard',
				'Smart_SEO_AI_Admin_Dashboard::render',
				'dashicons-superhero',
				30
			);
			$parent_slug = 'smart-seo-ai-dashboard';
		}

		// Submenus
		add_submenu_page(
			$parent_slug,
			__( 'Dashboard & Overview', 'smart-seo-ai-suite-pro' ),
			__( 'داشبورد', 'smart-seo-ai-suite-pro' ),
			'edit_posts',
			'smart-seo-ai-dashboard',
			'Smart_SEO_AI_Admin_Dashboard::render'
		);

		// SEO & AI Content Studio
		add_submenu_page(
			$parent_slug,
			__( 'SEO & AI Studio', 'smart-seo-ai-suite-pro' ),
			__( 'موتور سئو و هوش مصنوعی', 'smart-seo-ai-suite-pro' ),
			'edit_posts',
			'smart-seo-ai-studio',
			'Smart_SEO_AI_Admin_Dashboard::render_studio'
		);

		// WooCommerce Optimizer
		if ( Smart_SEO_AI_Permissions::can_manage_shop() ) {
			add_submenu_page(
			$parent_slug,
				__( 'WooCommerce SEO', 'smart-seo-ai-suite-pro' ),
				__( 'سئو ووکامرس', 'smart-seo-ai-suite-pro' ),
				'manage_smart_seo_ai_shop',
				'smart-seo-ai-woocommerce',
				'Smart_SEO_AI_Admin_Dashboard::render_woocommerce'
			);
		}

		// Security & Performance Audit
		if ( Smart_SEO_AI_Permissions::can_view_security() ) {
			add_submenu_page(
			$parent_slug,
				__( 'Security & Speed', 'smart-seo-ai-suite-pro' ),
				__( 'امنیت و سرعت', 'smart-seo-ai-suite-pro' ),
				'manage_options',
				'smart-seo-ai-security-speed',
				'Smart_SEO_AI_Admin_Dashboard::render_security_speed'
			);
		}

		// AutoFix & Backups
		if ( Smart_SEO_AI_Permissions::can_run_autofix() ) {
			add_submenu_page(
			$parent_slug,
				__( 'Auto Fix & Rollback', 'smart-seo-ai-suite-pro' ),
				__( 'اصلاح خودکار و بازگردانی', 'smart-seo-ai-suite-pro' ),
				'manage_options',
				'smart-seo-ai-autofix',
				'Smart_SEO_AI_Admin_Dashboard::render_autofix'
			);
		}

		// Reports & Analytics
		add_submenu_page(
			$parent_slug,
			__( 'Audit Reports', 'smart-seo-ai-suite-pro' ),
			__( 'گزارش‌ها و خروجی', 'smart-seo-ai-suite-pro' ),
			'edit_posts',
			'smart-seo-ai-reports',
			'Smart_SEO_AI_Admin_Reports::render'
		);

		// Settings
		if ( Smart_SEO_AI_Permissions::can_manage_all() ) {
			add_submenu_page(
			$parent_slug,
				__( 'Settings', 'smart-seo-ai-suite-pro' ),
				__( 'تنظیمات افزونه', 'smart-seo-ai-suite-pro' ),
				'manage_options',
				'smart-seo-ai-settings',
				'Smart_SEO_AI_Admin_Settings::render'
			);
		}
	}

	/**
	 * Enqueue Admin Styles and Scripts
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only enqueue on our plugin pages or post edit screens
		$is_plugin_page = strpos( $hook, 'smart-seo-ai' ) !== false;
		$is_post_edit   = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_page && ! $is_post_edit ) {
			return;
		}

		wp_enqueue_style(
			'smart-seo-ai-admin-css',
			SMART_SEO_AI_URL . 'assets/css/admin.css',
			array(),
			SMART_SEO_AI_VERSION
		);

		wp_enqueue_style(
			'smart-seo-ai-dashboard-css',
			SMART_SEO_AI_URL . 'assets/css/dashboard.css',
			array( 'smart-seo-ai-admin-css' ),
			SMART_SEO_AI_VERSION
		);

		wp_enqueue_script(
			'smart-seo-ai-charts-js',
			SMART_SEO_AI_URL . 'assets/js/charts.js',
			array(),
			SMART_SEO_AI_VERSION,
			true
		);

		wp_enqueue_script(
			'smart-seo-ai-admin-js',
			SMART_SEO_AI_URL . 'assets/js/admin.js',
			array( 'jquery', 'smart-seo-ai-charts-js' ),
			SMART_SEO_AI_VERSION,
			true
		);

		wp_localize_script(
			'smart-seo-ai-admin-js',
			'smartSeoAiData',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'smart_seo_ai_nonce_action' ),
				'isRTL'      => is_rtl(),
				'version'    => SMART_SEO_AI_VERSION,
				'i18n'       => array(
					'scanning'        => __( 'در حال اسکن کامل سایت...', 'smart-seo-ai-suite-pro' ),
					'fixing'          => __( 'در حال رفع خودکار مشکلات...', 'smart-seo-ai-suite-pro' ),
					'rollingBack'     => __( 'در حال بازگردانی تغییرات...', 'smart-seo-ai-suite-pro' ),
					'aiGenerating'    => __( 'در حال پردازش هوش مصنوعی...', 'smart-seo-ai-suite-pro' ),
					'success'         => __( 'عملیات با موفقیت انجام شد.', 'smart-seo-ai-suite-pro' ),
					'error'           => __( 'خطایی رخ داد. لطفا دوباره تلاش کنید.', 'smart-seo-ai-suite-pro' ),
					'confirmAutoFix'  => __( 'آیا از اجرای رفع خودکار مشکلات اطمینان دارید؟ نسخه پشتیبان قبل از اعمال تغییرات ذخیره خواهد شد.', 'smart-seo-ai-suite-pro' ),
					'confirmRollback' => __( 'آیا از بازگردانی این تغییر اطمینان دارید؟', 'smart-seo-ai-suite-pro' ),
				),
			)
		);
	}

	/**
	 * Scheduled Cron Handler
	 */
	public function run_daily_cron_scan() {
		$seo_data  = $this->seo->analyze_site_summary();
		$sec_data  = $this->security->run_audit();
		$perf_data = $this->performance->run_audit();
		$woo_data  = $this->woocommerce->run_audit();

		$overall = intval( round( ( $seo_data['score'] + $sec_data['score'] + $perf_data['score'] + ( $woo_data['is_active'] ? $woo_data['score'] : $seo_data['score'] ) ) / 4 ) );

		Smart_SEO_AI_Database::record_scan( array(
			'scan_type'         => 'cron_daily',
			'overall_score'     => $overall,
			'seo_score'         => $seo_data['score'],
			'security_score'    => $sec_data['score'],
			'performance_score' => $perf_data['score'],
			'woo_score'         => $woo_data['score'],
			'details'           => array(
				'seo'      => $seo_data,
				'security' => $sec_data,
				'perf'     => $perf_data,
				'woo'      => $woo_data,
			),
		) );

		Smart_SEO_AI_Logger::log( 'system', "Scheduled daily scan completed with overall score {$overall}/100.", 'info' );
	}
}
