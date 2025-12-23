<?php
/**
 * フローティングバナースナップショットページ
 */

$snapshots = get_option('floating_banner_snapshots', array());
if (!empty($snapshots)) {
    usort($snapshots, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// 現在のダッシュボード統計を取得（記録用）
$period = sanitize_text_field($_GET['period'] ?? 'week');
$admin = new Popup_Tracking_Admin();
list($start_date, $end_date) = $admin->get_date_range($period, $_GET['start_date'] ?? '', $_GET['end_date'] ?? '');

// フローティングバナーのデータのみを取得
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
$total_impressions = intval($summary->total_impressions ?? 0);
$total_clicks = intval($summary->total_clicks ?? 0);
$total_closes = intval($summary->total_closes ?? 0);
$ctr = $total_impressions > 0 ? round(($total_clicks / $total_impressions) * 100, 2) : 0;

// 期間ラベルは常に日付レンジで表示
$period_label = $start_date . '〜' . $end_date;
?>

<div class="wrap popup-tracking-admin">
    <h1>📸 フローティングバナースナップショット</h1>
    
    <div style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <h2>現在の統計を記録</h2>
        <p class="description">現在表示されている統計とバナー設定を記録します。記録名を付けて保存できます。</p>
        
        <div style="margin:15px 0;padding:15px;background:#f5f5f5;border-radius:4px;">
            <strong>現在の統計:</strong>
            <ul style="margin:10px 0 0 20px;">
                <li>期間: <?php echo esc_html($period_label); ?> (<?php echo esc_html($start_date); ?> 〜 <?php echo esc_html($end_date); ?>)</li>
                <li>表示数: <?php echo number_format($total_impressions); ?></li>
                <li>クリック数: <?php echo number_format($total_clicks); ?></li>
                <li>CTR: <?php echo number_format($ctr, 2); ?>%</li>
            </ul>
        </div>
        
        <button type="button" id="save-floating-snapshot-btn" class="button button-primary" style="font-size:14px;padding:8px 20px;margin-top:10px;">
            📸 現在のバナー設定を記録
        </button>
    </div>
    
    <!-- 記録一覧 -->
    <?php if (empty($snapshots)) : ?>
    <div style="margin:40px 0;padding:40px;background:#fff;border:1px solid #ddd;border-radius:4px;text-align:center;color:#999;">
        <p>まだ記録がありません。上記のボタンから現在の統計を記録してください。</p>
    </div>
    <?php else : ?>
    <div class="snapshots-section" style="margin-top:40px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <div>
                <h2 style="margin:0;">📸 記録一覧</h2>
                <p class="description" style="margin:5px 0 0 0;">過去に記録したバナー設定と統計情報です。</p>
            </div>
            <?php if (!empty($snapshots)) : ?>
            <button type="button" id="delete-all-floating-snapshots-btn" class="button" style="background:#e74c3c;color:#fff;border:none;">
                🗑️ すべての記録を削除
            </button>
            <?php endif; ?>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:200px;">記録名</th>
                    <th style="width:150px;">記録日時</th>
                    <th style="width:100px;">期間</th>
                    <th style="width:100px;">表示数</th>
                    <th style="width:100px;">クリック</th>
                    <th style="width:100px;">CTR</th>
                    <th style="width:200px;">バナーデザイン</th>
                    <th style="width:80px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snapshots as $index => $snapshot) : 
                    $snapshot_ctr = $snapshot['impressions'] > 0 ? round(($snapshot['clicks'] / $snapshot['impressions']) * 100, 2) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($snapshot['name']); ?></strong></td>
                    <td><?php echo esc_html($snapshot['created_at']); ?></td>
                    <td><?php echo esc_html($snapshot['period']); ?></td>
                    <td><?php echo number_format($snapshot['impressions']); ?></td>
                    <td><?php echo number_format($snapshot['clicks']); ?></td>
                    <td><strong style="color:#2ed573;"><?php echo $snapshot_ctr; ?>%</strong></td>
                    <td>
                        <?php 
                        $banner_info = array();
                        if (!empty($snapshot['variant'])) {
                            $banner_info[] = 'バリアント: ' . esc_html($snapshot['variant']);
                        }
                        if (!empty($snapshot['image_url_pc'])) {
                            $banner_info[] = '<a href="' . esc_url($snapshot['image_url_pc']) . '" target="_blank">PC画像</a>';
                        }
                        if (!empty($snapshot['image_url_sp'])) {
                            $banner_info[] = '<a href="' . esc_url($snapshot['image_url_sp']) . '" target="_blank">SP画像</a>';
                        }
                        if (!empty($snapshot['abtest_enabled'])) {
                            $banner_info[] = 'A/Bテスト: 有効';
                        }
                        echo !empty($banner_info) ? implode('<br>', $banner_info) : '-';
                        ?>
                    </td>
                    <td>
                        <button type="button" class="button delete-floating-snapshot-btn" data-index="<?php echo $index; ?>" style="padding:2px 8px;font-size:12px;">削除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- 記録モーダル -->
    <div id="floating-snapshot-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:30px;border-radius:8px;max-width:500px;width:90%;">
            <h2 style="margin-top:0;">📸 バナー設定を記録</h2>
            <p class="description">現在の統計とバナー設定を記録します。記録名を入力してください。</p>
            
            <table class="form-table">
                <tr>
                    <th><label for="floating-snapshot-name">記録名</label></th>
                    <td>
                        <input type="text" id="floating-snapshot-name" class="regular-text" placeholder="例: クリスマスキャンペーン" value="">
                        <p class="description">この記録を識別するための名前を入力してください</p>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top:20px;padding:15px;background:#f5f5f5;border-radius:4px;">
                <strong>記録される情報:</strong>
                <ul style="margin:10px 0 0 20px;">
                    <li>期間: <?php echo esc_html($period_label); ?> (<?php echo esc_html($start_date); ?> 〜 <?php echo esc_html($end_date); ?>)</li>
                    <li>表示数: <?php echo number_format($total_impressions); ?></li>
                    <li>クリック数: <?php echo number_format($total_clicks); ?></li>
                    <li>CTR: <?php echo number_format($ctr, 2); ?>%</li>
                    <li>現在のバナー設定（PC/SP画像、リンクURL）</li>
                    <li>使用中のバリアント</li>
                    <li>A/Bテスト設定</li>
                </ul>
            </div>
            
            <div style="margin-top:20px;text-align:right;">
                <button type="button" id="cancel-floating-snapshot-btn" class="button">キャンセル</button>
                <button type="button" id="confirm-save-floating-snapshot-btn" class="button button-primary">記録する</button>
            </div>
        </div>
    </div>
    
    <!-- データ属性で統計情報を渡す -->
    <div id="floating-snapshot-data" 
         data-impressions="<?php echo $total_impressions; ?>"
         data-clicks="<?php echo $total_clicks; ?>"
         data-ctr="<?php echo $ctr; ?>"
         data-start-date="<?php echo esc_attr($start_date); ?>"
         data-end-date="<?php echo esc_attr($end_date); ?>"
         data-period="<?php echo esc_attr($period_label); ?>"
         style="display:none;"></div>
</div>

