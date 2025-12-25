<?php
/**
 * フロントエンド表示クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Popup_Tracking_Frontend {
    
    private $variants = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    private $current_cta = null;
    
    public function __construct() {
        add_action('wp_footer', array($this, 'render_popup'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        add_action('wp_ajax_popup_tracking_log', array($this, 'handle_ajax_log'));
        add_action('wp_ajax_nopriv_popup_tracking_log', array($this, 'handle_ajax_log'));
    }
    
    public function enqueue_assets() {
        $show_popup = $this->should_show_popup();
        $show_floating = $this->should_show_floating();
        $show_floating_banner = $this->should_show_floating_banner();
        if (!$show_popup && !$show_floating && !$show_floating_banner) return;
        
        wp_enqueue_style('popup-tracking-frontend', POPUP_TRACKING_URL . 'assets/css/popup.css', array(), POPUP_TRACKING_VERSION);
        wp_enqueue_script('popup-tracking-frontend', POPUP_TRACKING_URL . 'assets/js/popup.js', array('jquery'), POPUP_TRACKING_VERSION, true);
        
        $settings = get_option('popup_tracking_settings', array());
        $floating_banner_settings = get_option('floating_banner_settings', array());
        
        // CTAを決定（タグ別 or デフォルト）
        $cta_data = $show_popup ? $this->get_cta_for_post(get_the_ID()) : array(
            'cta_id' => 'default',
            'variant' => 'A',
            'image_url' => '',
            'link_url' => '',
        );
        $this->current_cta = $cta_data;
        
        // フローティングバナー（下側横長）のCTA
        $floating_banner_cta = array(
            'cta_id' => 'floating',
            'variant' => 'A',
            'image_url_pc' => '',
            'image_url_sp' => '',
            'link_url' => '',
        );
        
        if ($show_floating_banner) {
            $floating_banner_cta = $this->get_floating_banner_cta();
            // 画像が実際に存在するか再確認
            $has_pc_img = !empty($floating_banner_cta['image_url_pc']);
            $has_sp_img = !empty($floating_banner_cta['image_url_sp']);
            if (!$has_pc_img && !$has_sp_img) {
                $show_floating_banner = false;
            }
        }
        
        // ポップアップサイズの決定
        $popup_size = $settings['popup_size'] ?? 'medium';
        $popup_width = $settings['popup_width'] ?? 400;
        $width_map = array('small' => 280, 'medium' => 400, 'large' => 550, 'custom' => $popup_width);
        $actual_width = $width_map[$popup_size] ?? 400;
        
        wp_localize_script('popup-tracking-frontend', 'popupTrackingConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('popup_tracking_frontend'),
            'postId' => get_the_ID(),
            'ctaId' => $cta_data['cta_id'],
            'variant' => $cta_data['variant'],
            'imageUrl' => $cta_data['image_url'],
            'linkUrl' => $cta_data['link_url'],
            'popupWidth' => $actual_width,
            'triggerType' => $settings['trigger_type'] ?? 'delay',
            'triggerValue' => intval($settings['trigger_value'] ?? 5),
            'frequency' => $settings['frequency'] ?? 'daily',
            'popupEnabled' => $show_popup ? 1 : 0,
            'floatingEnabled' => $show_floating ? 1 : 0,
            'floatingImageUrl' => $settings['floating_image_url'] ?? '',
            'floatingLinkUrl' => $settings['floating_link_url'] ?? '',
            'floatingPosition' => $settings['floating_position'] ?? 'br',
            'floatingCtaId' => 'floating',
            'floatingBannerEnabled' => $show_floating_banner ? 1 : 0,
            'floatingBannerCtaId' => $floating_banner_cta['cta_id'],
            'floatingBannerVariant' => $floating_banner_cta['variant'],
            'floatingBannerImageUrlPc' => $floating_banner_cta['image_url_pc'],
            'floatingBannerImageUrlSp' => $floating_banner_cta['image_url_sp'],
            'floatingBannerLinkUrl' => $floating_banner_cta['link_url'],
            'floatingBannerFrequency' => $floating_banner_settings['frequency'] ?? 'daily',
            'debug' => isset($_GET['popup_debug']) ? 1 : 0,
        ));
    }

    /**
     * 記事に対応するCTAを取得
     */
    private function get_cta_for_post($post_id) {
        $post_tags = get_the_tags($post_id);
        $post_tag_ids = array();
        
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                $post_tag_ids[] = $tag->term_id;
            }
        }
        
        // タグ別CTA設定を取得
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        
        // 優先順位順にマッチングをチェック
        foreach ($tag_ctas as $cta) {
            $cta_tags = $cta['tags'] ?? array();
            
            if (empty($cta_tags)) continue;
            
            // いずれかのタグがマッチするか
            $matched = !empty(array_intersect($post_tag_ids, $cta_tags));
            
            if ($matched && !empty($cta['image_url'])) {
                // このCTAを使用（画像がある場合のみ）
                return $this->resolve_cta_variant($cta);
            }
        }
        
        // マッチなし → デフォルトCTA
        return $this->get_default_cta();
    }
    
    /**
     * CTAのバリアントを決定（A/Bテスト対応）
     */
    private function resolve_cta_variant($cta) {
        $cta_id = $cta['id'] ?? 'default';
        
        // A/Bテストが有効でない場合
        if (empty($cta['abtest_enabled']) || empty($cta['variants'])) {
            return array(
                'cta_id' => $cta_id,
                'variant' => 'A',
                'image_url' => $cta['image_url'] ?? '',
                'link_url' => $cta['link_url'] ?? '',
            );
        }
        
        // A/Bテスト: 重み付きランダム選択
        $variants = $cta['variants'];
        $total_weight = 0;
        
        foreach ($variants as $v) {
            $w = intval($v['weight'] ?? 0);
            if (!empty($v['image_url'])) {
                $total_weight += $w;
            }
        }
        
        if ($total_weight <= 0) {
            return array(
                'cta_id' => $cta_id,
                'variant' => 'A',
                'image_url' => $cta['image_url'] ?? '',
                'link_url' => $cta['link_url'] ?? '',
            );
        }
        
        $rand = mt_rand(1, $total_weight);
        $cumulative = 0;
        
        foreach ($variants as $index => $v) {
            if (empty($v['image_url'])) continue;
            
            $cumulative += intval($v['weight'] ?? 0);
            if ($rand <= $cumulative) {
                return array(
                    'cta_id' => $cta_id,
                    'variant' => chr(65 + $index), // A, B, C...
                    'image_url' => $v['image_url'],
                    'link_url' => $v['link_url'] ?? $cta['link_url'] ?? '',
                );
            }
        }
        
        // フォールバック
        return array(
            'cta_id' => $cta_id,
            'variant' => 'A',
            'image_url' => $cta['image_url'] ?? '',
            'link_url' => $cta['link_url'] ?? '',
        );
    }
    
    /**
     * デフォルトCTAを取得
     */
    private function get_default_cta() {
        $settings = get_option('popup_tracking_settings', array());
        $abtest_enabled = !empty($settings['abtest_enabled']);
        
        // 後方互換性: 古いキー名もサポート
        $default_image = '';
        $default_link = '';
        
        // 新しいキー（image_url_a）を優先、なければ古いキー（image_url）
        if (!empty($settings['image_url_a'])) {
            $default_image = $settings['image_url_a'];
        } elseif (!empty($settings['image_url'])) {
            $default_image = $settings['image_url'];
        }
        
        if (!empty($settings['line_url_a'])) {
            $default_link = $settings['line_url_a'];
        } elseif (!empty($settings['line_url'])) {
            $default_link = $settings['line_url'];
        }
        
        if (!$abtest_enabled) {
            return array(
                'cta_id' => 'default',
                'variant' => 'A',
                'image_url' => $default_image,
                'link_url' => $default_link,
            );
        }
        
        // デフォルトCTAのA/Bテスト
        $active_count = intval($settings['active_variants'] ?? 2);
        $weights = array();
        $total_weight = 0;
        
        for ($i = 0; $i < $active_count; $i++) {
            $v = $this->variants[$i];
            $key = strtolower($v);
            $w = intval($settings['weight_' . $key] ?? 0);
            $img = $settings['image_url_' . $key] ?? '';
            
            // パターンAの場合は後方互換性を考慮
            if ($key === 'a' && empty($img) && !empty($settings['image_url'])) {
                $img = $settings['image_url'];
            }
            
            if (!empty($img)) {
                $link = $settings['line_url_' . $key] ?? '';
                if ($key === 'a' && empty($link) && !empty($settings['line_url'])) {
                    $link = $settings['line_url'];
                }
                
                $weights[$v] = array(
                    'weight' => $w,
                    'image_url' => $img,
                    'link_url' => $link,
                );
                $total_weight += $w;
            }
        }
        
        if ($total_weight <= 0 || empty($weights)) {
            return array(
                'cta_id' => 'default',
                'variant' => 'A',
                'image_url' => $default_image,
                'link_url' => $default_link,
            );
        }
        
        $rand = mt_rand(1, $total_weight);
        $cumulative = 0;
        
        foreach ($weights as $variant => $data) {
            $cumulative += $data['weight'];
            if ($rand <= $cumulative) {
                return array(
                    'cta_id' => 'default',
                    'variant' => $variant,
                    'image_url' => $data['image_url'],
                    'link_url' => $data['link_url'],
                );
            }
        }
        
        return array(
            'cta_id' => 'default',
            'variant' => 'A',
            'image_url' => $default_image,
            'link_url' => $default_link,
        );
    }
    
    private function passes_targeting($post_id) {
        $targeting = get_option('popup_tracking_targeting', array());
        
        $target_mode = $targeting['target_mode'] ?? 'all';
        $target_posts = $targeting['target_posts'] ?? array();
        $exclude_posts = $targeting['exclude_posts'] ?? array();
        
        if ($target_mode === 'include' && !in_array($post_id, $target_posts)) return false;
        if ($target_mode === 'exclude' && in_array($post_id, $exclude_posts)) return false;
        
        $category_mode = $targeting['category_mode'] ?? 'all';
        $target_categories = $targeting['target_categories'] ?? array();
        
        if ($category_mode !== 'all' && !empty($target_categories)) {
            $post_cats = wp_get_post_categories($post_id);
            $has_category = !empty(array_intersect($post_cats, $target_categories));
            
            if ($category_mode === 'include' && !$has_category) return false;
            if ($category_mode === 'exclude' && $has_category) return false;
        }
        
        return true;
    }
    
    public function should_show_popup() {
        if (!is_single()) return false;
        
        $settings = get_option('popup_tracking_settings', array());
        if (empty($settings['is_active'])) return false;
        
        // CTAデータを取得して画像があるか確認
        $cta_data = $this->get_cta_for_post(get_the_ID());
        if (empty($cta_data['image_url'])) return false;
        
        if (!$this->passes_targeting(get_the_ID())) return false;
        
        return true;
    }
    
    public function should_show_floating() {
        if (!is_single()) return false;
        $settings = get_option('popup_tracking_settings', array());
        if (empty($settings['floating_enabled'])) return false;
        if (empty($settings['floating_image_url'])) return false;
        
        // フローティングポップアップ専用のカテゴリーチェック
        $floating_category_mode = $settings['floating_category_mode'] ?? 'all';
        $floating_target_categories = $settings['floating_target_categories'] ?? array();
        
        if ($floating_category_mode !== 'all' && !empty($floating_target_categories)) {
            $post_id = get_the_ID();
            $post_cats = wp_get_post_categories($post_id);
            $has_category = !empty(array_intersect($post_cats, $floating_target_categories));
            
            if ($floating_category_mode === 'include' && !$has_category) return false;
            if ($floating_category_mode === 'exclude' && $has_category) return false;
        }
        
        // 通常のターゲティングチェック（記事レベル）
        if (!$this->passes_targeting(get_the_ID())) return false;
        
        return true;
    }
    
    public function should_show_floating_banner() {
        if (!is_single()) return false;
        $settings = get_option('floating_banner_settings', array());
        if (empty($settings['is_active'])) return false;
        if (!$this->passes_floating_targeting(get_the_ID())) return false;
        
        // 画像があるか確認（PC or SP）
        $abtest_enabled = !empty($settings['abtest_enabled']);
        $active_count = intval($settings['active_variants'] ?? 2);
        
        for ($i = 0; $i < $active_count; $i++) {
            $v = $this->variants[$i];
            $key = strtolower($v);
            $pc_img = $settings['image_url_pc_' . $key] ?? '';
            $sp_img = $settings['image_url_sp_' . $key] ?? '';
            if (!empty($pc_img) || !empty($sp_img)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function passes_floating_targeting($post_id) {
        $targeting = get_option('floating_banner_targeting', array());
        
        $target_mode = $targeting['target_mode'] ?? 'all';
        $target_posts = $targeting['target_posts'] ?? array();
        $exclude_posts = $targeting['exclude_posts'] ?? array();
        
        if ($target_mode === 'include' && !in_array($post_id, $target_posts)) return false;
        if ($target_mode === 'exclude' && in_array($post_id, $exclude_posts)) return false;
        
        $category_mode = $targeting['category_mode'] ?? 'all';
        $target_categories = $targeting['target_categories'] ?? array();
        
        if ($category_mode !== 'all' && !empty($target_categories)) {
            $post_cats = wp_get_post_categories($post_id);
            $has_category = !empty(array_intersect($post_cats, $target_categories));
            
            if ($category_mode === 'include' && !$has_category) return false;
            if ($category_mode === 'exclude' && $has_category) return false;
        }
        
        return true;
    }
    
    private function get_floating_banner_cta() {
        $settings = get_option('floating_banner_settings', array());
        $abtest_enabled = !empty($settings['abtest_enabled']);
        
        if (!$abtest_enabled) {
            $key = 'a';
            return array(
                'cta_id' => 'floating',
                'variant' => 'A',
                'image_url_pc' => $settings['image_url_pc_' . $key] ?? '',
                'image_url_sp' => $settings['image_url_sp_' . $key] ?? '',
                'link_url' => $settings['link_url_' . $key] ?? '',
            );
        }
        
        // A/Bテスト: 重み付きランダム選択
        $active_count = intval($settings['active_variants'] ?? 2);
        $weights = array();
        $total_weight = 0;
        
        for ($i = 0; $i < $active_count; $i++) {
            $v = $this->variants[$i];
            $key = strtolower($v);
            $w = intval($settings['weight_' . $key] ?? 0);
            $pc_img = $settings['image_url_pc_' . $key] ?? '';
            $sp_img = $settings['image_url_sp_' . $key] ?? '';
            
            if (!empty($pc_img) || !empty($sp_img)) {
                $weights[$v] = array(
                    'weight' => $w,
                    'image_url_pc' => $pc_img,
                    'image_url_sp' => $sp_img,
                    'link_url' => $settings['link_url_' . $key] ?? '',
                );
                $total_weight += $w;
            }
        }
        
        if ($total_weight <= 0 || empty($weights)) {
            $key = 'a';
            return array(
                'cta_id' => 'floating',
                'variant' => 'A',
                'image_url_pc' => $settings['image_url_pc_' . $key] ?? '',
                'image_url_sp' => $settings['image_url_sp_' . $key] ?? '',
                'link_url' => $settings['link_url_' . $key] ?? '',
            );
        }
        
        $rand = mt_rand(1, $total_weight);
        $cumulative = 0;
        
        foreach ($weights as $variant => $data) {
            $cumulative += $data['weight'];
            if ($rand <= $cumulative) {
                return array(
                    'cta_id' => 'floating',
                    'variant' => $variant,
                    'image_url_pc' => $data['image_url_pc'],
                    'image_url_sp' => $data['image_url_sp'],
                    'link_url' => $data['link_url'],
                );
            }
        }
        
        // フォールバック
        $key = 'a';
        return array(
            'cta_id' => 'floating',
            'variant' => 'A',
            'image_url_pc' => $settings['image_url_pc_' . $key] ?? '',
            'image_url_sp' => $settings['image_url_sp_' . $key] ?? '',
            'link_url' => $settings['link_url_' . $key] ?? '',
        );
    }
    
    public function render_popup() {
        // デバッグモード: ?popup_debug=1 で詳細情報を画面に表示
        if (isset($_GET['popup_debug']) && current_user_can('manage_options')) {
            $this->render_debug_info();
        }
        
        $show_popup = $this->should_show_popup();
        $show_floating = $this->should_show_floating();
        $show_floating_banner = $this->should_show_floating_banner();
        if (!$show_popup && !$show_floating && !$show_floating_banner) return;
        
        if ($show_popup) {
            $cta_data = $this->current_cta;
            if (!$cta_data) {
                $cta_data = $this->get_cta_for_post(get_the_ID());
            }
            
            $image_url = $cta_data['image_url'] ?? '';
            $link_url = $cta_data['link_url'] ?? '';
            
            if (!empty($image_url)) {
                ?>
                <div id="popup-tracking-modal" class="popup-tracking-modal" style="display: none;">
                    <div class="popup-tracking-overlay"></div>
                    <div class="popup-tracking-content">
                        <button type="button" class="popup-tracking-close" aria-label="閉じる">&times;</button>
                        <a href="<?php echo esc_url($link_url); ?>" id="popup-tracking-link" class="popup-tracking-link" target="_blank" rel="noopener">
                            <img src="<?php echo esc_url($image_url); ?>" alt="ポップアップ" class="popup-tracking-image">
                        </a>
                    </div>
                </div>
                <?php
            }
        }

        if ($show_floating) {
            $settings = get_option('popup_tracking_settings', array());
            $f_image = $settings['floating_image_url'] ?? '';
            $f_link = $settings['floating_link_url'] ?? '';
            $position = $settings['floating_position'] ?? 'br';
            ?>
            <div id="popup-floating-banner" class="popup-floating-banner position-<?php echo esc_attr($position); ?>" style="display:none;">
                <a href="<?php echo esc_url($f_link); ?>" id="popup-floating-link" class="popup-floating-link" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url($f_image); ?>" alt="フローティングバナー" class="popup-floating-image">
                </a>
            </div>
            <?php
        }
        
        if ($show_floating_banner) {
            $floating_banner_cta = $this->get_floating_banner_cta();
            $pc_img = $floating_banner_cta['image_url_pc'] ?? '';
            $sp_img = $floating_banner_cta['image_url_sp'] ?? '';
            $link = $floating_banner_cta['link_url'] ?? '';
            
            if (!empty($pc_img) || !empty($sp_img)) {
                ?>
                <div id="popup-floating-banner-bottom" class="popup-floating-banner-bottom">
                    <a href="<?php echo esc_url($link); ?>" id="popup-floating-banner-link" class="popup-floating-banner-link" target="_blank" rel="noopener">
                        <?php if (!empty($pc_img)) : ?>
                            <img src="<?php echo esc_url($pc_img); ?>" alt="フローティングバナー" class="popup-floating-banner-image popup-floating-banner-image-pc">
                        <?php endif; ?>
                        <?php if (!empty($sp_img)) : ?>
                            <img src="<?php echo esc_url($sp_img); ?>" alt="フローティングバナー" class="popup-floating-banner-image popup-floating-banner-image-sp">
                        <?php endif; ?>
                    </a>
                </div>
                <?php
            }
        }
    }
    
    /**
     * デバッグ情報を画面に表示（管理者のみ）
     */
    private function render_debug_info() {
        $settings = get_option('popup_tracking_settings', array());
        $targeting = get_option('popup_tracking_targeting', array());
        $tag_ctas = get_option('popup_tracking_tag_ctas', array());
        $post_id = get_the_ID();
        $cta_data = $this->get_cta_for_post($post_id);
        
        // チェック項目
        $checks = array();
        $checks['is_single'] = is_single() ? '✅ OK' : '❌ 投稿ページではない';
        $checks['is_active'] = !empty($settings['is_active']) ? '✅ 有効' : '❌ 無効（設定で有効にしてください）';
        $checks['image_url'] = !empty($cta_data['image_url']) ? '✅ ' . $cta_data['image_url'] : '❌ 画像未設定';
        
        // ターゲティング
        $target_mode = $targeting['target_mode'] ?? 'all';
        if ($target_mode === 'all') {
            $checks['targeting'] = '✅ すべての記事に表示';
        } elseif ($target_mode === 'include') {
            $target_posts = $targeting['target_posts'] ?? array();
            $checks['targeting'] = in_array($post_id, $target_posts) ? '✅ この記事は対象' : '❌ この記事は対象外（include設定）';
        } else {
            $exclude_posts = $targeting['exclude_posts'] ?? array();
            $checks['targeting'] = in_array($post_id, $exclude_posts) ? '❌ この記事は除外されている' : '✅ 除外されていない';
        }
        
        // タグ情報
        $post_tags = get_the_tags($post_id);
        $tag_names = array();
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                $tag_names[] = $tag->name;
            }
        }
        
        ?>
        <div style="position:fixed;bottom:0;left:0;right:0;background:#1a1a2e;color:#fff;padding:20px;z-index:999999;font-family:monospace;font-size:13px;max-height:50vh;overflow:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <strong style="color:#00d9ff;font-size:16px;">🔍 Popup Tracking Debug</strong>
                <button onclick="this.parentElement.parentElement.remove()" style="background:#ff4757;color:#fff;border:none;padding:5px 15px;cursor:pointer;border-radius:4px;">閉じる</button>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
                <div>
                    <h4 style="color:#ffd93d;margin:0 0 10px;">📋 チェック結果</h4>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">投稿ページか</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo $checks['is_single']; ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">ポップアップ有効</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo $checks['is_active']; ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">画像URL</td><td style="padding:5px;border-bottom:1px solid #333;word-break:break-all;"><?php echo $checks['image_url']; ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">表示条件</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo $checks['targeting']; ?></td></tr>
                    </table>
                </div>
                
                <div>
                    <h4 style="color:#ffd93d;margin:0 0 10px;">🎯 CTA情報</h4>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">CTA ID</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo esc_html($cta_data['cta_id'] ?? 'default'); ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">バリアント</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo esc_html($cta_data['variant'] ?? 'A'); ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">リンクURL</td><td style="padding:5px;border-bottom:1px solid #333;word-break:break-all;"><?php echo esc_html($cta_data['link_url'] ?? '未設定'); ?></td></tr>
                    </table>
                </div>
                
                <div>
                    <h4 style="color:#ffd93d;margin:0 0 10px;">🏷️ 記事情報</h4>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">記事ID</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo $post_id; ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">タグ</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo !empty($tag_names) ? implode(', ', $tag_names) : 'なし'; ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">タグ別CTA数</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo count($tag_ctas); ?>個設定</td></tr>
                    </table>
                </div>
                
                <div>
                    <h4 style="color:#ffd93d;margin:0 0 10px;">⚙️ 設定値</h4>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">image_url</td><td style="padding:5px;border-bottom:1px solid #333;word-break:break-all;"><?php echo esc_html($settings['image_url'] ?? '未設定'); ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">image_url_a</td><td style="padding:5px;border-bottom:1px solid #333;word-break:break-all;"><?php echo esc_html($settings['image_url_a'] ?? '未設定'); ?></td></tr>
                        <tr><td style="padding:5px;border-bottom:1px solid #333;">trigger</td><td style="padding:5px;border-bottom:1px solid #333;"><?php echo esc_html($settings['trigger_type'] ?? 'delay'); ?> / <?php echo esc_html($settings['trigger_value'] ?? '5'); ?></td></tr>
                    </table>
                </div>
            </div>
            
            <div style="margin-top:15px;padding:10px;background:#2d2d44;border-radius:4px;">
                <strong>🔑 ポップアップ:</strong> 
                <?php 
                $should_show = $this->should_show_popup();
                if ($should_show) {
                    echo '<span style="color:#2ed573;">✅ 表示されるはずです</span>';
                } else {
                    echo '<span style="color:#ff4757;">❌ 表示されません</span> - 上記のチェック結果を確認してください';
                }
                ?>
            </div>
            
            <?php
            // フローティングバナーの情報
            $floating_settings = get_option('floating_banner_settings', array());
            $floating_targeting = get_option('floating_banner_targeting', array());
            $floating_cta = $this->get_floating_banner_cta();
            $should_show_floating = $this->should_show_floating_banner();
            ?>
            <div style="margin-top:20px;padding:15px;background:#1e3a5f;border-radius:4px;border-left:4px solid #00d9ff;">
                <h4 style="color:#00d9ff;margin:0 0 15px;">📌 フローティングバナー情報</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:15px;">
                    <div>
                        <table style="width:100%;border-collapse:collapse;">
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">有効/無効</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;"><?php echo !empty($floating_settings['is_active']) ? '✅ 有効' : '❌ 無効'; ?></td></tr>
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">PC画像</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;word-break:break-all;"><?php echo !empty($floating_cta['image_url_pc']) ? '✅ 設定済み' : '❌ 未設定'; ?></td></tr>
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">SP画像</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;word-break:break-all;"><?php echo !empty($floating_cta['image_url_sp']) ? '✅ 設定済み' : '❌ 未設定'; ?></td></tr>
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">CTA ID</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;"><?php echo esc_html($floating_cta['cta_id'] ?? 'floating'); ?></td></tr>
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">バリアント</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;"><?php echo esc_html($floating_cta['variant'] ?? 'A'); ?></td></tr>
                        </table>
                    </div>
                    <div>
                        <table style="width:100%;border-collapse:collapse;">
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">表示条件</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;">
                                <?php
                                $target_mode = $floating_targeting['target_mode'] ?? 'all';
                                if ($target_mode === 'all') {
                                    echo '✅ すべての記事';
                                } elseif ($target_mode === 'include') {
                                    $target_posts = $floating_targeting['target_posts'] ?? array();
                                    echo in_array($post_id, $target_posts) ? '✅ 対象' : '❌ 対象外';
                                } else {
                                    $exclude_posts = $floating_targeting['exclude_posts'] ?? array();
                                    echo in_array($post_id, $exclude_posts) ? '❌ 除外' : '✅ 対象';
                                }
                                ?>
                            </td></tr>
                            <tr><td style="padding:5px;border-bottom:1px solid #2d4a6b;">リンクURL</td><td style="padding:5px;border-bottom:1px solid #2d4a6b;word-break:break-all;"><?php echo esc_html($floating_cta['link_url'] ?? '未設定'); ?></td></tr>
                        </table>
                    </div>
                </div>
                <div style="margin-top:15px;padding:10px;background:#2d4a6b;border-radius:4px;">
                    <strong>🔑 フローティングバナー:</strong> 
                    <?php 
                    if ($should_show_floating) {
                        echo '<span style="color:#2ed573;">✅ 表示されるはずです（常時表示）</span>';
                    } else {
                        echo '<span style="color:#ff4757;">❌ 表示されません</span> - 上記のチェック結果を確認してください';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_ajax_log() {
        check_ajax_referer('popup_tracking_frontend', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $variant = sanitize_text_field($_POST['variant'] ?? 'A');
        $cta_id = sanitize_text_field($_POST['cta_id'] ?? 'default');
        
        if (!in_array($variant, $this->variants)) {
            $variant = 'A';
        }
        
        $valid_events = array('impression', 'click', 'close');
        if (!$post_id || !in_array($event_type, $valid_events)) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device = (preg_match('/Mobile|Android|iPhone|iPad/i', $user_agent)) ? 'sp' : 'pc';
        
        $session_id = '';
        if (!empty($_COOKIE['popup_tracking_session'])) {
            $session_id = sanitize_text_field($_COOKIE['popup_tracking_session']);
        }
        
        // クリックイベントの場合は、同じセッションで表示が記録されているか確認
        if ($event_type === 'click' && !empty($session_id)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'popup_logs';
            $has_impression = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE session_id = %s 
                AND post_id = %d 
                AND cta_id = %s 
                AND variant = %s 
                AND event_type = 'impression'",
                $session_id,
                $post_id,
                $cta_id,
                $variant
            ));
            
            if (!$has_impression) {
                wp_send_json_error(array('message' => 'Click without impression', 'code' => 'no_impression'));
                return;
            }
        }
        
        $result = Popup_Tracking_Database::insert_log(array(
            'post_id' => $post_id,
            'variant' => $variant,
            'cta_id' => $cta_id,
            'event_type' => $event_type,
            'device' => $device,
            'session_id' => $session_id,
        ));
        
        if ($result) {
            wp_send_json_success(array('logged' => true, 'event' => $event_type, 'variant' => $variant, 'cta_id' => $cta_id));
        } else {
            // 重複の可能性がある場合は特別なメッセージを返す
            wp_send_json_error(array('message' => 'Log failed or duplicate', 'code' => 'duplicate'));
        }
    }
}
