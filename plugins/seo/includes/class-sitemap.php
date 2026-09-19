<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Sitemap {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',             [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars',       [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect',[ $this, 'handle_sitemap_request' ] );
        add_action( 'save_post',        [ $this, 'flush_on_save' ] );
        add_action( 'delete_post',      [ $this, 'flush_on_save' ] );
        add_action( 'created_term',     [ $this, 'flush_on_save' ] );
        add_action( 'delete_term',      [ $this, 'flush_on_save' ] );

        // Ping search engines on update
        add_action( 'publish_post',     [ $this, 'ping_search_engines' ] );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^sitemap\.xml$',              'index.php?wss_sitemap=index',   'top' );
        add_rewrite_rule( '^sitemap-posts\.xml$',        'index.php?wss_sitemap=posts',   'top' );
        add_rewrite_rule( '^sitemap-pages\.xml$',        'index.php?wss_sitemap=pages',   'top' );
        add_rewrite_rule( '^sitemap-terms\.xml$',        'index.php?wss_sitemap=terms',   'top' );
        add_rewrite_rule( '^sitemap-images\.xml$',       'index.php?wss_sitemap=images',  'top' );
        add_rewrite_rule( '^news-sitemap\.xml$',         'index.php?wss_sitemap=news',    'top' );
        add_rewrite_rule( '^video-sitemap\.xml$',        'index.php?wss_sitemap=video',   'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'wss_sitemap';
        return $vars;
    }

    public function handle_sitemap_request() {
        $type = get_query_var('wss_sitemap');
        if ( ! $type ) return;

        $cache_key = 'wss_sitemap_' . $type;
        $xml       = get_transient( $cache_key );

        if ( false === $xml ) {
            switch ( $type ) {
                case 'index':  $xml = $this->build_index(); break;
                case 'posts':  $xml = $this->build_posts(); break;
                case 'pages':  $xml = $this->build_pages(); break;
                case 'terms':  $xml = $this->build_terms(); break;
                case 'images': $xml = $this->build_images(); break;
                case 'news':   $xml = $this->build_news(); break;
                case 'video':  $xml = $this->build_video(); break;
                default:       return;
            }
            set_transient( $cache_key, $xml, HOUR_IN_SECONDS * 12 );
        }

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        echo $xml;
        exit;
    }

    public function flush_on_save() {
        foreach ( ['index','posts','pages','terms','images','news','video'] as $type ) {
            delete_transient( 'wss_sitemap_' . $type );
        }
    }

    private function xml_header() {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    }

    private function build_index() {
        $sitemaps = [
            home_url('/sitemap-posts.xml'),
            home_url('/sitemap-pages.xml'),
            home_url('/sitemap-terms.xml'),
            home_url('/sitemap-images.xml'),
            home_url('/video-sitemap.xml'),
        ];

        $xml  = $this->xml_header();
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ( $sitemaps as $url ) {
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url($url) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . gmdate('c') . "</lastmod>\n";
            $xml .= "\t</sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    }

    private function build_posts() {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [[
                'key'     => '_wss_noindex',
                'value'   => '1',
                'compare' => '!=',
                'type'    => 'NUMERIC',
            ]],
        ]);

        return $this->build_url_set( $posts, 'post' );
    }

    private function build_pages() {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);

        return $this->build_url_set( $pages, 'page' );
    }

    private function build_url_set( $posts, $type ) {
        $freq     = $type === 'post' ? 'daily' : 'monthly';
        $priority = $type === 'post' ? '0.8'   : '0.6';

        $xml  = $this->xml_header();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $posts as $post ) {
            $noindex = get_post_meta( $post->ID, '_wss_noindex', true );
            if ( $noindex ) continue;

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url( get_permalink($post) ) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . gmdate('c', strtotime($post->post_modified_gmt)) . "</lastmod>\n";
            $xml .= "\t\t<changefreq>$freq</changefreq>\n";
            $xml .= "\t\t<priority>$priority</priority>\n";
            $xml .= "\t</url>\n";
        }

        // Add home
        if ( $type === 'page' ) {
            $xml = str_replace( '</urlset>', '', $xml );
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(home_url('/')) . "</loc>\n";
            $xml .= "\t\t<changefreq>daily</changefreq>\n";
            $xml .= "\t\t<priority>1.0</priority>\n";
            $xml .= "\t</url>\n";
            $xml .= '</urlset>';
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function build_terms() {
        $xml  = $this->xml_header();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $taxonomies = get_taxonomies([ 'public' => true ], 'names');
        foreach ( $taxonomies as $tax ) {
            $terms = get_terms([ 'taxonomy' => $tax, 'hide_empty' => true ]);
            if ( is_wp_error($terms) ) continue;
            foreach ( $terms as $term ) {
                $xml .= "\t<url>\n";
                $xml .= "\t\t<loc>" . esc_url(get_term_link($term)) . "</loc>\n";
                $xml .= "\t\t<changefreq>weekly</changefreq>\n";
                $xml .= "\t\t<priority>0.5</priority>\n";
                $xml .= "\t</url>\n";
            }
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function build_images() {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
        ]);

        $xml  = $this->xml_header();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ( $posts as $post ) {
            $attachments = get_attached_media('image', $post->ID);
            if ( empty($attachments) && ! has_post_thumbnail($post->ID) ) continue;

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(get_permalink($post)) . "</loc>\n";

            if ( has_post_thumbnail($post->ID) ) {
                $thumb_id  = get_post_thumbnail_id($post->ID);
                $thumb_src = wp_get_attachment_image_src($thumb_id, 'full');
                if ( $thumb_src ) {
                    $xml .= "\t\t<image:image>\n";
                    $xml .= "\t\t\t<image:loc>" . esc_url($thumb_src[0]) . "</image:loc>\n";
                    $xml .= "\t\t\t<image:title>" . esc_html(get_the_title($thumb_id)) . "</image:title>\n";
                    $xml .= "\t\t</image:image>\n";
                }
            }

            foreach ( $attachments as $att ) {
                $src = wp_get_attachment_image_src($att->ID, 'full');
                if ( $src ) {
                    $xml .= "\t\t<image:image>\n";
                    $xml .= "\t\t\t<image:loc>" . esc_url($src[0]) . "</image:loc>\n";
                    $xml .= "\t\t\t<image:title>" . esc_html($att->post_title) . "</image:title>\n";
                    if ( $att->post_excerpt ) {
                        $xml .= "\t\t\t<image:caption>" . esc_html($att->post_excerpt) . "</image:caption>\n";
                    }
                    $xml .= "\t\t</image:image>\n";
                }
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function build_news() {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'date_query'     => [[ 'after' => '2 days ago', 'inclusive' => true ]],
        ]);

        $xml  = $this->xml_header();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        foreach ( $posts as $post ) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(get_permalink($post)) . "</loc>\n";
            $xml .= "\t\t<news:news>\n";
            $xml .= "\t\t\t<news:publication>\n";
            $xml .= "\t\t\t\t<news:name>" . esc_html(get_bloginfo('name')) . "</news:name>\n";
            $xml .= "\t\t\t\t<news:language>fa</news:language>\n";
            $xml .= "\t\t\t</news:publication>\n";
            $xml .= "\t\t\t<news:publication_date>" . gmdate('c', strtotime($post->post_date_gmt)) . "</news:publication_date>\n";
            $xml .= "\t\t\t<news:title>" . esc_html($post->post_title) . "</news:title>\n";
            $xml .= "\t\t</news:news>\n";
            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function build_video() {
        $posts = get_posts([
            'post_type'      => ['post','page'],
            'post_status'    => 'publish',
            'posts_per_page' => 500,
        ]);

        // Patterns to extract video IDs / URLs
        $youtube_patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        ];
        $aparat_pattern  = '/aparat\.com\/v\/([a-zA-Z0-9]+)/';
        $vimeo_pattern   = '/vimeo\.com\/(\d+)/';

        $xml  = $this->xml_header();
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        foreach ( $posts as $post ) {
            $content = $post->post_content;
            $videos  = [];

            foreach ( $youtube_patterns as $pattern ) {
                if ( preg_match_all($pattern, $content, $m) ) {
                    foreach ( $m[1] as $vid ) {
                        $videos[] = [
                            'thumbnail' => "https://img.youtube.com/vi/{$vid}/hqdefault.jpg",
                            'title'     => $post->post_title,
                            'desc'      => wp_trim_words(strip_tags($post->post_content), 30),
                            'content'   => "https://www.youtube.com/watch?v={$vid}",
                            'player'    => "https://www.youtube.com/embed/{$vid}",
                        ];
                    }
                }
            }

            if ( preg_match_all($aparat_pattern, $content, $m) ) {
                foreach ( $m[1] as $vid ) {
                    $videos[] = [
                        'thumbnail' => home_url('/wp-content/plugins/wp-speed-seo/assets/img/video-thumb.png'),
                        'title'     => $post->post_title,
                        'desc'      => wp_trim_words(strip_tags($post->post_content), 30),
                        'content'   => "https://www.aparat.com/v/{$vid}",
                        'player'    => "https://www.aparat.com/video/video/embed/videohash/{$vid}/vt/frame",
                    ];
                }
            }

            if ( preg_match_all($vimeo_pattern, $content, $m) ) {
                foreach ( $m[1] as $vid ) {
                    $videos[] = [
                        'thumbnail' => "https://vumbnail.com/{$vid}.jpg",
                        'title'     => $post->post_title,
                        'desc'      => wp_trim_words(strip_tags($post->post_content), 30),
                        'content'   => "https://vimeo.com/{$vid}",
                        'player'    => "https://player.vimeo.com/video/{$vid}",
                    ];
                }
            }

            if ( empty($videos) ) continue;

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(get_permalink($post)) . "</loc>\n";

            foreach ( $videos as $v ) {
                $xml .= "\t\t<video:video>\n";
                $xml .= "\t\t\t<video:thumbnail_loc>" . esc_url($v['thumbnail']) . "</video:thumbnail_loc>\n";
                $xml .= "\t\t\t<video:title>" . esc_html($v['title']) . "</video:title>\n";
                $xml .= "\t\t\t<video:description>" . esc_html($v['desc']) . "</video:description>\n";
                $xml .= "\t\t\t<video:content_loc>" . esc_url($v['content']) . "</video:content_loc>\n";
                $xml .= "\t\t\t<video:player_loc>" . esc_url($v['player']) . "</video:player_loc>\n";
                $xml .= "\t\t\t<video:publication_date>" . gmdate('c', strtotime($post->post_date_gmt)) . "</video:publication_date>\n";
                $xml .= "\t\t</video:video>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function ping_search_engines( $post_id ) {
        $urls = [
            'https://www.google.com/ping?sitemap=' . urlencode(home_url('/sitemap.xml')),
            'https://www.bing.com/ping?sitemap='   . urlencode(home_url('/sitemap.xml')),
        ];

        foreach ( $urls as $url ) {
            wp_remote_get( $url, [ 'timeout' => 5, 'blocking' => false ] );
        }
    }
}
