<?php
/**
 * データベース操作クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Popup_Tracking_Database {
    
    /**
     * テーブル作成・更新
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            // variantカラムが存在するか確認
            $variant_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'variant'");
            if (!$variant_exists) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN variant varchar(10) DEFAULT 'A' AFTER post_id");
                $wpdb->query("ALTER TABLE $table_name ADD INDEX variant (variant)");
            }
            
            // cta_idカラムが存在するか確認
            $cta_id_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'cta_id'");
            if (!$cta_id_exists) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN cta_id varchar(32) DEFAULT 'default' AFTER variant");
                $wpdb->query("ALTER TABLE $table_name ADD INDEX cta_id (cta_id)");
            }
        } else {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                variant varchar(10) DEFAULT 'A',
                cta_id varchar(32) DEFAULT 'default',
                event_type varchar(20) NOT NULL,
                device varchar(10) DEFAULT 'pc',
                session_id varchar(64) DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY variant (variant),
                KEY cta_id (cta_id),
                KEY event_type (event_type),
                KEY device (device),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * ログ挿入（重複チェック付き）
     */
    public static function insert_log($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            self::create_tables();
        }
        
        $post_id = intval($data['post_id']);
        $variant = sanitize_text_field($data['variant'] ?? 'A');
        $cta_id = sanitize_text_field($data['cta_id'] ?? 'default');
        $event_type = sanitize_text_field($data['event_type']);
        $session_id = sanitize_text_field($data['session_id'] ?? '');
        
        // 重複チェック: 同じセッション、同じ記事、同じCTA、同じバリアント、同じイベントタイプで
        // 5分以内に既に記録されている場合は重複として扱う
        // ただし、フローティングバナーの表示（impression）は、同じセッション・同じ記事で1日1回のみ
        if (!empty($session_id)) {
            if ($cta_id === 'floating' && $event_type === 'impression') {
                // フローティングバナーの表示は、同じセッション・同じ記事で1日1回のみ
                $duplicate_check = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                    WHERE session_id = %s 
                    AND post_id = %d 
                    AND cta_id = %s 
                    AND variant = %s 
                    AND event_type = %s 
                    AND DATE(created_at) = CURDATE()",
                    $session_id,
                    $post_id,
                    $cta_id,
                    $variant,
                    $event_type
                ));
            } else {
                // その他のイベントは5分以内の重複をチェック
                $duplicate_check = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                    WHERE session_id = %s 
                    AND post_id = %d 
                    AND cta_id = %s 
                    AND variant = %s 
                    AND event_type = %s 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                    $session_id,
                    $post_id,
                    $cta_id,
                    $variant,
                    $event_type
                ));
            }
            
            if ($duplicate_check > 0) {
                // 重複ログを記録（デバッグ用）
                error_log('Popup Tracking: Duplicate log prevented - session: ' . $session_id . ', event: ' . $event_type . ', post: ' . $post_id . ', cta: ' . $cta_id);
                return false; // 重複のため記録しない
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'variant' => $variant,
                'cta_id' => $cta_id,
                'event_type' => $event_type,
                'device' => $data['device'],
                'session_id' => $session_id,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Popup Tracking Insert Error: ' . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * 記事別の集計データ取得
     */
    public static function get_stats_by_post($start_date = null, $end_date = null, $tag_ids = array(), $tag_mode = 'or', $cta_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND l.created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND l.created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        // CTA IDフィルター
        if ($cta_id !== null) {
            $where .= " AND l.cta_id = %s";
            $params[] = $cta_id;
        }
        
        // タグフィルター
        $post_id_filter = '';
        if (!empty($tag_ids)) {
            $filtered_post_ids = self::get_post_ids_by_tags($tag_ids, $tag_mode);
            if (empty($filtered_post_ids)) {
                return array(); // マッチする記事なし
            }
            $post_id_filter = " AND l.post_id IN (" . implode(',', array_map('intval', $filtered_post_ids)) . ")";
        }
        
        $sql = "SELECT 
                    l.post_id,
                    SUM(CASE WHEN l.event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN l.event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    SUM(CASE WHEN l.event_type = 'close' THEN 1 ELSE 0 END) as closes
                FROM $table_name l
                WHERE $where $post_id_filter
                GROUP BY l.post_id
                ORDER BY clicks DESC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * タグIDから記事IDを取得
     */
    public static function get_post_ids_by_tags($tag_ids, $mode = 'or') {
        global $wpdb;
        
        if (empty($tag_ids)) {
            return array();
        }
        
        $tag_ids = array_map('intval', $tag_ids);
        $placeholders = implode(',', array_fill(0, count($tag_ids), '%d'));
        
        if ($mode === 'and') {
            // すべてのタグを含む記事
            $sql = $wpdb->prepare(
                "SELECT tr.object_id
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'post_tag' AND tt.term_id IN ($placeholders)
                GROUP BY tr.object_id
                HAVING COUNT(DISTINCT tt.term_id) = %d",
                array_merge($tag_ids, array(count($tag_ids)))
            );
        } else {
            // いずれかのタグを含む記事
            $sql = $wpdb->prepare(
                "SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'post_tag' AND tt.term_id IN ($placeholders)",
                $tag_ids
            );
        }
        
        return $wpdb->get_col($sql);
    }
    
    /**
     * タグ別サマリー取得（合計・平均）
     */
    public static function get_tag_summary($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        // まず記事別のデータを取得
        $post_stats = self::get_stats_by_post($start_date, $end_date);
        
        // 記事ごとのCTRを計算
        $post_ctr = array();
        foreach ($post_stats as $stat) {
            $imp = intval($stat->impressions);
            $click = intval($stat->clicks);
            $post_ctr[$stat->post_id] = $imp > 0 ? ($click / $imp) * 100 : 0;
        }
        
        // タグごとに集計
        $tag_stats = array();
        $no_tag_posts = array();
        
        foreach ($post_stats as $stat) {
            $tags = get_the_tags($stat->post_id);
            
            if (!$tags) {
                $no_tag_posts[] = $stat;
            } else {
                foreach ($tags as $tag) {
                    if (!isset($tag_stats[$tag->term_id])) {
                        $tag_stats[$tag->term_id] = array(
                            'tag_id' => $tag->term_id,
                            'tag_name' => $tag->name,
                            'post_ids' => array(),
                            'impressions' => 0,
                            'clicks' => 0,
                            'closes' => 0,
                            'ctr_sum' => 0,
                        );
                    }
                    
                    // 同じ記事を複数回カウントしないようにチェック
                    if (!in_array($stat->post_id, $tag_stats[$tag->term_id]['post_ids'])) {
                        $tag_stats[$tag->term_id]['post_ids'][] = $stat->post_id;
                        $tag_stats[$tag->term_id]['impressions'] += intval($stat->impressions);
                        $tag_stats[$tag->term_id]['clicks'] += intval($stat->clicks);
                        $tag_stats[$tag->term_id]['closes'] += intval($stat->closes);
                        $tag_stats[$tag->term_id]['ctr_sum'] += $post_ctr[$stat->post_id];
                    }
                }
            }
        }
        
        // 結果を整形
        $results = array();
        foreach ($tag_stats as $ts) {
            $post_count = count($ts['post_ids']);
            $ctr = $ts['impressions'] > 0 ? round(($ts['clicks'] / $ts['impressions']) * 100, 2) : 0;
            $avg_ctr = $post_count > 0 ? round($ts['ctr_sum'] / $post_count, 2) : 0;
            
            $results[] = (object) array(
                'tag_id' => $ts['tag_id'],
                'tag_name' => $ts['tag_name'],
                'post_count' => $post_count,
                'impressions' => $ts['impressions'],
                'clicks' => $ts['clicks'],
                'closes' => $ts['closes'],
                'ctr' => $ctr,
                'avg_ctr' => $avg_ctr,
            );
        }
        
        // タグなしの記事
        if (!empty($no_tag_posts)) {
            $no_tag_impressions = 0;
            $no_tag_clicks = 0;
            $no_tag_closes = 0;
            $no_tag_ctr_sum = 0;
            
            foreach ($no_tag_posts as $stat) {
                $no_tag_impressions += intval($stat->impressions);
                $no_tag_clicks += intval($stat->clicks);
                $no_tag_closes += intval($stat->closes);
                $no_tag_ctr_sum += $post_ctr[$stat->post_id];
            }
            
            $no_tag_count = count($no_tag_posts);
            $results[] = (object) array(
                'tag_id' => 0,
                'tag_name' => '(タグなし)',
                'post_count' => $no_tag_count,
                'impressions' => $no_tag_impressions,
                'clicks' => $no_tag_clicks,
                'closes' => $no_tag_closes,
                'ctr' => $no_tag_impressions > 0 ? round(($no_tag_clicks / $no_tag_impressions) * 100, 2) : 0,
                'avg_ctr' => $no_tag_count > 0 ? round($no_tag_ctr_sum / $no_tag_count, 2) : 0,
            );
        }
        
        // クリック数でソート
        usort($results, function($a, $b) {
            return $b->clicks - $a->clicks;
        });
        
        return $results;
    }
    
    /**
     * CTA別の集計データ取得
     */
    public static function get_stats_by_cta($start_date = null, $end_date = null, $cta_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        if ($cta_id !== null) {
            $where .= " AND cta_id = %s";
            $params[] = $cta_id;
        }
        
        $sql = "SELECT 
                    cta_id,
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as closes
                FROM $table_name
                WHERE $where
                GROUP BY cta_id
                ORDER BY clicks DESC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * CTA別の記事別統計を取得
     */
    public static function get_cta_post_stats($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND l.created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND l.created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    l.cta_id,
                    l.post_id,
                    SUM(CASE WHEN l.event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN l.event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    SUM(CASE WHEN l.event_type = 'close' THEN 1 ELSE 0 END) as closes
                FROM $table_name l
                WHERE $where
                GROUP BY l.cta_id, l.post_id
                ORDER BY l.cta_id, clicks DESC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * デバイス別の集計データ取得
     */
    public static function get_stats_by_device($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
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
                    device,
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as closes
                FROM $table_name
                WHERE $where
                GROUP BY device";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * バリアント別の集計データ取得
     */
    public static function get_stats_by_variant($start_date = null, $end_date = null, $cta_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        if ($cta_id !== null) {
            $where .= " AND cta_id = %s";
            $params[] = $cta_id;
        }
        
        $sql = "SELECT 
                    variant,
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as closes
                FROM $table_name
                WHERE $where
                GROUP BY variant
                ORDER BY variant ASC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 全体のサマリー取得
     */
    public static function get_summary($start_date = null, $end_date = null, $tag_ids = array(), $tag_mode = 'or') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date . ' 23:59:59';
        }
        
        // タグフィルター
        $post_id_filter = '';
        if (!empty($tag_ids)) {
            $filtered_post_ids = self::get_post_ids_by_tags($tag_ids, $tag_mode);
            if (empty($filtered_post_ids)) {
                return (object) array(
                    'total_impressions' => 0,
                    'total_clicks' => 0,
                    'total_closes' => 0,
                );
            }
            $post_id_filter = " AND post_id IN (" . implode(',', array_map('intval', $filtered_post_ids)) . ")";
        }
        
        $sql = "SELECT 
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as total_impressions,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as total_clicks,
                    SUM(CASE WHEN event_type = 'close' THEN 1 ELSE 0 END) as total_closes
                FROM $table_name
                WHERE $where $post_id_filter";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * ログの削除（リセット）
     */
    public static function reset_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        return $wpdb->query("TRUNCATE TABLE $table_name");
    }
    
    /**
     * フローティングバナーのログのみ削除
     */
    public static function reset_floating_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'popup_logs';
        
        return $wpdb->delete($table_name, array('cta_id' => 'floating'), array('%s'));
    }
}
