<?php if (!defined('ABSPATH')) exit; ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-editor-expand"></span> سئو پیشرفته</h1>
        <p>تحلیل رقبا، پیشنهاد لینک داخلی، تحلیل خوانایی و مدیریت تگ Alt</p>
    </div>

    <div class="wss-action-hero">
        <div>
            <h3>ابزارهای پیشرفته سئو</h3>
            <p>تحلیل رقبا، پیشنهاد لینک داخلی، بهبود خوانایی متن، و رفع Alt تصاویر — همه در یک پنل.</p>
        </div>
        <button class="button" id="wss-advseo-guide-toggle">
            <span class="dashicons dashicons-info"></span> راهنمای ابزارها
        </button>
    </div>

    <div class="wss-card" id="wss-advseo-guide" style="margin-bottom:16px;display:none">
        <div class="wss-card-header"><span class="dashicons dashicons-info"></span> راهنمای ابزارهای سئو پیشرفته</div>
        <div style="padding:18px">
            <div class="wss-steps">
                <div class="wss-step"><div class="wss-step-num">۱</div><div class="wss-step-body"><strong>تحلیل رقبا</strong><span>URL یک صفحه رقیب را وارد کنید — عنوان، توضیحات، کلمات کلیدی و ساختار آن را ببینید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۲</div><div class="wss-step-body"><strong>لینک داخلی</strong><span>یک پست انتخاب کنید — سیستم پست‌های مرتبط را پیشنهاد می‌دهد تا لینک داخلی اضافه کنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۳</div><div class="wss-step-body"><strong>خوانایی</strong><span>پست انتخاب کنید — نمره خوانایی، طول جملات و بهبودهای پیشنهادی را مشاهده کنید.</span></div></div>
                <div class="wss-step"><div class="wss-step-num">۴</div><div class="wss-step-body"><strong>تگ Alt</strong><span>تصاویر بدون Alt را پیدا کنید و مستقیماً Alt Text اضافه کنید — بدون رفتن به کتابخانه رسانه.</span></div></div>
            </div>
        </div>
    </div>

    <div class="wss-tab-nav">
        <button class="wss-nav-tab active" data-tab="competitor">تحلیل رقبا</button>
        <button class="wss-nav-tab" data-tab="internal-links">لینک داخلی</button>
        <button class="wss-nav-tab" data-tab="readability">خوانایی</button>
        <button class="wss-nav-tab" data-tab="alt-text">تگ Alt</button>
    </div>

    <!-- ── Competitor ── -->
    <div class="wss-tab-panel active" id="tab-competitor">
        <div class="wss-settings-grid">
            <div class="wss-card wss-card-wide">
                <div class="wss-card-header"><span class="dashicons dashicons-visibility"></span> تحلیل URL رقیب</div>
                <div style="padding:18px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                    <div class="wss-field" style="flex:1;margin:0">
                        <label>آدرس صفحه رقیب</label>
                        <input type="url" id="wss-comp-url" class="widefat" placeholder="https://competitor.com/page">
                    </div>
                    <button class="button button-primary" id="wss-analyze-comp">
                        <span class="dashicons dashicons-search"></span> تحلیل
                    </button>
                </div>
                <div id="wss-comp-loading" style="display:none;padding:20px;text-align:center"><div class="wss-spinner"></div></div>
                <div id="wss-comp-result" style="display:none"></div>
            </div>
        </div>
    </div>

    <!-- ── Internal Links ── -->
    <div class="wss-tab-panel" id="tab-internal-links">
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-admin-links"></span> پیشنهاد لینک داخلی</div>
            <div style="padding:18px">
                <div class="wss-field">
                    <label>انتخاب پست</label>
                    <select id="wss-il-post">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach (get_posts(['posts_per_page'=>100,'post_status'=>'publish','fields'=>'ids']) as $pid): ?>
                        <option value="<?php echo $pid; ?>"><?php echo esc_html(get_the_title($pid)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button-primary" id="wss-get-il">پیشنهاد لینک‌ها</button>
                <div id="wss-il-result" style="margin-top:16px"></div>
            </div>
        </div>
    </div>

    <!-- ── Readability ── -->
    <div class="wss-tab-panel" id="tab-readability">
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-editor-paragraph"></span> تحلیل خوانایی متن</div>
            <div style="padding:18px">
                <div class="wss-field">
                    <label>انتخاب پست</label>
                    <select id="wss-read-post">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach (get_posts(['posts_per_page'=>100,'post_status'=>'publish','fields'=>'ids']) as $pid): ?>
                        <option value="<?php echo $pid; ?>"><?php echo esc_html(get_the_title($pid)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button-primary" id="wss-get-read">تحلیل خوانایی</button>
                <div id="wss-read-result" style="margin-top:16px"></div>
            </div>
        </div>
    </div>

    <!-- ── Alt Text ── -->
    <div class="wss-tab-panel" id="tab-alt-text">
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-format-image"></span> تصاویر بدون Alt Text</div>
            <div style="padding:18px">
                <button class="button button-primary" id="wss-load-alt">بارگذاری تصاویر بدون Alt</button>
            </div>
            <div id="wss-alt-result"></div>
        </div>
    </div>

</div>

<script>
(function($){
    // Guide toggle
    $('#wss-advseo-guide-toggle').on('click', function(){
        var guide = document.getElementById('wss-advseo-guide');
        guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
    });

    // ── Tabs ──────────────────────────────────────────────────────────────────
    $('.wss-nav-tab').on('click', function(){
        var tab=$(this).data('tab');
        $('.wss-nav-tab,.wss-tab-panel').removeClass('active');
        $(this).addClass('active');
        $('#tab-'+tab).addClass('active');
    });

    // ── Competitor ────────────────────────────────────────────────────────────
    $('#wss-analyze-comp').on('click', function(){
        var url = $('#wss-comp-url').val();
        if(!url){alert('URL الزامی است');return;}
        $('#wss-comp-result').hide();
        $('#wss-comp-loading').show();
        $(this).prop('disabled',true);
        var btn = this;
        $.post(wssAdmin.ajaxUrl,{action:'wss_competitor',nonce:wssAdmin.nonce,url:url},function(res){
            $('#wss-comp-loading').hide();
            $(btn).prop('disabled',false);
            if(!res.success){alert(res.data||'خطا');return;}
            var d = res.data;
            var h = '<div class="wss-comp-grid">';
            h += '<div class="wss-comp-item"><strong>عنوان</strong><p>'+esc(d.title)+'</p><small class="'+lenClass(d.title_length,30,60)+'">'+d.title_length+' کاراکتر</small></div>';
            h += '<div class="wss-comp-item"><strong>توضیحات</strong><p>'+esc(d.description)+'</p><small class="'+lenClass(d.desc_length,100,160)+'">'+d.desc_length+' کاراکتر</small></div>';
            h += '<div class="wss-comp-item"><strong>H1</strong>'+(d.h1.length?'<p>'+esc(d.h1[0])+'</p>':'<p class="td-bad">ندارد</p>')+'</div>';
            h += '<div class="wss-comp-item"><strong>آمار</strong><ul>';
            h += '<li>کلمه: '+d.word_count+'</li>';
            h += '<li>لینک داخلی: '+d.links_internal+'</li>';
            h += '<li>لینک خارجی: '+d.links_external+'</li>';
            h += '<li>تصویر: '+d.images_total+' ('+d.images_no_alt+' بدون alt)</li>';
            h += '</ul></div>';
            h += '<div class="wss-comp-item"><strong>Schema</strong><p>'+(d.schema_types.join(', ')||'ندارد')+'</p></div>';
            h += '<div class="wss-comp-item wss-comp-wide"><strong>کلمات کلیدی اصلی</strong><div class="wss-kw-tags">';
            d.top_keywords.forEach(function(k){ h += '<span class="wss-kw-tag">'+esc(k)+'</span>'; });
            h += '</div></div>';
            if(d.h2.length){
                h += '<div class="wss-comp-item wss-comp-wide"><strong>هدینگ‌های H2</strong><ol>';
                d.h2.forEach(function(t){ h += '<li>'+esc(t)+'</li>'; });
                h += '</ol></div>';
            }
            h += '</div>';
            $('#wss-comp-result').html(h).show();
        });
    });

    function esc(s){ return $('<span>').text(s||'').html(); }
    function lenClass(l, min, max){ return l>=min&&l<=max?'td-good':(l>0?'wss-warn-text':'td-bad'); }

    // ── Internal Links ────────────────────────────────────────────────────────
    $('#wss-get-il').on('click', function(){
        var pid = $('#wss-il-post').val();
        if(!pid){alert('پست انتخاب کنید');return;}
        $(this).prop('disabled',true).text('در حال بارگذاری...');
        var btn = this;
        $.post(wssAdmin.ajaxUrl,{action:'wss_internal_links',nonce:wssAdmin.nonce,post_id:pid},function(res){
            $(btn).prop('disabled',false).text('پیشنهاد لینک‌ها');
            if(!res.success||!res.data.length){$('#wss-il-result').html('<p>پیشنهادی یافت نشد.</p>');return;}
            var h='<table class="wss-table wss-table-full"><thead><tr><th>پست پیشنهادی</th><th>کلمه مرتبط</th><th>عملیات</th></tr></thead><tbody>';
            res.data.forEach(function(s){
                h+='<tr><td><strong>'+esc(s.title)+'</strong></td><td><span class="wss-kw-tag">'+esc(s.keyword)+'</span></td>';
                h+='<td><a href="'+s.url+'" target="_blank" class="button button-small">مشاهده</a> ';
                h+='<a href="#" class="button button-small wss-copy-link" data-url="'+esc(s.url)+'" data-title="'+esc(s.title)+'">کپی لینک</a></td></tr>';
            });
            h+='</tbody></table>';
            $('#wss-il-result').html(h);
        });
    });

    $(document).on('click','.wss-copy-link',function(e){
        e.preventDefault();
        var url=$(this).data('url'), title=$(this).data('title');
        var link = '<a href="'+url+'">'+title+'</a>';
        navigator.clipboard.writeText(link).then(function(){alert('لینک کپی شد: '+link);});
    });

    // ── Readability ───────────────────────────────────────────────────────────
    $('#wss-get-read').on('click', function(){
        var pid = $('#wss-read-post').val();
        if(!pid){alert('پست انتخاب کنید');return;}
        $(this).prop('disabled',true).text('در حال تحلیل...');
        var btn = this;
        $.post(wssAdmin.ajaxUrl,{action:'wss_readability',nonce:wssAdmin.nonce,post_id:pid},function(res){
            $(btn).prop('disabled',false).text('تحلیل خوانایی');
            if(!res.success){return;}
            var d = res.data;
            var gradeColors={'A':'#46b450','B':'#00a0d2','C':'#ffba00','D':'#dc3232'};
            var h='<div class="wss-read-header"><div class="wss-read-score" style="background:'+gradeColors[d.grade]+'">'+d.grade+'</div>';
            h+='<div class="wss-read-stats"><span>'+d.word_count+' کلمه</span><span>'+d.sentence_count+' جمله</span><span>'+d.reading_time+' دقیقه مطالعه</span><span>میانگین جمله: '+d.avg_sentence_len+' کلمه</span></div></div>';
            h+='<div class="wss-checks-list">';
            d.checks.forEach(function(c){
                var icon=c.status==='good'?'✓':(c.status==='warn'?'!':'✗');
                h+='<div class="wss-check-row '+c.status+'"><span class="wss-ci">'+icon+'</span><span>'+c.label+'</span></div>';
            });
            h+='</div>';
            $('#wss-read-result').html(h);
        });
    });

    // ── Alt Text ──────────────────────────────────────────────────────────────
    $('#wss-load-alt').on('click', function(){
        $(this).prop('disabled',true).text('در حال بارگذاری...');
        var btn=this;
        $.post(wssAdmin.ajaxUrl,{action:'wss_alt_report',nonce:wssAdmin.nonce},function(res){
            $(btn).prop('disabled',false).text('بارگذاری تصاویر بدون Alt');
            if(!res.success||!res.data.length){$('#wss-alt-result').html('<div class="wss-empty-state"><span class="dashicons dashicons-yes-alt" style="color:#46b450;opacity:1"></span><p>همه تصاویر دارای Alt هستند!</p></div>');return;}
            var h='<table class="wss-table wss-table-full"><thead><tr><th>تصویر</th><th>عنوان</th><th>Alt جدید</th><th>ذخیره</th></tr></thead><tbody>';
            res.data.forEach(function(img){
                h+='<tr><td>'+(img.thumb?'<img src="'+img.thumb+'" style="width:50px;height:50px;object-fit:cover;border-radius:4px">':'—')+'</td>';
                h+='<td>'+esc(img.title)+'</td>';
                h+='<td><input type="text" class="wss-alt-input" data-id="'+img.id+'" value="" style="width:100%"></td>';
                h+='<td><button class="button button-small wss-save-alt" data-id="'+img.id+'">ذخیره</button></td></tr>';
            });
            h+='</tbody></table>';
            $('#wss-alt-result').html(h);
        });
    });

    $(document).on('click','.wss-save-alt',function(){
        var id=$(this).data('id');
        var alt=$('.wss-alt-input[data-id='+id+']').val();
        var btn=$(this).prop('disabled',true).text('...');
        $.post(wssAdmin.ajaxUrl,{action:'wss_fix_alt',nonce:wssAdmin.nonce,attachment_id:id,alt:alt},function(res){
            btn.prop('disabled',false).text(res.success?'✓':'✗');
        });
    });

})(jQuery);
</script>

<style>
.wss-comp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;padding:18px}
.wss-comp-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px}
.wss-comp-item strong{display:block;font-size:13px;margin-bottom:6px;color:#0073aa}
.wss-comp-item p{margin:0;font-size:13px;color:#1e293b}
.wss-comp-item small{font-size:11px}
.wss-comp-item ol,.wss-comp-item ul{padding-right:18px;font-size:13px;margin:0}
.wss-comp-wide{grid-column:1/-1}
.wss-kw-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.wss-kw-tag{background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:12px;font-size:12px}
.wss-warn-text{color:#ffba00}
.wss-read-header{display:flex;align-items:center;gap:16px;padding:16px 18px;border-bottom:1px solid #e2e8f0}
.wss-read-score{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;flex-shrink:0}
.wss-read-stats{display:flex;flex-wrap:wrap;gap:12px}
.wss-read-stats span{background:#f1f5f9;padding:4px 12px;border-radius:12px;font-size:13px}
.wss-spinner{width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:#0073aa;border-radius:50%;animation:wss-spin 0.8s linear infinite;margin:0 auto 10px}
@keyframes wss-spin{to{transform:rotate(360deg)}}
</style>
