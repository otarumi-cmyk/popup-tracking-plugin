<?php
/**
 * CTR異常値解析ページ
 */

// 期間を取得
$period = sanitize_text_field($_GET['period'] ?? 'week');
$admin = new Popup_Tracking_Admin();
list($start_date, $end_date) = $admin->get_date_range($period, $_GET['start_date'] ?? '', $_GET['end_date'] ?? '');

global $wpdb;
$table_name = $wpdb->prefix . 'popup_logs';

// ポップアップの詳細分析
$popup_analysis = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        event_type,
        COUNT(*) as count,
        COUNT(DISTINCT session_id) as unique_sessions,
        COUNT(DISTINCT post_id) as unique_posts,
        COUNT(DISTINCT CONCAT(session_id, '-', post_id, '-', variant)) as unique_combinations
    FROM $table_name
    WHERE created_at >= %s 
    AND created_at <= %s
    AND cta_id != 'floating'
    GROUP BY event_type
    ORDER BY event_type",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));

// フローティングバナーの詳細分析
$floating_analysis = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        event_type,
        COUNT(*) as count,
        COUNT(DISTINCT session_id) as unique_sessions,
        COUNT(DISTINCT post_id) as unique_posts,
        COUNT(DISTINCT CONCAT(session_id, '-', post_id, '-', variant)) as unique_combinations
    FROM $table_name
    WHERE created_at >= %s 
    AND created_at <= %s
    AND cta_id = 'floating'
    GROUP BY event_type
    ORDER BY event_type",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));

// 重複の可能性があるログ（同じセッション、同じ記事、同じCTA、同じバリアント、同じイベントタイプで5分以内に複数回記録）
$popup_duplicates = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        session_id,
        post_id,
        cta_id,
        variant,
        event_type,
        COUNT(*) as duplicate_count,
        MIN(created_at) as first_occurrence,
        MAX(created_at) as last_occurrence,
        TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as time_diff_seconds
    FROM $table_name
    WHERE created_at >= %s 
    AND created_at <= %s
    AND cta_id != 'floating'
    AND session_id != ''
    GROUP BY session_id, post_id, cta_id, variant, event_type
    HAVING duplicate_count > 1
    ORDER BY duplicate_count DESC
    LIMIT 20",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));

$floating_duplicates = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        session_id,
        post_id,
        cta_id,
        variant,
        event_type,
        COUNT(*) as duplicate_count,
        MIN(created_at) as first_occurrence,
        MAX(created_at) as last_occurrence,
        TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as time_diff_seconds
    FROM $table_name
    WHERE created_at >= %s 
    AND created_at <= %s
    AND cta_id = 'floating'
    AND session_id != ''
    GROUP BY session_id, post_id, cta_id, variant, event_type
    HAVING duplicate_count > 1
    ORDER BY duplicate_count DESC
    LIMIT 20",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));

// セッションIDの分析（同じセッションIDで複数の記事にアクセスしているか）
$session_analysis = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        session_id,
        COUNT(DISTINCT post_id) as post_count,
        COUNT(*) as total_events,
        SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
        SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks
    FROM $table_name
    WHERE created_at >= %s 
    AND created_at <= %s
    AND session_id != ''
    GROUP BY session_id
    HAVING total_events > 1
    ORDER BY total_events DESC
    LIMIT 20",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));
?>

