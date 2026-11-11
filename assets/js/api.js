// ============================================================
// ELIPS LMS - api.js
// Jembatan antara UI sinkron dan backend PHP/MySQL.
//
// Mode server aktif ketika halaman disajikan melalui HTTP/HTTPS dan
// window.ELIPS_CONFIG.apiBase tersedia (lihat config.js). Pada mode
// ini data dibaca dari cache snapshot yang diambil sekali saat halaman
// dimuat, lalu setiap operasi tulis memanggil API dan menyegarkan
// cache dari snapshot terbaru.
//
// Jika backend tidak tersedia (mis. file:// atau JS DOM tanpa server),
// aplikasi otomatis memakai localStorage seperti mode demo lama.
// ============================================================

(function (window) {
    'use strict';

    const ELIPS = window.ELIPS = window.ELIPS || {};
    const KEYS = ELIPS.KEYS || {};
    const originalRead = ELIPS.read;
    const originalWrite = ELIPS.write;

    const api = {
        mode: 'local',
        enabled: false,
        base: '',
        cache: {},
        session: null,
        error: null,
        call: function () {
            return { ok: false, message: 'Backend tidak aktif.', data: null };
        }
    };
    ELIPS.api = api;

    function clone(value) {
        if (value === undefined) return undefined;
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (err) {
            return value;
        }
    }

    function basePrefix() {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function resolveBase() {
        const config = window.ELIPS_CONFIG || null;
        if (config && config.apiBase) return config.apiBase;
        return basePrefix() + 'api/index.php';
    }

    function supportedProtocol() {
        return window.location.protocol === 'http:' || window.location.protocol === 'https:';
    }

    function applyCollections(collections, session) {
        const next = {};
        if (collections && typeof collections === 'object') {
            Object.keys(collections).forEach(function (name) {
                const key = KEYS[name] || name;
                next[key] = collections[name];
            });
        }
        if (session) next[KEYS.session] = session;
        api.cache = next;
    }

    function applyPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        if (payload.collections) {
            applyCollections(payload.collections, payload.session || null);
        } else if (payload.snapshot) {
            applyCollections(payload.snapshot, payload.session || null);
        }
        if (Object.prototype.hasOwnProperty.call(payload, 'session')) {
            api.session = payload.session || null;
            if (payload.session) api.cache[KEYS.session] = payload.session;
            else delete api.cache[KEYS.session];
        }
    }

    function request(method, action, payload) {
        let xhr;
        try {
            xhr = new XMLHttpRequest();
        } catch (err) {
            return { ok: false, message: 'XMLHttpRequest tidak tersedia.', data: null, network: true };
        }

        const separator = api.base.indexOf('?') === -1 ? '?' : '&';
        const url = api.base + (method === 'GET' ? separator + 'action=' + encodeURIComponent(action) : '');

        try {
            xhr.open(method, url, false);
            xhr.withCredentials = true;
            if (method === 'POST') {
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify({ action: action, payload: payload || {} }));
            } else {
                xhr.send(null);
            }
        } catch (err) {
            return { ok: false, message: 'Tidak dapat menghubungi server.', data: null, network: true };
        }

        if (!xhr.responseText) {
            return { ok: false, message: 'Respons server kosong.', data: null, network: true };
        }
        try {
            return JSON.parse(xhr.responseText);
        } catch (err) {
            return { ok: false, message: 'Respons server tidak valid.', data: null, network: true };
        }
    }

    api.call = function (action, payload) {
        const result = request('POST', action, payload || {});
        if (result.network) {
            return { ok: false, message: result.message, data: null };
        }
        applyPayload(result);
        return {
            ok: !!result.ok,
            message: result.message || '',
            data: result.data === undefined ? null : result.data
        };
    };

    const config = window.ELIPS_CONFIG || null;
    if (!config || !config.apiBase || !supportedProtocol()) {
        api.mode = 'local';
        return;
    }

    api.base = resolveBase();
    const boot = request('GET', 'bootstrap');
    if (boot.network || !boot.ok) {
        api.mode = 'local';
        api.error = boot.message || 'Backend tidak tersedia.';
        if (window.console && console.warn) {
            console.warn('[ELIPS] Backend tidak tersedia, memakai localStorage:', api.error);
        }
        return;
    }

    api.enabled = true;
    api.mode = 'server';
    applyCollections(boot.collections || {}, boot.session || null);

    // Getter/setter tetap sinkron: pembacaan berasal dari cache snapshot,
    // sedangkan penulisan langsung ditangani aksi API pada modul CRUD.
    ELIPS.read = function (key, fallback) {
        if (!Object.prototype.hasOwnProperty.call(api.cache, key)) return fallback;
        const value = api.cache[key];
        if (value === null || value === undefined) return fallback;
        return clone(value);
    };

    ELIPS.write = function (key, value) {
        api.cache[key] = clone(value);
        return value;
    };

    // Bantu debugging: ELIPS.api.refresh() memuat ulang seluruh snapshot.
    api.refresh = function () {
        const result = request('GET', 'bootstrap');
        if (result.ok) applyPayload(result);
        return result;
    };
})(window);
