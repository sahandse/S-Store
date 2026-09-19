<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_Redirects {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', [ $this, 'handle_redirect' ], 1 );
    }

    public function handle_redirect() {
        global $wpdb;

        $request_uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
        if ( ! $request_uri ) return;

        $request_uri = rtrim( $request_uri, '/' );
        if ( empty($request_uri) ) $request_uri = '/';

        $redirect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wss_redirects WHERE source_url = %s AND enabled = 1 LIMIT 1",
            $request_uri
        ) );

        if ( ! $redirect ) return;

        // Increment hit count
        $wpdb->update(
            $wpdb->prefix . 'wss_redirects',
            [ 'hits' => $redirect->hits + 1 ],
            [ 'id'   => $redirect->id ]
        );

        $target = $redirect->target_url;
        if ( strpos($target, 'http') !== 0 ) {
            $target = home_url( $target );
        }

        wp_redirect( $target, (int) $redirect->type );
        exit;
    }

    public static function add( $source, $target, $type = 301 ) {
        global $wpdb;

        $source = '/' . ltrim(rtrim(parse_url($source, PHP_URL_PATH), '/'), '/');
        $target = $target;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wss_redirects WHERE source_url = %s",
            $source
        ) );

        if ( $existing ) {
            return $wpdb->update(
                $wpdb->prefix . 'wss_redirects',
                [ 'target_url' => $target, 'type' => $type ],
                [ 'id' => $existing ]
            );
        }

        return $wpdb->insert(
            $wpdb->prefix . 'wss_redirects',
            [ 'source_url' => $source, 'target_url' => $target, 'type' => $type ],
            [ '%s', '%s', '%d' ]
        );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'wss_redirects', [ 'id' => (int)$id ] );
    }

    public static function toggle( $id ) {
        global $wpdb;
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT enabled FROM {$wpdb->prefix}wss_redirects WHERE id = %d",
            $id
        ) );
        return $wpdb->update(
            $wpdb->prefix . 'wss_redirects',
            [ 'enabled' => $current ? 0 : 1 ],
            [ 'id' => (int)$id ]
        );
    }

    public static function get_all( $per_page = 20, $page = 1 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wss_redirects ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wss_redirects" );
    }

    public static function import_csv( $csv_content ) {
        $lines = array_filter(array_map('trim', explode("\n", $csv_content)));
        $imported = 0;
        foreach ( $lines as $line ) {
            $parts = str_getcsv( $line );
            if ( count($parts) >= 2 ) {
                $type = isset($parts[2]) ? (int)$parts[2] : 301;
                if ( self::add($parts[0], $parts[1], $type) !== false ) {
                    $imported++;
                }
            }
        }
        return $imported;
    }
}
