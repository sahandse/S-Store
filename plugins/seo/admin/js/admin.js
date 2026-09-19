/* WSS Admin JS */
(function($){
    'use strict';

    // Flush sitemap
    $(document).on('click', '#wss-flush-sitemap', function(){
        var btn = $(this);
        var msg = $('#wss-sitemap-msg');
        btn.prop('disabled', true).text('در حال بازسازی...');

        $.post(wssAdmin.ajaxUrl, {
            action: 'wss_flush_sitemap',
            nonce:  wssAdmin.nonce
        }, function(res){
            btn.prop('disabled', false).text('بازسازی نقشه سایت');
            if(res.success){
                msg.text('✓ ' + res.data).css('color','#46b450');
            } else {
                msg.text('✗ خطا').css('color','#dc3232');
            }
            setTimeout(function(){ msg.text(''); }, 4000);
        });
    });

    // Tab navigation (generic)
    $(document).on('click', '.wss-nav-tab', function(){
        var tab = $(this).data('tab');
        $('.wss-nav-tab').removeClass('active');
        $('.wss-tab-panel').removeClass('active');
        $(this).addClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Save notice auto-dismiss
    setTimeout(function(){
        $('.notice.is-dismissible').fadeOut(400);
    }, 4000);

    // Confirm deletes
    $(document).on('click', '[data-confirm]', function(e){
        if(!confirm($(this).data('confirm'))){
            e.preventDefault();
        }
    });

    // Character counters for text fields
    function attachCounters(){
        $('[data-maxlen]').each(function(){
            var $el  = $(this);
            var max  = parseInt($el.data('maxlen'));
            var $cnt = $('<span class="wss-char-count"></span>').insertAfter($el);
            function update(){
                var len = $el.val().length;
                $cnt.text(len + '/' + max);
                $cnt.css('color', len > max ? '#dc3232' : (len < max*0.5 ? '#ffba00' : '#46b450'));
            }
            $el.on('input', update);
            update();
        });
    }

    attachCounters();

})(jQuery);
