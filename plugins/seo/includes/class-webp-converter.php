<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSS_WebP_Converter {

    private static $instance = null;
    private $opts = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->opts = (array) get_option( 'wss_webp', [] );

        if ( $this->opt('enabled') && self::is_supported() ) {
            add_filter( 'wp_handle_upload', [ $this, 'convert_on_upload' ], 10, 2 );
            add_filter( 'wp_generate_attachment_metadata', [ $this, 'convert_sizes' ], 10, 2 );

            if ( $this->opt('serve_webp') ) {
                add_filter( 'wp_get_attachment_image_src', [ $this, 'maybe_serve_webp' ], 10, 4 );
                add_filter( 'the_content', [ $this, 'replace_images_in_content' ] );
            }
        }

        // Bulk conversion via AJAX
        add_action( 'wp_ajax_wss_convert_batch', [ $this, 'ajax_convert_batch' ] );
        add_action( 'wp_ajax_wss_webp_status',   [ $this, 'ajax_webp_status' ] );
    }

    private function opt( $key, $default = 0 ) {
        return $this->opts[ $key ] ?? $default;
    }

    public static function is_supported() {
        if ( function_exists('imagewebp') ) return true;
        if ( extension_loaded('imagick') ) {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats('WEBP');
            return ! empty($formats);
        }
        return false;
    }

    // ─── Convert on upload ──────────────────────────────────────────────────

    public function convert_on_upload( $upload, $context ) {
        if ( $context !== 'upload' ) return $upload;
        if ( ! in_array($upload['type'], ['image/jpeg','image/png','image/gif'], true) ) return $upload;

        $webp_path = $this->get_webp_path($upload['file']);
        $result    = $this->convert_image($upload['file'], $webp_path);

        if ( $result ) {
            update_option('wss_webp_count', (int)get_option('wss_webp_count', 0) + 1);
        }

        return $upload;
    }

    public function convert_sizes( $metadata, $attachment_id ) {
        $file    = get_attached_file($attachment_id);
        $dir     = dirname($file);
        $type    = get_post_mime_type($attachment_id);

        if ( ! in_array($type, ['image/jpeg','image/png'], true) ) return $metadata;

        if ( ! empty($metadata['sizes']) ) {
            foreach ($metadata['sizes'] as $size => $data) {
                $size_file = $dir . '/' . $data['file'];
                $webp_path = $this->get_webp_path($size_file);
                $this->convert_image($size_file, $webp_path);
            }
        }

        return $metadata;
    }

    // ─── Core conversion ────────────────────────────────────────────────────

    public function convert_image( $source, $destination, $quality = null ) {
        if ( ! file_exists($source) ) return false;
        if ( file_exists($destination) ) return true; // already converted

        $quality = $quality ?? (int) $this->opt('quality', 82);

        // Try GD first
        if ( function_exists('imagewebp') ) {
            return $this->convert_gd($source, $destination, $quality);
        }

        // Try Imagick
        if ( extension_loaded('imagick') ) {
            return $this->convert_imagick($source, $destination, $quality);
        }

        return false;
    }

    private function convert_gd( $source, $destination, $quality ) {
        $mime = mime_content_type($source);
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
            case 'image/png':  $img = @imagecreatefrompng($source);  break;
            case 'image/gif':  $img = @imagecreatefromgif($source);  break;
            default: return false;
        }

        if ( ! $img ) return false;

        // Preserve transparency
        if ( $mime === 'image/png' || $mime === 'image/gif' ) {
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        $result = imagewebp($img, $destination, $quality);
        imagedestroy($img);

        return $result && file_exists($destination);
    }

    private function convert_imagick( $source, $destination, $quality ) {
        try {
            $imagick = new \Imagick($source);
            $imagick->setImageFormat('WEBP');
            $imagick->setImageCompressionQuality($quality);
            $imagick->setOption('webp:lossless', 'false');
            $result = $imagick->writeImage($destination);
            $imagick->destroy();
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function get_webp_path( $path ) {
        return preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
    }

    // ─── Serve WebP ─────────────────────────────────────────────────────────

    public function maybe_serve_webp( $image, $attachment_id, $size, $icon ) {
        if ( ! $image || $icon ) return $image;

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ( strpos($accept, 'image/webp') === false ) return $image;

        $src      = $image[0];
        $file     = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $src);
        $webp_file = $this->get_webp_path($file);

        if ( file_exists($webp_file) ) {
            $image[0] = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $webp_file);
        }

        return $image;
    }

    public function replace_images_in_content( $content ) {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ( strpos($accept, 'image/webp') === false ) return $content;

        $upload = wp_get_upload_dir();

        return preg_replace_callback(
            '/<img([^>]+)src=["\']([^"\']+\.(jpe?g|png))["\']([^>]*)>/i',
            function($m) use ($upload) {
                $src       = $m[2];
                $file      = str_replace($upload['baseurl'], $upload['basedir'], $src);
                $webp_file = $this->get_webp_path($file);

                if ( file_exists($webp_file) ) {
                    $webp_url = str_replace($upload['basedir'], $upload['baseurl'], $webp_file);
                    return '<picture><source srcset="' . esc_url($webp_url) . '" type="image/webp"><img' . $m[1] . 'src="' . esc_url($src) . '"' . $m[4] . '></picture>';
                }

                return $m[0];
            },
            $content
        );
    }

    // ─── Bulk conversion ────────────────────────────────────────────────────

    public function ajax_convert_batch() {
        check_ajax_referer('wss_admin_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_die();

        $offset     = (int)($_POST['offset'] ?? 0);
        $batch_size = 5;

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg','image/png'],
            'post_status'    => 'inherit',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
        ]);

        $converted = 0;
        $skipped   = 0;
        $errors    = 0;

        foreach ($attachments as $id) {
            $file = get_attached_file($id);
            if (!$file || !file_exists($file)) { $errors++; continue; }

            $webp = $this->get_webp_path($file);
            if (file_exists($webp)) { $skipped++; continue; }

            if ($this->convert_image($file, $webp)) {
                $converted++;
                // Also convert sizes
                $meta = wp_get_attachment_metadata($id);
                $dir  = dirname($file);
                if (!empty($meta['sizes'])) {
                    foreach ($meta['sizes'] as $data) {
                        $sf = $dir . '/' . $data['file'];
                        $this->convert_image($sf, $this->get_webp_path($sf));
                    }
                }
            } else {
                $errors++;
            }
        }

        $total = (int)wp_count_posts('attachment')->inherit;

        wp_send_json_success([
            'converted' => $converted,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'done'      => count($attachments) < $batch_size,
            'next'      => $offset + $batch_size,
            'total'     => $total,
            'progress'  => min(100, round(($offset + $batch_size) / max(1, $total) * 100)),
        ]);
    }

    public function ajax_webp_status() {
        check_ajax_referer('wss_admin_nonce', 'nonce');

        $total   = (int)wp_count_posts('attachment')->inherit;
        $upload  = wp_get_upload_dir();
        $webp_count = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload['basedir'], FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'webp') $webp_count++;
        }

        wp_send_json_success([
            'total_images' => $total,
            'webp_files'   => $webp_count,
            'supported'    => self::is_supported(),
        ]);
    }

    public function get_stats() {
        $upload     = wp_get_upload_dir();
        $webp_count = 0;
        $webp_size  = 0;

        if (is_dir($upload['basedir'])) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($upload['basedir'], FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'webp') {
                    $webp_count++;
                    $webp_size += $file->getSize();
                }
            }
        }

        return [
            'supported'  => self::is_supported(),
            'webp_files' => $webp_count,
            'webp_size'  => $webp_size > 1048576 ? round($webp_size/1048576,1).' MB' : round($webp_size/1024,1).' KB',
            'total_imgs' => (int)wp_count_posts('attachment')->inherit,
        ];
    }
}
