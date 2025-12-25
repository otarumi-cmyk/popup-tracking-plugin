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
        // クリックイベントの場合は、表示が記録されていることを確認
        if (eventType === 'click' && !state.impression) {
            log('Click event ignored: impression not recorded yet');
            return;
        }
        
        if (state[eventType]) {
            log('Event already logged: ' + eventType);
            return;
        }
        state[eventType] = true;
        var ctaId = meta.ctaId || 'default';
        var variant = meta.variant || 'A';
        log('Sending log: ' + eventType + ' (cta: ' + ctaId + ', variant: ' + variant + ')');
        
        var data = new FormData();
        data.append('action', 'popup_tracking_log');
        data.append('nonce', config.nonce);
        data.append('post_id', config.postId);
        data.append('cta_id', ctaId);
        data.append('variant', variant);
        data.append('event_type', eventType);
        
        fetch(config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(json) { 
                log('Log response: ' + JSON.stringify(json));
                if (!json.success && json.data && json.data.indexOf('Duplicate') !== -1) {
                    log('Duplicate log prevented by server');
                    state[eventType] = false; // リセットして再試行可能に
                }
            })
            .catch(function(err) { 
                log('Log error: ' + err);
                state[eventType] = false; // エラー時はリセット
            });
    }
    
    // ============================================
    // ポップアップ本体
    // ============================================
    function showPopup() {
        if (popupShown) return;
        popupShown = true;
        
        var modal = document.getElementById('popup-tracking-modal');
        if (!modal) { log('Modal not found'); return; }
        
        var content = modal.querySelector('.popup-tracking-content');
        if (content && config.popupWidth) {
            content.style.maxWidth = config.popupWidth + 'px';
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        markAsShown('popup');
        log('Popup displayed (cta: ' + popupMeta.ctaId + ', variant: ' + popupMeta.variant + ')');
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
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
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
            log('Floating banner already shown');
            return;
        }
        
        var banner = document.getElementById('popup-floating-banner');
        if (!banner) { 
            log('Floating banner not found'); 
            return; 
        }
        
        // PCのみ表示（再確認）
        var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            log('Floating banner: Mobile device detected, not showing');
            return;
        }
        
        floatingShown = true;
        banner.style.display = 'block';
        log('Floating banner displayed (PC only, always shown)');
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
        var closeBtn = banner.querySelector('.popup-floating-close');
        var link = document.getElementById('popup-floating-link');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideFloating();
                sendLog('close', floatingMeta, floatingLogged);
            });
        }
        
        if (link) {
            var clickHandled = false;
            var handleClick = function(e) {
                if (clickHandled) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
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
        if (!floatingEnabled) return;
        if (!config.floatingImageUrl) { log('No floating image configured'); return; }
        
        // PCのみ表示（モバイルでは表示しない）
        var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
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
            log('Floating banner already shown in this page load');
            return;
        }
        
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (!banner) { 
            log('Floating banner bottom not found in DOM'); 
            return; 
        }
        
        // 画像が実際に存在するか確認
        var pcImg = banner.querySelector('.popup-floating-banner-image-pc');
        var spImg = banner.querySelector('.popup-floating-banner-image-sp');
        if (!pcImg && !spImg) {
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
            log('Floating banner images not loaded yet, waiting...');
            // 画像の読み込みを待つ
            var imgToWait = pcImg || spImg;
            if (imgToWait) {
                imgToWait.addEventListener('load', function() {
                    if (!floatingBannerShown) {
                        floatingBannerShown = true;
                        banner.style.display = 'block';
                        log('Floating banner bottom displayed after image load');
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
                    log('Floating banner not shown, ignoring click');
                    return;
                }
                clickHandled = true;
                e.preventDefault();
                e.stopPropagation();
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
        if (!floatingBannerEnabled) {
            log('Floating banner not enabled');
            return;
        }
        
        // バナーがDOMに存在するか確認
        var banner = document.getElementById('popup-floating-banner-bottom');
        if (!banner) {
            log('Floating banner element not found in DOM');
            return;
        }
        
        var hasPc = !!config.floatingBannerImageUrlPc;
        var hasSp = !!config.floatingBannerImageUrlSp;
        if (!hasPc && !hasSp) { 
            log('No floating banner image configured'); 
            return; 
        }
        
        // セッションIDを確実に設定（フローティングバナー用）
        if (!document.cookie.match(/popup_tracking_session=/)) {
            var sessionId = Math.random().toString(36).substr(2, 16);
            document.cookie = 'popup_tracking_session=' + sessionId + '; path=/; max-age=86400'; // 24時間有効
            log('Floating banner session ID created: ' + sessionId);
        } else {
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