<div class="wrap popup-tracking-admin">
    <h1>🔍 CTR異常値解析</h1>
    
    <div style="margin:20px 0;">
        <form method="get" action="">
            <input type="hidden" name="page" value="popup-tracking-ctr-analysis">
            <label>期間: 
                <select name="period">
                    <option value="today" <?php selected($period, 'today'); ?>>今日</option>
                    <option value="week" <?php selected($period, 'week'); ?>>今週</option>
                    <option value="month" <?php selected($period, 'month'); ?>>今月</option>
                </select>
            </label>
            <button type="submit" class="button">適用</button>
        </form>
        <p><strong>期間:</strong> <?php echo esc_html($start_date); ?> 〜 <?php echo esc_html($end_date); ?></p>
    </div>
    
    <!-- ポップアップ分析 -->
    <div style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <h2>📊 ポップアップ分析</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>イベントタイプ</th>
                    <th>総記録数</th>
                    <th>ユニークセッション数</th>
                    <th>ユニーク記事数</th>
                    <th>ユニーク組み合わせ数</th>
                    <th>重複率</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $popup_impressions = 0;
                $popup_clicks = 0;
                foreach ($popup_analysis as $row) : 
                    if ($row->event_type === 'impression') $popup_impressions = intval($row->count);
                    if ($row->event_type === 'click') $popup_clicks = intval($row->count);
                    $duplicate_rate = $row->unique_combinations > 0 ? round((($row->count - $row->unique_combinations) / $row->count) * 100, 2) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($row->event_type); ?></strong></td>
                    <td><?php echo number_format($row->count); ?></td>
                    <td><?php echo number_format($row->unique_sessions); ?></td>
                    <td><?php echo number_format($row->unique_posts); ?></td>
                    <td><?php echo number_format($row->unique_combinations); ?></td>
                    <td style="color:<?php echo $duplicate_rate > 10 ? '#e74c3c' : '#2ed573'; ?>;">
                        <strong><?php echo $duplicate_rate; ?>%</strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($popup_impressions > 0) : 
            $popup_ctr = round(($popup_clicks / $popup_impressions) * 100, 2);
        ?>
        <div style="margin-top:15px;padding:15px;background:#f5f5f5;border-radius:4px;">
            <strong>計算されたCTR:</strong> <?php echo $popup_ctr; ?>% 
            (クリック: <?php echo number_format($popup_clicks); ?> / 表示: <?php echo number_format($popup_impressions); ?>)
        </div>
        <?php endif; ?>
    </div>
    
    <!-- フローティングバナー分析 -->
    <div style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <h2>📊 フローティングバナー分析</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>イベントタイプ</th>
                    <th>総記録数</th>
                    <th>ユニークセッション数</th>
                    <th>ユニーク記事数</th>
                    <th>ユニーク組み合わせ数</th>
                    <th>重複率</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $floating_impressions = 0;
                $floating_clicks = 0;
                foreach ($floating_analysis as $row) : 
                    if ($row->event_type === 'impression') $floating_impressions = intval($row->count);
                    if ($row->event_type === 'click') $floating_clicks = intval($row->count);
                    $duplicate_rate = $row->unique_combinations > 0 ? round((($row->count - $row->unique_combinations) / $row->count) * 100, 2) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($row->event_type); ?></strong></td>
                    <td><?php echo number_format($row->count); ?></td>
                    <td><?php echo number_format($row->unique_sessions); ?></td>
                    <td><?php echo number_format($row->unique_posts); ?></td>
                    <td><?php echo number_format($row->unique_combinations); ?></td>
                    <td style="color:<?php echo $duplicate_rate > 10 ? '#e74c3c' : '#2ed573'; ?>;">
                        <strong><?php echo $duplicate_rate; ?>%</strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($floating_impressions > 0) : 
            $floating_ctr = round(($floating_clicks / $floating_impressions) * 100, 2);
        ?>
        <div style="margin-top:15px;padding:15px;background:#f5f5f5;border-radius:4px;">
            <strong>計算されたCTR:</strong> <?php echo $floating_ctr; ?>% 
            (クリック: <?php echo number_format($floating_clicks); ?> / 表示: <?php echo number_format($floating_impressions); ?>)
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 重複ログの詳細 -->
    <?php if (!empty($popup_duplicates)) : ?>
    <div style="margin:20px 0;padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
        <h2>⚠️ ポップアップ: 重複ログ検出（上位20件）</h2>
        <p class="description">同じセッション・記事・CTA・バリアント・イベントタイプで5分以内に複数回記録されたログです。</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>セッションID</th>
                    <th>記事ID</th>
                    <th>CTA ID</th>
                    <th>バリアント</th>
                    <th>イベント</th>
                    <th>重複回数</th>
                    <th>最初の記録</th>
                    <th>最後の記録</th>
                    <th>時間差（秒）</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($popup_duplicates as $dup) : ?>
                <tr>
                    <td style="font-size:11px;"><?php echo esc_html(substr($dup->session_id, 0, 20)); ?>...</td>
                    <td><?php echo $dup->post_id; ?></td>
                    <td><?php echo esc_html($dup->cta_id); ?></td>
                    <td><?php echo esc_html($dup->variant); ?></td>
                    <td><?php echo esc_html($dup->event_type); ?></td>
                    <td style="color:#e74c3c;"><strong><?php echo $dup->duplicate_count; ?></strong></td>
                    <td><?php echo esc_html($dup->first_occurrence); ?></td>
                    <td><?php echo esc_html($dup->last_occurrence); ?></td>
                    <td><?php echo $dup->time_diff_seconds; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($floating_duplicates)) : ?>
    <div style="margin:20px 0;padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
        <h2>⚠️ フローティングバナー: 重複ログ検出（上位20件）</h2>
        <p class="description">同じセッション・記事・CTA・バリアント・イベントタイプで5分以内に複数回記録されたログです。</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>セッションID</th>
                    <th>記事ID</th>
                    <th>CTA ID</th>
                    <th>バリアント</th>
                    <th>イベント</th>
                    <th>重複回数</th>
                    <th>最初の記録</th>
                    <th>最後の記録</th>
                    <th>時間差（秒）</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($floating_duplicates as $dup) : ?>
                <tr>
                    <td style="font-size:11px;"><?php echo esc_html(substr($dup->session_id, 0, 20)); ?>...</td>
                    <td><?php echo $dup->post_id; ?></td>
                    <td><?php echo esc_html($dup->cta_id); ?></td>
                    <td><?php echo esc_html($dup->variant); ?></td>
                    <td><?php echo esc_html($dup->event_type); ?></td>
                    <td style="color:#e74c3c;"><strong><?php echo $dup->duplicate_count; ?></strong></td>
                    <td><?php echo esc_html($dup->first_occurrence); ?></td>
                    <td><?php echo esc_html($dup->last_occurrence); ?></td>
                    <td><?php echo $dup->time_diff_seconds; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- セッション分析 -->
    <?php if (!empty($session_analysis)) : ?>
    <div style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <h2>🔍 セッション分析（上位20件）</h2>
        <p class="description">同じセッションIDで複数のイベントが記録されているセッションです。</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>セッションID</th>
                    <th>記事数</th>
                    <th>総イベント数</th>
                    <th>表示数</th>
                    <th>クリック数</th>
                    <th>CTR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($session_analysis as $session) : 
                    $session_ctr = $session->impressions > 0 ? round(($session->clicks / $session->impressions) * 100, 2) : 0;
                ?>
                <tr>
                    <td style="font-size:11px;"><?php echo esc_html(substr($session->session_id, 0, 30)); ?>...</td>
                    <td><?php echo $session->post_count; ?></td>
                    <td><?php echo number_format($session->total_events); ?></td>
                    <td><?php echo number_format($session->impressions); ?></td>
                    <td><?php echo number_format($session->clicks); ?></td>
                    <td style="color:<?php echo $session_ctr > 10 ? '#e74c3c' : '#2ed573'; ?>;">
                        <strong><?php echo $session_ctr; ?>%</strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div style="margin:20px 0;padding:20px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;">
        <h3>📝 解析のポイント</h3>
        <ul style="line-height:2;">
            <li><strong>重複率:</strong> 10%を超える場合は重複記録の可能性が高いです</li>
            <li><strong>ユニーク組み合わせ数:</strong> セッションID + 記事ID + バリアントの組み合わせがユニークな数です。これが総記録数より少ない場合は重複があります</li>
            <li><strong>重複ログ:</strong> 同じセッション・記事・CTA・バリアント・イベントタイプで5分以内に複数回記録されたログです</li>
            <li><strong>セッション分析:</strong> 同じセッションIDで複数の記事にアクセスしている場合、セッションIDが正しく管理されていない可能性があります</li>
        </ul>
    </div>
</div>









