<?php if (!defined('ABSPATH')) exit; ?>
<?php
$keywords = WSS_Rank_Tracker::instance()->get_all();
$stats    = WSS_Rank_Tracker::instance()->get_stats();
$api_key  = get_option('wss_serp_api_key', '');
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-chart-line"></span> ردیابی رتبه کلمات کلیدی</h1>
        <p>پیگیری رتبه سایت در گوگل برای کلمات کلیدی هدف</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>رتبه سایت خود را در گوگل دنبال کنید</h3>
            <p>کلمات کلیدی اضافه کنید و هر هفته رتبه را بررسی کنید — روند صعود یا نزول را ببینید.</p>
        </div>
        <button class="button" id="wss-rank-guide-toggle">
            <span class="dashicons dashicons-info"></span> راهنمای گام‌به‌گام
        </button>
    </div>

    <div class="wss-card" id="wss-rank-guide" style="margin-bottom:16px;display:none">
        <div class="wss-card-header"><span class="dashicons dashicons-info"></span> راهنمای ردیابی رتبه</div>
        <div style="padding:18px">
            <div class="wss-steps">
                <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>کلمه کلیدی اضافه کنید</strong><span>کلمه کلیدی هدف و URL صفحه مرتبط را وارد کنید. کشور ir = ایران.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>رتبه را بررسی کنید</strong><span>دکمه "بررسی" کنار هر کلمه یا دکمه "بررسی خودکار همه" را بزنید. نیاز به API key دارد.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>رتبه دستی هم می‌توانید وارد کنید</strong><span>از Google Search Console رتبه صفحات را ببینید و در فرم دستی وارد کنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>نمودار تغییرات را ببینید</strong><span>در جدول، نمودار کوچک تغییرات رتبه در طول زمان نمایش داده می‌شود.</span></div></div>
            </div>
        </div>
    </div>

    <div class="wss-stats-bar">
        <div class="wss-stat-box"><div class="val"><?php echo $stats['total']; ?></div><div class="lbl">کل کلمات</div></div>
        <div class="wss-stat-box good"><div class="val"><?php echo $stats['top3']; ?></div><div class="lbl">جایگاه ۱-۳</div></div>
        <div class="wss-stat-box"><div class="val"><?php echo $stats['top10']; ?></div><div class="lbl">جایگاه ۱-۱۰</div></div>
        <div class="wss-stat-box" style="border-color:#46b450"><div class="val" style="color:#46b450"><?php echo $stats['improved']; ?></div><div class="lbl">بهبودیافته</div></div>
    </div>

    <?php if (!$api_key): ?>
    <div class="wss-info-box" style="margin-bottom:20px">
        برای چک خودکار رتبه، کلید API از <a href="https://valueserp.com" target="_blank">ValueSERP</a> (رایگان تا 100 درخواست/ماه) دریافت کنید:
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline">
            <input type="hidden" name="action" value="wss_save_serp_key">
            <?php wp_nonce_field('wss_save_serp_key'); ?>
            <input type="text" name="api_key" placeholder="API Key..." style="width:220px;margin:0 8px">
            <button type="submit" class="button">ذخیره</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="wss-settings-grid">

        <!-- Add Keyword -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-plus-alt"></span> افزودن کلمه کلیدی</div>
            <div style="padding:18px">
                <div class="wss-field">
                    <label>کلمه کلیدی</label>
                    <input type="text" id="wss-kw-input" class="widefat" placeholder="خرید لپتاپ">
                </div>
                <div class="wss-field">
                    <label>URL هدف</label>
                    <input type="url" id="wss-kw-url" class="widefat" placeholder="<?php echo esc_url(home_url('/')); ?>">
                </div>
                <div class="wss-row">
                    <div class="wss-field">
                        <label>موتور جستجو</label>
                        <select id="wss-kw-engine">
                            <option value="google">Google</option>
                            <option value="bing">Bing</option>
                        </select>
                    </div>
                    <div class="wss-field">
                        <label>کشور</label>
                        <input type="text" id="wss-kw-country" value="ir" style="width:60px">
                    </div>
                </div>
                <div class="wss-field">
                    <label>یادداشت</label>
                    <input type="text" id="wss-kw-notes" class="widefat" placeholder="اختیاری">
                </div>
                <button class="button button-primary" id="wss-add-kw">افزودن کلمه کلیدی</button>
            </div>
        </div>

        <!-- Manual rank update -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-edit"></span> بروزرسانی دستی رتبه</div>
            <div style="padding:18px">
                <p class="wss-help">رتبه را از Google Search Console یا بررسی دستی وارد کنید:</p>
                <div class="wss-field">
                    <label>کلمه کلیدی</label>
                    <select id="wss-update-kw">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($keywords as $kw): ?>
                        <option value="<?php echo $kw->id; ?>"><?php echo esc_html($kw->keyword); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wss-field">
                    <label>رتبه فعلی</label>
                    <input type="number" id="wss-rank-val" min="1" max="200" style="width:100px">
                </div>
                <button class="button" id="wss-update-rank">ذخیره رتبه</button>
                <span id="wss-rank-msg" style="margin-right:8px;font-size:12px"></span>
            </div>
        </div>

    </div>

    <!-- Keywords Table -->
    <div class="wss-card" style="margin-top:20px">
        <div class="wss-card-header">
            <span class="dashicons dashicons-list-view"></span> کلمات کلیدی
            <button class="button button-small" id="wss-check-all" style="margin-right:auto">بررسی خودکار همه</button>
        </div>
        <?php if ($keywords): ?>
        <table class="wss-table wss-table-full" id="wss-kw-table">
            <thead>
                <tr>
                    <th>کلمه کلیدی</th>
                    <th>رتبه فعلی</th>
                    <th>تغییر</th>
                    <th>بهترین</th>
                    <th>موتور</th>
                    <th>آخرین بررسی</th>
                    <th>نمودار</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($keywords as $kw):
                $change = ($kw->prev_rank && $kw->current_rank) ? $kw->prev_rank - $kw->current_rank : null;
                $history = json_decode($kw->history ?: '[]', true);
            ?>
            <tr id="kw-row-<?php echo $kw->id; ?>">
                <td>
                    <strong><?php echo esc_html($kw->keyword); ?></strong>
                    <?php if ($kw->url): ?><br><a href="<?php echo esc_url($kw->url); ?>" target="_blank" class="wss-link" style="font-size:11px"><?php echo esc_html(parse_url($kw->url, PHP_URL_PATH)); ?></a><?php endif; ?>
                    <?php if ($kw->notes): ?><br><small><?php echo esc_html($kw->notes); ?></small><?php endif; ?>
                </td>
                <td>
                    <?php if ($kw->current_rank): ?>
                    <span class="wss-rank-num <?php echo $kw->current_rank<=3?'top3':($kw->current_rank<=10?'top10':''); ?>">
                        #<?php echo (int)$kw->current_rank; ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if ($change !== null): ?>
                    <span class="wss-rank-change <?php echo $change > 0 ? 'up' : ($change < 0 ? 'down' : 'same'); ?>">
                        <?php if($change>0): ?>▲ +<?php echo $change;
                        elseif($change<0): ?>▼ <?php echo $change;
                        else: ?>—<?php endif; ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?php echo $kw->best_rank ? '#'.(int)$kw->best_rank : '—'; ?></td>
                <td><?php echo esc_html(strtoupper($kw->search_engine)); ?></td>
                <td style="font-size:11px"><?php echo $kw->last_checked ? esc_html(substr($kw->last_checked,0,10)) : '—'; ?></td>
                <td>
                    <?php if (count($history) >= 2): ?>
                    <canvas class="wss-mini-chart" data-history='<?php echo esc_attr(json_encode(array_slice($history,-15))); ?>' width="100" height="30"></canvas>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <button class="button button-small wss-serp-check" data-id="<?php echo $kw->id; ?>">بررسی</button>
                    <button class="button button-small wss-delete-kw" data-id="<?php echo $kw->id; ?>">حذف</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="wss-empty-state"><span class="dashicons dashicons-chart-line"></span><p>هنوز کلمه کلیدی اضافه نشده.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
