/**
 * PhantomTrack - Universal Analytics
 * One script tag. Any website. Zero config.
 */
(function() {
    'use strict';
    
    console.log('[PhantomTrack] Starting initialization...');
    
    if (window.__phantomTrackInitialized) {
        console.log('[PhantomTrack] Already initialized, skipping');
        return;
    }
    window.__phantomTrackInitialized = true;
    
    let trackId = null;
    let endpoint = null;
    
    if (document.currentScript && document.currentScript.src) {
        try {
            const url = new URL(document.currentScript.src);
            trackId = url.searchParams.get('trackid');
            console.log('[PhantomTrack] Got trackId from currentScript:', trackId);
            
            const customEndpoint = url.searchParams.get('endpoint');
            if (customEndpoint) {
                endpoint = customEndpoint;
            }
        } catch (e) {
            console.error('[PhantomTrack] Error parsing currentScript URL:', e);
        }
    }
    
    if (!trackId) {
        console.log('[PhantomTrack] No trackId from currentScript, scanning all scripts...');
        const scripts = document.getElementsByTagName('script');
        
        for (let i = scripts.length - 1; i >= 0; i--) {
            const src = scripts[i].src;
            
            if (src && src.includes('phantom.js')) {
                try {
                    const url = new URL(src);
                    const paramValue = url.searchParams.get('trackid');
                    
                    if (paramValue) {
                        trackId = paramValue;
                        console.log('[PhantomTrack] Found trackId in script tag:', trackId);
                        
                        const customEndpoint = url.searchParams.get('endpoint');
                        if (customEndpoint) {
                            endpoint = customEndpoint;
                        }
                        break;
                    }
                } catch (e) {
                    console.error('[PhantomTrack] Error parsing script URL:', e);
                }
            }
        }
    }
    
    if (!endpoint) {
        const currentHost = window.location.hostname;
        
        if (currentHost === 'localhost' || currentHost === '127.0.0.1' || currentHost.startsWith('192.168.')) {
            endpoint = 'track';
        } else {
            const scriptSrc = document.currentScript ? document.currentScript.src : '';
            if (scriptSrc) {
                try {
                    const scriptUrl = new URL(scriptSrc);
                    endpoint = scriptUrl.origin + '/track';
                    console.log('[PhantomTrack] Endpoint set to:', endpoint);
                } catch (e) {
                    // Fallback
                    endpoint = 'https://phantomtrack.unaux.com/track';
                }
            } else {
                endpoint = 'https://phantomtrack.unaux.com/track';
            }
        }
    }
    
    console.log('[PhantomTrack] Final trackId:', trackId);
    console.log('[PhantomTrack] Final endpoint:', endpoint);
    
    if (!trackId || !/^track_[a-zA-Z0-9]{20,30}$/.test(trackId)) {
        console.error('[PhantomTrack] INVALID or MISSING trackId! Got:', trackId);
        console.error('[PhantomTrack] Expected format: track_[20-30 alphanumeric characters]');
        
        // CREATE window.phantom ANYWAY so the page doesn't break
        window.phantom = {
            track: function(eventName, properties) {
                console.error('[PhantomTrack] Cannot track - invalid trackId');
            },
            getSessionId: function() {
                return 'invalid';
            },
            version: '1.0.0',
            error: 'Invalid or missing trackId'
        };
        return;
    }
    
    console.log('[PhantomTrack] TrackId validation passed ✓');
    
    const INACTIVITY_THRESHOLD = 30000;
    const HEARTBEAT_INTERVAL = 30000;
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 2000;
    
    let sessionId = generateSessionId();
    let activeTime = 0;
    let lastActivityTime = Date.now();
    let isActive = true;
    let heartbeatTimer = null;
    let inactivityTimer = null;
    
    function generateSessionId() {
        let randomPart;
        
        if (window.crypto && window.crypto.getRandomValues) {
            const array = new Uint32Array(2);
            window.crypto.getRandomValues(array);
            randomPart = array[0].toString(36) + array[1].toString(36);
        } else {
            randomPart = Math.random().toString(36).substr(2, 9);
        }
        
        return 'sess_' + Date.now() + '_' + randomPart.substr(0, 12);
    }
    
    function getDeviceType() {
        try {
            const ua = navigator.userAgent || '';
            
            if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
                return 'tablet';
            }
            if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
                return 'mobile';
            }
            
            return 'desktop';
        } catch (e) {
            return 'unknown';
        }
    }
    
    function getScreenResolution() {
        try {
            const width = screen.width || 0;
            const height = screen.height || 0;
            
            if (width > 0 && height > 0 && width < 100000 && height < 100000) {
                return width + 'x' + height;
            }
        } catch (e) {
            // Ignore error
        }
        
        return 'unknown';
    }
    
    function sanitizeURL(url) {
        if (typeof url !== 'string') return '';
        
        try {
            const urlObj = new URL(url);
            urlObj.username = '';
            urlObj.password = '';
            
            const sanitized = urlObj.toString();
            return sanitized.length > 2048 ? sanitized.substr(0, 2048) : sanitized;
        } catch (e) {
            return url.substr(0, 2048);
        }
    }
    
    function collectPageData() {
        return {
            trackid: trackId,
            session_id: sessionId,
            page_url: sanitizeURL(window.location.href),
            referrer: sanitizeURL(document.referrer) || 'direct',
            device_type: getDeviceType(),
            screen_resolution: getScreenResolution(),
            user_agent: (navigator.userAgent || '').substr(0, 500)
        };
    }
    
    function sendData(eventType, additionalData, retryCount) {
        retryCount = retryCount || 0;
        
        const data = {
            ...collectPageData(),
            event_type: eventType,
            ...additionalData
        };
        
        console.log('[PhantomTrack] Sending:', eventType, data);
        
        // Try sendBeacon for leave/end events
        if ((eventType === 'leave' || eventType === 'pageview_end') && navigator.sendBeacon) {
            try {
                const blob = new Blob([JSON.stringify(data)], { type: 'text/plain' });
                const sent = navigator.sendBeacon(endpoint, blob);
                
                if (sent) {
                    console.log('[PhantomTrack] Sent via sendBeacon');
                    return;
                }
            } catch (e) {
                console.log('[PhantomTrack] sendBeacon failed, using fetch');
            }
        }
        
        // Use text/plain to avoid CORS preflight
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'text/plain'
            },
            body: JSON.stringify(data),
            keepalive: true
        })
        .then(function(response) {
            console.log('[PhantomTrack] Response status:', response.status);
            if (response.ok) {
                return response.json().catch(function() {
                    return { success: true };
                });
            } else if (response.status === 429) {
                console.warn('[PhantomTrack] Rate limited, will retry');
                if (retryCount < MAX_RETRIES) {
                    setTimeout(function() {
                        sendData(eventType, additionalData, retryCount + 1);
                    }, RETRY_DELAY * (retryCount + 1));
                }
            }
        })
        .catch(function(error) {
            console.error('[PhantomTrack] Fetch error:', error);
            if (retryCount < MAX_RETRIES) {
                setTimeout(function() {
                    sendData(eventType, additionalData, retryCount + 1);
                }, RETRY_DELAY * (retryCount + 1));
            }
        });
    }
    
    function updateActiveTime() {
        if (isActive && document.visibilityState === 'visible') {
            const now = Date.now();
            const timeSinceLastActivity = now - lastActivityTime;
            
            if (timeSinceLastActivity > 0 && timeSinceLastActivity < INACTIVITY_THRESHOLD) {
                activeTime += timeSinceLastActivity;
            }
            
            lastActivityTime = now;
        }
    }
    
    function markActive() {
        const now = Date.now();
        
        if (!isActive) {
            isActive = true;
            lastActivityTime = now;
        } else {
            const timeSinceLastActivity = now - lastActivityTime;
            
            if (timeSinceLastActivity > 0 && timeSinceLastActivity < INACTIVITY_THRESHOLD) {
                activeTime += timeSinceLastActivity;
            }
            
            lastActivityTime = now;
        }
        
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(markInactive, INACTIVITY_THRESHOLD);
    }
    
    function markInactive() {
        if (isActive) {
            updateActiveTime();
            isActive = false;
        }
    }
    
    function sendHeartbeat() {
        if (document.visibilityState === 'visible') {
            sendData('heartbeat', {
                is_active: isActive
            });
        }
    }
    
    function startHeartbeat() {
        if (heartbeatTimer) return;
        heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);
    }
    
    function stopHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }
    
    function handleVisibilityChange() {
        try {
            if (document.visibilityState === 'hidden') {
                markInactive();
                stopHeartbeat();
            } else {
                markActive();
                startHeartbeat();
            }
        } catch (e) {
            // Ignore error
        }
    }
    
    function handlePageLeave() {
        try {
            if (window.__phantomPageLeft) return;
            window.__phantomPageLeft = true;
            
            updateActiveTime();
            const timeSpent = Math.round(activeTime / 1000);
            
            sendData('pageview_end', {
                timespent: timeSpent
            });
            
            sendData('leave');
            
        } catch (e) {
            // Ignore error
        }
    }
    
    window.phantom = {
        track: function(eventName, properties) {
            try {
                if (!eventName || typeof eventName !== 'string') {
                    console.error('[PhantomTrack] Invalid event name');
                    return;
                }
                
                if (eventName.length > 100) {
                    eventName = eventName.substr(0, 100);
                }
                
                properties = properties || {};
                
                let propertiesJSON;
                try {
                    propertiesJSON = JSON.stringify(properties);
                    
                    if (propertiesJSON.length > 10000) {
                        console.error('[PhantomTrack] Properties too large');
                        return;
                    }
                } catch (e) {
                    console.error('[PhantomTrack] Cannot stringify properties');
                    return;
                }
                
                sendData('custom_event', {
                    event_name: eventName,
                    event_properties: propertiesJSON
                });
                
            } catch (e) {
                console.error('[PhantomTrack] Error in track():', e);
            }
        },
        
        getSessionId: function() {
            return sessionId;
        },
        
        version: '1.0.0'
    };
    
    window.phantom.event = window.phantom.track;
    
    console.log('[PhantomTrack] window.phantom created successfully ✓');
    
    function init() {
        try {
            console.log('[PhantomTrack] Initializing tracking...');
            sendData('pageview');
            
            const activityEvents = [
                'mousedown', 
                'mousemove', 
                'keypress', 
                'scroll', 
                'touchstart', 
                'click'
            ];
            
            activityEvents.forEach(function(event) {
                document.addEventListener(event, markActive, { 
                    passive: true, 
                    capture: false 
                });
            });
            
            document.addEventListener('visibilitychange', handleVisibilityChange, { 
                passive: true 
            });
            
            window.addEventListener('beforeunload', handlePageLeave, { 
                capture: true 
            });
            window.addEventListener('pagehide', handlePageLeave, { 
                passive: true 
            });
            
            inactivityTimer = setTimeout(markInactive, INACTIVITY_THRESHOLD);
            
            startHeartbeat();
            setTimeout(sendHeartbeat, 1000);
            
            console.log('[PhantomTrack] Initialization complete ✓');
            
        } catch (e) {
            console.error('[PhantomTrack] Initialization error:', e);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 0);
    }
    
})();