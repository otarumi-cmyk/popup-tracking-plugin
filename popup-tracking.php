<?php
/**
 * Plugin Name: Popup Tracking
 * Plugin URI: https://migi-nanameue.co.jp
 * Description: 記事ごとのポップアップ表示・クリック率を計測（タグ別CTA出し分け・A/Bテスト10パターン対応）
 * Version: 3.3.2
 * Author: migi-nanameue
 * Text Domain: popup-tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POPUP_TRACKING_VERSION', '3.3.2');
define('POPUP_TRACKING_PATH', plugin_dir_path(__FILE__));
define('POPUP_TRACKING_URL', plugin_dir_url(__FILE__));

require_once POPUP_TRACKING_PATH . 'includes/class-database.php';
require_once POPUP_TRACKING_PATH . 'includes/class-admin.php';
require_once POPUP_TRACKING_PATH . 'includes/class-frontend.php';

class Popup_Tracking {
    
    private static $instance = null;
    private $variants = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'check_db_update'));
        
        if (is_admin()) {
            new Popup_Tracking_Admin();
        }
        
        new Popup_Tracking_Frontend();
        
        add_action('wp_ajax_popup_tracking_reset_logs', array($this, 'ajax_reset_logs'));
        add_action('wp_ajax_popup_tracking_reset_floating_logs', array($this, 'ajax_reset_floating_logs'));
        add_action('wp_ajax_popup_tracking_log', array($this, 'ajax_log_event'));
        add_action('wp_ajax_nopriv_popup_tracking_log', array($this, 'ajax_log_event'));
    }
    
    public function check_db_update() {
        $db_version = get_option('popup_tracking_db_version', '0');
        
        if (version_compare($db_version, '3.1.0', '<')) {
            Popup_Tracking_Database::create_tables();
            update_option('popup_tracking_db_version', '3.1.0');
        }
    }
    
    public function activate() {
        Popup_Tracking_Database::create_tables();
        update_option('popup_tracking_db_version', '3.0.0');
        
        $default_settings = array(
            'is_active' => false,
            'abtest_enabled' => false,
            'active_variants' => 2,
            'popup_size' => 'medium',
            'popup_width' => 400,
            'trigger_type' => 'delay',
            'trigger_value' => 5,
            'frequency' => 'daily',
        );
        
        foreach ($this->variants as $index => $v) {
            $key = strtolower($v);
            $default_settings['image_url_' . $key] = '';
            $default_settings['line_url_' . $key] = '';
            $default_settings['weight_' . $key] = ($index === 0) ? 100 : 0;
        }
        
        if (!get_option('popup_tracking_settings')) {
            add_option('popup_tracking_settings', $default_settings);
        }
        
        $default_targeting = array(
            'target_mode' => 'all',
            'category_mode' => 'all',
            'target_categories' => array(),
            'target_posts' => array(),
            'exclude_posts' => array(),
        );
        
        if (!get_option('popup_tracking_targeting')) {
            add_option('popup_tracking_targeting', $default_targeting);
        }
        
        // タグ別CTA設定のデフォルト
        if (!get_option('popup_tracking_tag_ctas')) {
            add_option('popup_tracking_tag_ctas', array());
        }
    }
    
    public function deactivate() {
        // クリーンアップ
    }
    
    public function init() {
        // 初期化処理
    }
    
    public function ajax_log_event() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : '';
        $device = isset($_POST['device']) ? sanitize_text_field($_POST['device']) : 'pc';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $variant = isset($_POST['variant']) ? sanitize_text_field($_POST['variant']) : 'A';
        $cta_id = isset($_POST['cta_id']) ? sanitize_text_field($_POST['cta_id']) : 'default';
        
        error_log("Popup Tracking Log: post_id=$post_id, event=$event_type, cta=$cta_id, variant=$variant");
        
        if (!$post_id) {
            wp_send_json_error('post_id is required');
            return;
        }
        
        if (!in_array($event_type, array('impression', 'click', 'close'))) {
            wp_send_json_error('invalid event_type');
            return;
        }
        
        if (!in_array($variant, $this->variants)) {
            $variant = 'A';
        }
        
        $result = Popup_Tracking_Database::insert_log(array(
            'post_id' => $post_id,
            'variant' => $variant,
            'cta_id' => $cta_id,
            'event_type' => $event_type,
            'device' => in_array($device, array('pc', 'sp')) ? $device : 'pc',
            'session_id' => $session_id,
        ));
        
        if ($result) {
            wp_send_json_success(array('logged' => true, 'cta_id' => $cta_id, 'variant' => $variant));
        } else {
            global $wpdb;
            error_log("Popup Tracking DB Error: " . $wpdb->last_error);
            wp_send_json_error('database error');
        }
    }
    
    public function ajax_reset_logs() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $result = Popup_Tracking_Database::reset_logs();
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('リセットに失敗しました');
        }
    }
    
    public function ajax_reset_floating_logs() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $result = Popup_Tracking_Database::reset_floating_logs();
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('リセットに失敗しました');
        }
    }
}

Popup_Tracking::get_instance();
