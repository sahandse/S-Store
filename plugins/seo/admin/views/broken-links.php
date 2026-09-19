<?php if (!defined('ABSPATH')) exit; ?>
<?php $stats = WSS_Broken_Links::instance()->get_stats(); ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-warning"></span> بررسی لینک‌های شکسته</h1>
        <p>پیدا کردن و رفع لینک‌های 404 و خراب در محتوای سایت</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>بررسی خودکار لینک‌های شکسته</h3>
            <p>سایت شما برای لینک‌های ۴۰۴ و خراب اسکن می‌شود — بدون نیاز به تنظیم اضافه.</p>
        </div>
        <button class="button" id="wss-start-scan-hero">
            <span class="dashicons dashicons-search"></span> شروع اسکن خودکار
        </button>
    </div>

    <div class="wss-card" style="margin-bottom:16px">
        <div class="wss-card-header" style="cursor:pointer" onclick="this.nextElementSibling.classList.toggle('open')">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
            <span style="margin-right:auto;font-size:11px;color:var(--wss-muted)">▼</span>
        </div>
        <div class="wss-guide" style="margin:0;border:none;border-radius:0">
            <div class="wss-guide-body" style="padding:0">
                <div class="wss-steps">
                    <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>اسکن را شروع کنید</strong><span>مرحله ۱ لینک‌ها را از محتوای پست‌ها استخراج می‌کند.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>بررسی HTTP انجام می‌شود</strong><span>هر لینک چک می‌شود — ۴۰۴ = شکسته | ۳۰۱/۳۰۲ = ریدایرکت | ۲۰۰ = سالم</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>رفع لینک‌های شکسته</strong><span>از دکمه "رفع" برای جایگزینی URL، یا "حذف لینک" برای برداشتن کامل لینک استفاده کنید.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>اسکن هفتگی خودکار</strong><span>سیستم هر هفته به صورت خودکار اسکن می‌کند — لازم نیست هر بار دستی اجرا کنید.</span></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="wss-stats-bar">
        <div class="wss-stat-box">
            <div class="val"><?php echo $stats['total']; ?></div>
            <div class="lbl">کل لینک‌ها</div>
        </div>
        <div class="wss-stat-box" style="border-color:<?php echo $stats['broken'] ? '#dc3232' : '#e2e8f0'; ?>">
            <div class="val" style="color:<?php echo $stats['broken'] ? '#dc3232' : '#46b450'; ?>"><?php echo $stats['broken']; ?></div>
            <div class="lbl">لینک شکسته</div>
        </div>
        <div class="wss-stat-box">
            <div class="val" style="color:#ffba00"><?php echo $stats['redirect']; ?></div>
            <div class="lbl">ریدایرکت</div>
        </div>
        <div class="wss-stat-box">
            <div class="val" style="color:#46b450"><?php echo $stats['ok']; ?></div>
            <div class="lbl">سالم</div>
        </div>
    </div>

    <!-- Scanner -->
    <div class="wss-card" style="margin-bottom:20px">
        <div class="wss-card-header"><span class="dashicons dashicons-search"></span> اسکن لینک‌ها</div>
        <div style="padding:18px">
            <p>آخرین اسکن: <strong><?php echo esc_html($stats['last_scan']); ?></strong></p>
            <div class="wss-scan-steps">
                <div class="wss-step" id="step-extract">
                    <div class="wss-step-num">۱</div>
                    <div>استخراج لینک‌ها از محتوا</div>
                    <div id="extract-progress" class="wss-step-progress"></div>
                </div>
                <div class="wss-step" id="step-check">
                    <div class="wss-step-num">۲</div>
                    <div>بررسی وضعیت HTTP</div>
                    <div id="check-progress" class="wss-step-progress"></div>
                </div>
            </div>
            <div class="wss-progress-bar" id="scan-progress-bar" style="display:none;margin-top:12px">
                <div class="wss-progress-fill" id="scan-progress-fill" style="width:0%"></div>
                <span id="scan-pct">0%</span>
            </div>
            <div id="scan-status" style="margin:8px 0;font-size:13px;color:#555"></div>
            <button type="button" class="button button-primary" id="wss-start-scan">
                <span class="dashicons dashicons-search"></span> شروع اسکن
            </button>
        </div>
    </div>

    <!-- Results -->
    <?php
    $filter   = sanitize_key($_GET['status'] ?? '');
    $page     = max(1,(int)($_GET['paged'] ?? 1));
    $links    = WSS_Broken_Links::instance()->get_all(25, $page, $filter);
    ?>
    <div class="wss-card">
        <div class="wss-card-header">
            <span class="dashicons dashicons-list-view"></span> نتایج
            <div class="wss-filter-tabs">
                <?php foreach (['' => 'همه', 'broken' => 'شکسته', 'redirect' => 'ریدایرکت', 'ok' => 'سالم'] as $k => $l): ?>
                <a href="<?php echo admin_url('admin.php?page=wss-broken-links&status=' . $k); ?>"
                   class="wss-filter-tab <?php echo $filter === $k ? 'active' : ''; ?>"><?php echo $l; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($links): ?>
        <table class="wss-table wss-table-full">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>متن لینک</th>
                    <th>پست</th>
                    <th>کد</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($links as $link): ?>
            <tr id="link-row-<?php echo $link->id; ?>">
                <td class="wss-td-truncate" style="max-width:250px">
                    <a href="<?php echo esc_url($link->url); ?>" target="_blank" title="<?php echo esc_attr($link->url); ?>">
                        <?php echo esc_html(mb_strimwidth($link->url, 0, 60, '...')); ?>
                    </a>
                </td>
                <td><?php echo esc_html(mb_strimwidth($link->anchor_text, 0, 30, '...')); ?></td>
                <td><a href="<?php echo get_edit_post_link($link->post_id); ?>"><?php echo esc_html(mb_strimwidth($link->post_title ?? '', 0, 30, '...')); ?></a></td>
                <td>
                    <?php if ($link->status_code): ?>
                    <span class="wss-badge <?php echo $link->status_code >= 400 ? 'wss-badge-bad' : ($link->status_code >= 300 ? 'wss-badge-warn' : 'wss-badge-good'); ?>">
                        <?php echo (int)$link->status_code; ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php $sc = ['broken'=>'bad','redirect'=>'warn','ok'=>'good','error'=>'bad','unchecked'=>'']; ?>
                    <span class="wss-badge wss-badge-<?php echo $sc[$link->status] ?? ''; ?>">
                        <?php echo ['broken'=>'شکسته','redirect'=>'ریدایرکت','ok'=>'سالم','error'=>'خطا','unchecked'=>'بررسی نشده'][$link->status] ?? $link->status; ?>
                    </span>
                </td>
                <td>
                    <div class="wss-link-actions">
                        <button class="button button-small wss-fix-link" data-id="<?php echo $link->id; ?>" data-url="<?php echo esc_attr($link->url); ?>">اصلاح</button>
                        <button class="button button-small wss-unlink" data-id="<?php echo $link->id; ?>">حذف لینک</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="wss-empty-state"><span class="dashicons dashicons-yes-alt" style="color:#46b450;opacity:1"></span><p>لینک شکسته‌ای یافت نشد.</p></div>
        <?php endif; ?>
    </div>

    <!-- Fix modal -->
    <div id="wss-fix-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:12px;padding:24px;width:500px;max-width:90vw">
            <h3 style="margin-top:0">اصلاح لینک</h3>
            <p>URL جدید را وارد کنید:</p>
            <input type="text" id="wss-new-url" class="widefat" placeholder="https://...">
            <div style="margin-top:12px;display:flex;gap:10px">
                <button class="button button-primary" id="wss-fix-confirm">ذخیره</button>
                <button class="button" id="wss-fix-cancel">انصراف</button>
            </div>
        </div>
    </div>
