// ============================================================
// ELIPS LMS - pwa.js
// Registrasi service worker, banner "Pasang di layar utama",
// petunjuk pemasangan iOS, dan pemberitahuan saat offline.
//
// Dimuat terakhir di setiap halaman. Semua fitur bersifat progresif:
// browser tanpa dukungan PWA tetap menjalankan aplikasi seperti biasa.
// ============================================================

(function (window, document) {
    'use strict';

    const ELIPS = window.ELIPS = window.ELIPS || {};
    if (ELIPS.pwa && ELIPS.pwa.__initialized) return;

    const DISMISS_KEY = 'elips_pwa_install_dismissed';
    const PROMPTED_KEY = 'elips_pwa_install_prompted';
    const DISMISS_DAYS = 7;
    const DAY_MS = 24 * 60 * 60 * 1000;

    let deferredPrompt = null;
    let installBanner = null;
    let networkBanner = null;
    let registration = null;
    let hadController = false;
    let refreshing = false;
    let wasOffline = window.navigator ? window.navigator.onLine === false : false;

    function basePrefix() {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function storageGet(store, key) {
        try {
            return store.getItem(key);
        } catch (err) {
            return null;
        }
    }

    function storageSet(store, key, value) {
        try {
            store.setItem(key, value);
        } catch (err) {
            // Mode privat atau storage penuh: abaikan.
        }
    }

    function isStandalone() {
        try {
            if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
                return true;
            }
        } catch (err) {
            // matchMedia tidak tersedia.
        }
        return window.navigator ? window.navigator.standalone === true : false;
    }

    function isIOS() {
        const nav = window.navigator || {};
        const ua = nav.userAgent || '';
        if (/iphone|ipad|ipod/i.test(ua)) return true;
        return nav.platform === 'MacIntel' && Number(nav.maxTouchPoints) > 1;
    }

    function isIOSSafari() {
        const ua = (window.navigator && window.navigator.userAgent) || '';
        if (!isIOS()) return false;
        if (/crios|fxios|edgios|opios|brave/i.test(ua)) return false;
        return /safari/i.test(ua);
    }

    function installDismissed() {
        const raw = storageGet(window.localStorage, DISMISS_KEY);
        if (!raw) return false;
        const at = Number(raw);
        if (!at || isNaN(at)) return false;
        return Date.now() - at < DISMISS_DAYS * DAY_MS;
    }

    function promptedThisSession() {
        return storageGet(window.sessionStorage, PROMPTED_KEY) === '1';
    }

    function markPrompted() {
        storageSet(window.sessionStorage, PROMPTED_KEY, '1');
    }

    function dismissInstall() {
        storageSet(window.localStorage, DISMISS_KEY, String(Date.now()));
        hideInstallBanner();
    }

    function canShowInstall() {
        return !isStandalone() && !installDismissed() && !promptedThisSession();
    }

    function setBodyClass(name, active) {
        if (!document.body) return;
        document.body.classList.toggle(name, !!active);
    }

    function hideInstallBanner() {
        if (installBanner && installBanner.parentNode) {
            installBanner.parentNode.removeChild(installBanner);
        }
        installBanner = null;
        setBodyClass('pwa-install-visible', false);
    }

    function showInstallBanner(mode) {
        if (installBanner || !canShowInstall() || !document.body) return;
        if ((window.navigator && window.navigator.onLine === false) && mode !== 'ios') return;

        const banner = document.createElement('div');
        banner.className = 'pwa-banner pwa-install-banner';
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', 'Pasang aplikasi ELIPS');
        banner.innerHTML = [
            '<img class="pwa-banner-icon" src="', basePrefix(), 'assets/icons/icon-192.png"',
            ' alt="" width="44" height="44" loading="lazy">',
            '<div class="pwa-banner-text">',
            '<strong>Pasang ELIPS di layar utama</strong>',
            '<span>', mode === 'ios'
                ? 'Ikuti 3 langkah di Safari agar absensi terbuka seperti aplikasi.'
                : 'Buka absensi lebih cepat tanpa mengetik alamat situs.',
            '</span>',
            '</div>',
            '<div class="pwa-banner-actions">',
            '<button type="button" class="btn btn-primary pwa-banner-install">',
            mode === 'ios' ? 'Cara pasang' : 'Pasang',
            '</button>',
            '<button type="button" class="btn pwa-banner-dismiss">Nanti</button>',
            '</div>'
        ].join('');

        const installBtn = banner.querySelector('.pwa-banner-install');
        const dismissBtn = banner.querySelector('.pwa-banner-dismiss');

        if (installBtn) {
            installBtn.addEventListener('click', function () {
                if (mode === 'ios') {
                    showIosSheet();
                } else {
                    promptInstall();
                }
            });
        }
        if (dismissBtn) {
            dismissBtn.addEventListener('click', dismissInstall);
        }

        document.body.appendChild(banner);
        installBanner = banner;
        setBodyClass('pwa-install-visible', true);
        markPrompted();
    }

    function promptInstall() {
        if (!deferredPrompt) {
            if (isIOS()) showIosSheet();
            return Promise.resolve(false);
        }
        const promptEvent = deferredPrompt;
        deferredPrompt = null;
        try {
            promptEvent.prompt();
        } catch (err) {
            hideInstallBanner();
            return Promise.resolve(false);
        }
        return Promise.resolve(promptEvent.userChoice).then(function (choice) {
            if (choice && choice.outcome === 'accepted') {
                hideInstallBanner();
            } else {
                dismissInstall();
            }
            return !!(choice && choice.outcome === 'accepted');
        }).catch(function () {
            hideInstallBanner();
            return false;
        });
    }

    function showIosSheet() {
        if (!document.body || document.getElementById('pwaIosSheet')) return;
        const overlay = document.createElement('div');
        overlay.className = 'pwa-sheet-backdrop';
        overlay.id = 'pwaIosSheet';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'pwaIosSheetTitle');
        overlay.innerHTML = [
            '<div class="pwa-sheet">',
            '<div class="pwa-sheet-header">',
            '<img class="pwa-banner-icon" src="', basePrefix(), 'assets/icons/icon-192.png"',
            ' alt="" width="44" height="44">',
            '<div>',
            '<h2 class="pwa-sheet-title" id="pwaIosSheetTitle">Pasang di iPhone/iPad</h2>',
            '<p class="pwa-sheet-subtitle">Lewat Safari, tanpa App Store.</p>',
            '</div>',
            '</div>',
            '<ol class="pwa-sheet-steps">',
            '<li><span class="pwa-step-number">1</span><span>Ketuk tombol <strong>Bagikan</strong>',
            ' (ikon kotak dengan panah ke atas) di bagian bawah Safari.</span></li>',
            '<li><span class="pwa-step-number">2</span><span>Pilih <strong>Tambahkan ke Layar Utama</strong>',
            ' pada daftar tindakan.</span></li>',
            '<li><span class="pwa-step-number">3</span><span>Ketuk <strong>Tambah</strong>. Ikon ELIPS muncul',
            ' di layar utama dan terbuka langsung ke halaman absen.</span></li>',
            '</ol>',
            '<button type="button" class="btn btn-primary pwa-sheet-close" style="width: 100%;">',
            'Mengerti</button>',
            '</div>'
        ].join('');

        function close() {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            setBodyClass('pwa-sheet-open', false);
            dismissInstall();
        }

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) close();
        });
        const closeBtn = overlay.querySelector('.pwa-sheet-close');
        if (closeBtn) closeBtn.addEventListener('click', close);
        document.addEventListener('keydown', function onKey(event) {
            if (event.key === 'Escape') {
                document.removeEventListener('keydown', onKey);
                close();
            }
        });

        document.body.appendChild(overlay);
        setBodyClass('pwa-sheet-open', true);
        if (closeBtn) closeBtn.focus();
    }

    function hideNetworkBanner() {
        if (networkBanner && networkBanner.parentNode) {
            networkBanner.parentNode.removeChild(networkBanner);
        }
        networkBanner = null;
        setBodyClass('pwa-offline-visible', false);
    }

    function showNetworkBanner() {
        if (networkBanner || !document.body) return;
        const banner = document.createElement('div');
        banner.className = 'pwa-network-banner';
        banner.setAttribute('role', 'status');
        banner.setAttribute('aria-live', 'polite');
        banner.innerHTML = [
            '<span class="pwa-network-icon" aria-hidden="true">&#9888;&#65039;</span>',
            '<span class="pwa-network-text">Anda sedang offline. Absensi memerlukan koneksi internet.</span>',
            '<button type="button" class="pwa-network-retry">Coba lagi</button>'
        ].join('');
        const retry = banner.querySelector('.pwa-network-retry');
        if (retry) {
            retry.addEventListener('click', function () {
                retry.disabled = true;
                retry.textContent = 'Memuat\u2026';
                window.location.reload();
            });
        }
        document.body.appendChild(banner);
        networkBanner = banner;
        setBodyClass('pwa-offline-visible', true);
        if (installBanner) hideInstallBanner();
    }

    function updateNetworkState() {
        const offline = window.navigator ? window.navigator.onLine === false : false;
        if (offline) {
            showNetworkBanner();
        } else {
            hideNetworkBanner();
            if (wasOffline && ELIPS.toast) {
                ELIPS.toast('Koneksi internet kembali. Silakan muat ulang data bila perlu.');
            }
        }
        wasOffline = offline;
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in window.navigator)) return;
        const protocol = window.location.protocol;
        if (protocol !== 'http:' && protocol !== 'https:') return;

        window.addEventListener('load', function () {
            hadController = !!window.navigator.serviceWorker.controller;
            window.navigator.serviceWorker.register(basePrefix() + 'sw.js', {
                scope: basePrefix() || './'
            }).then(function (reg) {
                registration = reg;
                if (typeof reg.update === 'function') {
                    reg.update().catch(function () {});
                }
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible' && registration
                        && typeof registration.update === 'function') {
                        registration.update().catch(function () {});
                    }
                });
            }).catch(function (err) {
                if (window.console && console.warn) {
                    console.warn('[ELIPS] Service worker tidak aktif:', err);
                }
            });
        });

        window.navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (!hadController) {
                hadController = true;
                return;
            }
            if (refreshing) return;
            refreshing = true;
            window.location.reload();
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        showInstallBanner('android');
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        hideInstallBanner();
    });

    window.addEventListener('online', updateNetworkState);
    window.addEventListener('offline', updateNetworkState);

    function start() {
        updateNetworkState();
        if (isIOSSafari() && canShowInstall()) {
            window.setTimeout(function () {
                if (canShowInstall()) showInstallBanner('ios');
            }, 1200);
        }
    }

    if (document.readyState === 'loading') {
        // Script defer dieksekusi setelah body tersedia; tetap coba tampilkan
        // status jaringan segera, lalu ulangi setelah DOM siap.
        updateNetworkState();
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
    registerServiceWorker();

    ELIPS.pwa = {
        __initialized: true,
        isStandalone: isStandalone,
        isIOS: isIOS,
        isIOSSafari: isIOSSafari,
        canInstall: function () { return !!deferredPrompt; },
        promptInstall: promptInstall,
        showInstallBanner: showInstallBanner,
        showIosSheet: showIosSheet,
        refreshNetworkState: updateNetworkState,
        dismissInstall: dismissInstall,
        serviceWorkerRegistration: function () { return registration; }
    };
})(window, document);
