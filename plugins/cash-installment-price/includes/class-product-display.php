<?php
defined( 'ABSPATH' ) || exit;

class WPT_Product_Display {

    public function __construct() {
        // Remove the default WC price; we own that slot
        add_action( 'woocommerce_single_product_summary', [ $this, 'remove_default_price' ], 9 );

        // Front-end assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

        // Single product: our price widget + hidden field for form
        add_action( 'woocommerce_single_product_summary', [ $this, 'single_product_prices' ], 10 );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'hidden_price_type_field' ] );
        add_action( 'woocommerce_after_add_to_cart_button',  [ $this, 'inline_type_selector' ] );

        // Shop loop: remove default price then show ours
        add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'remove_loop_price' ], 9 );
        add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'loop_prices' ], 15 );
        add_filter( 'woocommerce_loop_add_to_cart_args', [ $this, 'loop_button_args' ], 10, 2 );

        // Modal markup in footer (once per page)
        add_action( 'wp_footer', [ $this, 'modal_html' ] );
    }

    public function remove_default_price() {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    }

    // ── Assets ────────────────────────────────

    public function enqueue() {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'wpt-frontend',
            WPT_URL . 'assets/css/frontend.css',
            [],
            WPT_VERSION
        );

        wp_enqueue_script(
            'wpt-frontend',
            WPT_URL . 'assets/js/frontend.js',
            [ 'jquery' ],
            WPT_VERSION,
            true
        );

        $current_type = WC()->session ? WC()->session->get( 'wpt_price_type', '' ) : '';

        wp_localize_script( 'wpt-frontend', 'wptData', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'wpt_nonce' ),
            'cartType'     => $current_type,
            'markup'       => WPT_Admin_Settings::get_markup(),
            'cashLabel'    => WPT_Admin_Settings::get_cash_label(),
            'installLabel' => WPT_Admin_Settings::get_installment_label(),

            'priceFormat'  => [
                'currency'    => get_woocommerce_currency_symbol(),
                'position'    => get_option( 'woocommerce_currency_pos', 'right_space' ),
                'thousandSep' => wc_get_price_thousand_separator(),
                'decimalSep'  => wc_get_price_decimal_separator(),
                'decimals'    => wc_get_price_decimals(),
            ],
            'selectorMode' => WPT_Admin_Settings::get_selector_mode(),
            'i18n' => [
                'selectType'      => 'نوع پرداخت را انتخاب کنید',
                'cashOrInstall'   => 'این محصول را نقدی یا قسطی می‌خرید؟',
                'cancel'          => 'انصراف',
                'conflictCash'    => 'سبد شما نقدی است. برای افزودن قسطی ابتدا سبد را خالی کنید یا کل سبد را به قسطی تغییر دهید.',
                'conflictInstall' => 'سبد شما قسطی است. برای افزودن نقدی ابتدا سبد را خالی کنید یا کل سبد را به نقدی تغییر دهید.',
                'switching'       => 'در حال تغییر...',
            ],
        ] );
    }

    // ── Single product price widget ───────────

    public function single_product_prices() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $markup = WPT_Admin_Settings::get_markup();

        // get_price() for variable products returns the minimum variation price —
        // used as the visible default until the user picks a specific variation
        $cash_price    = (float) $product->get_price();
        $install_price = $cash_price ? WPT_Admin_Settings::calc_installment_price( $cash_price ) : 0;

        $cash_html    = $cash_price    ? wp_kses_post( wc_price( $cash_price ) )    : '';
        $install_html = $install_price ? wp_kses_post( wc_price( $install_price ) ) : '';

        printf(
            '<div class="wpt-price-widget" id="wpt-price-widget" data-base-cash="%s" data-is-variable="%s">',
            esc_attr( $cash_price ),
            $product->is_type( 'variable' ) ? '1' : '0'
        );

        printf(
            '<div class="wpt-pw-card wpt-pw-cash">
                <span class="wpt-pw-type">%s</span>
                <span class="wpt-pw-amount" id="wpt-pw-cash-amount">%s</span>
             </div>',
            esc_html( WPT_Admin_Settings::get_cash_label() ),
            $cash_html
        );

        echo '<div class="wpt-pw-sep" aria-hidden="true">یا</div>';

        printf(
            '<div class="wpt-pw-card wpt-pw-inst">
                <span class="wpt-pw-type">%s</span>
                <span class="wpt-pw-amount" id="wpt-pw-inst-amount">%s</span>
             </div>',
            esc_html( WPT_Admin_Settings::get_installment_label() ),
            $install_html
        );

        echo '</div>'; // .wpt-price-widget
    }

    public function hidden_price_type_field() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $cash_price    = (float) $product->get_price();
        $install_price = WPT_Admin_Settings::calc_installment_price( $cash_price );

        printf(
            '<input type="hidden" name="wpt_price_type" id="wpt_price_type_field" value=""
                data-cash-price="%s" data-install-price="%s"
                data-cash-formatted="%s" data-install-formatted="%s">',
            esc_attr( $cash_price ),
            esc_attr( $install_price ),
            esc_attr( strip_tags( wc_price( $cash_price ) ) ),
            esc_attr( strip_tags( wc_price( $install_price ) ) )
        );
    }

    // ── Inline type selector (replaces modal on product page) ──

    public function inline_type_selector() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $cash_price    = (float) $product->get_price();
        $install_price = WPT_Admin_Settings::calc_installment_price( $cash_price );
        $cash_fmt      = $cash_price    ? strip_tags( wc_price( $cash_price ) )    : '—';
        $inst_fmt      = $install_price ? strip_tags( wc_price( $install_price ) ) : '—';

        printf(
            '<div class="wpt-inline-selector" id="wpt-inline-selector" style="display:none" dir="rtl">'
            . '<p class="wpt-inline-label">نوع پرداخت را انتخاب کنید</p>'
            . '<div class="wpt-inline-btns">'
            .   '<button type="button" class="wpt-inline-btn wpt-inline-btn--cash" data-type="cash">'
            .     '<span class="wpt-inline-btn-title">%s</span>'
            .     '<span class="wpt-inline-btn-price" id="wpt-inline-cash-price">%s</span>'
            .   '</button>'
            .   '<button type="button" class="wpt-inline-btn wpt-inline-btn--inst" data-type="installment">'
            .     '<span class="wpt-inline-btn-title">%s</span>'
            .     '<span class="wpt-inline-btn-price" id="wpt-inline-inst-price">%s</span>'
            .   '</button>'
            . '</div>'
            . '</div>',
            esc_html( WPT_Admin_Settings::get_cash_label() ),
            esc_html( $cash_fmt ),
            esc_html( WPT_Admin_Settings::get_installment_label() ),
            esc_html( $inst_fmt )
        );
    }

    // ── Shop loop ─────────────────────────────

    public function remove_loop_price() {
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    }

    public function loop_prices() {
        global $product;
        if ( ! $product || ! $product->get_price() ) {
            return;
        }

        $cash_price    = (float) $product->get_price();
        $install_price = WPT_Admin_Settings::calc_installment_price( $cash_price );

        printf(
            '<div class="wpt-loop-prices">
                <span class="wpt-loop-cash">%s: %s</span>
                <span class="wpt-loop-install">%s: %s</span>
             </div>',
            esc_html( WPT_Admin_Settings::get_cash_label() ),
            wp_kses_post( wc_price( $cash_price ) ),
            esc_html( WPT_Admin_Settings::get_installment_label() ),
            wp_kses_post( wc_price( $install_price ) )
        );
    }

    public function loop_button_args( $args, $product ) {
        $cash_price    = (float) $product->get_price();
        $install_price = WPT_Admin_Settings::calc_installment_price( $cash_price );

        $args['attributes']['data-cash-price']        = $cash_price;
        $args['attributes']['data-install-price']     = $install_price;
        $args['attributes']['data-cash-formatted']    = strip_tags( wc_price( $cash_price ) );
        $args['attributes']['data-install-formatted'] = strip_tags( wc_price( $install_price ) );

        return $args;
    }

    // ── Modal ─────────────────────────────────

    public function modal_html() {
        if ( ! is_woocommerce() && ! is_cart() ) {
            return;
        }

        $icon_cash = '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M6 14h2"/></svg>';
        $icon_inst = '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>';
        ?>
        <div id="wpt-modal" class="wpt-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wpt-modal-title">
            <div class="wpt-modal-overlay"></div>
            <div class="wpt-modal-box">
                <h3 id="wpt-modal-title" class="wpt-modal-title">نوع پرداخت را انتخاب کنید</h3>
                <p class="wpt-modal-subtitle">این محصول را نقدی یا قسطی می‌خرید؟</p>
                <div class="wpt-modal-options">
                    <button class="wpt-type-btn" data-type="cash">
                        <span class="wpt-btn-icon"><?php echo $icon_cash; // phpcs:ignore ?></span>
                        <strong class="wpt-btn-title" id="wpt-modal-cash-label"><?php echo esc_html( WPT_Admin_Settings::get_cash_label() ); ?></strong>
                        <span class="wpt-btn-price" id="wpt-modal-cash-price"></span>
                    </button>
                    <button class="wpt-type-btn" data-type="installment">
                        <span class="wpt-btn-icon"><?php echo $icon_inst; // phpcs:ignore ?></span>
                        <strong class="wpt-btn-title" id="wpt-modal-install-label"><?php echo esc_html( WPT_Admin_Settings::get_installment_label() ); ?></strong>
                        <span class="wpt-btn-price" id="wpt-modal-install-price"></span>
                    </button>
                </div>
                <p class="wpt-conflict-msg" id="wpt-conflict-msg" style="display:none;"></p>
                <button class="wpt-close-btn" id="wpt-modal-cancel">انصراف</button>
            </div>
        </div>
        <?php
    }
}
