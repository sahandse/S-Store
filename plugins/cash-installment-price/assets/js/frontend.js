/* global jQuery, wptData */
( function ( $ ) {
    'use strict';

    var modal         = null;
    var pendingForm   = null;
    var pendingButton = null;

    /* ─────────────────────────────────────────────────
       Bootstrap
    ───────────────────────────────────────────────── */
    $( function () {
        modal = $( '#wpt-modal' );

        bindVariationPrices();
        bindProductPage();   // runs in both inline and modal modes

        if ( ! modal.length ) return;
        bindShopLoop();
        bindModal();
        bindCartSwitcher();
    } );

    /* ─────────────────────────────────────────────────
       قیمت‌گذار: فرمت عدد با تنظیمات WooCommerce
    ───────────────────────────────────────────────── */
    function formatPrice( raw ) {
        var f   = wptData.priceFormat;
        var num = parseFloat( raw );
        if ( isNaN( num ) || num <= 0 ) return '—';

        var dec   = parseInt( f.decimals, 10 );
        var fixed = num.toFixed( dec );
        var parts = fixed.split( '.' );

        // thousand separator
        parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, f.thousandSep || ',' );
        var numStr = dec > 0 ? parts.join( f.decimalSep || '.' ) : parts[ 0 ];

        var sym = f.currency || '';
        switch ( ( f.position || 'right_space' ) ) {
            case 'left':       return sym + numStr;
            case 'left_space': return sym + ' ' + numStr;
            case 'right':      return numStr + sym;
            default:           return numStr + ' ' + sym; // right_space
        }
    }

    /* ─────────────────────────────────────────────────
       آپدیت ویجت قیمت (single product)
    ───────────────────────────────────────────────── */
    function updatePriceWidget( cashRaw ) {
        var markup     = parseFloat( wptData.markup );
        var installRaw = cashRaw * ( 1 + markup / 100 );

        var cashFmt    = formatPrice( cashRaw );
        var installFmt = formatPrice( installRaw );

        var $widget = $( '#wpt-price-widget' );
        $widget.addClass( 'wpt-pw-updating' );

        $( '#wpt-pw-cash-amount' ).text( cashFmt );
        $( '#wpt-pw-inst-amount' ).text( installFmt );

        // sync داده‌های hidden field
        $( '#wpt_price_type_field' )
            .attr( 'data-cash-price',        cashRaw )
            .attr( 'data-install-price',     installRaw )
            .attr( 'data-cash-formatted',    cashFmt )
            .attr( 'data-install-formatted', installFmt );

        // sync inline selector prices
        $( '#wpt-inline-cash-price' ).text( cashFmt );
        $( '#wpt-inline-inst-price' ).text( installFmt );

        setTimeout( function () { $widget.removeClass( 'wpt-pw-updating' ); }, 280 );
    }

    function showPricePlaceholder() {
        $( '#wpt-pw-cash-amount, #wpt-pw-inst-amount' ).html( '<span class="wpt-ph">—</span>' );
        $( '#wpt_price_type_field' )
            .attr( 'data-cash-price', '' )
            .attr( 'data-install-price', '' )
            .attr( 'data-cash-formatted', '' )
            .attr( 'data-install-formatted', '' );
    }

    /* ─────────────────────────────────────────────────
       محصول متغیر: live update با found_variation
    ───────────────────────────────────────────────── */
    function bindVariationPrices() {
        var $form   = $( 'form.variations_form' );
        var $widget = $( '#wpt-price-widget' );
        if ( ! $form.length || ! $widget.length ) return;

        $form
            .on( 'found_variation', function ( e, variation ) {
                if ( ! variation || ! variation.display_price ) return;
                updatePriceWidget( parseFloat( variation.display_price ) );
            } )
            .on( 'reset_data', function () {
                // برگشت به قیمت پیش‌فرض (اولین ویژگی موجود / حداقل قیمت)
                var baseCash = parseFloat( $widget.data( 'base-cash' ) );
                if ( baseCash > 0 ) {
                    updatePriceWidget( baseCash );
                }
            } );
    }

    /* ─────────────────────────────────────────────────
       صفحه محصول — افزودن به سبد
    ───────────────────────────────────────────────── */
    function bindProductPage() {
        // Bind directly to the button element so our handler fires before
        // WooCommerce's document-level AJAX add-to-cart delegation handler.
        var $addBtn = $( 'form.cart .single_add_to_cart_button' );
        if ( $addBtn.length ) {
            $addBtn.on( 'click', handleAddToCartClick );
        }
        // Fallback: also catch via delegation for themes that render the button late
        $( document ).on( 'click', 'form.cart .single_add_to_cart_button', handleAddToCartClick );

        // Reset hidden field after WC AJAX add-to-cart completes so next add asks again
        $( document.body ).on( 'added_to_cart', function () {
            $( '#wpt_price_type_field' ).val( '' );
        } );

        $( document ).on( 'click', '.wpt-inline-btn', function () {
            var type    = $( this ).data( 'type' );
            var $inline = $( '#wpt-inline-selector' );
            $inline.slideUp( 150 );
            setTimeout( function () { proceedWithType( type ); }, 160 );
        } );
    }

    function handleAddToCartClick( e ) {
        var $form   = $( this ).closest( 'form.cart' );
        var $hidden = $form.find( '#wpt_price_type_field' );
        if ( ! $hidden.length ) return;
        if ( $hidden.val() ) return; // type already chosen — let WC proceed normally

        e.preventDefault();
        e.stopImmediatePropagation(); // block WC's own click/AJAX handler this round
        pendingForm = $form;

        if ( wptData.selectorMode === 'modal' ) {
            openModal( $hidden );
        } else {
            var $inline = $( '#wpt-inline-selector' );
            if ( $inline.length ) {
                if ( $inline.is( ':visible' ) ) {
                    $inline.slideUp( 150 );
                    pendingForm = null;
                } else {
                    syncInlinePrices( $hidden );
                    $inline.slideDown( 220 );
                }
            } else {
                openModal( $hidden );
            }
        }
    }

    function syncInlinePrices( $hidden ) {
        var cashFmt    = $hidden.data( 'cash-formatted' )    || '—';
        var installFmt = $hidden.data( 'install-formatted' ) || '—';
        $( '#wpt-inline-cash-price' ).text( cashFmt );
        $( '#wpt-inline-inst-price' ).text( installFmt );
    }

    /* ─────────────────────────────────────────────────
       لیست محصولات — دکمه افزودن به سبد
    ───────────────────────────────────────────────── */
    function bindShopLoop() {
        $( document ).on( 'click', '.add_to_cart_button[data-cash-price]', function ( e ) {
            e.preventDefault();
            e.stopImmediatePropagation();
            pendingButton = $( this );
            openModal( null );
        } );
    }

    /* ─────────────────────────────────────────────────
       مودال
    ───────────────────────────────────────────────── */
    function openModal( $hidden ) {
        var cartType = wptData.cartType;

        // قیمت‌ها: از hidden field یا از data-* دکمه loop
        var cashFmt, installFmt;
        if ( $hidden && $hidden.length ) {
            cashFmt    = $hidden.data( 'cash-formatted' );
            installFmt = $hidden.data( 'install-formatted' );
        } else if ( pendingButton ) {
            cashFmt    = pendingButton.data( 'cash-formatted' );
            installFmt = pendingButton.data( 'install-formatted' );
        }

        $( '#wpt-modal-cash-price' ).text( cashFmt || '' );
        $( '#wpt-modal-install-price' ).text( installFmt || '' );

        // ریست — همیشه هر دو دکمه نمایش داده شوند
        modal.find( '.wpt-type-btn' ).prop( 'disabled', false ).removeClass( 'wpt-selected wpt-current' ).show();
        modal.find( '.wpt-modal-options' ).removeClass( 'wpt-single' );
        $( '#wpt-conflict-msg' ).hide().text( '' );

        // نوع فعلی سبد را با استایل متفاوت نشان بده (بدون مخفی کردن گزینه دیگر)
        if ( cartType ) {
            modal.find( '.wpt-type-btn[data-type="' + cartType + '"]' ).addClass( 'wpt-current' );
        }

        modal.fadeIn( 180 );
    }

    function bindModal() {
        modal.on( 'click', '.wpt-type-btn', function () {
            var $btn = $( this );
            if ( $btn.prop( 'disabled' ) ) return;

            modal.find( '.wpt-type-btn' ).removeClass( 'wpt-selected' );
            $btn.addClass( 'wpt-selected' );

            var type = $btn.data( 'type' );
            setTimeout( function () {
                modal.hide();
                proceedWithType( type );
            }, 150 );
        } );

        modal.on( 'click', '#wpt-modal-cancel, .wpt-modal-overlay', closeModal );

        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && modal.is( ':visible' ) ) closeModal();
        } );
    }

    function closeModal() {
        modal.fadeOut( 160 );
        pendingForm   = null;
        pendingButton = null;
    }

    function proceedWithType( type ) {
        wptData.cartType = type;

        if ( pendingForm ) {
            var $form = pendingForm;
            pendingForm = null;
            $form.find( '#wpt_price_type_field' ).val( type );
            // Re-trigger the Add to Cart button. Our handler returns early (val is set),
            // so WC's AJAX/submit handler takes over from here.
            var $btn = $form.find( '.single_add_to_cart_button' );
            if ( $btn.length ) {
                $btn.trigger( 'click' );
            } else {
                $form.trigger( 'submit' );
            }
        } else if ( pendingButton ) {
            var $btn2 = pendingButton;
            pendingButton = null;
            addToCartAjax( $btn2, type );
        }
    }

    function addToCartAjax( $btn, type ) {
        $btn.addClass( 'loading' );
        $.ajax( {
            url:  wptData.ajaxUrl,
            type: 'POST',
            data: {
                action:         'wpt_loop_add_to_cart',
                nonce:          wptData.nonce,
                product_id:     $btn.data( 'product_id' ),
                quantity:       $btn.data( 'quantity' ) || 1,
                wpt_price_type: type,
            },
            success: function ( res ) {
                if ( res.success ) {
                    $btn.removeClass( 'loading' ).addClass( 'added' );
                    // Refresh mini-cart and trigger WC added_to_cart event
                    $( document.body ).trigger( 'added_to_cart', [ res.data.fragments || {}, res.data.cart_hash || '', $btn ] );
                    $( document.body ).trigger( 'wc_fragment_refresh' );
                } else {
                    $btn.removeClass( 'loading' );
                    alert( res.data || 'خطا در افزودن به سبد.' );
                }
            },
            error: function () { $btn.removeClass( 'loading' ); },
        } );
    }

    /* ─────────────────────────────────────────────────
       سبد خرید — تغییر کلی نوع پرداخت
    ───────────────────────────────────────────────── */
    function bindCartSwitcher() {
        $( document ).on( 'click', '.wpt-switch-btn', function () {
            var $btn = $( this );
            if ( $btn.hasClass( 'wpt-active' ) ) return;

            var type = $btn.data( 'type' );
            $( '.wpt-switch-btn' ).addClass( 'wpt-loading' ).text( wptData.i18n.switching );

            $.post( wptData.ajaxUrl, {
                action: 'wpt_switch_type',
                nonce:  wptData.nonce,
                type:   type,
            }, function ( res ) {
                if ( res.success ) {
                    wptData.cartType = type;
                    window.location.reload();
                } else {
                    $( '.wpt-switch-btn' ).removeClass( 'wpt-loading' );
                    alert( res.data || 'خطا در تغییر نوع پرداخت.' );
                }
            } );
        } );
    }

} )( jQuery );
