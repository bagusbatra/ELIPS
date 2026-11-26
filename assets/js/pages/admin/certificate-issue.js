// Direktori: assets/js/pages/admin/certificate-issue.js
// ELIPS LMS - Penerbitan Sertifikat (admin & Operasional). Server memeriksa semua aturan.
(function (window) {
    'use strict';
    var ELIPS = window.ELIPS = window.ELIPS || {};

    function $(id) { return document.getElementById(id); }
    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function call(action, payload) {
        var api = ELIPS.api || {};
        if (!api.enabled || !api.base) return Promise.reject(new Error('Server tidak tersedia.'));
        return fetch(api.base, {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, payload: payload || {} })
        }).then(function (r) { return r.json(); }).then(function (b) {
            if (!b.ok) throw new Error(b.message || 'Permintaan gagal.');
            return b;
        });
    }
    function fileUrl(id, format, side) {
        return ELIPS.api.base + '?action=certificate.file&id=' + id + '&format=' + format + (side ? '&side=' + side : '');
    }
    function fmt(n) { return n == null ? '-' : String(Math.round(n * 100) / 100).replace('.', ','); }

    function rowHtml(cls, s) {
        var status, check = '', links = '';
        if (s.issued) {
            status = '<span class="cis-chip cis-chip--done">Terbit</span><div class="cis-muted cis-num">' + esc(s.certificateNumber) + '</div>';
            links = '<div class="cis-links"><a href="' + fileUrl(s.certificateId, 'pdf') + '">PDF</a>' +
                '<a href="' + fileUrl(s.certificateId, 'jpg', 'front') + '">JPG</a>' +
                '<a href="' + fileUrl(s.certificateId, 'png', 'front') + '">PNG</a></div>';
        } else if (s.ready) {
            status = '<span class="cis-chip cis-chip--ok">Siap diterbitkan</span>';
            check = '<input type="checkbox" class="cis-pick" data-class="' + cls.classId + '" data-enr="' + s.enrollmentId + '" checked aria-label="Pilih ' + esc(s.name) + '">';
        } else {
            status = '<span class="cis-chip cis-chip--wait">Nilai belum lengkap</span>';
        }
        return '<tr><td style="width:2rem">' + check + '</td><td><strong>' + esc(s.name) + '</strong></td>' +
            '<td class="cis-num">' + fmt(s.average) + (s.predicate ? ' (' + esc(s.predicate) + ')' : '') + '</td>' +
            '<td>' + esc(s.predicateLabel || '-') + '</td><td class="cis-num">' + fmt(s.totalJp) + ' JP</td>' +
            '<td>' + status + '</td><td>' + links + '</td></tr>';
    }

    function render(classes) {
        var box = $('cisList');
        if (!classes.length) {
            box.innerHTML = '<p class="cis-muted">Belum ada angkatan yang nilainya dikirim tentor.</p>';
            return;
        }
        box.innerHTML = classes.map(function (c) {
            var ready = c.students.filter(function (s) { return s.ready && !s.issued; }).length;
            return '<section class="cis-card" data-class="' + c.classId + '">' +
                '<div class="cis-card-head"><div><div class="cis-card-title">' + esc(c.name) + ' <span class="cis-muted">(' + esc(c.code) + ')</span></div>' +
                '<div class="cis-muted">' + esc(c.course) + ' &middot; ' + ready + ' siap diterbitkan</div></div>' +
                '<div class="cis-actions"><button type="button" class="btn btn-primary cis-issue" data-class="' + c.classId + '"' + (ready ? '' : ' disabled') + '>' +
                '<i class="bi bi-patch-check"></i> Terbitkan terpilih</button></div></div>' +
                '<table class="cis-table"><thead><tr><th></th><th>Peserta</th><th>Rata-rata</th><th>Predikat</th><th>Durasi</th><th>Status</th><th>Unduh</th></tr></thead><tbody>' +
                c.students.map(function (s) { return rowHtml(c, s); }).join('') + '</tbody></table></section>';
        }).join('');
    }

    function load() {
        return call('cert.queue', {}).then(function (b) {
            $('cisError').innerHTML = '';
            render(b.data.classes || []);
        }).catch(function (e) {
            $('cisError').innerHTML = '<div class="alert alert-danger">' + esc(e.message) + '</div>';
            $('cisList').innerHTML = '';
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.cis-issue');
        if (!btn) return;
        var classId = Number(btn.getAttribute('data-class'));
        var ids = Array.prototype.map.call(
            document.querySelectorAll('.cis-pick[data-class="' + classId + '"]:checked'),
            function (el) { return Number(el.getAttribute('data-enr')); });
        if (!ids.length) { if (ELIPS.toast) ELIPS.toast('Pilih minimal satu peserta.'); return; }
        if (!window.confirm('Terbitkan ' + ids.length + ' sertifikat? Nomor sertifikat akan dibuat dan tidak dapat diubah.')) return;
        btn.disabled = true;
        call('cert.issue', { classId: classId, enrollmentIds: ids }).then(function (b) {
            if (ELIPS.toast) ELIPS.toast(b.message);
            return load();
        }).catch(function (err) {
            if (ELIPS.toast) ELIPS.toast(err.message);
            btn.disabled = false;
        });
    });

    document.addEventListener('DOMContentLoaded', load);
})(window);