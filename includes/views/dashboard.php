<div class="wrap popup-tracking-admin">
    <h1>📊 ポップアップ計測 ダッシュボード</h1>
    
    <!-- データ属性で統計情報を渡す -->
    <div id="popup-dashboard-data" 
         data-impressions="<?php echo $total_impressions; ?>"
         data-clicks="<?php echo $total_clicks; ?>"
         data-closes="<?php echo $total_closes; ?>"
         data-ctr="<?php echo $total_ctr; ?>"
         data-start-date="<?php echo esc_attr($start_date); ?>"
         data-end-date="<?php echo esc_attr($end_date); ?>"
         data-period="<?php echo esc_attr($period_label ?? $period); ?>"
         style="display:none;"></div>
    
    <div class="period-filter">
        <a href="?page=popup-tracking&period=today" class="button <?php echo $period === 'today' ? 'button-primary' : ''; ?>">今日</a>
        <a href="?page=popup-tracking&period=week" class="button <?php echo $period === 'week' ? 'button-primary' : ''; ?>">今週</a>
        <a href="?page=popup-tracking&period=month" class="button <?php echo $period === 'month' ? 'button-primary' : ''; ?>">今月</a>
        <span class="custom-period">
            <input type="date" id="start_date" value="<?php echo esc_attr($start_date); ?>">〜
            <input type="date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
            <button type="button" id="apply-custom-period" class="button">適用</button>
        </span>
        <a href="<?php echo admin_url('admin-ajax.php?action=popup_tracking_export_csv&nonce=' . wp_create_nonce('popup_tracking_admin') . '&start_date=' . $start_date . '&end_date=' . $end_date); ?>" class="button export-btn">📥 CSV</a>
    </div>
    
    <?php if (!empty($filter_tags)) : 
        $filter_tag_names = array();
        foreach ($filter_tags as $tid) {
            $t = get_tag($tid);
            if ($t) $filter_tag_names[] = $t->name;
        }
    ?>
    <div class="active-filter">
        <span>🏷️ フィルター中: <strong><?php echo esc_html(implode(', ', $filter_tag_names)); ?></strong></span>
        <span class="filter-mode">(<?php echo $tag_mode === 'and' ? 'すべてを含む' : 'いずれかを含む'; ?>)</span>
        <a href="?page=popup-tracking&period=<?php echo $period; ?>" class="clear-filter">× クリア</a>
    </div>
    <?php endif; ?>
    
    <div class="summary-cards">
        <div class="summary-card"><div class="card-label">表示数</div><div class="card-value"><?php echo number_format($total_impressions); ?></div></div>
        <div class="summary-card"><div class="card-label">クリック数</div><div class="card-value"><?php echo number_format($total_clicks); ?></div></div>
        <div class="summary-card"><div class="card-label">閉じた数</div><div class="card-value"><?php echo number_format($total_closes); ?></div></div>
        <div class="summary-card highlight"><div class="card-label">CTR</div><div class="card-value"><?php echo $total_ctr; ?>%</div></div>
    </div>
    
    <div class="device-stats">
        <h3>📱 デバイス別クリック</h3>
        <div class="device-bars">
            <div class="device-bar">
                <span class="device-label">💻 PC</span>
                <div class="bar-container"><div class="bar pc-bar" style="width: <?php echo ($total_clicks > 0) ? round(($pc_clicks / $total_clicks) * 100) : 0; ?>%;"></div></div>
                <span class="device-count"><?php echo number_format($pc_clicks); ?></span>
            </div>
            <div class="device-bar">
                <span class="device-label">📱 SP</span>
                <div class="bar-container"><div class="bar sp-bar" style="width: <?php echo ($total_clicks > 0) ? round(($sp_clicks / $total_clicks) * 100) : 0; ?>%;"></div></div>
                <span class="device-count"><?php echo number_format($sp_clicks); ?></span>
            </div>
        </div>
    </div>
    
    <!-- タグ別サマリー -->
    <div class="tag-summary-section">
        <h3>🏷️ タグ別サマリー</h3>
        <p class="description">タグ名をクリックするとその記事でフィルタリングできます</p>
        
        <?php if (empty($tag_summary)) : ?>
            <p class="no-data">データがありません。</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped tag-summary-table">
                <thead>
                    <tr>
                        <th>タグ名</th>
                        <th style="width:80px;">記事数</th>
                        <th style="width:100px;">表示数</th>
                        <th style="width:100px;">クリック</th>
                        <th style="width:80px;">CTR</th>
                        <th style="width:100px;">平均CTR/記事</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_tag_imp = 0;
                    $total_tag_click = 0;
                    $total_tag_posts = 0;
                    $total_ctr_sum = 0;
                    
                    foreach ($tag_summary as $ts) : 
                        $is_active = in_array($ts->tag_id, $filter_tags);
                        $total_tag_imp += $ts->impressions;
                        $total_tag_click += $ts->clicks;
                        $total_tag_posts += $ts->post_count;
                        $total_ctr_sum += $ts->avg_ctr * $ts->post_count;
                    ?>
                    <tr class="<?php echo $is_active ? 'active-tag' : ''; ?>" data-tag-id="<?php echo $ts->tag_id; ?>">
                        <td>
                            <?php if ($ts->tag_id > 0) : ?>
                                <a href="?page=popup-tracking&period=<?php echo $period; ?>&tags=<?php echo $ts->tag_id; ?>" class="tag-filter-link">
                                    <?php echo esc_html($ts->tag_name); ?>
                                </a>
                            <?php else : ?>
                                <span class="no-tag"><?php echo esc_html($ts->tag_name); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($ts->post_count); ?></td>
                        <td><?php echo number_format($ts->impressions); ?></td>
                        <td><?php echo number_format($ts->clicks); ?></td>
                        <td><strong><?php echo $ts->ctr; ?>%</strong></td>
                        <td><?php echo $ts->avg_ctr; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>合計</strong></td>
                        <td><?php echo number_format($total_tag_posts); ?></td>
                        <td><?php echo number_format($total_tag_imp); ?></td>
                        <td><?php echo number_format($total_tag_click); ?></td>
                        <td><strong><?php echo $total_tag_imp > 0 ? round(($total_tag_click / $total_tag_imp) * 100, 1) : 0; ?>%</strong></td>
                        <td><?php echo $total_tag_posts > 0 ? round($total_ctr_sum / $total_tag_posts, 1) : 0; ?>%</td>
                    </tr>
                </tfoot>
            </table>
            
            <div class="tag-filter-options">
                <span>複数タグでフィルター:</span>
                <select id="tag-filter-select" multiple style="width:300px;height:100px;">
                    <?php foreach ($all_tags as $tag) : ?>
                        <option value="<?php echo $tag->term_id; ?>" <?php selected(in_array($tag->term_id, $filter_tags)); ?>><?php echo esc_html($tag->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="tag-filter-mode">
                    <option value="or" <?php selected($tag_mode, 'or'); ?>>いずれかを含む (OR)</option>
                    <option value="and" <?php selected($tag_mode, 'and'); ?>>すべてを含む (AND)</option>
                </select>
                <button type="button" id="apply-tag-filter" class="button">適用</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- CTA別パフォーマンス -->
    <div class="cta-performance-section" style="margin-top:40px;">
        <h3>📊 CTA別パフォーマンス一覧</h3>
        <p class="description">各CTA（ポップアップ）がどの記事でどれくらい表示・クリックされているか、平均CTRを表示します。</p>
        
        <?php if (empty($cta_stats)) : ?>
            <p class="no-data">データがありません。</p>
        <?php else : ?>
            <?php foreach ($cta_stats as $cta_stat) : 
                $cta_id = $cta_stat->cta_id;
                $cta_name = '';
                if ($cta_id === 'default') {
                    $cta_name = 'デフォルトCTA';
                } elseif ($cta_id === 'floating') {
                    $cta_name = 'フローティングバナー';
                } elseif (isset($tag_cta_names[$cta_id])) {
                    $cta_name = $tag_cta_names[$cta_id];
                } else {
                    $cta_name = $cta_id;
                }
                
                $impressions = intval($cta_stat->impressions);
                $clicks = intval($cta_stat->clicks);
                $closes = intval($cta_stat->closes);
                $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
                $avg_ctr = $cta_avg_ctr[$cta_id] ?? 0;
                $posts = $cta_posts_map[$cta_id] ?? array();
            ?>
            <div class="cta-card" style="margin-bottom:30px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:15px;border-bottom:2px solid #f0f0f0;">
                    <div>
                        <h4 style="margin:0;font-size:18px;color:#333;">
                            <?php if ($cta_id === 'default') : ?>
                                🎯 <?php echo esc_html($cta_name); ?>
                            <?php elseif ($cta_id === 'floating') : ?>
                                📌 <?php echo esc_html($cta_name); ?>
                            <?php else : ?>
                                🏷️ <?php echo esc_html($cta_name); ?>
                            <?php endif; ?>
                        </h4>
                        <span style="color:#999;font-size:12px;">CTA ID: <?php echo esc_html($cta_id); ?></span>
                    </div>
                    <div style="display:flex;gap:20px;text-align:center;">
                        <div>
                            <div style="font-size:12px;color:#666;">表示数</div>
                            <div style="font-size:20px;font-weight:bold;"><?php echo number_format($impressions); ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#666;">クリック</div>
                            <div style="font-size:20px;font-weight:bold;color:#2ecc71;"><?php echo number_format($clicks); ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#666;">CTR</div>
                            <div style="font-size:20px;font-weight:bold;color:#3498db;"><?php echo $ctr; ?>%</div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#666;">平均CTR/記事</div>
                            <div style="font-size:20px;font-weight:bold;color:#9b59b6;"><?php echo $avg_ctr; ?>%</div>
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
    </div>
    
    <!-- 記事別ランキング -->
    <div class="ranking-section">
        <div class="ranking-tabs">
            <button class="ranking-tab active" data-tab="ctr">📈 CTRランキング</button>
            <button class="ranking-tab" data-tab="clicks">🔥 クリック数</button>
            <button class="ranking-tab" data-tab="impressions">👁️ 表示数</button>
        </div>
        
        <?php if (empty($stats_by_post)) : ?>
            <p class="no-data">データがありません。</p>
        <?php else : ?>
            <?php 
            function render_table($stats, $sort) {
                $sorted = $stats;
                if ($sort === 'ctr') {
                    usort($sorted, function($a, $b) {
                        $ctr_a = $a->impressions > 0 ? $a->clicks / $a->impressions : 0;
                        $ctr_b = $b->impressions > 0 ? $b->clicks / $b->impressions : 0;
                        return $ctr_b <=> $ctr_a;
                    });
                } elseif ($sort === 'clicks') {
                    usort($sorted, function($a, $b) { return intval($b->clicks) <=> intval($a->clicks); });
                } else {
                    usort($sorted, function($a, $b) { return intval($b->impressions) <=> intval($a->impressions); });
                }
                
                echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:60px;">順位</th><th>記事</th><th style="width:150px;">タグ</th><th style="width:100px;">表示</th><th style="width:100px;">Click</th><th style="width:100px;">CTR</th></tr></thead><tbody>';
                $rank = 1;
                foreach ($sorted as $stat) {
                    $post = get_post($stat->post_id);
                    $title = $post ? $post->post_title : '(削除)';
                    $imp = intval($stat->impressions);
                    $click = intval($stat->clicks);
                    $ctr = $imp > 0 ? round(($click / $imp) * 100, 1) : 0;
                    $link = $post ? '<a href="' . get_permalink($stat->post_id) . '" target="_blank">' . esc_html($title) . '</a>' : esc_html($title);
                    
                    // タグ取得
                    $tags = get_the_tags($stat->post_id);
                    $tag_names = array();
                    if ($tags) {
                        foreach ($tags as $tag) {
                            $tag_names[] = '<span class="post-tag">' . esc_html($tag->name) . '</span>';
                        }
                    }
                    $tags_html = !empty($tag_names) ? implode(' ', array_slice($tag_names, 0, 3)) : '<span class="no-tag">-</span>';
                    
                    echo "<tr><td><strong>{$rank}</strong></td><td>{$link}</td><td>{$tags_html}</td><td>" . number_format($imp) . "</td><td>" . number_format($click) . "</td><td><strong>{$ctr}%</strong></td></tr>";
                    $rank++;
                }
                echo '</tbody></table>';
            }
            ?>
            <div class="ranking-content active" id="tab-ctr"><?php render_table($stats_by_post, 'ctr'); ?></div>
            <div class="ranking-content" id="tab-clicks"><?php render_table($stats_by_post, 'clicks'); ?></div>
            <div class="ranking-content" id="tab-impressions"><?php render_table($stats_by_post, 'impressions'); ?></div>
        <?php endif; ?>
    </div>
</div>
