(function() {
    'use strict';
    
    var config = window.popupTrackingConfig || {};
    var debug = config.debug == 1;
    var popupEnabled = config.popupEnabled == 1 && !!config.imageUrl;
    var floatingEnabled = config.floatingEnabled == 1 && !!config.floatingImageUrl;
    
    var popupShown = false;
    var floatingShown = false;
    var popupLogged = { impression: false, click: false, close: false };
    var floatingLogged = { impression: false, click: false, close: false };
    
    var popupMeta = {
        ctaId: config.ctaId || 'default',
        variant: config.variant || 'A'
    };
    var floatingMeta = {
        ctaId: config.floatingCtaId || 'floating',
        variant: 'A'
    };
    
    function log(msg) {
        if (debug) console.log('[PopupTracking]', msg);
    }
    
    // デバッグログ（常に出力）
    function debugLog(msg, data) {
        var timestamp = new Date().toLocaleTimeString();
        console.log('[PopupTracking Debug ' + timestamp + ']', msg, data || '');
    }
    
    // セッションIDを取得
    function getSessionId() {
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();
            if (cookie.indexOf('popup_tracking_session=') === 0) {
                return cookie.substring('popup_tracking_session='.length);
            }
        }
        return 'not-set';
    }
    
    function getStorageKey(prefix) {
        var base = prefix === 'floating' ? 'popup_floating' : 'popup';
        var suffix = config.frequency === 'session' ? 'session' : new Date().toISOString().slice(0, 10);
        return base + '_shown_' + suffix;
    }
    
    function hasShownBefore(prefix) {
        if (debug) return false;
        try {
            var storage = config.frequency === 'session' ? sessionStorage : localStorage;
            return storage.getItem(getStorageKey(prefix)) === '1';
        } catch (e) { return false; }
    }
    
    function markAsShown(prefix) {
        try {
            var storage = config.frequency === 'session' ? sessionStorage : localStorage;
            storage.setItem(getStorageKey(prefix), '1');
        } catch (e) {}
    }
    
    function sendLog(eventType, meta, state) {
        var ctaId = meta.ctaId || 'default';
        var variant = meta.variant || 'A';
        var sessionId = getSessionId();
        
        // デバッグ: イベント送信前の状態確認
        debugLog('=== sendLog Called ===', {
            eventType: eventType,
            ctaId: ctaId,
            variant: variant,
            postId: config.postId,
            sessionId: sessionId,
            currentState: {
                impression: state.impression || false,
                click: state.click || false,
                close: state.close || false
            },
            popupShown: popupShown,
            floatingShown: floatingShown
        });
        
        // クリックイベントの場合は、表示が記録されていることを確認
        if (eventType === 'click' && !state.impression) {
            debugLog('❌ Click event ignored: impression not recorded yet', {
                eventType: eventType,
                impressionRecorded: state.impression,
                ctaId: ctaId
            });
            log('Click event ignored: impression not recorded yet');
            return;
        }
        
        if (state[eventType]) {
            debugLog('❌ Event already logged (duplicate prevented)', {
                eventType: eventType,
                ctaId: ctaId,
                variant: variant
            });
            log('Event already logged: ' + eventType);
            return;
        }
        
        state[eventType] = true;
        log('Sending log: ' + eventType + ' (cta: ' + ctaId + ', variant: ' + variant + ')');
        
        var data = new FormData();
        data.append('action', 'popup_tracking_log');
        data.append('nonce', config.nonce);
        data.append('post_id', config.postId);
        data.append('cta_id', ctaId);
        data.append('variant', variant);
        data.append('event_type', eventType);
        
        // デバッグ: 送信データの詳細
        var sendData = {
            action: 'popup_tracking_log',
            post_id: config.postId,
            cta_id: ctaId,
            variant: variant,
            event_type: eventType,
            session_id: sessionId,
            timestamp: new Date().toISOString()
        };
        debugLog('📤 Sending to server', sendData);
        
        var requestStartTime = Date.now();
        
        fetch(config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(res) { 
                var requestTime = Date.now() - requestStartTime;
                debugLog('📥 Response received (' + requestTime + 'ms)', {
                    status: res.status,
                    statusText: res.statusText
                });
                return res.json(); 
            })
            .then(function(json) { 
                debugLog('✅ Server response', {
                    success: json.success || false,
                    data: json.data || '',
                    message: json.message || '',
                    fullResponse: json
                });
                
                log('Log response: ' + JSON.stringify(json));
                
                if (!json.success && json.data && json.data.indexOf('Duplicate') !== -1) {
                    debugLog('⚠️ Duplicate log prevented by server', {
                        eventType: eventType,
                        ctaId: ctaId,
                        response: json.data
                    });
                    log('Duplicate log prevented by server');
                    state[eventType] = false; // リセットして再試行可能に
                } else if (json.success) {
                    debugLog('✅ Event successfully recorded', {
                        eventType: eventType,
                        ctaId: ctaId,
                        variant: variant
                    });
                }
            })
            .catch(function(err) { 
                debugLog('❌ Request error', {
                    error: err.message || err,
                    eventType: eventType,
                    ctaId: ctaId,
                    stack: err.stack
                });
                log('Log error: ' + err);
                state[eventType] = false; // エラー時はリセット
            });
    }
    
    // ============================================
    // ポップアップ本体
    // ============================================
    function showPopup() {
        if (popupShown) {
            debugLog('⚠️ showPopup called but popup already shown', {
                popupShown: popupShown,
                impressionRecorded: popupLogged.impression
            });
            return;
        }
        popupShown = true;
        
        var modal = document.getElementById('popup-tracking-modal');
        if (!modal) { 
            debugLog('❌ Modal not found in DOM');
            log('Modal not found'); 
            return; 
        }
        
        var content = modal.querySelector('.popup-tracking-content');
        if (content && config.popupWidth) {
            content.style.maxWidth = config.popupWidth + 'px';
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        markAsShown('popup');
        log('Popup displayed (cta: ' + popupMeta.ctaId + ', variant: ' + popupMeta.variant + ')');
        
        debugLog('📊 Recording POPUP impression', {
            ctaId: popupMeta.ctaId,
            variant: popupMeta.variant,
            postId: config.postId,
            sessionId: getSessionId(),
            beforeState: {
                impression: popupLogged.impression || false,
                click: popupLogged.click || false
            }
        });
        
        sendLog('impression', popupMeta, popupLogged);
    }
    
    function hidePopup() {
        var modal = document.getElementById('popup-tracking-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    function setupPopupListeners() {
        var modal = document.getElementById('popup-tracking-modal');
        if (!modal) return;
        
        var closeBtn = modal.querySelector('.popup-tracking-close');
        var overlay = modal.querySelector('.popup-tracking-overlay');
        var link = document.getElementById('popup-tracking-link');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hidePopup();
                sendLog('close', popupMeta, popupLogged);
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                hidePopup();
                sendLog('close', popupMeta, popupLogged);
            });
        }
        
        if (link) {
            var clickHandled = false;
            var handleClick = function(e) {
                if (clickHandled) {
                    debugLog('⚠️ Click already handled (duplicate prevented)', {
                        ctaId: popupMeta.ctaId,
                        eventType: e.type
                    });
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
                
                debugLog('🖱️ Recording POPUP click', {
                    ctaId: popupMeta.ctaId,
                    variant: popupMeta.variant,
                    postId: config.postId,
                    sessionId: getSessionId(),
                    eventType: e.type,
                    impressionRecorded: popupLogged.impression || false,
                    beforeState: {
                        impression: popupLogged.impression || false,
                        click: popupLogged.click || false
                    }
                });
                
                sendLog('click', popupMeta, popupLogged);
                var href = link.getAttribute('href');
                setTimeout(function() {
                    window.open(href, '_blank');
                    // リセット（次のクリックのために）
                    setTimeout(function() { clickHandled = false; }, 1000);
                }, 500);
            };
            // touchendを先に処理し、clickイベントを無効化
            link.addEventListener('touchend', function(e) {
                handleClick(e);
                // モバイルではclickイベントを防ぐ
                setTimeout(function() {
                    clickHandled = false;
                }, 300);
            }, { passive: false });
            link.addEventListener('click', function(e) {
                // タッチデバイスの場合は無視（touchendで処理済み）
                if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                    if (clickHandled) {
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                }
                handleClick(e);
            });
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                hidePopup();
                sendLog('close', popupMeta, popupLogged);
            }
        });
    }
    
    function setupPopupTrigger() {
        var type = config.triggerType || 'delay';
        var value = parseInt(config.triggerValue) || 5;
        log('Trigger: ' + type + ', value: ' + value);
        
        if (type === 'delay') {
            setTimeout(showPopup, value * 1000);
        } else if (type === 'scroll') {
            var scrollHandler = function() {
                var scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
                if (scrollPercent >= value) {
                    showPopup();
                    window.removeEventListener('scroll', scrollHandler);
                }
            };
            window.addEventListener('scroll', scrollHandler, { passive: true });
        } else if (type === 'exit') {
            var exitTriggered = false;
            document.addEventListener('mouseout', function(e) {
                if (!exitTriggered && e.clientY < 10 && e.relatedTarget === null) {
                    exitTriggered = true;
                    showPopup();
                }
            });
            setTimeout(function() {
                if (!exitTriggered) showPopup();
            }, 8000);
        }
    }
    
    function initPopup() {
        if (!popupEnabled) return;
        if (!config.imageUrl) { log('No image URL configured'); return; }
        if (hasShownBefore('popup')) { log('Popup already shown (frequency: ' + config.frequency + ')'); return; }
        document.cookie = 'popup_tracking_session=' + Math.random().toString(36).substr(2, 16) + '; path=/';
        log('Init popup (cta: ' + popupMeta.ctaId + ', variant: ' + popupMeta.variant + ')');
        setupPopupListeners();
        setupPopupTrigger();
    }
    
    // ============================================
    // フローティングバナー
    // ============================================
    function showFloating() {
        if (floatingShown) {
            debugLog('⚠️ showFloating called but floating already shown', {
                floatingShown: floatingShown,
                impressionRecorded: floatingLogged.impression
            });
            log('Floating banner already shown');
            return;
        }
        
        var banner = document.getElementById('popup-floating-banner');
        if (!banner) { 
            debugLog('❌ Floating banner not found in DOM');
            log('Floating banner not found'); 
            return; 
        }
        
        // PCのみ表示（再確認）
        var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            debugLog('📱 Floating banner: Mobile device detected, not showing', {
                windowWidth: window.innerWidth,
                userAgent: navigator.userAgent
            });
            log('Floating banner: Mobile device detected, not showing');
            return;
        }
        
        floatingShown = true;
        banner.style.display = 'block';
        log('Floating banner displayed (PC only, always shown)');
        
        debugLog('📊 Recording FLOATING POPUP impression', {
            ctaId: floatingMeta.ctaId,
            variant: floatingMeta.variant,
            postId: config.postId,
            sessionId: getSessionId(),
            beforeState: {
                impression: floatingLogged.impression || false,
                click: floatingLogged.click || false
            }
        });
        
        // 頻度制限なしなのでmarkAsShownは呼ばない
        sendLog('impression', floatingMeta, floatingLogged);
    }
    
    function hideFloating() {
        var banner = document.getElementById('popup-floating-banner');
        if (banner) banner.style.display = 'none';
    }
    
    function setupFloatingListeners() {
        var banner = document.getElementById('popup-floating-banner');
        if (!banner) return;
        var link = document.getElementById('popup-floating-link');
        
        if (link) {
            var clickHandled = false;
            var handleClick = function(e) {
                if (clickHandled) {
                    debugLog('⚠️ Floating click already handled (duplicate prevented)', {
                        ctaId: floatingMeta.ctaId,
                        eventType: e.type
                    });
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
                
                debugLog('🖱️ Recording FLOATING POPUP click', {
                    ctaId: floatingMeta.ctaId,
                    variant: floatingMeta.variant,
                    postId: config.postId,
                    sessionId: getSessionId(),
                    eventType: e.type,
                    impressionRecorded: floatingLogged.impression || false,
                    beforeState: {
                        impression: floatingLogged.impression || false,
                        click: floatingLogged.click || false
                    }
                });
                
                sendLog('click', floatingMeta, floatingLogged);
                var href = link.getAttribute('href');
                setTimeout(function() {
                    window.open(href, '_blank');
                    setTimeout(function() { clickHandled = false; }, 1000);
                }, 300);
            };
            link.addEventListener('touchend', function(e) {
                handleClick(e);
                setTimeout(function() { clickHandled = false; }, 300);
            }, { passive: false });
            link.addEventListener('click', function(e) {
                if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                    if (clickHandled) {
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                }
                handleClick(e);
            });
        }
    }
    
    function initFloating() {
        debugLog('🚀 initFloating called (右下フローティングポップアップ)', {
            enabled: floatingEnabled,
            hasImage: !!config.floatingImageUrl,
            postId: config.postId,
            windowWidth: window.innerWidth
        });
        
        if (!floatingEnabled) {
            debugLog('❌ Floating popup not enabled');
            return;
        }
        if (!config.floatingImageUrl) { 
            debugLog('❌ No floating image configured');
            log('No floating image configured'); 
            return; 
        }
        
        // PCのみ表示（モバイルでは表示しない）
        var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            debugLog('📱 Floating popup: Mobile device detected, not showing', {
                windowWidth: window.innerWidth,
                userAgent: navigator.userAgent
            });
            log('Floating banner: Mobile device detected, not showing');
            return;
        }
        
        // 頻度制限なし - 常に表示
        setupFloatingListeners();
        showFloating();
    }
    
    // ============================================
    // フローティングバナー（下側横長）
    // ============================================
    var floatingBannerEnabled = config.floatingBannerEnabled == 1;
    var floatingBannerShown = false;
    var floatingBannerLogged = { impression: false, click: false, close: false };
    var floatingBannerMeta = {
        ctaId: config.floatingBannerCtaId || 'floating',
        variant: config.floatingBannerVariant || 'A'
    };
    
    function showFloatingBanner() {
        if (floatingBannerShown) {
            debugLog('⚠️ showFloatingBanner called but already shown', {
                floatingBannerShown: floatingBannerShown,
                impressionRecorded: floatingBannerLogged.impression || false
            });
            log('Floating banner already shown in this page load');
            return;
        }
        
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (!banner) { 
            debugLog('❌ Floating banner bottom not found in DOM');
            log('Floating banner bottom not found in DOM'); 
            return; 
        }
        
        // 画像が実際に存在するか確認
        var pcImg = banner.querySelector('.popup-floating-banner-image-pc');
        var spImg = banner.querySelector('.popup-floating-banner-image-sp');
        if (!pcImg && !spImg) {
            debugLog('❌ No floating banner images found in DOM');
            log('No floating banner images found');
            return;
        }
        
        // 画像が実際に読み込まれているか確認（画像の幅が0でない）
        var imgLoaded = false;
        if (pcImg && pcImg.complete && pcImg.naturalWidth > 0) {
            imgLoaded = true;
        } else if (spImg && spImg.complete && spImg.naturalWidth > 0) {
            imgLoaded = true;
        }
        
        if (!imgLoaded) {
            debugLog('⏳ Floating banner images not loaded yet, waiting...', {
                pcImg: pcImg ? { complete: pcImg.complete, naturalWidth: pcImg.naturalWidth } : null,
                spImg: spImg ? { complete: spImg.complete, naturalWidth: spImg.naturalWidth } : null
            });
            log('Floating banner images not loaded yet, waiting...');
            // 画像の読み込みを待つ
            var imgToWait = pcImg || spImg;
            if (imgToWait) {
                imgToWait.addEventListener('load', function() {
                    if (!floatingBannerShown) {
                        floatingBannerShown = true;
                        banner.style.display = 'block';
                        log('Floating banner bottom displayed after image load');
                        
                        debugLog('📊 Recording FLOATING BANNER (bottom) impression (after image load)', {
                            ctaId: floatingBannerMeta.ctaId,
                            variant: floatingBannerMeta.variant,
                            postId: config.postId,
                            sessionId: getSessionId(),
                            beforeState: {
                                impression: floatingBannerLogged.impression || false,
                                click: floatingBannerLogged.click || false
                            }
                        });
                        
                        sendLog('impression', floatingBannerMeta, floatingBannerLogged);
                    }
                });
                // 既に読み込まれている場合のフォールバック
                if (imgToWait.complete) {
                    setTimeout(function() {
                        if (!floatingBannerShown && (imgToWait.naturalWidth > 0)) {
                            floatingBannerShown = true;
                            banner.style.display = 'block';
                            log('Floating banner bottom displayed (image already loaded)');
                            
                            debugLog('📊 Recording FLOATING BANNER (bottom) impression (image already loaded)', {
                                ctaId: floatingBannerMeta.ctaId,
                                variant: floatingBannerMeta.variant,
                                postId: config.postId,
                                sessionId: getSessionId(),
                                beforeState: {
                                    impression: floatingBannerLogged.impression || false,
                                    click: floatingBannerLogged.click || false
                                }
                            });
                            
                            sendLog('impression', floatingBannerMeta, floatingBannerLogged);
                        }
                    }, 100);
                }
            }
            return;
        }
        
        // バナーを表示（常に表示）
        floatingBannerShown = true;
        banner.style.display = 'block';
        log('Floating banner bottom displayed (always shown)');
        
        debugLog('📊 Recording FLOATING BANNER (bottom) impression', {
            ctaId: floatingBannerMeta.ctaId,
            variant: floatingBannerMeta.variant,
            postId: config.postId,
            sessionId: getSessionId(),
            beforeState: {
                impression: floatingBannerLogged.impression || false,
                click: floatingBannerLogged.click || false
            }
        });
        
        // バナーが実際に表示された場合のみログを送信（初回のみ）
        sendLog('impression', floatingBannerMeta, floatingBannerLogged);
    }
    
    function hideFloatingBanner() {
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (banner) banner.style.display = 'none';
    }
    
    function setupFloatingBannerListeners() {
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (!banner) {
            log('Cannot setup listeners: banner not found');
            return;
        }
        
        var link = document.getElementById('popup-floating-banner-link');
        
        if (link) {
            var clickHandled = false;
            var handleClick = function(e) {
                if (clickHandled) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (!floatingBannerShown) {
                    debugLog('❌ Floating banner not shown, ignoring click', {
                        ctaId: floatingBannerMeta.ctaId,
                        floatingBannerShown: floatingBannerShown,
                        impressionRecorded: floatingBannerLogged.impression || false
                    });
                    log('Floating banner not shown, ignoring click');
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
                
                debugLog('🖱️ Recording FLOATING BANNER (bottom) click', {
                    ctaId: floatingBannerMeta.ctaId,
                    variant: floatingBannerMeta.variant,
                    postId: config.postId,
                    sessionId: getSessionId(),
                    eventType: e.type,
                    impressionRecorded: floatingBannerLogged.impression || false,
                    beforeState: {
                        impression: floatingBannerLogged.impression || false,
                        click: floatingBannerLogged.click || false
                    }
                });
                
                sendLog('click', floatingBannerMeta, floatingBannerLogged);
                var href = link.getAttribute('href');
                if (href) {
                    setTimeout(function() {
                        window.open(href, '_blank');
                        setTimeout(function() { clickHandled = false; }, 1000);
                    }, 300);
                } else {
                    setTimeout(function() { clickHandled = false; }, 1000);
                }
            };
            link.addEventListener('touchend', function(e) {
                handleClick(e);
                setTimeout(function() { clickHandled = false; }, 300);
            }, { passive: false });
            link.addEventListener('click', function(e) {
                if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                    if (clickHandled) {
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                }
                handleClick(e);
            });
        }
    }
    
    function initFloatingBanner() {
        debugLog('🚀 initFloatingBanner called', {
            enabled: floatingBannerEnabled,
            hasPcImage: !!config.floatingBannerImageUrlPc,
            hasSpImage: !!config.floatingBannerImageUrlSp,
            postId: config.postId
        });
        
        if (!floatingBannerEnabled) {
            debugLog('❌ Floating banner not enabled');
            log('Floating banner not enabled');
            return;
        }
        
        // バナーがDOMに存在するか確認
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (!banner) {
            debugLog('❌ Floating banner element not found in DOM');
            log('Floating banner element not found in DOM');
            return;
        }
        
        var hasPc = !!config.floatingBannerImageUrlPc;
        var hasSp = !!config.floatingBannerImageUrlSp;
        if (!hasPc && !hasSp) { 
            debugLog('❌ No floating banner image configured');
            log('No floating banner image configured'); 
            return; 
        }
        
        // セッションIDを確実に設定（フローティングバナー用）
        var sessionId = getSessionId();
        if (sessionId === 'not-set') {
            sessionId = Math.random().toString(36).substr(2, 16);
            document.cookie = 'popup_tracking_session=' + sessionId + '; path=/; max-age=86400'; // 24時間有効
            debugLog('✅ Floating banner session ID created', { sessionId: sessionId });
            log('Floating banner session ID created: ' + sessionId);
        } else {
            debugLog('✅ Floating banner using existing session ID', { sessionId: sessionId });
            log('Floating banner using existing session ID');
        }
        
        // 頻度制限なし - 常に表示
        setupFloatingBannerListeners();
        showFloatingBanner();
    }
    
    function init() {
        if (popupEnabled) initPopup();
        if (floatingEnabled) initFloating();
        if (floatingBannerEnabled) initFloatingBanner();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
