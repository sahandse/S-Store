<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Breadcrumbs {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'wss_breadcrumb', [ $this, 'render' ] );
        add_filter( 'the_content', [ $this, 'maybe_auto_insert' ] );
    }

    public static function get_items() {
        $opts      = (array) get_option( 'wss_seo', [] );
        $home_text = $opts['breadcrumb_home'] ?? 'خانه';
        $items     = [];

        $items[] = [ 'url' => home_url('/'), 'label' => $home_text ];

        if ( is_singular() ) {
            $post = get_post();

            if ( is_singular('post') ) {
                $cats = get_the_category($post->ID);
                if ( $cats ) {
                    // Walk up category hierarchy
                    $cat = $cats[0];
                    $ancestors = array_reverse(get_ancestors($cat->term_id, 'category'));
                    foreach ( $ancestors as $anc_id ) {
                        $anc = get_category($anc_id);
                        $items[] = [ 'url' => get_category_link($anc), 'label' => $anc->name ];
                    }
                    $items[] = [ 'url' => get_category_link($cat), 'label' => $cat->name ];
                }
            } elseif ( 'page' === $post->post_type && $post->post_parent ) {
                $ancestors = array_reverse(get_post_ancestors($post->ID));
                foreach ( $ancestors as $anc_id ) {
                    $items[] = [ 'url' => get_permalink($anc_id), 'label' => get_the_title($anc_id) ];
                }
            }

            $items[] = [ 'url' => '', 'label' => get_the_title($post->ID) ];

        } elseif ( is_category() ) {
            $term = get_queried_object();
            $ancestors = array_reverse(get_ancestors($term->term_id, 'category'));
            foreach ( $ancestors as $anc_id ) {
                $anc = get_category($anc_id);
                $items[] = [ 'url' => get_category_link($anc), 'label' => $anc->name ];
            }
            $items[] = [ 'url' => '', 'label' => $term->name ];

        } elseif ( is_tag() ) {
            $items[] = [ 'url' => '', 'label' => single_tag_title('', false) ];

        } elseif ( is_tax() ) {
            $term = get_queried_object();
            $items[] = [ 'url' => '', 'label' => $term->name ];

        } elseif ( is_author() ) {
            $items[] = [ 'url' => '', 'label' => get_the_author_meta('display_name') ];

        } elseif ( is_search() ) {
            $items[] = [ 'url' => '', 'label' => 'جستجو: ' . get_search_query() ];

        } elseif ( is_404() ) {
            $items[] = [ 'url' => '', 'label' => 'صفحه یافت نشد' ];
        }

        return $items;
    }

    public function render( $atts = [] ) {
        $opts = (array) get_option( 'wss_seo', [] );
        if ( ! ( $opts['breadcrumbs'] ?? 1 ) ) return '';

        if ( is_front_page() ) return '';

        $items = self::get_items();
        if ( count($items) <= 1 ) return '';

        $html  = '<nav class="wss-breadcrumb" aria-label="مسیر صفحه" itemscope itemtype="https://schema.org/BreadcrumbList">';
        $html .= '<ol class="wss-breadcrumb-list">';

        foreach ( $items as $i => $item ) {
            $pos   = $i + 1;
            $last  = ( $i === count($items) - 1 );
            $html .= '<li class="wss-breadcrumb-item' . ($last ? ' current' : '') . '" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

            if ( $item['url'] && ! $last ) {
                $html .= '<a href="' . esc_url($item['url']) . '" itemprop="item"><span itemprop="name">' . esc_html($item['label']) . '</span></a>';
            } else {
                $html .= '<span itemprop="name">' . esc_html($item['label']) . '</span>';
            }

            $html .= '<meta itemprop="position" content="' . $pos . '">';
            $html .= '</li>';

            if ( ! $last ) {
                $html .= '<li class="wss-breadcrumb-sep" aria-hidden="true">›</li>';
            }
        }

        $html .= '</ol></nav>';
        return $html;
    }

    public function maybe_auto_insert( $content ) {
        $opts = (array) get_option( 'wss_seo', [] );
        if ( ! ( $opts['breadcrumbs_auto'] ?? 0 ) ) return $content;
        if ( ! is_singular() ) return $content;

        return $this->render() . $content;
    }
}
