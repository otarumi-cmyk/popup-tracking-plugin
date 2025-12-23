<?php
/**
 * フローティングバナーダッシュボード
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap popup-tracking-admin">
    <h1>📊 フローティングバナーダッシュボード</h1>
    
    <!-- データ属性で統計情報を渡す -->
    <div id="floating-dashboard-data" 
         data-impressions="<?php echo $total_impressions; ?>"
         data-clicks="<?php echo $total_clicks; ?>"
         data-ctr="<?php echo $ctr; ?>"
         data-start-date="<?php echo esc_attr($start_date); ?>"
         data-end-date="<?php echo esc_attr($end_date); ?>"
         data-period="<?php echo esc_attr($period_label ?? $period); ?>"
         style="display:none;"></div>
    
    <div class="period-filter" style="margin:20px 0;">
        <a href="?page=floating-banner&period=today" class="button <?php echo $period === 'today' ? 'button-primary' : ''; ?>">今日</a>
        <a href="?page=floating-banner&period=week" class="button <?php echo $period === 'week' ? 'button-primary' : ''; ?>">今週</a>
        <a href="?page=floating-banner&period=month" class="button <?php echo $period === 'month' ? 'button-primary' : ''; ?>">今月</a>
    </div>
    
    <div class="summary-section" style="display:flex;gap:20px;margin:20px 0;">
        <div style="flex:1;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
            <h3 style="margin:0 0 10px 0;font-size:14px;color:#666;">表示数</h3>
            <div style="font-size:32px;font-weight:bold;color:#333;"><?php echo number_format($total_impressions); ?></div>
        </div>
        <div style="flex:1;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
            <h3 style="margin:0 0 10px 0;font-size:14px;color:#666;">クリック数</h3>
            <div style="font-size:32px;font-weight:bold;color:#333;"><?php echo number_format($total_clicks); ?></div>
        </div>
        <div style="flex:1;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
            <h3 style="margin:0 0 10px 0;font-size:14px;color:#666;">CTR</h3>
            <div style="font-size:32px;font-weight:bold;color:#2ed573;"><?php echo number_format($ctr, 2); ?>%</div>
        </div>
    </div>
    
    
    <h2>📈 記事別パフォーマンス</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;">順位</th>
                <th>記事タイトル</th>
                <th style="width:100px;">表示数</th>
                <th style="width:100px;">クリック</th>
                <th style="width:100px;">CTR</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($post_stats)) : ?>
            <tr>
                <td colspan="5" style="text-align:center;padding:40px;color:#999;">データがありません</td>
            </tr>
            <?php else : 
                $rank = 1;
                foreach ($post_stats as $stat) :
                    $post = get_post($stat->post_id);
                    if (!$post) continue;
                    $post_ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0;
            ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td>
                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                        <?php echo esc_html($post->post_title); ?>
                    </a>
                </td>
                <td><?php echo number_format($stat->impressions); ?></td>
                <td><?php echo number_format($stat->clicks); ?></td>
                <td>
                    <strong style="color:#2ed573;"><?php echo number_format($post_ctr, 2); ?>%</strong>
                </td>
            </tr>
            <?php 
                endforeach;
            endif; 
            ?>
        </tbody>
    </table>
    
    <?php if (!empty($variant_stats)) : ?>
    <h2 style="margin-top:40px;">🔬 A/Bテスト結果（バリアント別パフォーマンス）</h2>
    <?php 
    $variants = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    foreach ($variants as $v) :
        $stat = $variant_stats_map[$v] ?? null;
        if (!$stat) continue;
        $variant_ctr = $stat->impressions > 0 ? ($stat->clicks / $stat->impressions) * 100 : 0;
        $avg_ctr = $variant_avg_ctr[$v] ?? 0;
        $posts = $variant_posts_map[$v] ?? array();
    ?>
    <div class="variant-card" style="margin-bottom:30px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:8px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:15px;border-bottom:2px solid #f0f0f0;">
            <div>
                <h4 style="margin:0;font-size:18px;color:#333;">
                    <span class="variant-badge" style="display:inline-block;width:30px;height:30px;line-height:30px;text-align:center;background:#3498db;color:#fff;border-radius:4px;margin-right:10px;"><?php echo $v; ?></span>
                    パターン<?php echo $v; ?>
                </h4>
            </div>
            <div style="display:flex;gap:20px;text-align:center;">
                <div>
                    <div style="font-size:12px;color:#666;">表示数</div>
                    <div style="font-size:20px;font-weight:bold;"><?php echo number_format($stat->impressions); ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:#666;">クリック</div>
                    <div style="font-size:20px;font-weight:bold;color:#2ecc71;"><?php echo number_format($stat->clicks); ?></div>
                </div>
                <div>
                    <div style="font-size:12px;color:#666;">CTR</div>
                    <div style="font-size:20px;font-weight:bold;color:#3498db;"><?php echo number_format($variant_ctr, 2); ?>%</div>
                </div>
                <div>
                    <div style="font-size:12px;color:#666;">平均CTR/記事</div>
                    <div style="font-size:20px;font-weight:bold;color:#9b59b6;"><?php echo $avg_ctr; ?>%</div>
                </div>
                <div>
                    <div style="font-size:12px;color:#666;">使用記事数</div>
                    <div style="font-size:20px;font-weight:bold;"><?php echo count($posts); ?></div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($posts)) : ?>
        <details style="margin-top:15px;">
            <summary style="cursor:pointer;padding:10px;background:#f5f5f5;border-radius:4px;font-weight:bold;">
                使用記事一覧 (<?php echo count($posts); ?>記事) ▼
            </summary>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:50px;">順位</th>
                        <th>記事タイトル</th>
                        <th style="width:100px;">表示数</th>
                        <th style="width:100px;">クリック</th>
                        <th style="width:100px;">CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($posts as $post_stat) :
                        $post = get_post($post_stat->post_id);
                        if (!$post) continue;
                        $post_imp = intval($post_stat->impressions);
                        $post_click = intval($post_stat->clicks);
                        $post_ctr = $post_imp > 0 ? round(($post_click / $post_imp) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo $rank++; ?></td>
                        <td>
                            <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($post_imp); ?></td>
                        <td><?php echo number_format($post_click); ?></td>
                        <td><strong style="color:#2ed573;"><?php echo $post_ctr; ?>%</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="reset-section" style="margin-top:40px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <h2>🗑️ データ管理</h2>
        <p class="description">フローティングバナーの計測ログのみをリセットします。ポップアップのログは影響を受けません。</p>
        <button type="button" id="reset-floating-logs-btn" class="button" style="background:#e74c3c;color:#fff;border:none;">フローティングバナーの計測ログをリセット</button>
    </div>
</div>

