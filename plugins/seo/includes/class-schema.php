<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Schema {

    private static $instance = null;
    private $opts   = [];
    private $social = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->opts   = (array) get_option( 'wss_schema', [] );
        $this->social = (array) get_option( 'wss_social', [] );

        add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
    }

    private function opt( $key, $default = '' ) {
        return $this->opts[ $key ] ?? $default;
    }

    public function output_schema() {
        $schemas = [];

        // Website schema
        $schemas[] = $this->website_schema();

        // Organization / Person schema (sitewide)
        $schemas[] = $this->org_schema();

        // Page-specific schemas
        if ( is_singular('post') ) {
            $schemas[] = $this->article_schema();
            $schemas[] = $this->breadcrumb_schema();
        } elseif ( is_page() ) {
            $schemas[] = $this->webpage_schema();
            $schemas[] = $this->breadcrumb_schema();
        } elseif ( is_front_page() || is_home() ) {
            // Already has org schema
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $schemas[] = $this->collection_page_schema();
        }

        // FAQ schema from shortcode content
        if ( is_singular() ) {
            $faq = $this->faq_schema();
            if ( $faq ) $schemas[] = $faq;
        }

        // Output
        foreach ( array_filter($schemas) as $schema ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n</script>\n";
        }
    }

    private function website_schema() {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => get_bloginfo('name'),
            'url'      => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $desc = get_bloginfo('description');
        if ( $desc ) $schema['description'] = $desc;

        return $schema;
    }

    private function org_schema() {
        $type = $this->opt('type', 'Organization');
        $name = $this->opt('name', get_bloginfo('name'));
        $logo = $this->opt('logo');

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            'name'     => $name,
            'url'      => home_url('/'),
        ];

        if ( $logo ) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo,
            ];
        }

        $phone = $this->opt('phone');
        if ( $phone ) $schema['telephone'] = $phone;

        $email = $this->opt('email');
        if ( $email ) $schema['email'] = $email;

        $address = $this->opt('address');
        $city    = $this->opt('city');
        $country = $this->opt('country', 'IR');
        if ( $address || $city ) {
            $schema['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressLocality' => $city,
                'addressCountry'  => $country,
            ]);
        }

        // Social profiles
        if ( $this->opt('social_profiles', 1) ) {
            $profiles = [];
            foreach ( $this->social as $key => $url ) {
                if ( $url ) $profiles[] = $url;
            }
            if ( $profiles ) {
                $schema['sameAs'] = $profiles;
            }
        }

        return $schema;
    }

    private function article_schema() {
        $post = get_post();
        if ( ! $post ) return null;

        $thumbnail = '';
        if ( has_post_thumbnail($post->ID) ) {
            $img = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'full' );
            if ( $img ) $thumbnail = $img[0];
        }

        $author_id   = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => get_the_title($post->ID),
            'url'              => get_permalink($post->ID),
            'datePublished'    => get_the_date('c', $post->ID),
            'dateModified'     => get_the_modified_date('c', $post->ID),
            'author'           => [
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => get_author_posts_url($author_id),
            ],
            'publisher'        => [
                '@type' => $this->opt('type', 'Organization'),
                'name'  => $this->opt('name', get_bloginfo('name')),
            ],
        ];

        if ( $thumbnail ) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url'   => $thumbnail,
            ];
            $schema['publisher']['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $this->opt('logo', $thumbnail),
            ];
        }

        $desc = get_post_meta($post->ID, '_wss_seo_description', true);
        if ( ! $desc ) {
            $desc = mb_strimwidth(wp_strip_all_tags($post->post_content), 0, 200, '...');
        }
        if ( $desc ) $schema['description'] = $desc;

        // Word count
        $word_count = str_word_count(strip_tags($post->post_content));
        if ( $word_count ) $schema['wordCount'] = $word_count;

        return $schema;
    }

    private function webpage_schema() {
        $post = get_post();
        if ( ! $post ) return null;

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'WebPage',
            'name'       => get_the_title($post->ID),
            'url'        => get_permalink($post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'description'  => get_post_meta($post->ID, '_wss_seo_description', true) ?: get_bloginfo('description'),
        ];
    }

    private function breadcrumb_schema() {
        $items = [];
        $pos   = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => 'خانه',
            'item'     => home_url('/'),
        ];

        if ( is_singular('post') ) {
            $categories = get_the_category();
            if ( $categories ) {
                $cat = $categories[0];
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => $cat->name,
                    'item'     => get_category_link($cat),
                ];
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            ];
        } elseif ( is_page() ) {
            $post = get_post();
            if ( $post->post_parent ) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => get_the_title($post->post_parent),
                    'item'     => get_permalink($post->post_parent),
                ];
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            ];
        }

        if ( count($items) <= 1 ) return null;

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private function collection_page_schema() {
        $term = get_queried_object();

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $term->name ?? get_bloginfo('name'),
            'url'         => get_term_link($term),
            'description' => $term->description ?? '',
        ];
    }

    private function faq_schema() {
        $post    = get_post();
        if ( ! $post ) return null;

        $content = $post->post_content;
        if ( strpos($content, '[wss_faq') === false && strpos($content, 'class="wp-block-faq"') === false ) {
            return null;
        }

        // Parse FAQ shortcodes: [wss_faq q="..."] answer [/wss_faq]
        preg_match_all('/\[wss_faq q="([^"]+)"\](.*?)\[\/wss_faq\]/s', $content, $matches, PREG_SET_ORDER);

        if ( empty($matches) ) return null;

        $items = [];
        foreach ( $matches as $match ) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags($match[1]),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($match[2]),
                ],
            ];
        }

        if ( empty($items) ) return null;

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];
    }
}

// FAQ shortcode renderer
add_shortcode('wss_faq', function( $atts, $content = '' ) {
    $atts = shortcode_atts(['q' => ''], $atts);
    return '<div class="wss-faq-item"><h3 class="wss-faq-q">' . esc_html($atts['q']) . '</h3><div class="wss-faq-a">' . wpautop($content) . '</div></div>';
});
