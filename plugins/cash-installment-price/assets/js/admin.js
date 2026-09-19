/* WPT Admin — live preview & tab switcher */
( function () {
    'use strict';

    var d = wptAdmin;

    function fmt( num ) {
        var n = Math.round( num );
        var s = n.toString().replace( /\B(?=(\d{3})+(?!\d))/g, d.thousandSep || ',' );
        return d.position === 'left'       ? d.currency + s
             : d.position === 'left_space' ? d.currency + ' ' + s
             : d.position === 'right'      ? s + d.currency
             :                               s + ' ' + d.currency; // right_space
    }

    function setText( id, val ) {
        var el = document.getElementById( id );
        if ( el ) el.textContent = val;
    }

    function updatePreview( markupVal ) {
        var markup  = parseFloat( markupVal );
        if ( isNaN( markup ) || markup < 0 ) markup = 0;

        var base    = parseFloat( d.cashPrice ) || 1000000;
        var install = base * ( 1 + markup / 100 );
        var pct     = '+' + markup + '٪';

        // Tab 1: price widget
        setText( 'wpt-prev-cash',  fmt( base ) );
        setText( 'wpt-prev-inst',  fmt( install ) );
        setText( 'wpt-prev-badge', pct );

        // Tab 2: modal
        setText( 'wpt-prev-m-cash', fmt( base ) );
        setText( 'wpt-prev-m-inst', fmt( install ) );

        // Tab 3: cart switcher
        setText( 'wpt-prev-cs-pct',   pct );
        setText( 'wpt-prev-cs-pct2',  pct );
        setText( 'wpt-prev-cs-badge', pct );

        // Tab 4: checkout notice
        setText( 'wpt-prev-co-badge', pct );
    }

    // ── markup input live update ──────────────────
    var markupInput = document.getElementById( 'wpt_markup' );
    if ( markupInput ) {
        markupInput.addEventListener( 'input', function () {
            updatePreview( this.value );
        } );
        updatePreview( markupInput.value );
    }

    // ── tab switching ─────────────────────────────
    var tabs   = document.querySelectorAll( '.wpt-ptab' );
    var panels = document.querySelectorAll( '.wpt-ptab-panel' );

    tabs.forEach( function ( tab ) {
        tab.addEventListener( 'click', function () {
            tabs.forEach( function ( t ) { t.classList.remove( 'wpt-ptab--active' ); } );
            panels.forEach( function ( p ) { p.style.display = 'none'; } );

            tab.classList.add( 'wpt-ptab--active' );
            var panel = document.querySelector(
                '.wpt-ptab-panel[data-panel="' + tab.getAttribute( 'data-tab' ) + '"]'
            );
            if ( panel ) panel.style.display = '';
        } );
    } );

} )();
