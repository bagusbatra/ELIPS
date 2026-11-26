// Direktori: assets/js/pages/verify-certificate.js
// ELIPS LMS - skrip halaman verify-certificate.php (publik, tanpa login).
// Target QR: verify-certificate.php?c=TOKEN. Data diambil dari server (aksi cert.verify).
document.addEventListener('DOMContentLoaded', function () {
    const verifyBtn = document.getElementById('verifyBtn');
    const verifyInput = document.getElementById('verifyInput');
    const verifyResult = document.getElementById('verifyResult');
    const base = (ELIPS.api && ELIPS.api.base) || 'api/index.php';
    const esc = ELIPS.escapeHtml;

    function showInvalid(message) {
        verifyResult.className = 'verify-result show invalid';
        verifyResult.innerHTML = '<div class="verify-result-header"><i class="bi bi-x-circle-fill" style="font-size: 1.25rem;"></i>' +
            '<span>Sertifikat Tidak Valid</span></div><div class="verify-result-details"><div>' + esc(message) + '</div></div>';
    }

    function fmtDate(iso) {
        return iso ? ELIPS.formatDate(iso) : '-';
    }

    function showValid(c) {
        const rows = (c.groups || []).map(function (g) {
            return '<tr><td colspan="4" style="padding-top:.75rem"><strong>' + esc(g.title) + '</strong></td></tr>' +
                g.items.map(function (it, i) {
                    return '<tr><td>' + (i + 1) + '</td><td>' + esc(it.title) + '</td><td style="text-align:center">' + esc(Math.round(it.score)) +
                        ' (' + esc(it.letter) + ')</td><td style="text-align:center">' + esc(it.jp) + '</td></tr>';
                }).join('');
        }).join('');
        const expired = c.expired;
        verifyResult.className = 'verify-result show ' + (expired ? 'invalid' : 'valid');
        verifyResult.innerHTML = [
            '<div class="verify-result-header"><i class="bi bi-' + (expired ? 'exclamation-triangle-fill' : 'check-circle-fill') + '" style="font-size: 1.25rem;"></i>',
            '<span>' + (expired ? 'Sertifikat Asli - Masa Berlaku Berakhir' : 'Sertifikat Asli & Valid') + '</span></div>',
            '<div class="verify-result-details">',
            '<div><strong>Nomor Sertifikat:</strong> ' + esc(c.number) + '</div>',
            '<div><strong>Nama Peserta:</strong> ' + esc(c.name) + '</div>',
            '<div><strong>Kursus:</strong> ' + esc(c.course) + '</div>',
            '<div><strong>Predikat:</strong> ' + esc(c.predicateLabel) + ' (rata-rata ' + esc(Math.round(c.average)) + ', ' + esc(c.averageLetter) + ')</div>',
            '<div><strong>Durasi Pembelajaran:</strong> ' + esc(c.totalJp) + ' jam</div>',
            '<div><strong>Tanggal Terbit:</strong> ' + fmtDate(c.issuedAt) + '</div>',
            '<div><strong>Berlaku s/d:</strong> ' + esc(c.validText) + '</div>',
            '<table style="width:100%;margin-top:1rem;font-size:.875rem;border-collapse:collapse"><thead><tr><th style="text-align:left">No</th>' +
                '<th style="text-align:left">Materi</th><th>Nilai</th><th>JP</th></tr></thead><tbody>' + rows + '</tbody></table>',
            '</div>'
        ].join('');
    }

    function verify(params) {
        verifyResult.className = 'verify-result';
        fetch(base, {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cert.verify', payload: params })
        }).then(function (r) { return r.json(); }).then(function (b) {
            if (!b.ok) { showInvalid(b.message || 'Sertifikat tidak ditemukan.'); return; }
            showValid(b.data.certificate);
        }).catch(function () { showInvalid('Tidak dapat menghubungi server.'); });
    }

    function fromInput() {
        const value = verifyInput.value.trim();
        if (!value) { ELIPS.toast('Masukkan nomor sertifikat terlebih dahulu'); return; }
        verify({ number: value });
    }

    verifyBtn.addEventListener('click', fromInput);
    verifyInput.addEventListener('keypress', function (e) { if (e.key === 'Enter') fromInput(); });

    const q = new URLSearchParams(window.location.search);
    if (q.get('c')) {
        verify({ token: q.get('c') });
    } else if (q.get('no')) {
        verifyInput.value = q.get('no');
        verify({ number: q.get('no') });
    }
});