<?php
/**
 * 管理画面クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Popup_Tracking_Admin {
    
    private $variants = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        add_action('wp_ajax_popup_tracking_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_popup_tracking_search_tags', array($this, 'ajax_search_tags'));
        add_action('wp_ajax_popup_tracking_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_popup_tracking_save_tag_ctas', array($this, 'ajax_save_tag_ctas'));
        add_action('wp_ajax_popup_tracking_save_snapshot', array($this, 'ajax_save_snapshot'));
        add_action('wp_ajax_popup_tracking_delete_snapshot', array($this, 'ajax_delete_snapshot'));
        add_action('wp_ajax_popup_tracking_delete_all_snapshots', array($this, 'ajax_delete_all_snapshots'));
        add_action('wp_ajax_popup_tracking_save_floating_snapshot', array($this, 'ajax_save_floating_snapshot'));
        add_action('wp_ajax_popup_tracking_delete_floating_snapshot', array($this, 'ajax_delete_floating_snapshot'));
        add_action('wp_ajax_popup_tracking_delete_all_floating_snapshots', array($this, 'ajax_delete_all_floating_snapshots'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'ポップアップ計測',
            'ポップアップ計測',
            'manage_options',
            'popup-tracking',
            array($this, 'render_dashboard'),
            'dashicons-chart-bar',
            30
        );
        
        add_submenu_page('popup-tracking', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'popup-tracking', array($this, 'render_dashboard'));
        add_submenu_page('popup-tracking', 'タグ別CTA設定', 'タグ別CTA設定', 'manage_options', 'popup-tracking-tag-cta', array($this, 'render_tag_cta'));
        add_submenu_page('popup-tracking', 'デフォルトCTA設定', 'デフォルトCTA設定', 'manage_options', 'popup-tracking-settings', array($this, 'render_settings'));
        add_submenu_page('popup-tracking', 'A/Bテスト結果', 'A/Bテスト結果', 'manage_options', 'popup-tracking-abtest', array($this, 'render_abtest'));
        add_submenu_page('popup-tracking', '表示条件設定', '表示条件設定', 'manage_options', 'popup-tracking-targeting', array($this, 'render_targeting'));
        add_submenu_page('popup-tracking', 'デバッグ', 'デバッグ', 'manage_options', 'popup-tracking-debug', array($this, 'render_debug'));
        add_submenu_page('popup-tracking', 'CTR異常値解析', 'CTR異常値解析', 'manage_options', 'popup-tracking-ctr-analysis', array($this, 'render_ctr_analysis'));
        
        // スナップショットメニュー
        add_menu_page(
            'ポップアップスナップショット',
            'ポップアップスナップショット',
            'manage_options',
            'popup-snapshots',
            array($this, 'render_popup_snapshots'),
            'dashicons-camera',
            32
        );
        add_submenu_page('popup-snapshots', 'ポップアップスナップショット', 'ポップアップ', 'manage_options', 'popup-snapshots', array($this, 'render_popup_snapshots'));
        add_submenu_page('popup-snapshots', 'フロバナスナップショット', 'フロバナ', 'manage_options', 'floating-snapshots', array($this, 'render_floating_snapshots'));
        
        // フローティングバナー
        add_menu_page(
            'フローティングバナー',
            'フローティングバナー',
            'manage_options',
            'floating-banner',
            array($this, 'render_floating_dashboard'),
            'dashicons-align-center',
            31
        );
        add_submenu_page('floating-banner', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'floating-banner', array($this, 'render_floating_dashboard'));
        add_submenu_page('floating-banner', 'バナー設定', 'バナー設定', 'manage_options', 'floating-banner-settings', array($this, 'render_floating_settings'));
        add_submenu_page('floating-banner', '表示条件設定', '表示条件設定', 'manage_options', 'floating-banner-targeting', array($this, 'render_floating_targeting'));
        add_submenu_page('floating-banner', 'テスト・デバッグ', 'テスト・デバッグ', 'manage_options', 'floating-banner-test', array($this, 'render_floating_test'));
    }
    
    public function register_settings() {
        register_setting('popup_tracking_settings_group', 'popup_tracking_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
        
        register_setting('popup_tracking_targeting_group', 'popup_tracking_targeting', array(
            'sanitize_callback' => array($this, 'sanitize_targeting'),
        ));
        
        register_setting('floating_banner_settings_group', 'floating_banner_settings', array(
            'sanitize_callback' => array($this, 'sanitize_floating_settings'),
        ));
        
        register_setting('floating_banner_targeting_group', 'floating_banner_targeting', array(
            'sanitize_callback' => array($this, 'sanitize_floating_targeting'),
        ));
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        foreach ($this->variants as $v) {
            $key = strtolower($v);
            $sanitized['image_url_' . $key] = esc_url_raw($input['image_url_' . $key] ?? '');
            $sanitized['line_url_' . $key] = esc_url_raw($input['line_url_' . $key] ?? '');
            $sanitized['weight_' . $key] = intval($input['weight_' . $key] ?? ($v === 'A' ? 100 : 0));
        }
        
        $sanitized['image_url'] = $sanitized['image_url_a'];
        $sanitized['line_url'] = $sanitized['line_url_a'];
        
        $sanitized['abtest_enabled'] = !empty($input['abtest_enabled']);
        $sanitized['active_variants'] = intval($input['active_variants'] ?? 2);
        if ($sanitized['active_variants'] < 2) $sanitized['active_variants'] = 2;
        if ($sanitized['active_variants'] > 10) $sanitized['active_variants'] = 10;
        
        $sanitized['popup_size'] = in_array($input['popup_size'] ?? '', array('small', 'medium', 'large', 'custom')) 
            ? $input['popup_size'] : 'medium';
        $sanitized['popup_width'] = absint($input['popup_width'] ?? 400);
        if ($sanitized['popup_width'] < 200) $sanitized['popup_width'] = 200;
        if ($sanitized['popup_width'] > 800) $sanitized['popup_width'] = 800;
        
        $sanitized['trigger_type'] = in_array($input['trigger_type'] ?? '', array('delay', 'scroll', 'exit')) 
            ? $input['trigger_type'] : 'delay';
        $sanitized['trigger_value'] = absint($input['trigger_value'] ?? 5);
        $sanitized['frequency'] = in_array($input['frequency'] ?? '', array('session', 'daily')) 
            ? $input['frequency'] : 'daily';

        // フローティングバナー
        $sanitized['floating_enabled'] = !empty($input['floating_enabled']);
        $sanitized['floating_image_url'] = esc_url_raw($input['floating_image_url'] ?? '');
        $sanitized['floating_link_url'] = esc_url_raw($input['floating_link_url'] ?? '');
        $sanitized['floating_position'] = in_array($input['floating_position'] ?? '', array('br', 'bl')) 
            ? $input['floating_position'] : 'br';

        $sanitized['is_active'] = !empty($input['is_active']);
        
        return $sanitized;
    }
    
    public function sanitize_targeting($input) {
        $sanitized = array();
        
        $sanitized['target_mode'] = in_array($input['target_mode'] ?? '', array('all', 'include', 'exclude')) 
            ? $input['target_mode'] : 'all';
        $sanitized['category_mode'] = in_array($input['category_mode'] ?? '', array('all', 'include', 'exclude')) 
            ? $input['category_mode'] : 'all';
        
        $sanitized['target_categories'] = array();
        if (!empty($input['target_categories']) && is_array($input['target_categories'])) {
            $sanitized['target_categories'] = array_map('absint', $input['target_categories']);
        }
        
        $sanitized['target_posts'] = array();
        if (!empty($input['target_posts'])) {
            $ids = explode(',', $input['target_posts']);
            $sanitized['target_posts'] = array_map('absint', array_filter($ids));
        }
        
        $sanitized['exclude_posts'] = array();
        if (!empty($input['exclude_posts'])) {
            $ids = explode(',', $input['exclude_posts']);
            $sanitized['exclude_posts'] = array_map('absint', array_filter($ids));
        }
        
        return $sanitized;
    }
    
    public function sanitize_floating_settings($input) {
        $sanitized = array();
        
        $sanitized['is_active'] = !empty($input['is_active']);
        $sanitized['abtest_enabled'] = !empty($input['abtest_enabled']);
        $sanitized['active_variants'] = intval($input['active_variants'] ?? 2);
        if ($sanitized['active_variants'] < 2) $sanitized['active_variants'] = 2;
        if ($sanitized['active_variants'] > 10) $sanitized['active_variants'] = 10;
        
        foreach ($this->variants as $v) {
            $key = strtolower($v);
            $sanitized['image_url_pc_' . $key] = esc_url_raw($input['image_url_pc_' . $key] ?? '');
            $sanitized['image_url_sp_' . $key] = esc_url_raw($input['image_url_sp_' . $key] ?? '');
            $sanitized['link_url_' . $key] = esc_url_raw($input['link_url_' . $key] ?? '');
            $sanitized['weight_' . $key] = intval($input['weight_' . $key] ?? ($v === 'A' ? 100 : 0));
        }
        
        $sanitized['frequency'] = in_array($input['frequency'] ?? '', array('session', 'daily')) 
            ? $input['frequency'] : 'daily';
        
        return $sanitized;
    }
    
    public function sanitize_floating_targeting($input) {
        $sanitized = array();
        
        $sanitized['target_mode'] = in_array($input['target_mode'] ?? '', array('all', 'include', 'exclude')) 
            ? $input['target_mode'] : 'all';
        $sanitized['category_mode'] = in_array($input['category_mode'] ?? '', array('all', 'include', 'exclude')) 
            ? $input['category_mode'] : 'all';
        
        $sanitized['target_categories'] = array();
        if (!empty($input['target_categories']) && is_array($input['target_categories'])) {
            $sanitized['target_categories'] = array_map('absint', $input['target_categories']);
        }
        
        $sanitized['target_posts'] = array();
        if (!empty($input['target_posts'])) {
            $ids = explode(',', $input['target_posts']);
            $sanitized['target_posts'] = array_map('absint', array_filter($ids));
        }
        
        $sanitized['exclude_posts'] = array();
        if (!empty($input['exclude_posts'])) {
            $ids = explode(',', $input['exclude_posts']);
            $sanitized['exclude_posts'] = array_map('absint', array_filter($ids));
        }
        
        return $sanitized;
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'popup-tracking') === false && 
            strpos($hook, 'floating-banner') === false && 
            strpos($hook, 'popup-snapshots') === false && 
            strpos($hook, 'floating-snapshots') === false) {
            return;
        }
        
        wp_enqueue_style('popup-tracking-admin', POPUP_TRACKING_URL . 'assets/css/admin.css', array(), POPUP_TRACKING_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('popup-tracking-admin', POPUP_TRACKING_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), POPUP_TRACKING_VERSION, true);
        
        wp_localize_script('popup-tracking-admin', 'popupTrackingConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('popup_tracking_admin'),
        ));
    }
    
    public function ajax_search_posts() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 20, 's' => $search));
        $results = array();
        foreach ($posts as $post) {
            $results[] = array('id' => $post->ID, 'title' => $post->post_title);
        }
        wp_send_json_success($results);
    }
    
    public function ajax_search_tags() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $tags = get_tags(array(
            'search' => $search,
            'hide_empty' => false,
            'number' => 20,
        ));
        
        $results = array();
        foreach ($tags as $tag) {
            $results[] = array('id' => $tag->term_id, 'name' => $tag->name);
        }
        wp_send_json_success($results);
    }
    
    public function ajax_save_tag_ctas() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $ctas = isset($_POST['ctas']) ? $_POST['ctas'] : array();
        $sanitized_ctas = array();
        
        foreach ($ctas as $index => $cta) {
            $sanitized_cta = array(
                'id' => sanitize_text_field($cta['id'] ?? 'cta_' . uniqid()),
                'name' => sanitize_text_field($cta['name'] ?? 'CTA ' . ($index + 1)),
                'tags' => array_map('intval', $cta['tags'] ?? array()),
                'image_url' => esc_url_raw($cta['image_url'] ?? ''),
                'link_url' => esc_url_raw($cta['link_url'] ?? ''),
                'abtest_enabled' => !empty($cta['abtest_enabled']),
                'variants' => array(),
                'order' => intval($index),
            );
            
            // バリアント設定
            if (!empty($cta['variants']) && is_array($cta['variants'])) {
                foreach ($cta['variants'] as $variant) {
                    $sanitized_cta['variants'][] = array(
                        'image_url' => esc_url_raw($variant['image_url'] ?? ''),
                        'link_url' => esc_url_raw($variant['link_url'] ?? ''),
                        'weight' => intval($variant['weight'] ?? 50),
                    );
                }
            }
            
            $sanitized_ctas[] = $sanitized_cta;
        }
        
        update_option('popup_tracking_tag_ctas', $sanitized_ctas);
        wp_send_json_success(array('message' => '保存しました'));
    }
    
    public function ajax_export_csv() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        if (!current_user_can('manage_options')) wp_die('権限がありません');
        
        $start_date = sanitize_text_field($_GET['start_date'] ?? '') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = sanitize_text_field($_GET['end_date'] ?? '') ?: date('Y-m-d');
        
        $stats = Popup_Tracking_Database::get_stats_by_post($start_date, $end_date);
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="popup-tracking-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('記事ID', '記事タイトル', '表示数', 'クリック数', 'CTR(%)'));
        
        foreach ($stats as $stat) {
            $post = get_post($stat->post_id);
            $impressions = intval($stat->impressions);
            $clicks = intval($stat->clicks);
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 1) : 0;
            fputcsv($output, array($stat->post_id, $post ? $post->post_title : '(削除)', $impressions, $clicks, $ctr));
        }
        fclose($output);
        exit;
    }
    
    private function get_date_range($period, $custom_start = '', $custom_end = '') {
        switch ($period) {
            case 'today': return array(date('Y-m-d'), date('Y-m-d'));
            case 'week': return array(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
            case 'month': return array(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
            case 'custom': return array($custom_start ?: date('Y-m-d', strtotime('-7 days')), $custom_end ?: date('Y-m-d'));
            default: return array(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
        }
    }
    
    public function render_dashboard() {
        $period = sanitize_text_field($_GET['period'] ?? 'week');
        list($start_date, $end_date) = $this->get_date_range($period, $_GET['start_date'] ?? '', $_GET['end_date'] ?? '');
        
        // 期間を文字列に変換
        $period_label = '';
        switch ($period) {
            case 'today': $period_label = '今日'; break;
            case 'week': $period_label = '今週'; break;
            case 'month': $period_label = '今月'; break;
            default: $period_label = $start_date . '〜' . $end_date;
        }
        
        // タグフィルター
        $filter_tags = array();
        if (!empty($_GET['tags'])) {
            $filter_tags = array_map('intval', explode(',', $_GET['tags']));
        }
        $tag_mode = sanitize_text_field($_GET['tag_mode'] ?? 'or');
        
        $summary = Popup_Tracking_Database::get_summary($start_date, $end_date, $filter_tags, $tag_mode);
        $stats_by_post = Popup_Tracking_Database::get_stats_by_post($start_date, $end_date, $filter_tags, $tag_mode);
        $device_stats = Popup_Tracking_Database::get_stats_by_device($start_date, $end_date);
        $tag_summary = Popup_Tracking_Database::get_tag_summary($start_date, $end_date);
        
        // CTA別統計
        $cta_stats = Popup_Tracking_Database::get_stats_by_cta($start_date, $end_date);
        $cta_post_stats = Popup_Tracking_Database::get_cta_post_stats($start_date, $end_date);
        
        // CTA別の記事別統計を整理
        $cta_posts_map = array();
        foreach ($cta_post_stats as $cps) {
            $cta_id = $cps->cta_id;
            if (!isset($cta_posts_map[$cta_id])) {
                $cta_posts_map[$cta_id] = array();
            }
            $cta_posts_map[$cta_id][] = $cps;
        }
        
        // 各CTAの平均CTRを計算
        $cta_avg_ctr = array();
        foreach ($cta_posts_map as $cta_id => $posts) {
            $ctr_sum = 0;
            $post_count = 0;
            foreach ($posts as $post_stat) {
                $imp = intval($post_stat->impressions);
                $click = intval($post_stat->clicks);
                if ($imp > 0) {
                    $ctr_sum += ($click / $imp) * 100;
                    $post_count++;
                }
            }
            $cta_avg_ctr[$cta_id] = $post_count > 0 ? round($ctr_sum / $post_count, 2) : 0;
        }
        
        $total_impressions = intval($summary->total_impressions ?? 0);
        $total_clicks = intval($summary->total_clicks ?? 0);
        $total_closes = intval($summary->total_closes ?? 0);
        $total_ctr = $total_impressions > 0 ? round(($total_clicks / $total_impressions) * 100, 1) : 0;
        
        $pc_clicks = $sp_clicks = 0;
        foreach ($device_stats as $ds) {
            if ($ds->device === 'pc') $pc_clicks = intval($ds->clicks);
            if ($ds->device === 'sp') $sp_clicks = intval($ds->clicks);
        }
        
        // 全タグ取得
        $all_tags = get_tags(array('hide_empty' => false));
        
        // タグ別CTA設定を取得（CTA名の表示用）
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        $tag_cta_names = array();
        foreach ($tag_ctas as $cta) {
            $tag_cta_names[$cta['id']] = $cta['name'] ?? $cta['id'];
        }
        
        // スナップショットデータ
        $snapshots = get_option('popup_tracking_snapshots', array());
        
        include POPUP_TRACKING_PATH . 'includes/views/dashboard.php';
    }
    
    public function render_tag_cta() {
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        $all_tags = get_tags(array('hide_empty' => false));
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🏷️ タグ別CTA設定</h1>
            <p class="description">記事のタグに応じて異なるポップアップを表示できます。上から順に優先されます。</p>
            
            <div id="tag-cta-list">
                <?php if (empty($tag_ctas)) : ?>
                    <p class="no-ctas">まだCTA設定がありません。下のボタンから追加してください。</p>
                <?php else : ?>
                    <?php foreach ($tag_ctas as $index => $cta) : ?>
                        <div class="cta-item" data-index="<?php echo $index; ?>">
                            <div class="cta-header">
                                <span class="cta-handle">☰</span>
                                <span class="cta-title"><?php echo esc_html($cta['name'] ?? 'CTA #' . ($index + 1)); ?></span>
                                <div class="cta-actions">
                                    <button type="button" class="button toggle-cta">▼</button>
                                    <button type="button" class="button delete-cta">🗑️</button>
                                </div>
                            </div>
                            <div class="cta-body">
                                <input type="hidden" class="cta-id" value="<?php echo esc_attr($cta['id'] ?? ''); ?>">
                                
                                <div class="cta-field">
                                    <label>CTA名</label>
                                    <input type="text" class="cta-name regular-text" value="<?php echo esc_attr($cta['name'] ?? ''); ?>" placeholder="例: 転職系CTA">
                                </div>
                                
                                <div class="cta-field">
                                    <label>対象タグ</label>
                                    <div class="tag-selector">
                                        <input type="text" class="tag-search regular-text" placeholder="タグ名で検索...">
                                        <div class="tag-search-results"></div>
                                        <div class="selected-tags">
                                            <?php 
                                            $cta_tags = $cta['tags'] ?? array();
                                            foreach ($cta_tags as $tag_id) :
                                                $tag = get_tag($tag_id);
                                                if ($tag) :
                                            ?>
                                                <span class="selected-tag" data-id="<?php echo $tag_id; ?>">
                                                    <?php echo esc_html($tag->name); ?>
                                                    <button type="button" class="remove-tag">×</button>
                                                </span>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="cta-field">
                                    <label>画像</label>
                                    <div class="image-upload-field">
                                        <input type="hidden" class="cta-image-url" value="<?php echo esc_url($cta['image_url'] ?? ''); ?>">
                                        <div class="image-preview-small">
                                            <?php if (!empty($cta['image_url'])) : ?>
                                                <img src="<?php echo esc_url($cta['image_url']); ?>" alt="">
                                            <?php else : ?>
                                                <span class="placeholder">画像なし</span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button upload-cta-image">選択</button>
                                        <button type="button" class="button remove-cta-image" style="<?php echo empty($cta['image_url']) ? 'display:none;' : ''; ?>">削除</button>
                                    </div>
                                </div>
                                
                                <div class="cta-field">
                                    <label>リンクURL</label>
                                    <input type="url" class="cta-link-url regular-text" value="<?php echo esc_url($cta['link_url'] ?? ''); ?>" placeholder="https://lin.ee/xxxxxx">
                                </div>
                                
                                <div class="cta-field">
                                    <label>
                                        <input type="checkbox" class="cta-abtest-enabled" <?php checked(!empty($cta['abtest_enabled'])); ?>>
                                        A/Bテストを有効にする
                                    </label>
                                </div>
                                
                                <div class="cta-variants" style="<?php echo empty($cta['abtest_enabled']) ? 'display:none;' : ''; ?>">
                                    <?php 
                                    $variants = $cta['variants'] ?? array();
                                    if (empty($variants)) {
                                        $variants = array(
                                            array('image_url' => '', 'link_url' => '', 'weight' => 50),
                                            array('image_url' => '', 'link_url' => '', 'weight' => 50),
                                        );
                                    }
                                    foreach ($variants as $vi => $variant) :
                                    ?>
                                    <div class="variant-item">
                                        <h4>パターン <?php echo chr(65 + $vi); ?></h4>
                                        <div class="variant-fields">
                                            <div class="image-upload-field inline">
                                                <input type="hidden" class="variant-image-url" value="<?php echo esc_url($variant['image_url'] ?? ''); ?>">
                                                <div class="image-preview-tiny">
                                                    <?php if (!empty($variant['image_url'])) : ?>
                                                        <img src="<?php echo esc_url($variant['image_url']); ?>" alt="">
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button" class="button button-small upload-variant-image">画像</button>
                                            </div>
                                            <input type="url" class="variant-link-url" value="<?php echo esc_url($variant['link_url'] ?? ''); ?>" placeholder="URL">
                                            <input type="number" class="variant-weight" value="<?php echo intval($variant['weight'] ?? 50); ?>" min="0" max="100" style="width:60px;"> %
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <button type="button" class="button add-variant">+ パターン追加</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="cta-actions-bottom">
                <button type="button" id="add-new-cta" class="button button-secondary">+ 新しいCTA設定を追加</button>
                <button type="button" id="save-tag-ctas" class="button button-primary">設定を保存</button>
            </div>
            
            <template id="cta-item-template">
                <div class="cta-item" data-index="__INDEX__">
                    <div class="cta-header">
                        <span class="cta-handle">☰</span>
                        <span class="cta-title">新しいCTA</span>
                        <div class="cta-actions">
                            <button type="button" class="button toggle-cta">▼</button>
                            <button type="button" class="button delete-cta">🗑️</button>
                        </div>
                    </div>
                    <div class="cta-body">
                        <input type="hidden" class="cta-id" value="">
                        <div class="cta-field">
                            <label>CTA名</label>
                            <input type="text" class="cta-name regular-text" placeholder="例: 転職系CTA">
                        </div>
                        <div class="cta-field">
                            <label>対象タグ</label>
                            <div class="tag-selector">
                                <input type="text" class="tag-search regular-text" placeholder="タグ名で検索...">
                                <div class="tag-search-results"></div>
                                <div class="selected-tags"></div>
                            </div>
                        </div>
                        <div class="cta-field">
                            <label>画像</label>
                            <div class="image-upload-field">
                                <input type="hidden" class="cta-image-url" value="">
                                <div class="image-preview-small"><span class="placeholder">画像なし</span></div>
                                <button type="button" class="button upload-cta-image">選択</button>
                                <button type="button" class="button remove-cta-image" style="display:none;">削除</button>
                            </div>
                        </div>
                        <div class="cta-field">
                            <label>リンクURL</label>
                            <input type="url" class="cta-link-url regular-text" placeholder="https://lin.ee/xxxxxx">
                        </div>
                        <div class="cta-field">
                            <label><input type="checkbox" class="cta-abtest-enabled"> A/Bテストを有効にする</label>
                        </div>
                        <div class="cta-variants" style="display:none;">
                            <div class="variant-item">
                                <h4>パターン A</h4>
                                <div class="variant-fields">
                                    <div class="image-upload-field inline">
                                        <input type="hidden" class="variant-image-url" value="">
                                        <div class="image-preview-tiny"></div>
                                        <button type="button" class="button button-small upload-variant-image">画像</button>
                                    </div>
                                    <input type="url" class="variant-link-url" placeholder="URL">
                                    <input type="number" class="variant-weight" value="50" min="0" max="100" style="width:60px;"> %
                                </div>
                            </div>
                            <div class="variant-item">
                                <h4>パターン B</h4>
                                <div class="variant-fields">
                                    <div class="image-upload-field inline">
                                        <input type="hidden" class="variant-image-url" value="">
                                        <div class="image-preview-tiny"></div>
                                        <button type="button" class="button button-small upload-variant-image">画像</button>
                                    </div>
                                    <input type="url" class="variant-link-url" placeholder="URL">
                                    <input type="number" class="variant-weight" value="50" min="0" max="100" style="width:60px;"> %
                                </div>
                            </div>
                            <button type="button" class="button add-variant">+ パターン追加</button>
                        </div>
                    </div>
                </div>
            </template>
            
            <template id="variant-item-template">
                <div class="variant-item">
                    <h4>パターン __LABEL__</h4>
                    <div class="variant-fields">
                        <div class="image-upload-field inline">
                            <input type="hidden" class="variant-image-url" value="">
                            <div class="image-preview-tiny"></div>
                            <button type="button" class="button button-small upload-variant-image">画像</button>
                        </div>
                        <input type="url" class="variant-link-url" placeholder="URL">
                        <input type="number" class="variant-weight" value="50" min="0" max="100" style="width:60px;"> %
                    </div>
                </div>
            </template>
        </div>
        <?php
    }
    
    public function render_settings() {
        $settings = get_option('popup_tracking_settings', array());
        
        $defaults = array(
            'is_active' => false,
            'abtest_enabled' => false,
            'active_variants' => 2,
            'popup_size' => 'medium',
            'popup_width' => 400,
            'trigger_type' => 'delay',
            'trigger_value' => 5,
            'frequency' => 'daily',
            'floating_enabled' => false,
            'floating_image_url' => '',
            'floating_link_url' => '',
            'floating_position' => 'br',
        );
        
        foreach ($this->variants as $v) {
            $key = strtolower($v);
            $defaults['image_url_' . $key] = '';
            $defaults['line_url_' . $key] = '';
            $defaults['weight_' . $key] = ($v === 'A') ? 100 : 0;
        }
        
        $settings = wp_parse_args($settings, $defaults);
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>⚙️ デフォルトCTA設定</h1>
            <p class="description">タグ別CTAにマッチしない記事で表示されるポップアップを設定します。</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('popup_tracking_settings_group'); ?>
                
                <div class="pattern-section">
                    <h2>基本設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>有効/無効</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="popup_tracking_settings[is_active]" value="1" <?php checked($settings['is_active']); ?>>
                                    <span class="slider"></span>
                                </label>
                                ポップアップを有効にする
                            </td>
                        </tr>
                        <tr>
                            <th>A/Bテストを有効にする</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="popup_tracking_settings[abtest_enabled]" id="abtest_enabled" value="1" <?php checked($settings['abtest_enabled']); ?>>
                                    <span class="slider"></span>
                                </label>
                                複数パターンをランダムに表示
                            </td>
                        </tr>
                        <tr id="active-variants-row" style="<?php echo $settings['abtest_enabled'] ? '' : 'display:none;'; ?>">
                            <th>使用するパターン数</th>
                            <td>
                                <select name="popup_tracking_settings[active_variants]" id="active_variants">
                                    <?php for ($i = 2; $i <= 10; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($settings['active_variants'], $i); ?>><?php echo $i; ?>パターン</option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php foreach ($this->variants as $index => $v) : 
                    $key = strtolower($v);
                    $show = ($index === 0) || ($settings['abtest_enabled'] && $index < $settings['active_variants']);
                ?>
                <div class="pattern-section variant-section variant-<?php echo $key; ?>" 
                     data-variant="<?php echo $index; ?>"
                     style="<?php echo $show ? '' : 'display:none;'; ?>">
                    <h2>
                        <?php if ($index === 0) : ?>
                            🅰️ パターンA（メイン）
                        <?php else : ?>
                            <span class="variant-badge"><?php echo $v; ?></span> パターン<?php echo $v; ?>
                        <?php endif; ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th>画像</th>
                            <td>
                                <div class="image-upload-field">
                                    <input type="hidden" name="popup_tracking_settings[image_url_<?php echo $key; ?>]" 
                                           id="popup_image_url_<?php echo $key; ?>" 
                                           value="<?php echo esc_url($settings['image_url_' . $key]); ?>">
                                    <div id="image-preview-<?php echo $key; ?>" class="image-preview-large">
                                        <?php if ($settings['image_url_' . $key]) : ?>
                                            <img src="<?php echo esc_url($settings['image_url_' . $key]); ?>" alt="">
                                        <?php else : ?>
                                            <span class="placeholder">画像を選択</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="image-buttons">
                                        <button type="button" class="button upload-image-btn" data-variant="<?php echo $key; ?>">選択</button>
                                        <button type="button" class="button remove-image-btn" data-variant="<?php echo $key; ?>" 
                                                style="<?php echo $settings['image_url_' . $key] ? '' : 'display:none;'; ?>">削除</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>リンク先URL</th>
                            <td>
                                <input type="url" name="popup_tracking_settings[line_url_<?php echo $key; ?>]" 
                                       value="<?php echo esc_url($settings['line_url_' . $key]); ?>" 
                                       class="regular-text" placeholder="https://lin.ee/xxxxxx">
                            </td>
                        </tr>
                        <?php if ($settings['abtest_enabled']) : ?>
                        <tr>
                            <th>表示比率（重み）</th>
                            <td>
                                <input type="number" name="popup_tracking_settings[weight_<?php echo $key; ?>]" 
                                       value="<?php echo esc_attr($settings['weight_' . $key]); ?>" 
                                       min="0" max="100" style="width: 80px;">
                                <span class="description">数値が大きいほど表示されやすい</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <div class="pattern-section">
                    <h2>📐 表示設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>表示サイズ</th>
                            <td>
                                <fieldset class="size-options">
                                    <label><input type="radio" name="popup_tracking_settings[popup_size]" value="small" <?php checked($settings['popup_size'], 'small'); ?>><span class="size-option"><span class="size-preview size-small"></span>小</span></label>
                                    <label><input type="radio" name="popup_tracking_settings[popup_size]" value="medium" <?php checked($settings['popup_size'], 'medium'); ?>><span class="size-option"><span class="size-preview size-medium"></span>中</span></label>
                                    <label><input type="radio" name="popup_tracking_settings[popup_size]" value="large" <?php checked($settings['popup_size'], 'large'); ?>><span class="size-option"><span class="size-preview size-large"></span>大</span></label>
                                    <label><input type="radio" name="popup_tracking_settings[popup_size]" value="custom" <?php checked($settings['popup_size'], 'custom'); ?>><span class="size-option"><span class="size-preview size-custom"></span>カスタム</span></label>
                                </fieldset>
                                <div id="custom-size-input" style="<?php echo $settings['popup_size'] === 'custom' ? '' : 'display:none;'; ?>">
                                    <input type="number" name="popup_tracking_settings[popup_width]" value="<?php echo esc_attr($settings['popup_width']); ?>" min="200" max="800" style="width: 80px;"> px
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>表示タイミング</th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="popup_tracking_settings[trigger_type]" value="delay" <?php checked($settings['trigger_type'], 'delay'); ?>> ページ読み込み後 <input type="number" name="popup_tracking_settings[trigger_value]" value="<?php echo esc_attr($settings['trigger_value']); ?>" min="1" max="60" style="width: 60px;"> 秒後</label><br><br>
                                    <label><input type="radio" name="popup_tracking_settings[trigger_type]" value="scroll" <?php checked($settings['trigger_type'], 'scroll'); ?>> スクロール <input type="number" name="popup_tracking_settings[trigger_value]" value="<?php echo esc_attr($settings['trigger_value']); ?>" min="10" max="100" style="width: 60px;"> % 到達時</label><br><br>
                                    <label><input type="radio" name="popup_tracking_settings[trigger_type]" value="exit" <?php checked($settings['trigger_type'], 'exit'); ?>> 離脱意図検知時</label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th>表示頻度</th>
                            <td>
                                <select name="popup_tracking_settings[frequency]">
                                    <option value="session" <?php selected($settings['frequency'], 'session'); ?>>1セッションに1回</option>
                                    <option value="daily" <?php selected($settings['frequency'], 'daily'); ?>>1日に1回</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="pattern-section">
                    <h2>📌 フローティングバナー</h2>
                    <p class="description">画面端に表示する小型バナー。クリック/表示/閉じるを計測します。</p>
                    <table class="form-table">
                        <tr>
                            <th>有効/無効</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="popup_tracking_settings[floating_enabled]" value="1" <?php checked($settings['floating_enabled']); ?>>
                                    <span class="slider"></span>
                                </label> フローティングバナーを表示する
                            </td>
                        </tr>
                        <tr>
                            <th>画像</th>
                            <td>
                                <div class="image-upload-field">
                                    <input type="hidden" name="popup_tracking_settings[floating_image_url]" id="floating_image_url" value="<?php echo esc_url($settings['floating_image_url']); ?>">
                                    <div id="floating-image-preview" class="image-preview-large">
                                        <?php if ($settings['floating_image_url']) : ?>
                                            <img src="<?php echo esc_url($settings['floating_image_url']); ?>" alt="">
                                        <?php else : ?>
                                            <span class="placeholder">画像を選択</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="image-buttons">
                                        <button type="button" class="button upload-image-btn" data-target="floating">選択</button>
                                        <button type="button" class="button remove-image-btn" data-target="floating" style="<?php echo $settings['floating_image_url'] ? '' : 'display:none;'; ?>">削除</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>リンク先URL</th>
                            <td><input type="url" name="popup_tracking_settings[floating_link_url]" value="<?php echo esc_url($settings['floating_link_url']); ?>" class="regular-text" placeholder="https://lin.ee/xxxxxx"></td>
                        </tr>
                        <tr>
                            <th>表示位置</th>
                            <td>
                                <label><input type="radio" name="popup_tracking_settings[floating_position]" value="br" <?php checked($settings['floating_position'], 'br'); ?>> 右下</label>
                                　<label><input type="radio" name="popup_tracking_settings[floating_position]" value="bl" <?php checked($settings['floating_position'], 'bl'); ?>> 左下</label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('設定を保存'); ?>
            </form>
            
            <div class="reset-section">
                <h2>🗑️ データ管理</h2>
                <button type="button" id="reset-logs-btn" class="button">計測ログをリセット</button>
            </div>
        </div>
        <?php
    }
    
    public function render_abtest() {
        $period = sanitize_text_field($_GET['period'] ?? 'week');
        list($start_date, $end_date) = $this->get_date_range($period, $_GET['start_date'] ?? '', $_GET['end_date'] ?? '');
        
        $variant_stats = Popup_Tracking_Database::get_stats_by_variant($start_date, $end_date);
        $cta_stats = Popup_Tracking_Database::get_stats_by_cta($start_date, $end_date);
        $settings = get_option('popup_tracking_settings', array());
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        
        $stats_map = array();
        foreach ($variant_stats as $vs) {
            $stats_map[$vs->variant] = $vs;
        }
        
        $cta_stats_map = array();
        foreach ($cta_stats as $cs) {
            $cta_stats_map[$cs->cta_id] = $cs;
        }
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🔬 A/Bテスト結果</h1>
            
            <div class="period-filter">
                <a href="?page=popup-tracking-abtest&period=today" class="button <?php echo $period === 'today' ? 'button-primary' : ''; ?>">今日</a>
                <a href="?page=popup-tracking-abtest&period=week" class="button <?php echo $period === 'week' ? 'button-primary' : ''; ?>">今週</a>
                <a href="?page=popup-tracking-abtest&period=month" class="button <?php echo $period === 'month' ? 'button-primary' : ''; ?>">今月</a>
            </div>
            
            <h2>📊 CTA別パフォーマンス</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>CTA名</th>
                        <th style="width:100px;">表示数</th>
                        <th style="width:100px;">クリック</th>
                        <th style="width:100px;">CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // デフォルトCTA
                    $default_stat = $cta_stats_map['default'] ?? null;
                    $default_imp = intval($default_stat->impressions ?? 0);
                    $default_click = intval($default_stat->clicks ?? 0);
                    $default_ctr = $default_imp > 0 ? round(($default_click / $default_imp) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><strong>デフォルトCTA</strong></td>
                        <td><?php echo number_format($default_imp); ?></td>
                        <td><?php echo number_format($default_click); ?></td>
                        <td><strong><?php echo $default_ctr; ?>%</strong></td>
                    </tr>
                    <?php foreach ($tag_ctas as $cta) : 
                        $cta_stat = $cta_stats_map[$cta['id']] ?? null;
                        $imp = intval($cta_stat->impressions ?? 0);
                        $click = intval($cta_stat->clicks ?? 0);
                        $ctr = $imp > 0 ? round(($click / $imp) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html($cta['name']); ?></td>
                        <td><?php echo number_format($imp); ?></td>
                        <td><?php echo number_format($click); ?></td>
                        <td><strong><?php echo $ctr; ?>%</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2 style="margin-top:32px;">🧪 バリアント別（デフォルトCTA）</h2>
            <?php if (empty($settings['abtest_enabled'])) : ?>
                <div class="notice notice-warning"><p>デフォルトCTAのA/Bテストは無効です。</p></div>
            <?php else : ?>
            <div class="abtest-grid">
                <?php 
                $active_count = intval($settings['active_variants'] ?? 2);
                $max_ctr = 0;
                $winner = '';
                
                foreach ($this->variants as $index => $v) {
                    if ($index >= $active_count) continue;
                    if (isset($stats_map[$v])) {
                        $imp = intval($stats_map[$v]->impressions);
                        if ($imp >= 50) {
                            $ctr = $imp > 0 ? ($stats_map[$v]->clicks / $imp) * 100 : 0;
                            if ($ctr > $max_ctr) {
                                $max_ctr = $ctr;
                                $winner = $v;
                            }
                        }
                    }
                }
                
                foreach ($this->variants as $index => $v) : 
                    if ($index >= $active_count) continue;
                    
                    $key = strtolower($v);
                    $stat = $stats_map[$v] ?? null;
                    $imp = intval($stat->impressions ?? 0);
                    $click = intval($stat->clicks ?? 0);
                    $ctr = $imp > 0 ? round(($click / $imp) * 100, 2) : 0;
                    $is_winner = ($winner === $v);
                ?>
                <div class="abtest-card <?php echo $is_winner ? 'winner' : ''; ?>">
                    <div class="variant-header">
                        <span class="variant-badge large"><?php echo $v; ?></span>
                        <?php if ($is_winner) : ?><span class="winner-badge">🏆 勝者</span><?php endif; ?>
                    </div>
                    <div class="variant-stats">
                        <div class="stat-row"><span class="stat-label">表示数</span><span class="stat-value"><?php echo number_format($imp); ?></span></div>
                        <div class="stat-row"><span class="stat-label">クリック</span><span class="stat-value"><?php echo number_format($click); ?></span></div>
                        <div class="stat-row highlight"><span class="stat-label">CTR</span><span class="stat-value"><?php echo $ctr; ?>%</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_targeting() {
        $targeting = get_option('popup_tracking_targeting', array());
        $defaults = array('target_mode' => 'all', 'category_mode' => 'all', 'target_categories' => array(), 'target_posts' => array(), 'exclude_posts' => array());
        $targeting = wp_parse_args($targeting, $defaults);
        $categories = get_categories(array('hide_empty' => false));
        
        $target_posts_data = array();
        foreach ($targeting['target_posts'] as $pid) {
            $p = get_post($pid);
            if ($p) $target_posts_data[] = array('id' => $p->ID, 'title' => $p->post_title);
        }
        $exclude_posts_data = array();
        foreach ($targeting['exclude_posts'] as $pid) {
            $p = get_post($pid);
            if ($p) $exclude_posts_data[] = array('id' => $p->ID, 'title' => $p->post_title);
        }
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🎯 表示条件設定</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('popup_tracking_targeting_group'); ?>
                
                <div class="targeting-section">
                    <h2>📝 記事の表示設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>表示モード</th>
                            <td>
                                <label><input type="radio" name="popup_tracking_targeting[target_mode]" value="all" <?php checked($targeting['target_mode'], 'all'); ?> class="target-mode-radio"> <strong>すべての記事に表示</strong></label><br><br>
                                <label><input type="radio" name="popup_tracking_targeting[target_mode]" value="include" <?php checked($targeting['target_mode'], 'include'); ?> class="target-mode-radio"> <strong>特定の記事のみに表示</strong></label><br><br>
                                <label><input type="radio" name="popup_tracking_targeting[target_mode]" value="exclude" <?php checked($targeting['target_mode'], 'exclude'); ?> class="target-mode-radio"> <strong>特定の記事を除外</strong></label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="targeting-section" id="include-posts-section" style="<?php echo $targeting['target_mode'] === 'include' ? '' : 'display:none;'; ?>">
                    <h3>✅ 表示する記事を選択</h3>
                    <div class="post-search-box">
                        <input type="text" id="search-target-posts" class="regular-text" placeholder="記事タイトルで検索...">
                        <div id="search-target-results" class="search-results"></div>
                    </div>
                    <div class="selected-posts" id="selected-target-posts">
                        <?php foreach ($target_posts_data as $pd) : ?>
                            <div class="selected-post-item" data-id="<?php echo $pd['id']; ?>"><span class="post-title"><?php echo esc_html($pd['title']); ?></span><button type="button" class="remove-post">×</button></div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="popup_tracking_targeting[target_posts]" id="target-posts-input" value="<?php echo implode(',', $targeting['target_posts']); ?>">
                </div>
                
                <div class="targeting-section" id="exclude-posts-section" style="<?php echo $targeting['target_mode'] === 'exclude' ? '' : 'display:none;'; ?>">
                    <h3>🚫 除外する記事を選択</h3>
                    <div class="post-search-box">
                        <input type="text" id="search-exclude-posts" class="regular-text" placeholder="記事タイトルで検索...">
                        <div id="search-exclude-results" class="search-results"></div>
                    </div>
                    <div class="selected-posts" id="selected-exclude-posts">
                        <?php foreach ($exclude_posts_data as $pd) : ?>
                            <div class="selected-post-item" data-id="<?php echo $pd['id']; ?>"><span class="post-title"><?php echo esc_html($pd['title']); ?></span><button type="button" class="remove-post">×</button></div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="popup_tracking_targeting[exclude_posts]" id="exclude-posts-input" value="<?php echo implode(',', $targeting['exclude_posts']); ?>">
                </div>
                
                <div class="targeting-section">
                    <h2>📁 カテゴリ設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>カテゴリフィルター</th>
                            <td>
                                <label><input type="radio" name="popup_tracking_targeting[category_mode]" value="all" <?php checked($targeting['category_mode'], 'all'); ?> class="category-mode-radio"> <strong>すべてのカテゴリ</strong></label><br><br>
                                <label><input type="radio" name="popup_tracking_targeting[category_mode]" value="include" <?php checked($targeting['category_mode'], 'include'); ?> class="category-mode-radio"> <strong>特定カテゴリのみ表示</strong></label><br><br>
                                <label><input type="radio" name="popup_tracking_targeting[category_mode]" value="exclude" <?php checked($targeting['category_mode'], 'exclude'); ?> class="category-mode-radio"> <strong>特定カテゴリを除外</strong></label>
                            </td>
                        </tr>
                    </table>
                    <div id="category-selection" class="category-selection" style="<?php echo $targeting['category_mode'] !== 'all' ? '' : 'display:none;'; ?>">
                        <div class="category-checkboxes">
                            <?php foreach ($categories as $cat) : ?>
                                <label class="category-checkbox"><input type="checkbox" name="popup_tracking_targeting[target_categories][]" value="<?php echo $cat->term_id; ?>" <?php checked(in_array($cat->term_id, $targeting['target_categories'])); ?>> <?php echo esc_html($cat->name); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="pattern-section">
                    <h2>📌 フローティングバナー</h2>
                    <p class="description">画面端に表示する小型バナー。クリック/表示/閉じるを計測します。</p>
                    <table class="form-table">
                        <tr>
                            <th>有効/無効</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="popup_tracking_settings[floating_enabled]" value="1" <?php checked($settings['floating_enabled']); ?>>
                                    <span class="slider"></span>
                                </label> フローティングバナーを表示する
                            </td>
                        </tr>
                        <tr>
                            <th>画像</th>
                            <td>
                                <div class="image-upload-field">
                                    <input type="hidden" name="popup_tracking_settings[floating_image_url]" id="floating_image_url" value="<?php echo esc_url($settings['floating_image_url']); ?>">
                                    <div id="floating-image-preview" class="image-preview-large">
                                        <?php if ($settings['floating_image_url']) : ?>
                                            <img src="<?php echo esc_url($settings['floating_image_url']); ?>" alt="">
                                        <?php else : ?>
                                            <span class="placeholder">画像を選択</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="image-buttons">
                                        <button type="button" class="button upload-image-btn" data-target="floating">選択</button>
                                        <button type="button" class="button remove-image-btn" data-target="floating" style="<?php echo $settings['floating_image_url'] ? '' : 'display:none;'; ?>">削除</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>リンク先URL</th>
                            <td><input type="url" name="popup_tracking_settings[floating_link_url]" value="<?php echo esc_url($settings['floating_link_url']); ?>" class="regular-text" placeholder="https://lin.ee/xxxxxx"></td>
                        </tr>
                        <tr>
                            <th>表示位置</th>
                            <td>
                                <label><input type="radio" name="popup_tracking_settings[floating_position]" value="br" <?php checked($settings['floating_position'], 'br'); ?>> 右下</label>
                                　<label><input type="radio" name="popup_tracking_settings[floating_position]" value="bl" <?php checked($settings['floating_position'], 'bl'); ?>> 左下</label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_debug() {
        $settings = get_option('popup_tracking_settings', array());
        $targeting = get_option('popup_tracking_targeting', array());
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        
        // 最新の記事を10件取得してチェック
        $recent_posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 10,
        ));
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🔧 デバッグ</h1>
            
            <div class="debug-section">
                <h2>📋 基本設定チェック</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td style="width:200px;"><strong>ポップアップ有効</strong></td>
                            <td>
                                <?php if (!empty($settings['is_active'])) : ?>
                                    <span style="color:#2ed573;">✅ 有効</span>
                                <?php else : ?>
                                    <span style="color:#ff4757;">❌ 無効</span>
                                    <a href="<?php echo admin_url('admin.php?page=popup-tracking-settings'); ?>" class="button button-small">設定を開く</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>画像URL (image_url)</strong></td>
                            <td>
                                <?php if (!empty($settings['image_url'])) : ?>
                                    <span style="color:#2ed573;">✅ <?php echo esc_html($settings['image_url']); ?></span>
                                <?php else : ?>
                                    <span style="color:#888;">未設定</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>画像URL (image_url_a)</strong></td>
                            <td>
                                <?php if (!empty($settings['image_url_a'])) : ?>
                                    <span style="color:#2ed573;">✅ <?php echo esc_html($settings['image_url_a']); ?></span>
                                <?php else : ?>
                                    <span style="color:#888;">未設定</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>リンクURL (line_url)</strong></td>
                            <td><?php echo !empty($settings['line_url']) ? esc_html($settings['line_url']) : '<span style="color:#888;">未設定</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>リンクURL (line_url_a)</strong></td>
                            <td><?php echo !empty($settings['line_url_a']) ? esc_html($settings['line_url_a']) : '<span style="color:#888;">未設定</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>表示タイミング</strong></td>
                            <td><?php echo esc_html($settings['trigger_type'] ?? 'delay'); ?> / <?php echo esc_html($settings['trigger_value'] ?? '5'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>A/Bテスト</strong></td>
                            <td><?php echo !empty($settings['abtest_enabled']) ? '有効 (' . ($settings['active_variants'] ?? 2) . 'パターン)' : '無効'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="debug-section" style="margin-top:24px;">
                <h2>🎯 表示条件設定</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td style="width:200px;"><strong>記事モード</strong></td>
                            <td>
                                <?php 
                                $mode = $targeting['target_mode'] ?? 'all';
                                if ($mode === 'all') echo '✅ すべての記事に表示';
                                elseif ($mode === 'include') echo '⚠️ 特定の記事のみ（' . count($targeting['target_posts'] ?? array()) . '件）';
                                else echo '⚠️ 特定の記事を除外（' . count($targeting['exclude_posts'] ?? array()) . '件）';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>カテゴリモード</strong></td>
                            <td>
                                <?php 
                                $cat_mode = $targeting['category_mode'] ?? 'all';
                                if ($cat_mode === 'all') echo '✅ すべてのカテゴリ';
                                else echo '⚠️ カテゴリフィルター有効';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="debug-section" style="margin-top:24px;">
                <h2>🏷️ タグ別CTA設定</h2>
                <?php if (empty($tag_ctas)) : ?>
                    <p>タグ別CTAは設定されていません（デフォルトCTAが使用されます）</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>CTA名</th>
                                <th>対象タグ</th>
                                <th>画像</th>
                                <th>A/B</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tag_ctas as $cta) : ?>
                            <tr>
                                <td><?php echo esc_html($cta['name'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $tag_names = array();
                                    foreach ($cta['tags'] ?? array() as $tid) {
                                        $t = get_tag($tid);
                                        if ($t) $tag_names[] = $t->name;
                                    }
                                    echo esc_html(implode(', ', $tag_names) ?: '未設定');
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($cta['image_url'])) : ?>
                                        <span style="color:#2ed573;">✅ 設定済</span>
                                    <?php else : ?>
                                        <span style="color:#ff4757;">❌ 未設定</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($cta['abtest_enabled']) ? '有効' : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="debug-section" style="margin-top:24px;">
                <h2>📝 最近の記事のポップアップ状態</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>記事</th>
                            <th>タグ</th>
                            <th>使用CTA</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_posts as $post) : 
                            $post_tags = get_the_tags($post->ID);
                            $tag_names = array();
                            $post_tag_ids = array();
                            if ($post_tags) {
                                foreach ($post_tags as $tag) {
                                    $tag_names[] = $tag->name;
                                    $post_tag_ids[] = $tag->term_id;
                                }
                            }
                            
                            // どのCTAが使われるか判定
                            $matched_cta = null;
                            foreach ($tag_ctas as $cta) {
                                $cta_tags = $cta['tags'] ?? array();
                                if (!empty($cta_tags) && !empty(array_intersect($post_tag_ids, $cta_tags))) {
                                    $matched_cta = $cta;
                                    break;
                                }
                            }
                            
                            // 画像があるか
                            if ($matched_cta) {
                                $has_image = !empty($matched_cta['image_url']);
                                $cta_name = $matched_cta['name'] ?? 'タグ別CTA';
                            } else {
                                $has_image = !empty($settings['image_url_a']) || !empty($settings['image_url']);
                                $cta_name = 'デフォルト';
                            }
                            
                            // 表示条件チェック
                            $target_mode = $targeting['target_mode'] ?? 'all';
                            $is_targeted = true;
                            if ($target_mode === 'include') {
                                $is_targeted = in_array($post->ID, $targeting['target_posts'] ?? array());
                            } elseif ($target_mode === 'exclude') {
                                $is_targeted = !in_array($post->ID, $targeting['exclude_posts'] ?? array());
                            }
                            
                            $will_show = !empty($settings['is_active']) && $has_image && $is_targeted;
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                                    <?php echo esc_html(mb_substr($post->post_title, 0, 30)); ?>...
                                </a>
                            </td>
                            <td><?php echo esc_html(implode(', ', array_slice($tag_names, 0, 3)) ?: '-'); ?></td>
                            <td><?php echo esc_html($cta_name); ?></td>
                            <td>
                                <?php if ($will_show) : ?>
                                    <span style="color:#2ed573;">✅ 表示</span>
                                <?php else : ?>
                                    <span style="color:#ff4757;">❌ 非表示</span>
                                    <?php 
                                    $reasons = array();
                                    if (empty($settings['is_active'])) $reasons[] = '無効';
                                    if (!$has_image) $reasons[] = '画像なし';
                                    if (!$is_targeted) $reasons[] = '対象外';
                                    echo '(' . implode(', ', $reasons) . ')';
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="debug-section" style="margin-top:24px;">
                <h2>💾 生データ</h2>
                <details>
                    <summary style="cursor:pointer;padding:10px;background:#f5f5f5;border-radius:4px;">popup_tracking_settings を表示</summary>
                    <pre style="background:#1a1a2e;color:#fff;padding:15px;border-radius:4px;overflow:auto;max-height:300px;"><?php print_r($settings); ?></pre>
                </details>
                <details style="margin-top:10px;">
                    <summary style="cursor:pointer;padding:10px;background:#f5f5f5;border-radius:4px;">popup_tracking_targeting を表示</summary>
                    <pre style="background:#1a1a2e;color:#fff;padding:15px;border-radius:4px;overflow:auto;max-height:300px;"><?php print_r($targeting); ?></pre>
                </details>
                <details style="margin-top:10px;">
                    <summary style="cursor:pointer;padding:10px;background:#f5f5f5;border-radius:4px;">popup_tracking_tag_ctas を表示</summary>
                    <pre style="background:#1a1a2e;color:#fff;padding:15px;border-radius:4px;overflow:auto;max-height:300px;"><?php print_r($tag_ctas); ?></pre>
                </details>
            </div>
        </div>
        <?php
    }
    
    // ============================================
    // フローティングバナー
    // ============================================
    
    public function render_floating_dashboard() {
        $period = sanitize_text_field($_GET['period'] ?? 'week');
        list($start_date, $end_date) = $this->get_date_range($period, $_GET['start_date'] ?? '', $_GET['end_date'] ?? '');
        
        // 期間を文字列に変換
        $period_label = '';
        switch ($period) {
            case 'today': $period_label = '今日'; break;
            case 'week': $period_label = '今週'; break;
            case 'month': $period_label = '今月'; break;
            default: $period_label = $start_date . '〜' . $end_date;
        }
        
        $stats = Popup_Tracking_Database::get_stats_by_cta($start_date, $end_date, 'floating');
        $total_impressions = 0;
        $total_clicks = 0;
        
        foreach ($stats as $stat) {
            if ($stat->cta_id === 'floating') {
                $total_impressions += $stat->impressions;
                $total_clicks += $stat->clicks;
            }
        }
        
        $ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) * 100 : 0;
        
        $post_stats = Popup_Tracking_Database::get_stats_by_post($start_date, $end_date, array(), 'or', 'floating');
        
        // A/Bテスト結果
        $variant_stats = Popup_Tracking_Database::get_stats_by_variant($start_date, $end_date, 'floating');
        $variant_stats_map = array();
        foreach ($variant_stats as $vs) {
            $variant_stats_map[$vs->variant] = $vs;
        }
        
        // バリアント別の記事別統計を取得
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        $variant_post_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                l.variant,
                l.post_id,
                SUM(CASE WHEN l.event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN l.event_type = 'click' THEN 1 ELSE 0 END) as clicks
            FROM $table_name l
            WHERE l.cta_id = %s AND l.created_at >= %s AND l.created_at <= %s
            GROUP BY l.variant, l.post_id
            ORDER BY l.variant, clicks DESC",
            'floating',
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        // バリアント別の記事別統計を整理
        $variant_posts_map = array();
        foreach ($variant_post_stats as $vps) {
            $variant = $vps->variant;
            if (!isset($variant_posts_map[$variant])) {
                $variant_posts_map[$variant] = array();
            }
            $variant_posts_map[$variant][] = $vps;
        }
        
        // 各バリアントの平均CTRを計算
        $variant_avg_ctr = array();
        foreach ($variant_posts_map as $variant => $posts) {
            $ctr_sum = 0;
            $post_count = 0;
            foreach ($posts as $post_stat) {
                $imp = intval($post_stat->impressions);
                $click = intval($post_stat->clicks);
                if ($imp > 0) {
                    $ctr_sum += ($click / $imp) * 100;
                    $post_count++;
                }
            }
            $variant_avg_ctr[$variant] = $post_count > 0 ? round($ctr_sum / $post_count, 2) : 0;
        }
        
        // スナップショットデータ
        $floating_snapshots = get_option('floating_banner_snapshots', array());
        
        include POPUP_TRACKING_PATH . 'includes/views/floating-dashboard.php';
    }
    
    public function render_floating_settings() {
        $settings = get_option('floating_banner_settings', array());
        
        $defaults = array(
            'is_active' => false,
            'abtest_enabled' => false,
            'active_variants' => 2,
            'frequency' => 'daily',
        );
        
        foreach ($this->variants as $v) {
            $key = strtolower($v);
            $defaults['image_url_pc_' . $key] = '';
            $defaults['image_url_sp_' . $key] = '';
            $defaults['link_url_' . $key] = '';
            $defaults['weight_' . $key] = ($v === 'A') ? 100 : 0;
        }
        
        $settings = wp_parse_args($settings, $defaults);
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>📌 フローティングバナー設定</h1>
            <p class="description">画面下部に横長で表示されるフローティングバナーを設定します。</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('floating_banner_settings_group'); ?>
                
                <div class="pattern-section">
                    <h2>基本設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>有効/無効</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="floating_banner_settings[is_active]" value="1" <?php checked($settings['is_active']); ?>>
                                    <span class="slider"></span>
                                </label>
                                フローティングバナーを有効にする
                            </td>
                        </tr>
                        <tr>
                            <th>A/Bテストを有効にする</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="floating_banner_settings[abtest_enabled]" id="floating_abtest_enabled" value="1" <?php checked($settings['abtest_enabled']); ?>>
                                    <span class="slider"></span>
                                </label>
                                複数パターンをランダムに表示
                            </td>
                        </tr>
                        <tr id="floating-active-variants-row" style="<?php echo $settings['abtest_enabled'] ? '' : 'display:none;'; ?>">
                            <th>使用するパターン数</th>
                            <td>
                                <select name="floating_banner_settings[active_variants]" id="floating_active_variants">
                                    <?php for ($i = 2; $i <= 10; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($settings['active_variants'], $i); ?>><?php echo $i; ?>パターン</option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>表示頻度</th>
                            <td>
                                <select name="floating_banner_settings[frequency]">
                                    <option value="session" <?php selected($settings['frequency'], 'session'); ?>>1セッションに1回</option>
                                    <option value="daily" <?php selected($settings['frequency'], 'daily'); ?>>1日に1回</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php foreach ($this->variants as $index => $v) : 
                    $key = strtolower($v);
                    $show = ($index === 0) || ($settings['abtest_enabled'] && $index < $settings['active_variants']);
                ?>
                <div class="pattern-section variant-section floating-variant-section floating-variant-<?php echo $key; ?>" 
                     data-variant="<?php echo $index; ?>"
                     style="<?php echo $show ? '' : 'display:none;'; ?>">
                    <h2>
                        <?php if ($index === 0) : ?>
                            🅰️ パターンA（メイン）
                        <?php else : ?>
                            <span class="variant-badge"><?php echo $v; ?></span> パターン<?php echo $v; ?>
                        <?php endif; ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th>PC用画像（横長）</th>
                            <td>
                                <div class="image-upload-field">
                                    <input type="hidden" name="floating_banner_settings[image_url_pc_<?php echo $key; ?>]" 
                                           id="floating_image_url_pc_<?php echo $key; ?>" 
                                           value="<?php echo esc_url($settings['image_url_pc_' . $key]); ?>">
                                    <div id="floating-image-preview-pc-<?php echo $key; ?>" class="image-preview-large">
                                        <?php if ($settings['image_url_pc_' . $key]) : ?>
                                            <img src="<?php echo esc_url($settings['image_url_pc_' . $key]); ?>" alt="">
                                        <?php else : ?>
                                            <span class="placeholder">画像を選択</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="image-buttons">
                                        <button type="button" class="button upload-floating-image-btn" data-variant="<?php echo $key; ?>" data-device="pc">選択</button>
                                        <button type="button" class="button remove-floating-image-btn" data-variant="<?php echo $key; ?>" data-device="pc" 
                                                style="<?php echo $settings['image_url_pc_' . $key] ? '' : 'display:none;'; ?>">削除</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>スマホ用画像（横長）</th>
                            <td>
                                <div class="image-upload-field">
                                    <input type="hidden" name="floating_banner_settings[image_url_sp_<?php echo $key; ?>]" 
                                           id="floating_image_url_sp_<?php echo $key; ?>" 
                                           value="<?php echo esc_url($settings['image_url_sp_' . $key]); ?>">
                                    <div id="floating-image-preview-sp-<?php echo $key; ?>" class="image-preview-large">
                                        <?php if ($settings['image_url_sp_' . $key]) : ?>
                                            <img src="<?php echo esc_url($settings['image_url_sp_' . $key]); ?>" alt="">
                                        <?php else : ?>
                                            <span class="placeholder">画像を選択</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="image-buttons">
                                        <button type="button" class="button upload-floating-image-btn" data-variant="<?php echo $key; ?>" data-device="sp">選択</button>
                                        <button type="button" class="button remove-floating-image-btn" data-variant="<?php echo $key; ?>" data-device="sp" 
                                                style="<?php echo $settings['image_url_sp_' . $key] ? '' : 'display:none;'; ?>">削除</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>リンク先URL</th>
                            <td>
                                <input type="url" name="floating_banner_settings[link_url_<?php echo $key; ?>]" 
                                       value="<?php echo esc_url($settings['link_url_' . $key]); ?>" 
                                       class="regular-text" placeholder="https://lin.ee/xxxxxx">
                            </td>
                        </tr>
                        <?php if ($settings['abtest_enabled']) : ?>
                        <tr>
                            <th>表示比率（重み）</th>
                            <td>
                                <input type="number" name="floating_banner_settings[weight_<?php echo $key; ?>]" 
                                       value="<?php echo esc_attr($settings['weight_' . $key]); ?>" 
                                       min="0" max="100" style="width: 80px;">
                                <span class="description">数値が大きいほど表示されやすい</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_floating_targeting() {
        $targeting = get_option('floating_banner_targeting', array());
        
        $defaults = array(
            'target_mode' => 'all',
            'category_mode' => 'all',
            'target_categories' => array(),
            'target_posts' => array(),
            'exclude_posts' => array(),
        );
        
        $targeting = wp_parse_args($targeting, $defaults);
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🎯 フローティングバナー表示条件設定</h1>
            <p class="description">どの記事にフローティングバナーを表示するかを設定します。</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('floating_banner_targeting_group'); ?>
                
                <div class="pattern-section">
                    <h2>記事フィルター</h2>
                    <table class="form-table">
                        <tr>
                            <th>表示モード</th>
                            <td>
                                <label><input type="radio" name="floating_banner_targeting[target_mode]" value="all" class="target-mode-radio" <?php checked($targeting['target_mode'], 'all'); ?>> 全記事に表示</label><br>
                                <label><input type="radio" name="floating_banner_targeting[target_mode]" value="include" class="target-mode-radio" <?php checked($targeting['target_mode'], 'include'); ?>> 指定記事のみ表示</label><br>
                                <label><input type="radio" name="floating_banner_targeting[target_mode]" value="exclude" class="target-mode-radio" <?php checked($targeting['target_mode'], 'exclude'); ?>> 指定記事を除外</label>
                            </td>
                        </tr>
                        <tr id="floating-include-posts-section" style="<?php echo $targeting['target_mode'] === 'include' ? '' : 'display:none;'; ?>">
                            <th>表示する記事</th>
                            <td>
                                <input type="text" id="floating-search-target-posts" placeholder="記事を検索..." style="width:300px;">
                                <div id="floating-search-target-results" class="search-results"></div>
                                <input type="hidden" name="floating_banner_targeting[target_posts]" id="floating-target-posts-input" value="<?php echo esc_attr(implode(',', $targeting['target_posts'])); ?>">
                                <div id="floating-selected-target-posts" class="selected-posts">
                                    <?php if (!empty($targeting['target_posts'])) : 
                                        foreach ($targeting['target_posts'] as $post_id) :
                                            $post = get_post($post_id);
                                            if ($post) :
                                    ?>
                                    <div class="selected-post-item" data-id="<?php echo $post_id; ?>">
                                        <span class="post-title"><?php echo esc_html($post->post_title); ?></span>
                                        <button type="button" class="remove-post">×</button>
                                    </div>
                                    <?php 
                                            endif;
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <tr id="floating-exclude-posts-section" style="<?php echo $targeting['target_mode'] === 'exclude' ? '' : 'display:none;'; ?>">
                            <th>除外する記事</th>
                            <td>
                                <input type="text" id="floating-search-exclude-posts" placeholder="記事を検索..." style="width:300px;">
                                <div id="floating-search-exclude-results" class="search-results"></div>
                                <input type="hidden" name="floating_banner_targeting[exclude_posts]" id="floating-exclude-posts-input" value="<?php echo esc_attr(implode(',', $targeting['exclude_posts'])); ?>">
                                <div id="floating-selected-exclude-posts" class="selected-posts">
                                    <?php if (!empty($targeting['exclude_posts'])) : 
                                        foreach ($targeting['exclude_posts'] as $post_id) :
                                            $post = get_post($post_id);
                                            if ($post) :
                                    ?>
                                    <div class="selected-post-item" data-id="<?php echo $post_id; ?>">
                                        <span class="post-title"><?php echo esc_html($post->post_title); ?></span>
                                        <button type="button" class="remove-post">×</button>
                                    </div>
                                    <?php 
                                            endif;
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="pattern-section">
                    <h2>カテゴリフィルター</h2>
                    <table class="form-table">
                        <tr>
                            <th>カテゴリモード</th>
                            <td>
                                <label><input type="radio" name="floating_banner_targeting[category_mode]" value="all" class="category-mode-radio" <?php checked($targeting['category_mode'], 'all'); ?>> 全カテゴリ</label><br>
                                <label><input type="radio" name="floating_banner_targeting[category_mode]" value="include" class="category-mode-radio" <?php checked($targeting['category_mode'], 'include'); ?>> 指定カテゴリのみ</label><br>
                                <label><input type="radio" name="floating_banner_targeting[category_mode]" value="exclude" class="category-mode-radio" <?php checked($targeting['category_mode'], 'exclude'); ?>> 指定カテゴリを除外</label>
                            </td>
                        </tr>
                        <tr id="floating-category-selection" style="<?php echo $targeting['category_mode'] !== 'all' ? '' : 'display:none;'; ?>">
                            <th>対象カテゴリ</th>
                            <td>
                                <?php
                                $categories = get_categories(array('hide_empty' => false));
                                $selected_cats = $targeting['target_categories'] ?? array();
                                foreach ($categories as $cat) :
                                ?>
                                <label style="display:block;margin:5px 0;">
                                    <input type="checkbox" name="floating_banner_targeting[target_categories][]" value="<?php echo $cat->term_id; ?>" 
                                           <?php checked(in_array($cat->term_id, $selected_cats)); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_floating_test() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        // 最新のログを取得
        $recent_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE cta_id = %s ORDER BY created_at DESC LIMIT 50",
            'floating'
        ));
        
        // 統計情報
        $today_start = date('Y-m-d 00:00:00');
        $today_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as closes
            FROM $table_name 
            WHERE cta_id = %s AND created_at >= %s",
            'floating',
            $today_start
        ));
        
        ?>
        <div class="wrap popup-tracking-admin">
            <h1>🧪 フローティングバナーテスト・デバッグ</h1>
            
            <div class="test-section" style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
                <h2>📊 今日の計測状況</h2>
                <div style="display:flex;gap:20px;margin:15px 0;">
                    <div style="flex:1;padding:15px;background:#f0f8ff;border-radius:4px;">
                        <strong>表示数</strong><br>
                        <span style="font-size:24px;font-weight:bold;"><?php echo number_format($today_stats->impressions ?? 0); ?></span>
                    </div>
                    <div style="flex:1;padding:15px;background:#fff0f0;border-radius:4px;">
                        <strong>クリック数</strong><br>
                        <span style="font-size:24px;font-weight:bold;"><?php echo number_format($today_stats->clicks ?? 0); ?></span>
                    </div>
                    <div style="flex:1;padding:15px;background:#f0fff0;border-radius:4px;">
                        <strong>閉じた数</strong><br>
                        <span style="font-size:24px;font-weight:bold;"><?php echo number_format($today_stats->closes ?? 0); ?></span>
                    </div>
                </div>
                <p class="description">
                    💡 <strong>テスト方法:</strong> 記事ページにアクセスして、フローティングバナーが表示されるか確認してください。<br>
                    URLに <code>?popup_debug=1</code> を追加すると、詳細なデバッグ情報が表示されます。
                </p>
            </div>
            
            <div class="test-section" style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
                <h2>📝 最新の計測ログ（最新50件）</h2>
                <p class="description">リアルタイムで計測状況を確認できます。ページを更新すると最新のログが表示されます。</p>
                
                <button type="button" id="refresh-logs-btn" class="button" style="margin-bottom:15px;">🔄 ログを更新</button>
                
                <?php if (empty($recent_logs)) : ?>
                <p style="padding:20px;background:#f9f9f9;border-radius:4px;color:#999;">
                    まだログが記録されていません。記事ページにアクセスしてフローティングバナーを表示すると、ここにログが表示されます。
                </p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th style="width:100px;">記事ID</th>
                            <th style="width:100px;">イベント</th>
                            <th style="width:80px;">バリアント</th>
                            <th style="width:80px;">デバイス</th>
                            <th style="width:180px;">日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log) : 
                            $post = get_post($log->post_id);
                            $event_colors = array(
                                'impression' => '#3498db',
                                'click' => '#2ecc71',
                                'close' => '#e74c3c'
                            );
                            $event_color = $event_colors[$log->event_type] ?? '#333';
                        ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td>
                                <?php if ($post) : ?>
                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank"><?php echo $log->post_id; ?></a>
                                <?php else : ?>
                                    <?php echo $log->post_id; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color:<?php echo $event_color; ?>;font-weight:bold;">
                                    <?php 
                                    $event_names = array(
                                        'impression' => '表示',
                                        'click' => 'クリック',
                                        'close' => '閉じる'
                                    );
                                    echo $event_names[$log->event_type] ?? $log->event_type;
                                    ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->variant); ?></td>
                            <td><?php echo esc_html($log->device); ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="test-section" style="margin:20px 0;padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
                <h2>🔍 テスト手順</h2>
                <ol style="line-height:2;">
                    <li><strong>フローティングバナーを有効にする</strong><br>
                        フローティングバナー → バナー設定 で「有効/無効」をONにしてください</li>
                    <li><strong>PC用・SP用画像を設定</strong><br>
                        少なくともPC用またはSP用の画像を1つ設定してください</li>
                    <li><strong>記事ページにアクセス</strong><br>
                        投稿ページにアクセスして、画面下部にフローティングバナーが表示されるか確認</li>
                    <li><strong>デバッグモードで確認</strong><br>
                        URLに <code>?popup_debug=1</code> を追加すると、詳細なデバッグ情報が表示されます</li>
                    <li><strong>計測を確認</strong><br>
                        このページに戻って、ログが記録されているか確認してください</li>
                </ol>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-logs-btn').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }
    
    public function ajax_save_snapshot() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error('記録名を入力してください');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        
        // 期間が空の場合は期間コードから計算
        if (empty($start_date) || empty($end_date)) {
            list($start_date, $end_date) = $this->get_date_range($period, '', '');
        }
        
        // サーバー側で最新の統計を再計算（ポップアップのみ、フローティングバナーを除外）
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        $where = "1=1 AND cta_id != 'floating'";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as total_impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as total_clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as total_closes
                FROM $table_name
                WHERE $where";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $summary = $wpdb->get_row($sql);
        $impressions = intval($summary->total_impressions ?? 0);
        $clicks = intval($summary->total_clicks ?? 0);
        $closes = intval($summary->total_closes ?? 0);
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
        
        // 現在のCTA設定を取得
        $settings = get_option('popup_tracking_settings', array());
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        
        // メインのCTA情報を取得
        $main_cta = array(
            'cta_id' => 'default',
            'variant' => 'A',
            'image_url' => $settings['image_url_a'] ?? $settings['image_url'] ?? '',
            'link_url' => $settings['line_url_a'] ?? $settings['line_url'] ?? '',
        );
        
        if (!empty($settings['abtest_enabled'])) {
            $active_count = intval($settings['active_variants'] ?? 2);
            $variants_info = array();
            for ($i = 0; $i < $active_count; $i++) {
                $v = $this->variants[$i];
                $key = strtolower($v);
                $variants_info[$v] = array(
                    'image_url' => $settings['image_url_' . $key] ?? '',
                    'link_url' => $settings['line_url_' . $key] ?? '',
                    'weight' => intval($settings['weight_' . $key] ?? 0),
                );
            }
            $main_cta['variants'] = $variants_info;
        }
        
        // 表示用の期間文字列（常に日付レンジで保存）
        $period_label = $start_date . '〜' . $end_date;
        
        $snapshot = array(
            'name' => $name,
            'created_at' => current_time('mysql'),
            'period' => $period_label,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'closes' => $closes,
            'ctr' => $ctr,
            'cta_id' => $main_cta['cta_id'],
            'variant' => $main_cta['variant'],
            'image_url' => $main_cta['image_url'],
            'link_url' => $main_cta['link_url'],
            'abtest_enabled' => !empty($settings['abtest_enabled']),
            'variants' => $main_cta['variants'] ?? array(),
            'tag_ctas_count' => count($tag_ctas),
        );
        
        $snapshots = get_option('popup_tracking_snapshots', array());
        $snapshots[] = $snapshot;
        update_option('popup_tracking_snapshots', $snapshots);
        
        wp_send_json_success(array('message' => '記録を保存しました'));
    }
    
    public function ajax_delete_snapshot() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $index = intval($_POST['index'] ?? -1);
        if ($index < 0) {
            wp_send_json_error('無効なインデックスです');
        }
        
        $snapshots = get_option('popup_tracking_snapshots', array());
        if (isset($snapshots[$index])) {
            unset($snapshots[$index]);
            $snapshots = array_values($snapshots); // インデックスを再構築
            update_option('popup_tracking_snapshots', $snapshots);
            wp_send_json_success(array('message' => '記録を削除しました'));
        } else {
            wp_send_json_error('記録が見つかりません');
        }
    }
    
    public function ajax_delete_all_snapshots() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        update_option('popup_tracking_snapshots', array());
        wp_send_json_success(array('message' => 'すべての記録を削除しました'));
    }
    
    public function ajax_save_floating_snapshot() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error('記録名を入力してください');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        
        // 期間が空の場合は期間コードから計算
        if (empty($start_date) || empty($end_date)) {
            list($start_date, $end_date) = $this->get_date_range($period, '', '');
        }
        
        // サーバー側で最新の統計を再計算（フローティングバナーのみ）
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        $where = "1=1 AND cta_id = 'floating'";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as total_impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as total_clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as total_closes
                FROM $table_name
                WHERE $where";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $summary = $wpdb->get_row($sql);
        $impressions = intval($summary->total_impressions ?? 0);
        $clicks = intval($summary->total_clicks ?? 0);
        $closes = intval($summary->total_closes ?? 0);
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
        
        // 現在のフローティングバナー設定を取得
        $settings = get_option('floating_banner_settings', array());
        
        // メインのバナー情報を取得
        $main_banner = array(
            'cta_id' => 'floating',
            'variant' => 'A',
            'image_url_pc' => $settings['image_url_pc_a'] ?? '',
            'image_url_sp' => $settings['image_url_sp_a'] ?? '',
            'link_url' => $settings['link_url_a'] ?? '',
        );
        
        if (!empty($settings['abtest_enabled'])) {
            $active_count = intval($settings['active_variants'] ?? 2);
            $variants_info = array();
            for ($i = 0; $i < $active_count; $i++) {
                $v = $this->variants[$i];
                $key = strtolower($v);
                $variants_info[$v] = array(
                    'image_url_pc' => $settings['image_url_pc_' . $key] ?? '',
                    'image_url_sp' => $settings['image_url_sp_' . $key] ?? '',
                    'link_url' => $settings['link_url_' . $key] ?? '',
                    'weight' => intval($settings['weight_' . $key] ?? 0),
                );
            }
            $main_banner['variants'] = $variants_info;
        }
        
        // 表示用の期間文字列（常に日付レンジで保存）
        $period_label = $start_date . '〜' . $end_date;
        
        $snapshot = array(
            'name' => $name,
            'created_at' => current_time('mysql'),
            'period' => $period_label,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'closes' => $closes,
            'ctr' => $ctr,
            'cta_id' => $main_banner['cta_id'],
            'variant' => $main_banner['variant'],
            'image_url_pc' => $main_banner['image_url_pc'],
            'image_url_sp' => $main_banner['image_url_sp'],
            'link_url' => $main_banner['link_url'],
            'abtest_enabled' => !empty($settings['abtest_enabled']),
            'variants' => $main_banner['variants'] ?? array(),
        );
        
        $snapshots = get_option('floating_banner_snapshots', array());
        $snapshots[] = $snapshot;
        update_option('floating_banner_snapshots', $snapshots);
        
        wp_send_json_success(array('message' => '記録を保存しました'));
    }
    
    public function ajax_delete_floating_snapshot() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $index = intval($_POST['index'] ?? -1);
        if ($index < 0) {
            wp_send_json_error('無効なインデックスです');
        }
        
        $snapshots = get_option('floating_banner_snapshots', array());
        if (isset($snapshots[$index])) {
            unset($snapshots[$index]);
            $snapshots = array_values($snapshots);
            update_option('floating_banner_snapshots', $snapshots);
            wp_send_json_success(array('message' => '記録を削除しました'));
        } else {
            wp_send_json_error('記録が見つかりません');
        }
    }
    
    public function ajax_delete_all_floating_snapshots() {
        check_ajax_referer('popup_tracking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        update_option('floating_banner_snapshots', array());
        wp_send_json_success(array('message' => 'すべての記録を削除しました'));
    }
    
    public function render_popup_snapshots() {
        include POPUP_TRACKING_PATH . 'includes/views/popup-snapshots.php';
    }
    
    public function render_floating_snapshots() {
        include POPUP_TRACKING_PATH . 'includes/views/floating-snapshots.php';
    }
    
    public function render_ctr_analysis() {
        include POPUP_TRACKING_PATH . 'includes/views/debug-ctr-analysis.php';
    }
}
