<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Install {
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $conv = $wpdb->prefix . 'sb_conversations';
        $msg  = $wpdb->prefix . 'sb_messages';

        dbDelta( "CREATE TABLE $conv (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id varchar(64) NOT NULL,
            name varchar(190) NOT NULL DEFAULT '',
            contact varchar(190) NOT NULL DEFAULT '',
            locale varchar(20) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'open',
            page_url text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_id (public_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) $charset;" );

        dbDelta( "CREATE TABLE $msg (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_type varchar(20) NOT NULL,
            sender_id bigint(20) unsigned NOT NULL DEFAULT 0,
            message longtext NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'website',
            external_message_id varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            read_at datetime NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at)
        ) $charset;" );

        if ( ! get_option( 'sb_settings' ) ) {
            add_option( 'sb_settings', self::defaults() );
        }
        update_option( 'sb_db_version', SB_VERSION );
    }

    public static function deactivate() {}

    public static function defaults() {
        return array(
            'enabled' => 1,
            'button_text_fa' => 'پشتیبانی',
            'button_text_en' => 'Support',
            'button_image_id' => 0,
            'button_position' => 'left',
            'button_size' => 58,
            'button_radius' => 50,
            'button_color' => '#2563eb',
            'button_text_color' => '#ffffff',
            'button_animation' => 'none',
            'panel_mode' => 'popup',
            'theme' => 'minimal-light',
            'primary_color' => '#2563eb',
            'panel_bg' => '#ffffff',
            'panel_text' => '#111827',
            'welcome_fa' => 'سلام 👋 چطور می‌توانیم کمکتان کنیم؟',
            'welcome_en' => 'Hi 👋 How can we help you?',
            'offline_fa' => 'در حال حاضر پشتیبان آنلاین نیست.',
            'offline_en' => 'Support is currently offline.',
            'online_mode' => 'schedule',
            'manual_status' => 'online',
            'working_hours' => array(
                'sat'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'sun'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'mon'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'tue'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'wed'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'thu'=>array('enabled'=>1,'from'=>'09:00','to'=>'18:00'),
                'fri'=>array('enabled'=>0,'from'=>'09:00','to'=>'18:00'),
            ),
            'faq_enabled' => 1,
            'chat_enabled' => 1,
            'social_enabled' => 1,
            'bale_enabled' => 0,
            'bale_bot_token' => '',
            'bale_chat_id' => '',
            'bale_support_url' => '',
            'bale_api_base' => 'https://tapi.bale.ai',
            'bale_webhook_secret' => wp_generate_password( 28, false, false ),
            'notify_bale' => 1,
            'privacy_retention_days' => 90,
            'socials' => array(),
            'faqs' => array(),
            'agents' => array(),
        );
    }
}
