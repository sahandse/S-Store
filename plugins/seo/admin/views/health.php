<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-heart"></span> بررسی سلامت سایت</h1>
        <p>اسکن کامل سایت — خطاها، هشدارها و راهنمای رفع آن‌ها</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>اسکن جامع سایت را اجرا کنید</h3>
            <p>سرور، وردپرس، عملکرد، سئو، امنیت و محتوا — همه یکجا بررسی می‌شوند.</p>
        </div>
        <button class="button" id="wss-run-health">
            <span class="dashicons dashicons-search"></span> شروع بررسی
        </button>
    </div>

    <!-- Summary bar (hidden until results load) -->
    <div id="wss-health-summary" style="display:none;margin-bottom:20px">
        <div class="wss-stats-bar">
            <div class="wss-stat-box" id="hsum-error" style="border-color:#dc2626">
                <div class="val" id="hsum-error-val" style="color:#dc2626">0</div>
                <div class="lbl">خطا</div>
            </div>
            <div class="wss-stat-box" id="hsum-warning" style="border-color:#d97706">
                <div class="val" id="hsum-warning-val" style="color:#d97706">0</div>
                <div class="lbl">هشدار</div>
            </div>
            <div class="wss-stat-box" id="hsum-good" style="border-color:#16a34a">
                <div class="val" id="hsum-good-val" style="color:#16a34a">0</div>
                <div class="lbl">سالم</div>
            </div>
            <div class="wss-stat-box">
                <div class="val" id="hsum-total-val">0</div>
                <div class="lbl">کل بررسی‌ها</div>
            </div>
        </div>
    </div>

    <!-- Spinner -->
    <div id="wss-health-loading" style="display:none;text-align:center;padding:40px">
        <div class="wss-health-spinner"></div>
        <p style="color:#64748b;margin-top:12px">در حال بررسی سایت...</p>
    </div>

    <!-- Results container -->
    <div id="wss-health-results"></div>
</div>

<script>
(function($){
    var icons = {
        good:    '<span class="wss-hi good">✓</span>',
        warning: '<span class="wss-hi warning">!</span>',
        error:   '<span class="wss-hi error">✗</span>'
    };
    var labels = { good: 'سالم', warning: 'هشدار', error: 'خطا' };
    var catIcons = {
        server:      'dashicons-admin-tools',
        wordpress:   'dashicons-wordpress-alt',
        performance: 'dashicons-performance',
        seo:         'dashicons-search',
        security:    'dashicons-shield',
        content:     'dashicons-admin-media'
    };

    $('#wss-run-health').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update wss-spin"></span> در حال اسکن...');
        $('#wss-health-results').html('');
        $('#wss-health-summary').hide();
        $('#wss-health-loading').show();

        $.post(wssAdmin.ajaxUrl, { action: 'wss_health_check', nonce: wssAdmin.nonce }, function(res){
            $('#wss-health-loading').hide();
            btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> اجرای مجدد');

            if ( ! res.success ) {
                $('#wss-health-results').html('<div class="wss-card" style="padding:20px;color:#dc2626">خطا در دریافت نتایج.</div>');
                return;
            }

            var data   = res.data;
            var totals = { good: 0, warning: 0, error: 0 };
            var html   = '';

            $.each(data, function(cat, cat_data){
                var checks  = cat_data.checks;
                var catErr  = 0, catWarn = 0, catGood = 0;
                $.each(checks, function(i, c){
                    totals[c.status]++;
                    if(c.status==='error') catErr++;
                    else if(c.status==='warning') catWarn++;
                    else catGood++;
                });

                // Category badge
                var badge = catErr > 0
                    ? '<span class="wss-cat-badge error">' + catErr + ' خطا</span>'
                    : (catWarn > 0 ? '<span class="wss-cat-badge warning">' + catWarn + ' هشدار</span>'
                    : '<span class="wss-cat-badge good">همه سالم</span>');

                html += '<div class="wss-card wss-health-cat" style="margin-bottom:16px">';
                html += '<div class="wss-card-header wss-health-cat-header" style="cursor:pointer" data-cat="' + cat + '">';
                html += '<span class="dashicons ' + (catIcons[cat]||'dashicons-yes') + '"></span>';
                html += ' ' + cat_data.label;
                html += badge;
                html += '<span class="wss-cat-toggle" style="margin-right:auto;font-size:11px;color:var(--wss-muted)">▼</span>';
                html += '</div>';
                html += '<div class="wss-health-cat-body" id="cat-' + cat + '">';

                $.each(checks, function(i, c){
                    var fix_id = cat + '-' + i;
                    html += '<div class="wss-health-row wss-hrow-' + c.status + '">';
                    html += '<div class="wss-health-left">';
                    html += icons[c.status];
                    html += '<div class="wss-health-info">';
                    html += '<div class="wss-health-title">' + esc(c.title) + '</div>';
                    html += '<div class="wss-health-value">' + esc(c.value) + '</div>';
                    html += '</div></div>';
                    html += '<div class="wss-health-right">';
                    html += '<span class="wss-badge wss-badge-' + (c.status==='good'?'good':(c.status==='warning'?'warn':'bad')) + '">' + labels[c.status] + '</span>';
                    if ( c.status !== 'good' ) {
                        html += ' <button class="button button-small wss-show-fix" data-id="' + fix_id + '">راهنمای رفع</button>';
                        if (c.link) html += ' <a href="' + esc(c.link) + '" class="button button-small button-primary">رفع</a>';
                    }
                    html += '</div>';
                    if ( c.status !== 'good' ) {
                        html += '<div class="wss-fix-guide" id="fix-' + fix_id + '" style="display:none">';
                        html += '<div class="wss-fix-desc">' + esc(c.desc) + '</div>';
                        html += '<pre class="wss-fix-pre">' + esc(c.fix) + '</pre>';
                        html += '</div>';
                    }
                    html += '</div>';
                });

                html += '</div></div>';
            });

            $('#wss-health-results').html(html);

            // Update summary
            $('#hsum-error-val').text(totals.error);
            $('#hsum-warning-val').text(totals.warning);
            $('#hsum-good-val').text(totals.good);
            $('#hsum-total-val').text(totals.error + totals.warning + totals.good);
            $('#wss-health-summary').show();
        }).fail(function(){
            $('#wss-health-loading').hide();
            btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> شروع بررسی');
        });
    });

    // Toggle category body
    $(document).on('click', '.wss-health-cat-header', function(){
        var cat  = $(this).data('cat');
        var body = $('#cat-' + cat);
        var tog  = $(this).find('.wss-cat-toggle');
        body.slideToggle(150);
        tog.text(body.is(':visible') ? '▲' : '▼');
    });

    // Show/hide fix guide
    $(document).on('click', '.wss-show-fix', function(){
        var id   = $(this).data('id');
        var guide = $('#fix-' + id);
        if ( guide.is(':visible') ) {
            guide.slideUp(150);
            $(this).text('راهنمای رفع');
        } else {
            guide.slideDown(150);
            $(this).text('بستن');
        }
    });

    function esc(s){
        return $('<div>').text(String(s||'')).html();
    }
})(jQuery);
</script>

