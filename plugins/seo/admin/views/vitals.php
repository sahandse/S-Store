<?php if (!defined('ABSPATH')) exit; ?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-chart-area"></span> Core Web Vitals</h1>
        <p>گزارش کامل سرعت و Core Web Vitals از Google PageSpeed Insights</p>
    </div>

    <?php $wm = (array) get_option('wss_webmaster', []); ?>

    <div class="wss-action-hero">
        <div>
            <h3>تحلیل سرعت صفحه اصلی</h3>
            <p>با یک کلیک، Core Web Vitals صفحه اصلی سایت شما تحلیل می‌شود.</p>
        </div>
        <button class="button" id="wss-auto-vitals">
            <span class="dashicons dashicons-chart-area"></span> تحلیل خودکار سایت
        </button>
    </div>

    <div class="wss-card" style="margin-bottom:16px">
        <div class="wss-card-header" style="cursor:pointer" onclick="this.nextElementSibling.classList.toggle('open')">
            <span class="dashicons dashicons-info"></span> راهنمای Core Web Vitals
            <span style="margin-right:auto;font-size:11px;color:var(--wss-muted)">▼</span>
        </div>
        <div class="wss-guide" style="margin:0;border:none;border-radius:0">
            <div class="wss-guide-body" style="padding:0">
                <div class="wss-steps">
                    <div class="wss-step"><div class="wss-step-num">LCP</div><div class="wss-step-body"><strong>Largest Contentful Paint — هدف: زیر ۲.۵ ثانیه</strong><span>زمان نمایش بزرگ‌ترین عنصر بصری. بهبود: تصاویر WebP، Critical CSS، کش صفحه.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">FID</div><div class="wss-step-body"><strong>First Input Delay — هدف: زیر ۱۰۰ms</strong><span>تأخیر اولین تعامل کاربر. بهبود: کاهش JS بلاکینگ، Defer JS.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">CLS</div><div class="wss-step-body"><strong>Cumulative Layout Shift — هدف: زیر ۰.۱</strong><span>جابه‌جایی غیرمنتظره المان‌ها. بهبود: ابعاد تصویر مشخص، فونت بدون FOUT.</span></div></div>
                    <div class="wss-step"><div class="wss-step-num">INP</div><div class="wss-step-body"><strong>Interaction to Next Paint — هدف: زیر ۲۰۰ms</strong><span>پاسخگویی کلی صفحه به تعاملات. بهبود: بهینه‌سازی JS، کاهش reflow.</span></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="wss-vitals-toolbar">
        <div class="wss-field" style="margin:0;flex:1">
            <input type="url" id="wss-vitals-url" value="<?php echo esc_url(home_url('/')); ?>" class="widefat" placeholder="https://...">
        </div>
        <div class="wss-strategy-switch">
            <button class="wss-strategy-btn active" data-strategy="mobile"><span class="dashicons dashicons-smartphone"></span> موبایل</button>
            <button class="wss-strategy-btn" data-strategy="desktop"><span class="dashicons dashicons-desktop"></span> دسکتاپ</button>
        </div>
        <button class="button button-primary" id="wss-run-vitals">
            <span class="dashicons dashicons-search"></span> تحلیل
        </button>
    </div>

    <?php if (empty($wm['pagespeed_key'])): ?>
    <div class="wss-info-box" style="margin-bottom:20px">
        بدون API Key، درخواست‌ها محدود می‌شوند. برای دریافت کلید رایگان به
        <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Developers</a> مراجعه کنید،
        سپس در <a href="<?php echo admin_url('admin.php?page=wss-seo'); ?>">تنظیمات وبمستر</a> وارد نمایید.
    </div>
    <?php endif; ?>

    <div id="wss-vitals-loading" style="display:none;text-align:center;padding:40px">
        <div class="wss-spinner"></div>
        <p>در حال تحلیل — ممکن است تا 30 ثانیه طول بکشد...</p>
    </div>

    <div id="wss-vitals-result" style="display:none">

        <!-- Scores -->
        <div class="wss-vitals-scores" id="wss-scores-row"></div>

        <!-- CWV -->
        <div class="wss-card" id="wss-cwv-card">
            <div class="wss-card-header"><span class="dashicons dashicons-chart-bar"></span> Core Web Vitals (داده واقعی کاربران)</div>
            <div id="wss-cwv-grid" class="wss-cwv-grid"></div>
        </div>

        <!-- Lab -->
        <div class="wss-card" id="wss-lab-card">
            <div class="wss-card-header"><span class="dashicons dashicons-performance"></span> داده آزمایشگاهی (Lighthouse)</div>
            <div id="wss-lab-grid" class="wss-lab-grid"></div>
        </div>

        <!-- Opportunities -->
        <div class="wss-card" id="wss-opp-card">
            <div class="wss-card-header"><span class="dashicons dashicons-lightbulb"></span> فرصت‌های بهبود</div>
            <div id="wss-opportunities"></div>
        </div>

        <!-- Screenshot -->
        <div class="wss-card" id="wss-screenshot-card" style="display:none">
            <div class="wss-card-header"><span class="dashicons dashicons-images-alt2"></span> اسکرین‌شات</div>
            <div style="padding:18px;text-align:center">
                <img id="wss-screenshot-img" src="" style="max-width:400px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15)">
            </div>
        </div>

    </div>

    <div id="wss-vitals-error" style="display:none" class="notice notice-error"><p id="wss-vitals-error-msg"></p></div>
