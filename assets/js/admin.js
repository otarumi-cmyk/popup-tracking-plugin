(function($) {
    'use strict';
    
    // メディアアップローダー（デフォルトCTA/フローティング兼用）
    $(document).on('click', '.upload-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        var target = $(this).data('target');
        var isFloating = target === 'floating';
        var frame = wp.media({
            title: '画像を選択',
            button: { text: '選択' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            if (isFloating) {
                $('#floating_image_url').val(attachment.url);
                $('#floating-image-preview').html('<img src="' + attachment.url + '" alt="">');
                $('.remove-image-btn[data-target="floating"]').show();
            } else {
                $('#popup_image_url_' + variant).val(attachment.url);
                $('#image-preview-' + variant).html('<img src="' + attachment.url + '" alt="">');
                $('.remove-image-btn[data-variant="' + variant + '"]').show();
            }
        });
        
        frame.open();
    });
    
    $(document).on('click', '.remove-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        var target = $(this).data('target');
        var isFloating = target === 'floating';
        if (isFloating) {
            $('#floating_image_url').val('');
            $('#floating-image-preview').html('<span class="placeholder">画像を選択</span>');
            $(this).hide();
        } else {
            $('#popup_image_url_' + variant).val('');
            $('#image-preview-' + variant).html('<span class="placeholder">画像を選択</span>');
            $(this).hide();
        }
    });
    
    // フローティングバナー用画像アップローダー
    $(document).on('click', '.upload-floating-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        var device = $(this).data('device');
        
        if (!variant || !device) {
            console.error('Variant or device not found');
            return;
        }
        
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert('メディアライブラリが読み込まれていません。ページを再読み込みしてください。');
            return;
        }
        
        var frame = wp.media({
            title: '画像を選択',
            button: { text: '選択' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var inputId = '#floating_image_url_' + device + '_' + variant;
            var previewId = '#floating-image-preview-' + device + '-' + variant;
            var removeBtn = '.remove-floating-image-btn[data-variant="' + variant + '"][data-device="' + device + '"]';
            
            $(inputId).val(attachment.url);
            $(previewId).html('<img src="' + attachment.url + '" alt="">');
            $(removeBtn).show();
        });
        
        frame.open();
    });
    
    $(document).on('click', '.remove-floating-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        var device = $(this).data('device');
        
        if (!variant || !device) {
            console.error('Variant or device not found');
            return;
        }
        
        var inputId = '#floating_image_url_' + device + '_' + variant;
        var previewId = '#floating-image-preview-' + device + '-' + variant;
        
        $(inputId).val('');
        $(previewId).html('<span class="placeholder">画像を選択</span>');
        $(this).hide();
    });
    
    // フローティングバナーA/Bテスト有効/無効切り替え
    $('#floating_abtest_enabled').on('change', function() {
        var enabled = $(this).is(':checked');
        $('#floating-active-variants-row').toggle(enabled);
        
        if (enabled) {
            var count = parseInt($('#floating_active_variants').val());
            updateFloatingVariantSections(count, true);
        } else {
            $('.floating-variant-section').each(function(i) {
                $(this).toggle(i === 0);
            });
        }
    });
    
    $('#floating_active_variants').on('change', function() {
        var count = parseInt($(this).val());
        updateFloatingVariantSections(count, true);
    });
    
    function updateFloatingVariantSections(count, abtestEnabled) {
        $('.floating-variant-section').each(function() {
            var index = parseInt($(this).data('variant'));
            if (index === 0) {
                $(this).show();
            } else {
                $(this).toggle(abtestEnabled && index < count);
            }
        });
    }
    
    // フローティングバナー表示条件設定
    $('.target-mode-radio').on('change', function() {
        var mode = $(this).val();
        $('#floating-include-posts-section').toggle(mode === 'include');
        $('#floating-exclude-posts-section').toggle(mode === 'exclude');
    });
    
    $('.category-mode-radio').on('change', function() {
        var mode = $(this).val();
        $('#floating-category-selection').toggle(mode !== 'all');
    });
    
    // フローティングポップアップA/Bテスト設定
    $('#floating_popup_abtest_enabled').on('change', function() {
        var enabled = $(this).is(':checked');
        $('#floating-popup-active-variants-row').toggle(enabled);
        updateFloatingPopupVariants(enabled, $('#floating_popup_active_variants').val());
    });
    
    $('#floating_popup_active_variants').on('change', function() {
        var enabled = $('#floating_popup_abtest_enabled').is(':checked');
        updateFloatingPopupVariants(enabled, $(this).val());
    });
    
    function updateFloatingPopupVariants(abtestEnabled, count) {
        count = parseInt(count) || 2;
        var variants = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        variants.forEach(function(v, index) {
            var $section = $('#floating-popup-variant-' + v);
            if (!abtestEnabled) {
                $section.toggle(index === 0);
            } else {
                $section.toggle(index < count);
            }
        });
    }
    
    // フローティングポップアップ画像アップローダー
    $(document).on('click', '.upload-floating-popup-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            alert('メディアライブラリを読み込み中です。ページを更新してから再度お試しください。');
            return;
        }
        
        var frame = wp.media({
            title: '画像を選択',
            button: { text: '選択' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#floating_popup_image_url_' + variant).val(attachment.url);
            $('#floating-popup-image-preview-' + variant).html('<img src="' + attachment.url + '" alt="">');
            $('.remove-floating-popup-image-btn[data-variant="' + variant + '"]').show();
        });
        
        frame.open();
    });
    
    $(document).on('click', '.remove-floating-popup-image-btn', function(e) {
        e.preventDefault();
        var variant = $(this).data('variant');
        $('#floating_popup_image_url_' + variant).val('');
        $('#floating-popup-image-preview-' + variant).html('<span class="placeholder">画像を選択</span>');
        $(this).hide();
    });
    
    // フローティングバナー記事検索
    setupPostSearch('floating-search-target-posts', 'floating-search-target-results', 'floating-selected-target-posts', 'floating-target-posts-input');
    setupPostSearch('floating-search-exclude-posts', 'floating-search-exclude-results', 'floating-selected-exclude-posts', 'floating-exclude-posts-input');
    
    $(document).on('click', '#floating-selected-target-posts .remove-post, #floating-selected-exclude-posts .remove-post', function() {
        var $item = $(this).closest('.selected-post-item');
        var $container = $item.parent();
        var hiddenId = $container.attr('id').includes('target') ? 'floating-target-posts-input' : 'floating-exclude-posts-input';
        
        $item.remove();
        
        var ids = [];
        $container.find('.selected-post-item').each(function() {
            ids.push($(this).data('id'));
        });
        $('#' + hiddenId).val(ids.join(','));
    });
    
    // A/Bテスト有効/無効切り替え
    $('#abtest_enabled').on('change', function() {
        var enabled = $(this).is(':checked');
        $('#active-variants-row').toggle(enabled);
        
        if (enabled) {
            var count = parseInt($('#active_variants').val());
            updateVariantSections(count, true);
        } else {
            $('.variant-section').each(function(i) {
                $(this).toggle(i === 0);
            });
        }
    });
    
    $('#active_variants').on('change', function() {
        var count = parseInt($(this).val());
        updateVariantSections(count, true);
    });
    
    function updateVariantSections(count, abtestEnabled) {
        $('.variant-section').each(function() {
            var index = parseInt($(this).data('variant'));
            if (index === 0) {
                $(this).show();
            } else {
                $(this).toggle(abtestEnabled && index < count);
            }
        });
    }
    
    // サイズ設定
    $('input[name="popup_tracking_settings[popup_size]"]').on('change', function() {
        $('#custom-size-input').toggle($(this).val() === 'custom');
    });
    
    // 表示モード切り替え
    $('.target-mode-radio').on('change', function() {
        var mode = $(this).val();
        $('#include-posts-section').toggle(mode === 'include');
        $('#exclude-posts-section').toggle(mode === 'exclude');
    });
    
    $('.category-mode-radio').on('change', function() {
        var mode = $(this).val();
        $('#category-selection').toggle(mode !== 'all');
    });
    
    // フローティングポップアップ専用のカテゴリーフィルター
    $('.floating-category-mode-radio').on('change', function() {
        var mode = $(this).val();
        $('#floating-category-selection').toggle(mode !== 'all');
    });
    
    // 記事検索
    var searchTimeout;
    
    function setupPostSearch(inputId, resultsId, containerId, hiddenId) {
        $('#' + inputId).on('input', function() {
            var $this = $(this);
            var search = $this.val();
            var $results = $('#' + resultsId);
            
            clearTimeout(searchTimeout);
            if (search.length < 2) { $results.hide(); return; }
            
            searchTimeout = setTimeout(function() {
                $.post(popupTrackingConfig.ajaxUrl, {
                    action: 'popup_tracking_search_posts',
                    nonce: popupTrackingConfig.nonce,
                    search: search
                }, function(res) {
                    if (res.success && res.data.length) {
                        var html = res.data.map(function(p) {
                            return '<div class="search-result-item" data-id="' + p.id + '" data-title="' + p.title.replace(/"/g, '&quot;') + '">' + p.title + '</div>';
                        }).join('');
                        $results.html(html).show();
                    } else {
                        $results.hide();
                    }
                });
            }, 300);
        });
        
        $(document).on('click', '#' + resultsId + ' .search-result-item', function() {
            var id = $(this).data('id');
            var title = $(this).data('title');
            var $container = $('#' + containerId);
            var $input = $('#' + hiddenId);
            
            if ($container.find('[data-id="' + id + '"]').length) return;
            
            $container.append('<div class="selected-post-item" data-id="' + id + '"><span class="post-title">' + title + '</span><button type="button" class="remove-post">×</button></div>');
            
            var ids = [];
            $container.find('.selected-post-item').each(function() {
                ids.push($(this).data('id'));
            });
            $input.val(ids.join(','));
            
            $('#' + inputId).val('');
            $results.hide();
        });
    }
    
    setupPostSearch('search-target-posts', 'search-target-results', 'selected-target-posts', 'target-posts-input');
    setupPostSearch('search-exclude-posts', 'search-exclude-results', 'selected-exclude-posts', 'exclude-posts-input');
    
    $(document).on('click', '.remove-post', function() {
        var $item = $(this).closest('.selected-post-item');
        var $container = $item.parent();
        var hiddenId = $container.attr('id').includes('target') ? 'target-posts-input' : 'exclude-posts-input';
        
        $item.remove();
        
        var ids = [];
        $container.find('.selected-post-item').each(function() {
            ids.push($(this).data('id'));
        });
        $('#' + hiddenId).val(ids.join(','));
    });
    
    // ログリセット
    $('#reset-logs-btn').on('click', function() {
        if (!confirm('本当に計測ログをリセットしますか？この操作は取り消せません。')) return;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_reset_logs',
            nonce: popupTrackingConfig.nonce
        }, function(res) {
            alert(res.success ? 'ログをリセットしました' : 'エラーが発生しました');
            if (res.success) location.reload();
        });
    });
    
    // フローティングバナーログリセット
    $('#reset-floating-logs-btn').on('click', function() {
        if (!confirm('本当にフローティングバナーの計測ログをリセットしますか？この操作は取り消せません。')) return;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_reset_floating_logs',
            nonce: popupTrackingConfig.nonce
        }, function(res) {
            alert(res.success ? 'フローティングバナーのログをリセットしました' : 'エラーが発生しました');
            if (res.success) location.reload();
        });
    });
    
    // ポップアップスナップショット保存（スナップショットページ用）
    $(document).on('click', '#save-popup-snapshot-btn', function() {
        $('#popup-snapshot-modal').css('display', 'flex');
    });
    
    $('#cancel-snapshot-btn, #popup-snapshot-modal').on('click', function(e) {
        if (e.target === this || $(e.target).is('#cancel-snapshot-btn')) {
            $('#popup-snapshot-modal').hide();
            $('#snapshot-name').val('');
        }
    });
    
    $('#popup-snapshot-modal > div').on('click', function(e) {
        e.stopPropagation();
    });
    
    $('#confirm-save-snapshot-btn').on('click', function() {
        var name = $('#snapshot-name').val().trim();
        if (!name) {
            alert('記録名を入力してください');
            return;
        }
        
        // スナップショットページ用のデータ取得
        var snapshotData = $('#popup-snapshot-data');
        var dashboardData = $('#popup-dashboard-data');
        var dataSource = snapshotData.length ? snapshotData : dashboardData;
        
        var period = dataSource.data('period') || new URLSearchParams(window.location.search).get('period') || 'week';
        var startDate = dataSource.data('start-date') || $('#start_date').val() || '';
        var endDate = dataSource.data('end-date') || $('#end_date').val() || '';
        
        // 現在の統計を取得（データ属性から）
        var impressions = parseInt(dataSource.data('impressions')) || 0;
        var clicks = parseInt(dataSource.data('clicks')) || 0;
        var closes = parseInt(dataSource.data('closes')) || 0;
        var ctr = parseFloat(dataSource.data('ctr')) || 0;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_save_snapshot',
            nonce: popupTrackingConfig.nonce,
            name: name,
            period: period,
            start_date: startDate,
            end_date: endDate,
            impressions: impressions,
            clicks: clicks,
            closes: closes,
            ctr: ctr
        }, function(res) {
            if (res.success) {
                alert('記録を保存しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '保存に失敗しました'));
            }
        });
    });
    
    // スナップショット削除
    $(document).on('click', '.delete-snapshot-btn', function() {
        if (!confirm('この記録を削除しますか？')) return;
        
        var index = $(this).data('index');
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_delete_snapshot',
            nonce: popupTrackingConfig.nonce,
            index: index
        }, function(res) {
            if (res.success) {
                alert('記録を削除しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '削除に失敗しました'));
            }
        });
    });
    
    // すべてのポップアップスナップショット削除
    $(document).on('click', '#delete-all-popup-snapshots-btn', function() {
        if (!confirm('すべてのポップアップスナップショットを削除しますか？この操作は取り消せません。')) return;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_delete_all_snapshots',
            nonce: popupTrackingConfig.nonce
        }, function(res) {
            if (res.success) {
                alert('すべての記録を削除しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '削除に失敗しました'));
            }
        });
    });
    
    // フローティングバナースナップショット保存（スナップショットページ用）
    $(document).on('click', '#save-floating-snapshot-btn', function() {
        $('#floating-snapshot-modal').css('display', 'flex');
    });
    
    $('#cancel-floating-snapshot-btn, #floating-snapshot-modal').on('click', function(e) {
        if (e.target === this || $(e.target).is('#cancel-floating-snapshot-btn')) {
            $('#floating-snapshot-modal').hide();
            $('#floating-snapshot-name').val('');
        }
    });
    
    $('#floating-snapshot-modal > div').on('click', function(e) {
        e.stopPropagation();
    });
    
    $('#confirm-save-floating-snapshot-btn').on('click', function() {
        var name = $('#floating-snapshot-name').val().trim();
        if (!name) {
            alert('記録名を入力してください');
            return;
        }
        
        // スナップショットページ用のデータ取得
        var snapshotData = $('#floating-snapshot-data');
        var dashboardData = $('#floating-dashboard-data');
        var dataSource = snapshotData.length ? snapshotData : dashboardData;
        
        var period = dataSource.data('period') || new URLSearchParams(window.location.search).get('period') || 'week';
        var startDate = dataSource.data('start-date') || '';
        var endDate = dataSource.data('end-date') || '';
        
        // 現在の統計を取得（データ属性から）
        var impressions = parseInt(dataSource.data('impressions')) || 0;
        var clicks = parseInt(dataSource.data('clicks')) || 0;
        var ctr = parseFloat(dataSource.data('ctr')) || 0;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_save_floating_snapshot',
            nonce: popupTrackingConfig.nonce,
            name: name,
            period: period,
            start_date: startDate,
            end_date: endDate,
            impressions: impressions,
            clicks: clicks,
            closes: 0,
            ctr: ctr
        }, function(res) {
            if (res.success) {
                alert('記録を保存しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '保存に失敗しました'));
            }
        });
    });
    
    // フローティングバナースナップショット削除
    $(document).on('click', '.delete-floating-snapshot-btn', function() {
        if (!confirm('この記録を削除しますか？')) return;
        
        var index = $(this).data('index');
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_delete_floating_snapshot',
            nonce: popupTrackingConfig.nonce,
            index: index
        }, function(res) {
            if (res.success) {
                alert('記録を削除しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '削除に失敗しました'));
            }
        });
    });
    
    // すべてのフローティングバナースナップショット削除
    $(document).on('click', '#delete-all-floating-snapshots-btn', function() {
        if (!confirm('すべてのフローティングバナースナップショットを削除しますか？この操作は取り消せません。')) return;
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_delete_all_floating_snapshots',
            nonce: popupTrackingConfig.nonce
        }, function(res) {
            if (res.success) {
                alert('すべての記録を削除しました');
                location.reload();
            } else {
                alert('エラー: ' + (res.data || '削除に失敗しました'));
            }
        });
    });
    
    // カスタム期間
    $('#apply-custom-period').on('click', function() {
        var start = $('#start_date').val();
        var end = $('#end_date').val();
        if (start && end) {
            window.location.href = '?page=popup-tracking&period=custom&start_date=' + start + '&end_date=' + end;
        }
    });
    
    // ランキングタブ
    $('.ranking-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.ranking-tab').removeClass('active');
        $(this).addClass('active');
        $('.ranking-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // タグフィルター
    $('#apply-tag-filter').on('click', function() {
        var tags = $('#tag-filter-select').val();
        var mode = $('#tag-filter-mode').val();
        var url = '?page=popup-tracking&period=' + (new URLSearchParams(window.location.search).get('period') || 'week');
        
        if (tags && tags.length > 0) {
            url += '&tags=' + tags.join(',') + '&tag_mode=' + mode;
        }
        
        window.location.href = url;
    });
    
    // ============================================
    // タグ別CTA設定ページ
    // ============================================
    
    // ソート可能にする
    if ($('#tag-cta-list').length) {
        $('#tag-cta-list').sortable({
            handle: '.cta-handle',
            placeholder: 'cta-placeholder',
            update: function() {
                updateCtaIndexes();
            }
        });
    }
    
    function updateCtaIndexes() {
        $('#tag-cta-list .cta-item').each(function(i) {
            $(this).attr('data-index', i);
        });
    }
    
    // CTAトグル
    $(document).on('click', '.toggle-cta', function() {
        var $body = $(this).closest('.cta-item').find('.cta-body');
        $body.slideToggle(200);
        $(this).text($body.is(':visible') ? '▲' : '▼');
    });
    
    // CTA削除
    $(document).on('click', '.delete-cta', function() {
        if (!confirm('このCTA設定を削除しますか？')) return;
        $(this).closest('.cta-item').remove();
        updateCtaIndexes();
        
        if ($('#tag-cta-list .cta-item').length === 0) {
            $('#tag-cta-list').html('<p class="no-ctas">まだCTA設定がありません。下のボタンから追加してください。</p>');
        }
    });
    
    // 新規CTA追加
    $('#add-new-cta').on('click', function() {
        var template = $('#cta-item-template').html();
        var index = $('#tag-cta-list .cta-item').length;
        var html = template.replace(/__INDEX__/g, index);
        
        $('#tag-cta-list .no-ctas').remove();
        $('#tag-cta-list').append(html);
        
        var $newItem = $('#tag-cta-list .cta-item').last();
        $newItem.find('.cta-id').val('cta_' + Date.now());
    });
    
    // タグ検索
    $(document).on('input', '.tag-search', function() {
        var $this = $(this);
        var search = $this.val();
        var $results = $this.siblings('.tag-search-results');
        
        clearTimeout(searchTimeout);
        if (search.length < 1) { $results.hide(); return; }
        
        searchTimeout = setTimeout(function() {
            $.post(popupTrackingConfig.ajaxUrl, {
                action: 'popup_tracking_search_tags',
                nonce: popupTrackingConfig.nonce,
                search: search
            }, function(res) {
                if (res.success && res.data.length) {
                    var html = res.data.map(function(t) {
                        return '<div class="tag-result-item" data-id="' + t.id + '" data-name="' + t.name + '">' + t.name + '</div>';
                    }).join('');
                    $results.html(html).show();
                } else {
                    $results.hide();
                }
            });
        }, 300);
    });
    
    $(document).on('click', '.tag-result-item', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var $container = $(this).closest('.tag-selector').find('.selected-tags');
        
        if ($container.find('[data-id="' + id + '"]').length) return;
        
        $container.append('<span class="selected-tag" data-id="' + id + '">' + name + '<button type="button" class="remove-tag">×</button></span>');
        
        $(this).closest('.tag-search-results').hide();
        $(this).closest('.tag-selector').find('.tag-search').val('');
    });
    
    $(document).on('click', '.remove-tag', function() {
        $(this).parent().remove();
    });
    
    // CTA画像アップロード
    $(document).on('click', '.upload-cta-image', function() {
        var $btn = $(this);
        var frame = wp.media({
            title: '画像を選択',
            button: { text: '選択' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var $field = $btn.closest('.image-upload-field');
            $field.find('.cta-image-url').val(attachment.url);
            $field.find('.image-preview-small').html('<img src="' + attachment.url + '" alt="">');
            $field.find('.remove-cta-image').show();
        });
        
        frame.open();
    });
    
    $(document).on('click', '.remove-cta-image', function() {
        var $field = $(this).closest('.image-upload-field');
        $field.find('.cta-image-url').val('');
        $field.find('.image-preview-small').html('<span class="placeholder">画像なし</span>');
        $(this).hide();
    });
    
    // バリアント画像アップロード
    $(document).on('click', '.upload-variant-image', function() {
        var $btn = $(this);
        var frame = wp.media({
            title: '画像を選択',
            button: { text: '選択' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var $field = $btn.closest('.image-upload-field');
            $field.find('.variant-image-url').val(attachment.url);
            $field.find('.image-preview-tiny').html('<img src="' + attachment.url + '" alt="">');
        });
        
        frame.open();
    });
    
    // A/Bテスト切り替え
    $(document).on('change', '.cta-abtest-enabled', function() {
        var $variants = $(this).closest('.cta-body').find('.cta-variants');
        $variants.toggle($(this).is(':checked'));
    });
    
    // バリアント追加
    $(document).on('click', '.add-variant', function() {
        var $variants = $(this).closest('.cta-variants');
        var count = $variants.find('.variant-item').length;
        
        if (count >= 10) {
            alert('パターンは最大10個までです');
            return;
        }
        
        var template = $('#variant-item-template').html();
        var label = String.fromCharCode(65 + count); // A, B, C...
        var html = template.replace(/__LABEL__/g, label);
        
        $(this).before(html);
    });
    
    // CTA保存
    $('#save-tag-ctas').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('保存中...');
        
        var ctas = [];
        
        $('#tag-cta-list .cta-item').each(function() {
            var $item = $(this);
            var tags = [];
            $item.find('.selected-tag').each(function() {
                tags.push(parseInt($(this).data('id')));
            });
            
            var variants = [];
            $item.find('.variant-item').each(function() {
                variants.push({
                    image_url: $(this).find('.variant-image-url').val(),
                    link_url: $(this).find('.variant-link-url').val(),
                    weight: parseInt($(this).find('.variant-weight').val()) || 50
                });
            });
            
            ctas.push({
                id: $item.find('.cta-id').val() || 'cta_' + Date.now(),
                name: $item.find('.cta-name').val() || 'CTA',
                tags: tags,
                image_url: $item.find('.cta-image-url').val(),
                link_url: $item.find('.cta-link-url').val(),
                abtest_enabled: $item.find('.cta-abtest-enabled').is(':checked'),
                variants: variants
            });
        });
        
        $.post(popupTrackingConfig.ajaxUrl, {
            action: 'popup_tracking_save_tag_ctas',
            nonce: popupTrackingConfig.nonce,
            ctas: ctas
        }, function(res) {
            $btn.prop('disabled', false).text('設定を保存');
            
            if (res.success) {
                alert('保存しました！');
            } else {
                alert('エラー: ' + (res.data || '保存に失敗しました'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('設定を保存');
            alert('通信エラーが発生しました');
        });
    });
    
    // 検索結果の外クリックで閉じる
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.tag-selector').length) {
            $('.tag-search-results').hide();
        }
        if (!$(e.target).closest('.post-search-box').length) {
            $('.search-results').hide();
        }
    });
    
})(jQuery);