<style>
.wss-health-spinner {
    width: 48px; height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #0073aa;
    border-radius: 50%;
    animation: wss-spin 0.8s linear infinite;
    margin: 0 auto;
}
@keyframes wss-spin { to { transform: rotate(360deg); } }
.wss-spin { animation: wss-spin 0.8s linear infinite; display:inline-block; }

.wss-health-cat-header { cursor: pointer; user-select: none; }
.wss-cat-badge {
    margin-right: 10px;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.wss-cat-badge.error   { background:#fee2e2; color:#dc2626; }
.wss-cat-badge.warning { background:#fef3c7; color:#d97706; }
.wss-cat-badge.good    { background:#dcfce7; color:#16a34a; }

.wss-health-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.wss-health-row:last-child { border-bottom: none; }
.wss-health-row:hover { background: #f8fafc; }

.wss-hrow-error   { border-right: 3px solid #dc2626; }
.wss-hrow-warning { border-right: 3px solid #d97706; }
.wss-hrow-good    { border-right: 3px solid #16a34a; }

.wss-health-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 200px;
}
.wss-health-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.wss-health-title { font-weight: 600; font-size: 13px; color: #1e293b; }
.wss-health-value { font-size: 12px; color: #64748b; margin-top: 2px; }

.wss-hi {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
    flex-shrink: 0;
}
.wss-hi.good    { background: #dcfce7; color: #16a34a; }
.wss-hi.warning { background: #fef3c7; color: #d97706; }
.wss-hi.error   { background: #fee2e2; color: #dc2626; }

.wss-fix-guide {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    margin-top: 8px;
}
.wss-fix-desc { font-size: 13px; color: #475569; margin-bottom: 10px; }
.wss-fix-pre {
    background: #1e293b;
    color: #e2e8f0;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 12px;
    font-family: 'Courier New', monospace;
    direction: ltr;
    text-align: left;
    white-space: pre-wrap;
    margin: 0;
}

@media (max-width: 600px) {
    .wss-health-row { flex-direction: column; align-items: flex-start; }
    .wss-health-right { width: 100%; }
}
</style>
