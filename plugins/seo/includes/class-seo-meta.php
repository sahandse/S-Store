<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_SEO_Meta {

    private static $instance = null;
    private $opts    = [];
    private $social  = [];
    private $wmaster = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->opts    = (array) get_option( 'wss_seo', [] );
        $this->social  = (array) get_option( 'wss_social', [] );
        $this->wmaster = (array) get_option( 'wss_webmaster', [] );

        add_action( 'wp_head',      [ $this, 'output_meta' ], 1 );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post',    [ $this, 'save_post_meta' ] );

        // Title filter
        add_filter( 'pre_get_document_title', [ $this, 'filter_title' ], 20 );
        add_filter( 'document_title_separator', [ $this, 'filter_separator' ] );

        // Google Analytics / GTM
        $ga  = $this->wmaster['google_analytics'] ?? '';
        $gtm = $this->wmaster['gtm_id'] ?? '';
        if ( $ga )  add_action( 'wp_head', [ $this, 'output_ga' ] );
        if ( $gtm ) {
            add_action( 'wp_head', [ $this, 'output_gtm_head' ] );
            add_action( 'wp_body_open', [ $this, 'output_gtm_body' ] );
        }

        // Noindex
        if ( $this->opts['noindex_404'] ?? 1 ) {
            add_action( 'wp_head', [ $this, 'noindex_404' ] );
        }
    }

    private function opt( $key, $default = '' ) {
        return $this->opts[ $key ] ?? $default;
    }

    // ─── Title ────────────────────────────────────────────────────────────────

    public function filter_title( $title ) {
        if ( is_front_page() ) {
            $custom = $this->opt('title_home');
            if ( $custom ) return $custom;
        }

        if ( is_singular() ) {
            $custom = get_post_meta( get_the_ID(), '_wss_seo_title', true );
            if ( $custom ) return $custom;
        }

        return $title;
    }

    public function filter_separator( $sep ) {
        $custom = $this->opt('title_separator', '|');
        return $custom ?: $sep;
    }

    // ─── Meta output ──────────────────────────────────────────────────────────

    public function output_meta() {
        $post_id     = get_the_ID();
        $is_singular = is_singular();

        $title       = $this->get_current_title();
        $description = $this->get_current_description( $post_id );
        $canonical   = $this->get_canonical();
        $robots      = $this->get_robots_meta( $post_id );
        $og_image    = $this->get_og_image( $post_id );
        $url         = get_permalink( $post_id ) ?: home_url('/');

        // Basic meta
        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        // Robots
        if ( $robots ) {
            echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";
        }

        // Canonical
        if ( $this->opt('canonical_enabled', 1) && $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }

        // Webmaster verification
        foreach ( [
            'google_verify' => 'google-site-verification',
            'bing_verify'   => 'msvalidate.01',
            'yandex_verify' => 'yandex-verification',
        ] as $opt_key => $meta_name ) {
            $val = $this->wmaster[ $opt_key ] ?? '';
            if ( $val ) {
                echo '<meta name="' . esc_attr( $meta_name ) . '" content="' . esc_attr( $val ) . '">' . "\n";
            }
        }

        // Open Graph
        if ( $this->opt('og_enabled', 1) ) {
            $og_type = is_singular('post') ? 'article' : 'website';
            echo '<meta property="og:type"        content="' . esc_attr( $og_type ) . '">' . "\n";
            echo '<meta property="og:url"         content="' . esc_url( $url ) . '">' . "\n";
            echo '<meta property="og:title"       content="' . esc_attr( $title ) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
            echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo('name') ) . '">' . "\n";
            if ( $og_image ) {
                echo '<meta property="og:image"       content="' . esc_url( $og_image ) . '">' . "\n";
                echo '<meta property="og:image:width" content="1200">' . "\n";
                echo '<meta property="og:image:height" content="630">' . "\n";
            }

            if ( $og_type === 'article' && $is_singular ) {
                $author = get_the_author_meta('display_name', get_post_field('post_author', $post_id));
                echo '<meta property="article:author"         content="' . esc_attr( $author ) . '">' . "\n";
                echo '<meta property="article:published_time" content="' . esc_attr( get_the_date('c', $post_id) ) . '">' . "\n";
                echo '<meta property="article:modified_time"  content="' . esc_attr( get_the_modified_date('c', $post_id) ) . '">' . "\n";
            }

            // Facebook app ID
            $fb = $this->social['facebook'] ?? '';
            if ( $fb ) {
                echo '<meta property="fb:admins" content="' . esc_attr( $fb ) . '">' . "\n";
            }
        }

        // Twitter Card
        if ( $this->opt('twitter_enabled', 1) ) {
            $card = $this->opt('twitter_card', 'summary_large_image');
            echo '<meta name="twitter:card"        content="' . esc_attr( $card ) . '">' . "\n";
            echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
            if ( $og_image ) {
                echo '<meta name="twitter:image"   content="' . esc_url( $og_image ) . '">' . "\n";
            }
            $tw = $this->social['twitter'] ?? '';
            if ( $tw ) {
                echo '<meta name="twitter:site"    content="@' . esc_attr( ltrim($tw,'@') ) . '">' . "\n";
            }
        }
    }

    private function get_current_title() {
        if ( is_front_page() ) return get_bloginfo('name');
        if ( is_singular() ) {
            $custom = get_post_meta( get_the_ID(), '_wss_seo_title', true );
            return $custom ?: get_the_title();
        }
        if ( is_category() || is_tag() || is_tax() ) return single_term_title('', false);
        if ( is_author() ) return get_the_author();
        return get_bloginfo('name');
    }

    private function get_current_description( $post_id ) {
        if ( is_front_page() ) {
            $custom = $this->opt('desc_home');
            if ( $custom ) return $custom;
            return get_bloginfo('description');
        }

        if ( is_singular() && $post_id ) {
            $custom = get_post_meta( $post_id, '_wss_seo_description', true );
            if ( $custom ) return $custom;

            if ( $this->opt('auto_description', 1) ) {
                $post    = get_post( $post_id );
                $content = $post->post_excerpt ?: wp_strip_all_tags( $post->post_content );
                $length  = (int) $this->opt('auto_desc_length', 160);
                return mb_strimwidth( $content, 0, $length, '...' );
            }
        }

        if ( is_category() || is_tag() ) {
            $desc = term_description();
            return wp_strip_all_tags( $desc );
        }

        return get_bloginfo('description');
    }

    private function get_canonical() {
        if ( is_singular() )   return get_permalink();
        if ( is_home() )       return home_url('/');
        if ( is_front_page() ) return home_url('/');
        if ( is_category() )   return get_category_link( get_queried_object_id() );
        if ( is_tag() )        return get_tag_link( get_queried_object_id() );
        return false;
    }

    private function get_robots_meta( $post_id ) {
        $parts = [];

        if ( is_singular() && $post_id ) {
            $noindex   = get_post_meta( $post_id, '_wss_noindex', true );
            $nofollow  = get_post_meta( $post_id, '_wss_nofollow', true );
            if ( $noindex )  $parts[] = 'noindex';
            if ( $nofollow ) $parts[] = 'nofollow';
        }

        if ( is_category() || is_tag() ) {
            if ( $this->opt('noindex_archives', 0) ) $parts[] = 'noindex';
        }

        if ( is_paged() ) {
            $parts[] = 'noindex, follow';
        }

        return $parts ? implode( ', ', $parts ) : '';
    }

    public function noindex_404() {
        if ( is_404() ) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }

    private function get_og_image( $post_id ) {
        if ( $post_id && has_post_thumbnail( $post_id ) ) {
            $img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
            if ( $img ) return $img[0];
        }
        $default = $this->opt('default_og_image');
        return $default ?: '';
    }

    // ─── Analytics ────────────────────────────────────────────────────────────

    public function output_ga() {
        $ga_id = $this->wmaster['google_analytics'] ?? '';
        if ( ! $ga_id || is_user_logged_in() ) return;
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
        <script>
        window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js($ga_id); ?>');
        </script>
        <?php
    }

    public function output_gtm_head() {
        $gtm = $this->wmaster['gtm_id'] ?? '';
        if ( ! $gtm ) return;
        echo "<!-- Google Tag Manager -->\n";
        echo '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . esc_js($gtm) . '");</script>' . "\n";
        echo "<!-- End Google Tag Manager -->\n";
    }

    public function output_gtm_body() {
        $gtm = $this->wmaster['gtm_id'] ?? '';
        if ( ! $gtm ) return;
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }

    // ─── Meta Box ─────────────────────────────────────────────────────────────

    public function register_meta_box() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'wss_seo_meta_box',
                'تنظیمات سئو - Speed SEO Optimizer',
                [ $this, 'render_meta_box' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'wss_seo_meta_box', 'wss_nonce' );

        $title       = get_post_meta( $post->ID, '_wss_seo_title',       true );
        $description = get_post_meta( $post->ID, '_wss_seo_description', true );
        $keywords    = get_post_meta( $post->ID, '_wss_seo_keywords',    true );
        $focus_kw    = get_post_meta( $post->ID, '_wss_focus_keyword',   true );
        $noindex     = get_post_meta( $post->ID, '_wss_noindex',         true );
        $nofollow    = get_post_meta( $post->ID, '_wss_nofollow',        true );
        $canonical   = get_post_meta( $post->ID, '_wss_canonical',       true );
        $og_image    = get_post_meta( $post->ID, '_wss_og_image',        true );
        ?>
        <div class="wss-meta-box" style="font-family:Tahoma,Arial,sans-serif;direction:rtl">
            <style>
            .wss-meta-box .wss-field{margin-bottom:15px}
            .wss-meta-box label{display:block;font-weight:bold;margin-bottom:5px;color:#333}
            .wss-meta-box input[type=text],.wss-meta-box textarea{width:100%;border:1px solid #ddd;border-radius:4px;padding:8px;box-sizing:border-box}
            .wss-meta-box textarea{height:80px;resize:vertical}
            .wss-meta-box .wss-counter{font-size:11px;color:#999;text-align:left;margin-top:3px}
            .wss-meta-box .wss-row{display:flex;gap:20px}
            .wss-meta-box .wss-row .wss-field{flex:1}
            .wss-meta-box .wss-tabs{display:flex;gap:0;border-bottom:2px solid #0073aa;margin-bottom:15px}
            .wss-meta-box .wss-tab{padding:8px 15px;cursor:pointer;background:#f1f1f1;border:1px solid #ddd;border-bottom:none;font-size:13px}
            .wss-meta-box .wss-tab.active{background:#fff;border-top:2px solid #0073aa;color:#0073aa}
            .wss-meta-box .wss-panel{display:none}.wss-meta-box .wss-panel.active{display:block}
            .wss-seo-preview{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:15px;margin-bottom:15px}
            .wss-seo-preview .preview-title{color:#1a0dab;font-size:18px;text-decoration:none;display:block}
            .wss-seo-preview .preview-url{color:#006621;font-size:13px;margin:2px 0}
            .wss-seo-preview .preview-desc{color:#545454;font-size:13px}
            .wss-analysis{background:#f9f9f9;border-radius:4px;padding:10px;margin-top:10px}
            .wss-analysis .wss-check{display:flex;align-items:center;gap:8px;margin-bottom:5px;font-size:13px}
            .wss-analysis .wss-check .icon{width:16px;height:16px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;color:#fff;flex-shrink:0}
            .wss-analysis .wss-check .icon.good{background:#46b450}
            .wss-analysis .wss-check .icon.bad{background:#dc3232}
            .wss-analysis .wss-check .icon.warn{background:#ffba00;color:#333}
            </style>

            <div class="wss-tabs">
                <div class="wss-tab active" data-panel="general">عمومی</div>
                <div class="wss-tab" data-panel="social">شبکه‌های اجتماعی</div>
                <div class="wss-tab" data-panel="advanced">پیشرفته</div>
            </div>

            <div class="wss-panel active" id="wss-panel-general">
                <div class="wss-seo-preview" id="wss-preview">
                    <a class="preview-title" id="preview-title" href="#"><?php echo esc_html($title ?: get_the_title($post)); ?></a>
                    <div class="preview-url"><?php echo esc_url(get_permalink($post->ID) ?: home_url('/' . $post->post_name)); ?></div>
                    <div class="preview-desc" id="preview-desc"><?php echo esc_html($description ?: mb_strimwidth(wp_strip_all_tags($post->post_content), 0, 160, '...')); ?></div>
                </div>

                <div class="wss-field">
                    <label>عنوان سئو (SEO Title)</label>
                    <input type="text" id="wss-seo-title" name="wss_seo_title" value="<?php echo esc_attr($title); ?>" placeholder="عنوان سئو را وارد کنید...">
                    <div class="wss-counter"><span id="wss-title-count">0</span>/60 کاراکتر</div>
                </div>

                <div class="wss-field">
                    <label>توضیحات متا (Meta Description)</label>
                    <textarea id="wss-seo-desc" name="wss_seo_description" placeholder="توضیحات متا را وارد کنید..."><?php echo esc_textarea($description); ?></textarea>
                    <div class="wss-counter"><span id="wss-desc-count">0</span>/160 کاراکتر</div>
                </div>

                <div class="wss-row">
                    <div class="wss-field">
                        <label>کلمات کلیدی (Keywords)</label>
                        <input type="text" name="wss_seo_keywords" value="<?php echo esc_attr($keywords); ?>" placeholder="کلمه1, کلمه2, کلمه3">
                    </div>
                    <div class="wss-field">
                        <label>کلمه کلیدی اصلی (Focus Keyword)</label>
                        <input type="text" id="wss-focus-kw" name="wss_focus_keyword" value="<?php echo esc_attr($focus_kw); ?>" placeholder="مثال: خرید آنلاین">
                    </div>
                </div>

                <div class="wss-analysis" id="wss-analysis">
                    <strong>تحلیل سئو:</strong>
                    <div id="wss-checks"></div>
                </div>
            </div>

            <div class="wss-panel" id="wss-panel-social">
                <div class="wss-field">
                    <label>تصویر Open Graph (شبکه‌های اجتماعی)</label>
                    <input type="text" name="wss_og_image" id="wss-og-image" value="<?php echo esc_url($og_image); ?>" placeholder="URL تصویر...">
                    <button type="button" class="button" id="wss-og-image-btn" style="margin-top:5px">انتخاب تصویر</button>
                    <?php if ($og_image): ?>
                    <br><img src="<?php echo esc_url($og_image); ?>" style="max-width:200px;margin-top:8px;border-radius:4px">
                    <?php endif; ?>
                </div>
            </div>

            <div class="wss-panel" id="wss-panel-advanced">
                <div class="wss-row">
                    <div class="wss-field">
                        <label><input type="checkbox" name="wss_noindex" value="1" <?php checked($noindex, 1); ?>> noindex (از ایندکس خارج کن)</label>
                    </div>
                    <div class="wss-field">
                        <label><input type="checkbox" name="wss_nofollow" value="1" <?php checked($nofollow, 1); ?>> nofollow (لینک‌ها را دنبال نکن)</label>
                    </div>
                </div>
                <div class="wss-field">
                    <label>URL کانونیکال (Canonical URL)</label>
                    <input type="text" name="wss_canonical" value="<?php echo esc_url($canonical); ?>" placeholder="<?php echo esc_url(get_permalink($post->ID)); ?>">
                </div>
            </div>
        </div>

        <script>
        (function(){
            // Tabs
            document.querySelectorAll('.wss-tab').forEach(function(tab){
                tab.addEventListener('click',function(){
                    document.querySelectorAll('.wss-tab,.wss-panel').forEach(function(el){el.classList.remove('active')});
                    tab.classList.add('active');
                    document.getElementById('wss-panel-'+tab.dataset.panel).classList.add('active');
                });
            });

            // Live preview
            var titleEl=document.getElementById('wss-seo-title');
            var descEl=document.getElementById('wss-seo-desc');
            var focusEl=document.getElementById('wss-focus-kw');
            var previewTitle=document.getElementById('preview-title');
            var previewDesc=document.getElementById('preview-desc');
            var titleCount=document.getElementById('wss-title-count');
            var descCount=document.getElementById('wss-desc-count');

            function updatePreview(){
                if(titleEl.value) previewTitle.textContent=titleEl.value;
                if(descEl.value) previewDesc.textContent=descEl.value;
                titleCount.textContent=titleEl.value.length;
                descCount.textContent=descEl.value.length;
                titleCount.style.color=titleEl.value.length>60?'#dc3232':(titleEl.value.length<30?'#ffba00':'#46b450');
                descCount.style.color=descEl.value.length>160?'#dc3232':(descEl.value.length<100?'#ffba00':'#46b450');
                runChecks();
            }

            function runChecks(){
                var checks=[];
                var title=titleEl.value;
                var desc=descEl.value;
                var focus=focusEl?focusEl.value:'';

                checks.push({ok:title.length>=30&&title.length<=60,warn:title.length>0&&(title.length<30||title.length>60),msg:'طول عنوان ('+title.length+'/60)'});
                checks.push({ok:desc.length>=100&&desc.length<=160,warn:desc.length>0&&(desc.length<100||desc.length>160),msg:'طول توضیحات ('+desc.length+'/160)'});
                if(focus){
                    checks.push({ok:title.toLowerCase().includes(focus.toLowerCase()),msg:'کلمه کلیدی در عنوان'});
                    checks.push({ok:desc.toLowerCase().includes(focus.toLowerCase()),msg:'کلمه کلیدی در توضیحات'});
                }

                var html='';
                checks.forEach(function(c){
                    var cls=c.ok?'good':(c.warn?'warn':'bad');
                    var icon=c.ok?'✓':(c.warn?'!':'✗');
                    html+='<div class="wss-check"><span class="icon '+cls+'">'+icon+'</span><span>'+c.msg+'</span></div>';
                });
                document.getElementById('wss-checks').innerHTML=html;
            }

            if(titleEl) titleEl.addEventListener('input',updatePreview);
            if(descEl)  descEl.addEventListener('input',updatePreview);
            if(focusEl) focusEl.addEventListener('input',updatePreview);
            updatePreview();

            // Image picker
            var ogBtn=document.getElementById('wss-og-image-btn');
            if(ogBtn && typeof wp !== 'undefined' && wp.media){
                ogBtn.addEventListener('click',function(){
                    var frame=wp.media({title:'انتخاب تصویر',button:{text:'انتخاب'},multiple:false});
                    frame.on('select',function(){
                        var att=frame.state().get('selection').first().toJSON();
                        document.getElementById('wss-og-image').value=att.url;
                    });
                    frame.open();
                });
            }
        })();
        </script>
        <?php
    }

    public function save_post_meta( $post_id ) {
        if ( ! isset($_POST['wss_nonce']) || ! wp_verify_nonce($_POST['wss_nonce'], 'wss_seo_meta_box') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $fields = [
            'wss_seo_title'       => '_wss_seo_title',
            'wss_seo_description' => '_wss_seo_description',
            'wss_seo_keywords'    => '_wss_seo_keywords',
            'wss_focus_keyword'   => '_wss_focus_keyword',
            'wss_canonical'       => '_wss_canonical',
            'wss_og_image'        => '_wss_og_image',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        update_post_meta( $post_id, '_wss_noindex',  isset($_POST['wss_noindex'])  ? 1 : 0 );
        update_post_meta( $post_id, '_wss_nofollow', isset($_POST['wss_nofollow']) ? 1 : 0 );
    }
}