(function($){
    // Guide toggle
    $('#wss-rank-guide-toggle').on('click', function(){
        var guide = document.getElementById('wss-rank-guide');
        guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
    });

    // ── Add keyword ───────────────────────────────────────────────────────────
    $('#wss-add-kw').on('click', function(){
        var kw = $('#wss-kw-input').val();
        if(!kw){alert('کلمه کلیدی الزامی است');return;}
        $.post(wssAdmin.ajaxUrl,{
            action:'wss_add_keyword',nonce:wssAdmin.nonce,
            keyword:kw, url:$('#wss-kw-url').val(),
            engine:$('#wss-kw-engine').val(), country:$('#wss-kw-country').val(),
            notes:$('#wss-kw-notes').val()
        },function(res){
            if(res.success){ location.reload(); }
            else alert(res.data||'خطا');
        });
    });

    // ── Update rank ───────────────────────────────────────────────────────────
    $('#wss-update-rank').on('click', function(){
        var id=$('#wss-update-kw').val(), rank=$('#wss-rank-val').val();
        if(!id||!rank){alert('کلمه و رتبه را وارد کنید');return;}
        $.post(wssAdmin.ajaxUrl,{action:'wss_update_rank',nonce:wssAdmin.nonce,id:id,rank:rank},function(res){
            if(res.success){
                $('#wss-rank-msg').text('✓ رتبه ذخیره شد').css('color','#46b450');
                setTimeout(function(){ location.reload(); }, 1000);
            }
        });
    });

    // ── SERP check ────────────────────────────────────────────────────────────
    $(document).on('click','.wss-serp-check',function(){
        var id=$(this).data('id');
        $(this).prop('disabled',true).text('...');
        var btn=$(this);
        $.post(wssAdmin.ajaxUrl,{action:'wss_check_serp',nonce:wssAdmin.nonce,id:id},function(res){
            btn.prop('disabled',false).text('بررسی');
            if(res.success){ alert('رتبه: '+(res.data.rank||'یافت نشد')); location.reload(); }
        });
    });

    // ── Delete keyword ────────────────────────────────────────────────────────
    $(document).on('click','.wss-delete-kw',function(){
        if(!confirm('حذف شود؟')) return;
        var id=$(this).data('id');
        $.post(wssAdmin.ajaxUrl,{action:'wss_delete_keyword',nonce:wssAdmin.nonce,id:id},function(res){
            if(res.success) $('#kw-row-'+id).fadeOut(300,function(){$(this).remove();});
        });
    });

    // ── Check all ─────────────────────────────────────────────────────────────
    $('#wss-check-all').on('click', function(){
        $('.wss-serp-check').each(function(i){
            var btn=$(this), id=btn.data('id');
            setTimeout(function(){
                btn.prop('disabled',true).text('...');
                $.post(wssAdmin.ajaxUrl,{action:'wss_check_serp',nonce:wssAdmin.nonce,id:id},function(){
                    btn.prop('disabled',false).text('بررسی');
                });
            }, i*2000);
        });
    });

    // ── Mini charts ───────────────────────────────────────────────────────────
    document.querySelectorAll('.wss-mini-chart').forEach(function(canvas){
        var history = JSON.parse(canvas.dataset.history||'[]');
        if(!history.length) return;
        var ctx = canvas.getContext('2d');
        var ranks = history.map(function(h){ return h.rank; });
        var min = Math.max(1, Math.min.apply(null,ranks)-2);
        var max = Math.max.apply(null,ranks)+2;
        var w = canvas.width, h = canvas.height, n = ranks.length;

        ctx.strokeStyle = '#0073aa';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ranks.forEach(function(r,i){
            var x = (i/(n-1||1))*w;
            var y = h - ((max-r)/(max-min||1))*h;
            i===0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
        });
        ctx.stroke();
    });
})(jQuery);
</script>

<style>
.wss-rank-num{font-size:18px;font-weight:700;color:#1e293b}
.wss-rank-num.top3{color:#f59e0b}
.wss-rank-num.top10{color:#0073aa}
.wss-rank-change{font-weight:700;font-size:13px}
.wss-rank-change.up{color:#46b450}
.wss-rank-change.down{color:#dc3232}
.wss-rank-change.same{color:#64748b}
.wss-row{display:flex;gap:16px}
.wss-row .wss-field{flex:1}
</style>
