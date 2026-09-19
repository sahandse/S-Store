<?php
defined( 'ABSPATH' ) || exit;

class WPT_Admin_Settings {

    const OPTION_MARKUP        = 'wpt_installment_markup';
    const OPTION_CASH_LBL      = 'wpt_cash_label';
    const OPTION_INST_LBL      = 'wpt_installment_label';
    const OPTION_CASH_GATEWAYS = 'wpt_cash_gateways';
    const OPTION_INST_GATEWAYS = 'wpt_installment_gateways';
    const OPTION_SELECTOR_MODE = 'wpt_selector_mode';
    const NONCE                = 'wpt_admin_save';
    const STATS_TRANSIENT      = 'wpt_order_stats';

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'woocommerce_order_status_changed', [ $this, 'clear_stats_cache' ] );
    }

    // ── منو ───────────────────────────────────

    public function register_menu() {
        if ( function_exists( 's_store_register_submenu' ) ) {
            $hook = s_store_register_submenu(
                'wpt-settings',
                'قیمت نقدی / قسطی',
                [ $this, 'render_page' ],
                'manage_woocommerce',
                'قیمت نقدی / قسطی'
            );
        } else {
            $hook = add_submenu_page(
                'woocommerce',
                'قیمت نقدی / قسطی',
                'قیمت نقدی / قسطی',
                'manage_woocommerce',
                'wpt-settings',
                [ $this, 'render_page' ]
            );
        }

        if ( $hook ) {
            add_action( 'load-' . $hook, [ $this, 'handle_save' ] );
        }
    }

    // ── Assets ────────────────────────────────

    public function enqueue_admin_assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'wpt-settings' !== $page && 'woocommerce_page_wpt-settings' !== $hook ) return;

        wp_enqueue_style(  'wpt-admin', WPT_URL . 'assets/css/admin.css',  [], WPT_VERSION );
        wp_enqueue_script( 'wpt-admin', WPT_URL . 'assets/js/admin.js', [], WPT_VERSION, true );
        wp_localize_script( 'wpt-admin', 'wptAdmin', [
            'cashPrice' => 1000000,
            'markup'    => self::get_markup(),
            'currency'  => get_woocommerce_currency_symbol(),
            'position'  => get_option( 'woocommerce_currency_pos', 'right_space' ),
            'thousandSep' => wc_get_price_thousand_separator(),
        ] );
    }

    // ── ذخیره ─────────────────────────────────

    public function handle_save() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }
        if ( empty( $_POST['wpt_do_save'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }

        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            wp_die( 'خطای امنیتی. لطفاً دوباره تلاش کنید.' );
        }

        update_option( self::OPTION_MARKUP,   max( 0, (float) ( $_POST[ self::OPTION_MARKUP ] ?? 20 ) ) );
        update_option( self::OPTION_CASH_LBL, sanitize_text_field( wp_unslash( $_POST[ self::OPTION_CASH_LBL ] ?? 'قیمت نقدی' ) ) );
        update_option( self::OPTION_INST_LBL, sanitize_text_field( wp_unslash( $_POST[ self::OPTION_INST_LBL ] ?? 'قیمت قسطی' ) ) );

        $mode = ( isset( $_POST[ self::OPTION_SELECTOR_MODE ] )
            && in_array( $_POST[ self::OPTION_SELECTOR_MODE ], [ 'inline', 'modal' ], true ) )
            ? sanitize_key( $_POST[ self::OPTION_SELECTOR_MODE ] ) : 'inline';
        update_option( self::OPTION_SELECTOR_MODE, $mode );

        $cash_gw = ( isset( $_POST[ self::OPTION_CASH_GATEWAYS ] ) && is_array( $_POST[ self::OPTION_CASH_GATEWAYS ] ) )
            ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) $_POST[ self::OPTION_CASH_GATEWAYS ] ) )
            : [];
        $inst_gw = ( isset( $_POST[ self::OPTION_INST_GATEWAYS ] ) && is_array( $_POST[ self::OPTION_INST_GATEWAYS ] ) )
            ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) $_POST[ self::OPTION_INST_GATEWAYS ] ) )
            : [];
        update_option( self::OPTION_CASH_GATEWAYS, $cash_gw );
        update_option( self::OPTION_INST_GATEWAYS, $inst_gw );

        delete_transient( self::STATS_TRANSIENT );

        wp_safe_redirect( add_query_arg( [ 'page' => 'wpt-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── آمار سفارش‌ها ─────────────────────────

    public function clear_stats_cache() {
        delete_transient( self::STATS_TRANSIENT );
    }

    private function get_stats() {
        $cached = get_transient( self::STATS_TRANSIENT );
        if ( false !== $cached ) return $cached;

        $stats = [ 'cash_count' => 0, 'cash_total' => 0.0, 'inst_count' => 0, 'inst_total' => 0.0 ];

        $orders = wc_get_orders( [ 'status' => [ 'completed', 'processing', 'on-hold' ], 'limit' => -1, 'return' => 'ids' ] );

        foreach ( $orders as $id ) {
            $order = wc_get_order( $id );
            if ( ! $order ) continue;
            $type = '';
            foreach ( $order->get_items() as $item ) {
                $t = $item->get_meta( 'نوع پرداخت' );
                if ( $t ) { $type = $t; break; }
            }
            if ( 'نقدی' === $type )       { $stats['cash_count']++; $stats['cash_total'] += $order->get_total(); }
            elseif ( 'قسطی' === $type )   { $stats['inst_count']++; $stats['inst_total'] += $order->get_total(); }
        }

        set_transient( self::STATS_TRANSIENT, $stats, HOUR_IN_SECONDS );
        return $stats;
    }

    // ── رندر صفحه ─────────────────────────────

    public function render_page() {
        $markup        = self::get_markup();
        $cash_lbl      = self::get_cash_label();
        $inst_lbl      = self::get_installment_label();
        $cash_gw       = self::get_cash_gateways();
        $inst_gw       = self::get_installment_gateways();
        $selector_mode = self::get_selector_mode();
        $all_gw   = WC()->payment_gateways->payment_gateways();
        if ( empty( $all_gw ) ) {
            WC()->payment_gateways->init();
            $all_gw = WC()->payment_gateways->payment_gateways();
        }
        $saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
        $stats         = $this->get_stats();
        $total_tracked = $stats['cash_count'] + $stats['inst_count'];
        ?>
        <div class="wrap wpt-admin-wrap">

        <h1 class="wpt-admin-title">
            <span class="wpt-admin-icon"><?php echo $this->icon_svg( 'tag' ); // phpcs:ignore ?></span>
            قیمت نقدی / قسطی
        </h1>

        <?php if ( $saved ) : ?>
        <div class="wpt-notice wpt-notice--ok">
            <?php echo $this->icon_svg( 'check' ); // phpcs:ignore ?> تنظیمات با موفقیت ذخیره شد.
        </div>
        <?php endif; ?>

        <div class="wpt-admin-layout">

        <!-- ════ ستون چپ: فرم ════ -->
        <form method="post" action="" class="wpt-admin-form">
            <?php wp_nonce_field( self::NONCE ); ?>
            <input type="hidden" name="wpt_do_save" value="1">

            <!-- ── درصد قسطی ── -->
            <div class="wpt-card">
                <div class="wpt-card-header">
                    <h2><?php echo $this->icon_svg( 'percent' ); // phpcs:ignore ?> درصد قسطی</h2>
                    <p>قیمت قسطی = قیمت نقدی + (قیمت نقدی × درصد)</p>
                </div>
                <div class="wpt-card-body">

                    <div class="wpt-field wpt-field--markup">
                        <label for="wpt_markup">درصد اضافه</label>
                        <div class="wpt-input-group">
                            <input type="number" id="wpt_markup"
                                name="<?php echo esc_attr( self::OPTION_MARKUP ); ?>"
                                value="<?php echo esc_attr( $markup ); ?>"
                                min="0" step="0.01" class="wpt-input" autocomplete="off">
                            <span class="wpt-suffix">٪</span>
                        </div>
                    </div>

                    <!-- پیش‌نمایش چند حالتی -->
                    <div class="wpt-preview">
                        <div class="wpt-preview-tabs">
                            <button type="button" class="wpt-ptab wpt-ptab--active" data-tab="widget">ویجت قیمت</button>
                            <button type="button" class="wpt-ptab" data-tab="modal">مودال</button>
                            <button type="button" class="wpt-ptab" data-tab="cart">سبد خرید</button>
                            <button type="button" class="wpt-ptab" data-tab="checkout">پرداخت</button>
                        </div>

                        <!-- ══════ حالت ۱: ویجت قیمت ══════ -->
                        <div class="wpt-ptab-panel" data-panel="widget">
                            <div class="wpt-pv-scene">
                                <div class="wpt-pv-widget">
                                    <div class="wpt-pv-w-col wpt-pv-w-cash">
                                        <span class="wpt-pv-w-lbl"><?php echo esc_html( $cash_lbl ); ?></span>
                                        <span class="wpt-pv-w-price" id="wpt-prev-cash">—</span>
                                    </div>
                                    <div class="wpt-pv-w-sep">یا</div>
                                    <div class="wpt-pv-w-col wpt-pv-w-inst">
                                        <span class="wpt-pv-w-lbl"><?php echo esc_html( $inst_lbl ); ?></span>
                                        <span class="wpt-pv-w-price" id="wpt-prev-inst">—</span>
                                        <span class="wpt-pv-w-badge" id="wpt-prev-badge">+<?php echo esc_html( $markup ); ?>٪</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══════ حالت ۲: مودال ══════ -->
                        <div class="wpt-ptab-panel" data-panel="modal" style="display:none">
                            <div class="wpt-pv-scene">
                                <div class="wpt-pv-modal-title">نوع پرداخت را انتخاب کنید</div>
                                <div class="wpt-pv-modal-sub">این محصول را نقدی یا قسطی می‌خرید؟</div>
                                <div class="wpt-pv-modal-opts">
                                    <div class="wpt-pv-mopt wpt-pv-mopt--cash">
                                        <div class="wpt-pv-mopt-icon wpt-pv-mopt-icon--cash">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M6 14h2"/></svg>
                                        </div>
                                        <span class="wpt-pv-mopt-lbl"><?php echo esc_html( $cash_lbl ); ?></span>
                                        <span class="wpt-pv-mopt-price" id="wpt-prev-m-cash">—</span>
                                    </div>
                                    <div class="wpt-pv-mopt wpt-pv-mopt--inst">
                                        <div class="wpt-pv-mopt-icon wpt-pv-mopt-icon--inst">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/></svg>
                                        </div>
                                        <span class="wpt-pv-mopt-lbl"><?php echo esc_html( $inst_lbl ); ?></span>
                                        <span class="wpt-pv-mopt-price" id="wpt-prev-m-inst">—</span>
                                    </div>
                                </div>
                                <div class="wpt-pv-modal-cancel">انصراف</div>
                            </div>
                        </div>

                        <!-- ══════ حالت ۳: سبد خرید ══════ -->
                        <div class="wpt-ptab-panel" data-panel="cart" style="display:none">
                            <div class="wpt-pv-scene">
                                <div class="wpt-pv-state-lbl">
                                    <span class="wpt-pv-dot wpt-pv-dot--cash"></span>
                                    نقدی انتخاب شده
                                </div>
                                <div class="wpt-pv-cart">
                                    <div class="wpt-pv-cart-info">
                                        <span>نوع پرداخت:</span>
                                        <strong class="wpt-pv-cart-cash"><?php echo esc_html( $cash_lbl ); ?></strong>
                                    </div>
                                    <div class="wpt-pv-toggle">
                                        <span class="wpt-pv-tbtn wpt-pv-tbtn--on"><?php echo esc_html( $cash_lbl ); ?></span>
                                        <span class="wpt-pv-tbtn"><?php echo esc_html( $inst_lbl ); ?> <em id="wpt-prev-cs-pct">+<?php echo esc_html( $markup ); ?>٪</em></span>
                                    </div>
                                </div>

                                <div class="wpt-pv-state-lbl" style="margin-top:12px">
                                    <span class="wpt-pv-dot wpt-pv-dot--inst"></span>
                                    قسطی انتخاب شده
                                </div>
                                <div class="wpt-pv-cart wpt-pv-cart--inst">
                                    <div class="wpt-pv-cart-info">
                                        <span>نوع پرداخت:</span>
                                        <strong class="wpt-pv-cart-inst"><?php echo esc_html( $inst_lbl ); ?></strong>
                                        <span class="wpt-pv-cart-badge" id="wpt-prev-cs-badge">+<?php echo esc_html( $markup ); ?>٪</span>
                                    </div>
                                    <div class="wpt-pv-toggle">
                                        <span class="wpt-pv-tbtn"><?php echo esc_html( $cash_lbl ); ?></span>
                                        <span class="wpt-pv-tbtn wpt-pv-tbtn--on wpt-pv-tbtn--inst"><?php echo esc_html( $inst_lbl ); ?> <em id="wpt-prev-cs-pct2">+<?php echo esc_html( $markup ); ?>٪</em></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══════ حالت ۴: صفحه پرداخت ══════ -->
                        <div class="wpt-ptab-panel" data-panel="checkout" style="display:none">
                            <div class="wpt-pv-scene">
                                <div class="wpt-pv-state-lbl">
                                    <span class="wpt-pv-dot wpt-pv-dot--cash"></span>
                                    درگاه‌های نقدی فعال
                                </div>
                                <div class="wpt-pv-co wpt-pv-co--cash">
                                    <span class="wpt-pv-co-label">نوع پرداخت:</span>
                                    <strong><?php echo esc_html( $cash_lbl ); ?></strong>
                                    <span class="wpt-pv-co-link">تغییر در سبد خرید</span>
                                </div>

                                <div class="wpt-pv-state-lbl" style="margin-top:12px">
                                    <span class="wpt-pv-dot wpt-pv-dot--inst"></span>
                                    درگاه‌های قسطی فعال
                                </div>
                                <div class="wpt-pv-co wpt-pv-co--inst">
                                    <span class="wpt-pv-co-label">نوع پرداخت:</span>
                                    <strong><?php echo esc_html( $inst_lbl ); ?></strong>
                                    <span class="wpt-pv-co-badge" id="wpt-prev-co-badge">+<?php echo esc_html( $markup ); ?>٪</span>
                                    <span class="wpt-pv-co-link">تغییر در سبد خرید</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            <!-- ── درگاه‌های پرداخت ── -->
            <div class="wpt-card">
                <div class="wpt-card-header">
                    <h2><?php echo $this->icon_svg( 'gateway' ); // phpcs:ignore ?> تخصیص درگاه پرداخت</h2>
                    <p>مشخص کنید کدام درگاه‌ها برای نقدی و کدام برای قسطی نمایش داده شود</p>
                </div>
                <div class="wpt-card-body">
                <?php if ( empty( $all_gw ) ) : ?>
                    <p class="wpt-stats-empty">هیچ درگاه پرداختی در ووکامرس تعریف نشده.</p>
                <?php else : ?>
                    <table class="wpt-gw-table">
                        <thead>
                            <tr>
                                <th class="wpt-gwt-name">درگاه</th>
                                <th class="wpt-gwt-type wpt-gwt-cash"><?php echo esc_html( $cash_lbl ); ?></th>
                                <th class="wpt-gwt-type wpt-gwt-inst"><?php echo esc_html( $inst_lbl ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $all_gw as $gw_id => $gw ) : ?>
                            <tr class="<?php echo $gw->enabled === 'yes' ? '' : 'wpt-gw-disabled'; ?>">
                                <td class="wpt-gwt-name">
                                    <span class="wpt-gw-title"><?php echo esc_html( $gw->get_title() ); ?></span>
                                    <?php if ( $gw->enabled !== 'yes' ) : ?>
                                        <span class="wpt-gw-badge wpt-gw-off">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wpt-gwt-check">
                                    <label class="wpt-chk wpt-chk-cash">
                                        <input type="checkbox"
                                            name="<?php echo esc_attr( self::OPTION_CASH_GATEWAYS ); ?>[]"
                                            value="<?php echo esc_attr( $gw_id ); ?>"
                                            <?php checked( in_array( $gw_id, $cash_gw, true ) ); ?>>
                                        <span class="wpt-chk-box"></span>
                                    </label>
                                </td>
                                <td class="wpt-gwt-check">
                                    <label class="wpt-chk wpt-chk-inst">
                                        <input type="checkbox"
                                            name="<?php echo esc_attr( self::OPTION_INST_GATEWAYS ); ?>[]"
                                            value="<?php echo esc_attr( $gw_id ); ?>"
                                            <?php checked( in_array( $gw_id, $inst_gw, true ) ); ?>>
                                        <span class="wpt-chk-box"></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="wpt-gw-note">اگر درگاهی تیک نخورد، برای هر دو نوع پرداخت نمایش داده می‌شود.</p>
                <?php endif; ?>
                </div>
            </div>

            <!-- ── حالت نمایش انتخاب ── -->
            <div class="wpt-card">
                <div class="wpt-card-header">
                    <h2><?php echo $this->icon_svg( 'display' ); // phpcs:ignore ?> حالت نمایش انتخاب نوع پرداخت</h2>
                    <p>نحوه نمایش گزینه‌های نقدی/قسطی هنگام کلیک روی «افزودن به سبد»</p>
                </div>
                <div class="wpt-card-body">
                    <div class="wpt-mode-group">
                        <label class="wpt-mode-opt <?php echo $selector_mode === 'inline' ? 'wpt-mode-opt--active' : ''; ?>">
                            <input type="radio" name="<?php echo esc_attr( self::OPTION_SELECTOR_MODE ); ?>" value="inline" <?php checked( $selector_mode, 'inline' ); ?>>
                            <span class="wpt-mode-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="14" width="8" height="7" rx="1.5"/><rect x="13" y="14" width="8" height="7" rx="1.5"/><rect x="5" y="4" width="14" height="7" rx="1.5"/><path d="M12 11v3"/></svg>
                            </span>
                            <span class="wpt-mode-text">
                                <strong>زیر دکمه</strong>
                                <span>گزینه‌ها مستقیم زیر دکمه افزودن به سبد باز می‌شوند</span>
                            </span>
                        </label>
                        <label class="wpt-mode-opt <?php echo $selector_mode === 'modal' ? 'wpt-mode-opt--active' : ''; ?>">
                            <input type="radio" name="<?php echo esc_attr( self::OPTION_SELECTOR_MODE ); ?>" value="modal" <?php checked( $selector_mode, 'modal' ); ?>>
                            <span class="wpt-mode-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M8 3v3M16 3v3"/></svg>
                            </span>
                            <span class="wpt-mode-text">
                                <strong>پاپ‌آپ</strong>
                                <span>پنجره انتخاب نوع پرداخت روی صفحه باز می‌شود</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── برچسب‌ها ── -->
            <div class="wpt-card">
                <div class="wpt-card-header">
                    <h2><?php echo $this->icon_svg( 'label' ); // phpcs:ignore ?> برچسب‌ها</h2>
                </div>
                <div class="wpt-card-body wpt-card-body--row">
                    <div class="wpt-field">
                        <label for="wpt_cash_lbl">برچسب نقدی</label>
                        <input type="text" id="wpt_cash_lbl"
                            name="<?php echo esc_attr( self::OPTION_CASH_LBL ); ?>"
                            value="<?php echo esc_attr( $cash_lbl ); ?>" class="wpt-input">
                    </div>
                    <div class="wpt-field">
                        <label for="wpt_inst_lbl">برچسب قسطی</label>
                        <input type="text" id="wpt_inst_lbl"
                            name="<?php echo esc_attr( self::OPTION_INST_LBL ); ?>"
                            value="<?php echo esc_attr( $inst_lbl ); ?>" class="wpt-input">
                    </div>
                </div>
            </div>

            <div class="wpt-actions">
                <button type="submit" class="wpt-btn-save">ذخیره تنظیمات</button>
            </div>

        </form>

        <!-- ════ ستون راست: گزارش ════ -->
        <aside class="wpt-stats-aside">
            <div class="wpt-card">
                <div class="wpt-card-header">
                    <h2><?php echo $this->icon_svg( 'chart' ); // phpcs:ignore ?> گزارش سفارش‌ها</h2>
                    <p>سفارش‌های تکمیل‌شده، در حال پردازش، در انتظار</p>
                </div>
                <div class="wpt-card-body">

                <?php if ( $total_tracked === 0 ) : ?>
                    <p class="wpt-stats-empty">هنوز سفارشی با نوع پرداخت مشخص ثبت نشده.</p>
                <?php else :
                    $cash_pct = $total_tracked ? round( $stats['cash_count'] / $total_tracked * 100 ) : 0;
                    $inst_pct = 100 - $cash_pct;
                ?>

                    <!-- بار پیشرفت -->
                    <div class="wpt-bar-wrap">
                        <div class="wpt-bar">
                            <div class="wpt-bar-cash"  style="width:<?php echo esc_attr( $cash_pct ); ?>%"></div>
                            <div class="wpt-bar-inst"  style="width:<?php echo esc_attr( $inst_pct ); ?>%"></div>
                        </div>
                        <div class="wpt-bar-labels">
                            <span class="wpt-bar-lbl wpt-lbl-cash"><?php echo esc_html( $cash_pct ); ?>٪ نقدی</span>
                            <span class="wpt-bar-lbl wpt-lbl-inst"><?php echo esc_html( $inst_pct ); ?>٪ قسطی</span>
                        </div>
                    </div>

                    <!-- کارت‌های آمار -->
                    <div class="wpt-stat-cards">

                        <div class="wpt-stat-card wpt-stat-cash">
                            <span class="wpt-stat-num"><?php echo number_format( $stats['cash_count'] ); ?></span>
                            <span class="wpt-stat-lbl">سفارش نقدی</span>
                            <span class="wpt-stat-amt"><?php echo wp_kses_post( wc_price( $stats['cash_total'] ) ); ?></span>
                        </div>

                        <div class="wpt-stat-card wpt-stat-inst">
                            <span class="wpt-stat-num"><?php echo number_format( $stats['inst_count'] ); ?></span>
                            <span class="wpt-stat-lbl">سفارش قسطی</span>
                            <span class="wpt-stat-amt"><?php echo wp_kses_post( wc_price( $stats['inst_total'] ) ); ?></span>
                        </div>

                    </div>
                    <p class="wpt-stats-note">آمار هر ساعت یک‌بار به‌روزرسانی می‌شود.</p>
                <?php endif; ?>

                </div>
            </div>
        </aside>

        </div><!-- .wpt-admin-layout -->
        </div><!-- .wrap -->
        <?php
    }

    // ── آیکون SVG ─────────────────────────────

    private function icon_svg( $name ) {
        $icons = [
            'tag'     => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M6 14h2"/></svg>',
            'percent' => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
            'gateway' => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>',
            'label'   => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>',
            'chart'   => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
            'check'   => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            'display' => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        ];
        return $icons[ $name ] ?? '';
    }

    // ── Helper‌های استاتیک ─────────────────────

    public static function get_markup() {
        return (float) get_option( self::OPTION_MARKUP, 20 );
    }

    public static function get_cash_label() {
        return get_option( self::OPTION_CASH_LBL, 'قیمت نقدی' );
    }

    public static function get_installment_label() {
        return get_option( self::OPTION_INST_LBL, 'قیمت قسطی' );
    }

    public static function get_cash_gateways() {
        return (array) get_option( self::OPTION_CASH_GATEWAYS, [] );
    }

    public static function get_installment_gateways() {
        return (array) get_option( self::OPTION_INST_GATEWAYS, [] );
    }

    public static function calc_installment_price( $base_price ) {
        return (float) $base_price * ( 1 + self::get_markup() / 100 );
    }

    public static function get_selector_mode() {
        return get_option( self::OPTION_SELECTOR_MODE, 'inline' );
    }
}