</div>

<script>
(function($){
    var strategy = 'mobile';

    // Auto-analyze home page
    $('#wss-auto-vitals').on('click', function(){
        $('#wss-vitals-url').val('<?php echo esc_js(home_url('/')); ?>');
        $('.wss-strategy-btn').removeClass('active').filter('[data-strategy="mobile"]').addClass('active');
        strategy = 'mobile';
        $('#wss-run-vitals').trigger('click');
        $('html,body').animate({scrollTop: $('.wss-vitals-toolbar').offset().top - 40}, 400);
    });

    $('.wss-strategy-btn').on('click', function(){
        $('.wss-strategy-btn').removeClass('active');
        $(this).addClass('active');
        strategy = $(this).data('strategy');
    });

    $('#wss-run-vitals').on('click', function(){
        var url = $('#wss-vitals-url').val();
        if(!url){ alert('URL الزامی است'); return; }

        $('#wss-vitals-result, #wss-vitals-error').hide();
        $('#wss-vitals-loading').show();
        $(this).prop('disabled', true);

        $.post(wssAdmin.ajaxUrl, {
            action: 'wss_fetch_vitals_url',
            nonce:  wssAdmin.nonce,
            url:    url,
            strategy: strategy
        }, function(res){
            $('#wss-vitals-loading').hide();
            $('#wss-run-vitals').prop('disabled', false);

            if(!res.success){
                $('#wss-vitals-error-msg').text(res.data || 'خطا در دریافت داده');
                $('#wss-vitals-error').show();
                return;
            }

            renderVitals(res.data);
            $('#wss-vitals-result').show();
        });
    });

    function renderVitals(d){
        // Scores
        var colors = {performance:'#0073aa', seo:'#46b450', accessibility:'#ffba00', 'best-practices':'#9c27b0'};
        var labels = {performance:'عملکرد', seo:'سئو', accessibility:'دسترس‌پذیری', 'best-practices':'بهترین روش‌ها'};
        var html = '';
        for(var k in d.scores){
            var s = d.scores[k];
            var cls = s>=90?'good':(s>=50?'warn':'bad');
            html += '<div class="wss-score-card '+cls+'"><div class="wss-score-num">'+s+'</div><div class="wss-score-lbl">'+(labels[k]||k)+'</div></div>';
        }
        $('#wss-scores-row').html(html);

        // CWV
        var cwvHtml = '';
        var cwvLabels = {GOOD:'خوب', NEEDS_IMPROVEMENT:'نیاز به بهبود', POOR:'ضعیف'};
        var cwvColors = {GOOD:'#46b450', NEEDS_IMPROVEMENT:'#ffba00', POOR:'#dc3232'};
        for(var k in d.cwv){
            var m = d.cwv[k];
            var color = cwvColors[m.category] || '#64748b';
            cwvHtml += '<div class="wss-cwv-item"><div class="wss-cwv-name">'+k+'</div>';
            cwvHtml += '<div class="wss-cwv-label">'+m.label+'</div>';
            if(m.percentile) cwvHtml += '<div class="wss-cwv-val" style="color:'+color+'">'+m.percentile+'ms</div>';
            if(m.category)   cwvHtml += '<div class="wss-cwv-cat" style="background:'+color+'">'+(cwvLabels[m.category]||m.category)+'</div>';
            if(m.good !== null){
                cwvHtml += '<div class="wss-cwv-bars">';
                cwvHtml += '<div class="wss-cwv-bar good" style="width:'+m.good+'%"></div>';
                cwvHtml += '<div class="wss-cwv-bar ni"   style="width:'+m.ni+'%"></div>';
                cwvHtml += '<div class="wss-cwv-bar poor" style="width:'+m.poor+'%"></div>';
                cwvHtml += '</div><div class="wss-cwv-bar-labels"><span>خوب '+m.good+'%</span><span>متوسط '+m.ni+'%</span><span>ضعیف '+m.poor+'%</span></div>';
            }
            cwvHtml += '</div>';
        }
        $('#wss-cwv-grid').html(cwvHtml || '<p style="padding:18px;color:#64748b">داده واقعی در دسترس نیست (نیاز به ترافیک کافی)</p>');

        // Lab
        var labHtml = '';
        var labColors = {good:'#46b450',average:'#ffba00',mediocre:'#dc3232'};
        function scoreClass(s){ return s>=0.9?'good':(s>=0.5?'warn':'bad'); }
        for(var k in d.lab){
            var m = d.lab[k];
            var cls = m.score!==null ? scoreClass(m.score) : '';
            labHtml += '<div class="wss-lab-item '+cls+'"><div class="wss-lab-name">'+k+'</div><div class="wss-lab-val">'+m.value+'</div></div>';
        }
        $('#wss-lab-grid').html(labHtml);

        // Opportunities
        var oppHtml = '';
        if(d.opportunities.length){
            d.opportunities.forEach(function(o){
                var savings = o.savings ? ' (~'+Math.round(o.savings/1000*10)/10+'s صرفه‌جویی)' : '';
                var cls = o.impact < 0.5 ? 'bad' : 'warn';
                oppHtml += '<div class="wss-opp-item '+cls+'"><span class="dashicons dashicons-warning"></span><div><strong>'+o.title+'</strong><span>'+savings+'</span></div></div>';
            });
        } else {
            oppHtml = '<div class="wss-empty-state"><span class="dashicons dashicons-yes-alt" style="color:#46b450;opacity:1"></span><p>همه چیز بهینه است!</p></div>';
        }
        $('#wss-opportunities').html(oppHtml);

        // Screenshot
        if(d.screenshot){
            $('#wss-screenshot-img').attr('src', d.screenshot);
            $('#wss-screenshot-card').show();
        }
    }
})(jQuery);
</script>

