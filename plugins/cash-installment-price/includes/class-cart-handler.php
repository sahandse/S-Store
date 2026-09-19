<?php
defined( 'ABSPATH' ) || exit;

class WPT_Cart_Handler {

    public function __construct() {
        // Validate before cart insertion
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate' ], 10, 3 );

        // Attach price_type to cart item data
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'attach_item_data' ], 10, 3 );

        // Re-price items on every totals calculation
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'reprice_cart_items' ], 99 );

        // Show payment type in cart/checkout line
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_item_data' ], 10, 2 );

        // Persist to order meta
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_to_order' ], 10, 4 );

        // Cart-page switcher UI
        add_action( 'woocommerce_before_cart', [ $this, 'cart_switcher_ui' ] );

        // Checkout: show selected payment type (read-only, no switcher)
        add_action( 'woocommerce_checkout_before_order_review_heading', [ $this, 'checkout_type_notice' ] );

        // Filter available payment gateways by cart type
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ] );

        // Clear session when cart is emptied
        add_action( 'woocommerce_cart_emptied', [ $this, 'clear_session' ] );

        // Redirect to cart if checkout reached without a valid type
        add_action( 'template_redirect', [ $this, 'guard_checkout' ] );

        // AJAX handlers
        add_action( 'wp_ajax_wpt_switch_type',       [ $this, 'ajax_switch_type' ] );
        add_action( 'wp_ajax_nopriv_wpt_switch_type', [ $this, 'ajax_switch_type' ] );

        add_action( 'wp_ajax_wpt_get_type',          [ $this, 'ajax_get_type' ] );
        add_action( 'wp_ajax_nopriv_wpt_get_type',   [ $this, 'ajax_get_type' ] );

        add_action( 'wp_ajax_wpt_loop_add_to_cart',        [ $this, 'ajax_loop_add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_wpt_loop_add_to_cart', [ $this, 'ajax_loop_add_to_cart' ] );
    }

    // ── Helpers ───────────────────────────────

    private function session_type() {
        return WC()->session ? WC()->session->get( 'wpt_price_type', '' ) : '';
    }

    private function set_session_type( $type ) {
        if ( WC()->session ) {
            WC()->session->set( 'wpt_price_type', $type );
        }
    }

    private function type_label( $type ) {
        return $type === 'cash'
            ? WPT_Admin_Settings::get_cash_label()
            : WPT_Admin_Settings::get_installment_label();
    }

    // ── Switch all cart items to a new type ───

    private function switch_all_items( $type ) {
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            WC()->cart->cart_contents[ $key ]['wpt_price_type'] = $type;
        }
        WC()->cart->set_session();
    }

    // ── Add-to-cart validation ─────────────────

    public function validate( $passed, $product_id, $quantity ) {
        $requested = isset( $_POST['wpt_price_type'] )
            ? sanitize_key( $_POST['wpt_price_type'] )
            : '';

        if ( ! in_array( $requested, [ 'cash', 'installment' ], true ) ) {
            return $passed;
        }

        $current = $this->session_type();

        // Auto-switch all existing cart items to the newly requested type
        if ( ! empty( $current ) && $current !== $requested && WC()->cart->get_cart_contents_count() > 0 ) {
            $this->switch_all_items( $requested );
        }

        $this->set_session_type( $requested );
        return $passed;
    }

    // ── Cart item data ─────────────────────────

    public function attach_item_data( $data, $product_id, $variation_id ) {
        $type = isset( $_POST['wpt_price_type'] )
            ? sanitize_key( $_POST['wpt_price_type'] )
            : $this->session_type();

        if ( in_array( $type, [ 'cash', 'installment' ], true ) ) {
            $data['wpt_price_type'] = $type;
        }

        return $data;
    }

    // ── Price adjustment ──────────────────────

    public function reprice_cart_items( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $markup = WPT_Admin_Settings::get_markup();

        foreach ( $cart->get_cart() as $item ) {
            $type = isset( $item['wpt_price_type'] ) ? $item['wpt_price_type'] : 'cash';

            if ( $type === 'installment' ) {
                /** @var WC_Product $product */
                $product    = $item['data'];
                $base_price = (float) $product->get_regular_price() ?: (float) $product->get_price();
                $product->set_price( $base_price * ( 1 + $markup / 100 ) );
            }
        }
    }

    // ── Display in cart / checkout ─────────────

    public function display_item_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['wpt_price_type'] ) ) {
            $item_data[] = [
                'key'   => 'نوع پرداخت',
                'value' => esc_html( $this->type_label( $cart_item['wpt_price_type'] ) ),
            ];
        }
        return $item_data;
    }

    // ── Save to order ─────────────────────────

    public function save_to_order( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['wpt_price_type'] ) ) {
            $item->add_meta_data(
                'نوع پرداخت',
                esc_html( $this->type_label( $values['wpt_price_type'] ) ),
                true
            );
        }
    }

    // ── Cart switcher UI ──────────────────────

    public function cart_switcher_ui() {
        if ( WC()->cart->is_empty() ) {
            return;
        }

        $current = $this->session_type();
        $markup  = WPT_Admin_Settings::get_markup();
        $mixed   = $this->has_mixed_types();

        if ( $mixed ) {
            $notice = '<p class="wpt-current-notice wpt-notice-warn">⚠ سبد خرید شما دارای محصولات نقدی و قسطی با هم است. یک نوع را انتخاب کنید تا همه اقلام به‌روزرسانی شوند:</p>';
        } elseif ( ! $current ) {
            $notice = '<p class="wpt-current-notice wpt-notice-required">برای ادامه خرید، نوع پرداخت همه اقلام را انتخاب کنید:</p>';
        } else {
            $badge  = '';
            $notice = sprintf(
                '<p class="wpt-current-notice">نوع پرداخت: <strong>%s</strong>%s</p>',
                esc_html( $this->type_label( $current ) ),
                $badge
            );
        }

        $btn_cash = sprintf(
            '<button class="wpt-switch-btn%s" data-type="cash">%s</button>',
            $current === 'cash' ? ' wpt-active' : '',
            esc_html( WPT_Admin_Settings::get_cash_label() )
        );
        $btn_inst = sprintf(
            '<button class="wpt-switch-btn%s" data-type="installment">%s</button>',
            $current === 'installment' ? ' wpt-active' : '',
            esc_html( WPT_Admin_Settings::get_installment_label() )
        );

        $required_cls = ( ! $current || $mixed ) ? ' wpt-cs-required' : '';

        echo '<div class="wpt-cart-switcher' . $required_cls . '">'
            . $notice
            . '<div class="wpt-switch-buttons">' . $btn_cash . $btn_inst . '</div>'
            . '</div>';
    }

    // ── Checkout: read-only payment type notice ─

    public function checkout_type_notice() {
        if ( WC()->cart->is_empty() ) {
            return;
        }

        $type = $this->session_type();
        if ( ! $type ) {
            return;
        }

        $label  = esc_html( $this->type_label( $type ) );
        $markup = WPT_Admin_Settings::get_markup();
        $badge  = '';

        $cls = $type === 'cash' ? 'wpt-co-notice--cash' : 'wpt-co-notice--inst';

        printf(
            '<div class="wpt-co-notice %s">'
            . '<span class="wpt-co-notice-label">نوع پرداخت:</span>'
            . ' <strong>%s</strong>%s'
            . '<a href="%s" class="wpt-co-notice-link">تغییر در سبد خرید</a>'
            . '</div>',
            esc_attr( $cls ),
            $label,
            $badge,
            esc_url( wc_get_cart_url() )
        );
    }

    // ── Gateway filter ────────────────────────

    public function filter_gateways( $gateways ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $gateways;
        }

        $type = $this->session_type();
        if ( ! $type ) {
            return $gateways;
        }

        $allowed = $type === 'cash'
            ? WPT_Admin_Settings::get_cash_gateways()
            : WPT_Admin_Settings::get_installment_gateways();

        // If admin hasn't configured gateways for this type, show all
        if ( empty( $allowed ) ) {
            return $gateways;
        }

        $filtered = $gateways;
        foreach ( array_keys( $filtered ) as $id ) {
            if ( ! in_array( $id, $allowed, true ) ) {
                unset( $filtered[ $id ] );
            }
        }

        // Safety: if configuration is wrong and all gateways got removed, show all
        if ( empty( $filtered ) ) {
            return $gateways;
        }

        return $filtered;
    }

    // ── Cart type helpers ─────────────────────

    private function has_mixed_types() {
        $seen = [];
        foreach ( WC()->cart->get_cart() as $item ) {
            $t = isset( $item['wpt_price_type'] ) ? $item['wpt_price_type'] : 'none';
            $seen[ $t ] = true;
        }
        return count( $seen ) > 1;
    }

    // ── Guard checkout ────────────────────────

    public function guard_checkout() {
        if ( ! is_checkout() || WC()->cart->is_empty() ) {
            return;
        }

        $type  = $this->session_type();
        $mixed = $this->has_mixed_types();

        if ( $mixed ) {
            wc_add_notice( 'سبد خرید شما دارای محصولات نقدی و قسطی با هم است. لطفاً نوع پرداخت را از صفحه سبد خرید مشخص کنید.', 'error' );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

        if ( ! $type ) {
            wc_add_notice( 'لطفاً ابتدا نوع پرداخت (نقدی یا قسطی) را از صفحه سبد خرید انتخاب کنید.', 'error' );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }
    }

    // ── Clear session ──────────────────────────

    public function clear_session() {
        $this->set_session_type( '' );
    }

    // ── AJAX: switch all items ─────────────────

    public function ajax_switch_type() {
        check_ajax_referer( 'wpt_nonce', 'nonce' );

        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';

        if ( ! in_array( $type, [ 'cash', 'installment' ], true ) ) {
            wp_send_json_error( 'نوع پرداخت نامعتبر است.' );
        }

        $this->set_session_type( $type );

        foreach ( WC()->cart->get_cart() as $key => $item ) {
            WC()->cart->cart_contents[ $key ]['wpt_price_type'] = $type;
        }

        WC()->cart->set_session();
        WC()->cart->calculate_totals();

        wp_send_json_success( [
            'type'    => $type,
            'message' => $type === 'cash'
                ? 'همه محصولات سبد به نقدی تغییر یافت.'
                : 'همه محصولات سبد به قسطی تغییر یافت.',
        ] );
    }

    // ── AJAX: return current type ──────────────

    public function ajax_get_type() {
        wp_send_json_success( [ 'type' => $this->session_type() ] );
    }

    // ── AJAX: loop add-to-cart ─────────────────

    public function ajax_loop_add_to_cart() {
        check_ajax_referer( 'wpt_nonce', 'nonce' );

        $product_id = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
        $quantity   = max( 1, absint( wp_unslash( $_POST['quantity'] ?? 1 ) ) );
        $type       = sanitize_key( wp_unslash( $_POST['wpt_price_type'] ?? '' ) );

        if ( ! $product_id || ! in_array( $type, [ 'cash', 'installment' ], true ) ) {
            wp_send_json_error( 'داده‌های نامعتبر.' );
        }

        $current = $this->session_type();

        // Auto-switch all existing cart items to the newly requested type
        if ( ! empty( $current ) && $current !== $type && WC()->cart->get_cart_contents_count() > 0 ) {
            $this->switch_all_items( $type );
        }

        $this->set_session_type( $type );

        $result = WC()->cart->add_to_cart( $product_id, $quantity );

        if ( false === $result ) {
            $notices = wc_get_notices( 'error' );
            $msg     = ! empty( $notices )
                ? wp_strip_all_tags( $notices[0]['notice'] )
                : 'افزودن به سبد با خطا مواجه شد.';
            wc_clear_notices();
            wp_send_json_error( $msg );
        }

        WC()->cart->calculate_totals();

        $fragments  = apply_filters( 'woocommerce_add_to_cart_fragments', [] );
        $cart_hash  = WC()->cart->get_cart_hash();

        wp_send_json_success( [
            'product_id' => $product_id,
            'fragments'  => $fragments,
            'cart_hash'  => $cart_hash,
        ] );
    }
}
