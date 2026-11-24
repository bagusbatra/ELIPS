// Direktori: assets/js/pages/instructor/tp-core.js
// ============================================================
// ELIPS LMS - inti portal tentor (namespace TP)
// Dipakai semua halaman tentor: panggilan API async, format,
// komponen kecil (lencana, kerangka muat, modal konfirmasi).
// Dimuat lewat 'scripts' => ['pages/instructor/tp-core'] pada tiap halaman.
// ============================================================
(function (window) {
    'use strict';

    const ELIPS = window.ELIPS = window.ELIPS || {};
    const TP = window.TP = {};

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    TP.esc = function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    TP.qs = function (name) {
        return new URLSearchParams(window.location.search).get(name);
    };

    TP.id = function (name) {
        const value = parseInt(TP.qs(name), 10);
        return value > 0 ? value : 0;
    };

    /** Angka gaya Indonesia: 94,92. */
    TP.num = function (value, digits) {
        if (value === null || value === undefined || value === '' || isNaN(Number(value))) return '-';
        const d = digits === undefined ? 2 : digits;
        let text = Number(value).toFixed(d);
        if (d > 0) text = text.replace(/0+$/, '').replace(/\.$/, '');
        return text.replace('.', ',');
    };

    /** '2026-09-01' -> '1 Sep 2026'. */
    TP.date = function (value) {
        if (!value) return '-';
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
        if (!m) return String(value);
        return Number(m[3]) + ' ' + MONTHS[Number(m[2]) - 1] + ' ' + m[1];
    };

    TP.period = function (start, end) {
        if (!start && !end) return 'Jadwal belum diatur';
        return TP.date(start) + ' – ' + TP.date(end);
    };

    TP.initials = function (name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '').charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : '')).toUpperCase() || '?';
    };

    TP.debounce = function (fn, wait) {
        let timer = null;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    };

    // ---------- Komponen ----------

    TP.badge = function (text, tone) {
        return '<span class="tp-badge' + (tone ? ' tp-badge--' + tone : '') + '">' + TP.esc(text) + '</span>';
    };

    /** Lencana status angkatan menurut kelompok tentor. */
    TP.classBadge = function (c) {
        if (c.status === 'ongoing') return TP.badge('Berjalan', 'teal');
        if (c.status === 'ready') return TP.badge('Siap mulai', 'blue');
        if (c.status === 'open') return TP.badge('Pendaftaran', 'amber');
        if (c.status === 'completed') return TP.badge('Selesai', 'gray');
        return TP.badge(c.status, 'gray');
    };

    TP.lockIcon = function (label) {
        return '<svg class="tp-lock" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="' + TP.esc(label || 'Terkunci') + '"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
    };

    TP.progress = function (done, total, tone) {
        const pct = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0;
        return '<div class="tp-progress' + (tone ? ' tp-progress--' + tone : '') + '" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"><span data-w="' + pct + '"></span></div>';
    };

    /** Animasikan semua bar progres dalam root setelah dirender. */
    TP.animateBars = function (root) {
        const scope = root || document;
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                scope.querySelectorAll('.tp-progress > span[data-w]').forEach(function (bar) {
                    bar.style.width = bar.getAttribute('data-w') + '%';
                });
            });
        });
    };

    TP.skeleton = function (rows, height) {
        let html = '';
        for (let i = 0; i < (rows || 3); i++) {
            html += '<div class="tp-skel" style="height:' + (height || 88) + 'px;margin-bottom:.75rem"></div>';
        }
        return html;
    };

    TP.empty = function (icon, title, text) {
        return '<div class="tp-empty"><i class="bi ' + TP.esc(icon || 'bi-inbox') + '"></i><strong>' + TP.esc(title) + '</strong>' + (text ? '<span>' + TP.esc(text) + '</span>' : '') + '</div>';
    };

    TP.errorBox = function (message) {
        return '<div class="tp-note tp-note--amber" role="alert"><i class="bi bi-exclamation-triangle"></i><span>' + TP.esc(message) + '</span></div>';
    };

    TP.toast = function (message) {
        if (ELIPS.toast) ELIPS.toast(message);
    };

    /** Modal konfirmasi: Promise<boolean>. */
    TP.confirm = function (title, text, okLabel) {
        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'tp-modal';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML = '<div class="tp-modal-box"><h3>' + TP.esc(title) + '</h3><p>' + TP.esc(text) + '</p>' +
                '<div class="tp-modal-actions"><button type="button" class="btn btn-ghost" data-act="no">Batal</button>' +
                '<button type="button" class="btn btn-primary" data-act="yes">' + TP.esc(okLabel || 'Lanjutkan') + '</button></div></div>';
            function close(result) {
                document.removeEventListener('keydown', onKey);
                overlay.remove();
                resolve(result);
            }
            function onKey(event) {
                if (event.key === 'Escape') close(false);
            }
            overlay.addEventListener('click', function (event) {
                const act = event.target.getAttribute && event.target.getAttribute('data-act');
                if (act === 'yes') close(true);
                else if (act === 'no' || event.target === overlay) close(false);
            });
            document.addEventListener('keydown', onKey);
            document.body.appendChild(overlay);
            overlay.querySelector('[data-act="yes"]').focus();
        });
    };

    // ---------- API ----------

    /** Panggil API portal. Resolve ke data, reject dengan Error(pesan). */
    TP.call = function (action, payload) {
        const api = ELIPS.api || {};
        if (!api.enabled || !api.base) {
            return Promise.reject(new Error('Server tidak tersedia. Buka aplikasi lewat server web.'));
        }
        return fetch(api.base, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, payload: payload || {} })
        }).then(function (response) {
            return response.text();
        }).then(function (text) {
            let body;
            try { body = JSON.parse(text); } catch (err) { throw new Error('Respons server tidak valid.'); }
            if (!body.ok) {
                if (!body.session && /masuk terlebih dahulu/i.test(body.message || '')) {
                    window.location.href = '../login.php';
                }
                throw new Error(body.message || 'Permintaan gagal.');
            }
            return body;
        }).catch(function (err) {
            if (err instanceof TypeError) throw new Error('Tidak dapat menghubungi server.');
            throw err;
        });
    };

    /** Panggil API lalu kembalikan {data, message}. */
    TP.data = function (action, payload) {
        return TP.call(action, payload).then(function (body) {
            return { data: body.data, message: body.message || '' };
        });
    };

    /** Mulai halaman: validasi peran, isi sidebar, kembalikan pengguna. */
    TP.init = function () {
        const user = ELIPS.requireRole('instruktur');
        if (!user) return null;
        const name = user.name || 'Tentor';
        const nameEl = document.getElementById('instructorName');
        const avatarEl = document.getElementById('instructorAvatar');
        if (nameEl) nameEl.textContent = name;
        if (avatarEl) avatarEl.textContent = TP.initials(name);
        return user;
    };

    /** Peringatkan bila ada perubahan yang belum disimpan. */
    TP.guardUnsaved = function (isDirty) {
        window.addEventListener('beforeunload', function (event) {
            if (isDirty()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    };
})(window);