// Direktori: assets/js/admin-badges.js
// ============================================================
// ELIPS LMS - admin-badges.js
// Menyegarkan angka badge pada sidebar (tanpa memuat ulang halaman).
// Memakai endpoint ringan api/index.php?action=badges (tanpa snapshot).
// ============================================================

(function () {
    'use strict';

    var INTERVAL_MS = 60000;
    var base = typeof window.ELIPS_BASE === 'string' ? window.ELIPS_BASE : '';
    var endpoint = base + '/api/index.php?action=badges';

    function apply(counts) {
        document.querySelectorAll('[data-badge]').forEach(function (el) {
            var key = el.getAttribute('data-badge');
            var value = Number(counts && counts[key]) || 0;
            el.textContent = value > 99 ? '99+' : String(value);
            el.setAttribute('data-count', String(value));
            el.hidden = value <= 0;
        });
    }

    function refresh() {
        if (document.hidden || typeof window.fetch !== 'function') return;
        window.fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (result) { if (result && result.ok) apply(result.data || {}); })
            .catch(function () { /* diam: angka lama tetap tampil */ });
    }

    if (!document.querySelector('[data-badge]')) return;
    setInterval(refresh, INTERVAL_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });
})();