</div>

<script>
(function($){
    // ── Scanner ──────────────────────────────────────────────────────────────
    var scanning = false;

    // Hero button triggers the main scan
    $('#wss-start-scan-hero').on('click', function(){
        $('html,body').animate({scrollTop: $('#wss-start-scan').closest('.wss-card').offset().top - 40}, 300);
        setTimeout(function(){ $('#wss-start-scan').trigger('click'); }, 350);
    });

    $('#wss-start-scan').on('click', function(){
        if(scanning) return;
        scanning = true;
        $(this).prop('disabled',true).text('در حال اسکن...');
        $('#scan-progress-bar').show();
        $('#step-extract').addClass('active');
        extractBatch(0);
    });

    function extractBatch(offset){
        $('#scan-status').text('استخراج لینک‌ها... ');
        $.post(wssAdmin.ajaxUrl, {action:'wss_scan_links_batch',nonce:wssAdmin.nonce,offset:offset,mode:'extract'}, function(res){
            if(!res.success){ finishScan('خطا'); return; }
            var d = res.data;
            $('#extract-progress').text('یافت: '+d.found);
            $('#scan-progress-fill').css('width', Math.min(50,d.progress/2)+'%');
            $('#scan-pct').text(Math.min(50,Math.round(d.progress/2))+'%');
            if(d.done){ $('#step-extract').addClass('done'); checkBatch(0); }
            else setTimeout(function(){ extractBatch(d.next); }, 100);
        });
    }

    function checkBatch(offset){
        $('#step-check').addClass('active');
        $('#scan-status').text('بررسی HTTP...');
        $.post(wssAdmin.ajaxUrl, {action:'wss_scan_links_batch',nonce:wssAdmin.nonce,offset:offset,mode:'check'}, function(res){
            if(!res.success){ finishScan('خطا'); return; }
            var d = res.data;
            $('#check-progress').text('بررسی‌شده: '+d.checked+' | باقی‌مانده: '+d.pending);
            var pct = 50 + d.progress/2;
            $('#scan-progress-fill').css('width',pct+'%');
            $('#scan-pct').text(Math.round(pct)+'%');
            if(d.done){ finishScan('✓ اسکن کامل شد! صفحه را رفرش کنید.'); }
            else setTimeout(function(){ checkBatch(d.next); }, 300);
        });
    }

    function finishScan(msg){
        scanning = false;
        $('#wss-start-scan').prop('disabled',false).text('شروع اسکن');
        $('#scan-status').html('<strong>'+msg+'</strong>');
    }

    // ── Fix Link ──────────────────────────────────────────────────────────────
    var fixLinkId = null;

    $(document).on('click','.wss-fix-link', function(){
        fixLinkId = $(this).data('id');
        $('#wss-new-url').val($(this).data('url'));
        $('#wss-fix-modal').css('display','flex');
    });

    $('#wss-fix-cancel').on('click', function(){ $('#wss-fix-modal').hide(); });

    $('#wss-fix-confirm').on('click', function(){
        var newUrl = $('#wss-new-url').val();
        if(!newUrl) return;
        $.post(wssAdmin.ajaxUrl, {action:'wss_fix_link',nonce:wssAdmin.nonce,link_id:fixLinkId,new_url:newUrl}, function(res){
            if(res.success){ location.reload(); }
        });
    });

    // ── Unlink ────────────────────────────────────────────────────────────────
    $(document).on('click','.wss-unlink', function(){
        if(!confirm('لینک از محتوا حذف شود؟ (متن لینک باقی می‌ماند)')) return;
        var id = $(this).data('id');
        $.post(wssAdmin.ajaxUrl, {action:'wss_unlink',nonce:wssAdmin.nonce,link_id:id}, function(res){
            if(res.success) $('#link-row-'+id).fadeOut(300, function(){ $(this).remove(); });
        });
    });
})(jQuery);
</script>

<style>
.wss-scan-steps{display:flex;gap:20px;margin-bottom:15px}
.wss-step{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;flex:1}
.wss-step.active{border-color:#0073aa;background:#f0f7fc}
.wss-step.done{border-color:#46b450;background:#f0faf0}
.wss-step-num{width:28px;height:28px;border-radius:50%;background:#0073aa;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;flex-shrink:0}
.wss-step-progress{margin-right:auto;color:#64748b;font-size:11px}
.wss-filter-tabs{display:flex;gap:4px;margin-right:auto}
.wss-filter-tab{padding:4px 12px;border-radius:20px;font-size:12px;text-decoration:none;color:#64748b;border:1px solid #e2e8f0}
.wss-filter-tab.active{background:#0073aa;color:#fff;border-color:#0073aa}
.wss-link-actions{display:flex;gap:4px}
</style>