<style>
.wss-vitals-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:24px;background:#fff;padding:14px 18px;border-radius:10px;border:1px solid #e2e8f0}
.wss-strategy-switch{display:flex;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;flex-shrink:0}
.wss-strategy-btn{padding:6px 14px;border:none;background:none;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:5px;font-family:Tahoma}
.wss-strategy-btn.active{background:#0073aa;color:#fff}
.wss-vitals-scores{display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.wss-score-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;text-align:center;flex:1;min-width:120px}
.wss-score-card.good .wss-score-num{color:#46b450}
.wss-score-card.warn .wss-score-num{color:#ffba00}
.wss-score-card.bad  .wss-score-num{color:#dc3232}
.wss-score-num{font-size:40px;font-weight:700}
.wss-score-lbl{font-size:12px;color:#64748b;margin-top:4px}
.wss-cwv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0;padding:0}
.wss-cwv-item{padding:16px 18px;border-bottom:1px solid #f0f0f0;border-right:1px solid #f0f0f0}
.wss-cwv-name{font-size:18px;font-weight:700;color:#1e293b}
.wss-cwv-label{font-size:11px;color:#64748b;margin-bottom:8px}
.wss-cwv-val{font-size:22px;font-weight:700;margin-bottom:4px}
.wss-cwv-cat{display:inline-block;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-bottom:8px}
.wss-cwv-bars{display:flex;height:8px;border-radius:4px;overflow:hidden;margin-bottom:4px}
.wss-cwv-bar.good{background:#46b450}
.wss-cwv-bar.ni{background:#ffba00}
.wss-cwv-bar.poor{background:#dc3232}
.wss-cwv-bar-labels{display:flex;justify-content:space-between;font-size:10px;color:#64748b}
.wss-lab-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;padding:18px}
.wss-lab-item{padding:12px 14px;border-radius:8px;border:1px solid #e2e8f0}
.wss-lab-item.good{background:#f0faf0;border-color:#b8e0bb}
.wss-lab-item.warn{background:#fff9e6;border-color:#ffe580}
.wss-lab-item.bad{background:#fff0f0;border-color:#f5b8b8}
.wss-lab-name{font-size:11px;color:#64748b;margin-bottom:4px}
.wss-lab-val{font-size:18px;font-weight:700;color:#1e293b}
.wss-opp-item{display:flex;align-items:flex-start;gap:12px;padding:12px 18px;border-bottom:1px solid #f0f0f0;font-size:13px}
.wss-opp-item.bad .dashicons{color:#dc3232}
.wss-opp-item.warn .dashicons{color:#ffba00}
.wss-opp-item strong{display:block;margin-bottom:2px}
.wss-opp-item span{color:#64748b;font-size:12px}
.wss-spinner{width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#0073aa;border-radius:50%;animation:wss-spin 0.8s linear infinite;margin:0 auto 12px}
@keyframes wss-spin{to{transform:rotate(360deg)}}
</style>
