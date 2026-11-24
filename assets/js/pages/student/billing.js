// Direktori: assets/js/pages/student/billing.js
// ELIPS LMS - tagihan & pembayaran di dashboard siswa
// Menampilkan sisa tagihan, rekening, riwayat pembayaran, nomor siswa (EC-xxxxxx),
// dan formulir unggah bukti pembayaran (dikompres otomatis bila gambar besar).
(function (ELIPS) {
    'use strict';

    var MAX_BYTES = 2 * 1024 * 1024;
    var base = typeof window.ELIPS_BASE === 'string' ? window.ELIPS_BASE : '';
    var state = null;
    var uploadFor = null;

    function esc(v) { return ELIPS.escapeHtml(v === null || v === undefined ? '' : String(v)); }
    function rp(n) { return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID'); }

    var PAY_STATUS = {
        pending: ['Menunggu verifikasi', 'fin-badge-pending'],
        verified: ['Terverifikasi', 'fin-badge-verified'],
        rejected: ['Ditolak', 'fin-badge-rejected'],
        void: ['Dibatalkan', 'fin-badge-void']
    };

    function badge(key) {
        var m = PAY_STATUS[key] || [key, ''];
        return '<span class="fin-badge ' + m[1] + '">' + esc(m[0]) + '</span>';
    }

    function bankHtml(bank) {
        if (!bank || (!bank.bankName && !bank.accountNumber)) {
            return '<div class="bill-bank">Informasi rekening akan diberikan admin lewat WhatsApp.</div>';
        }
        return '<div class="bill-bank"><strong>Transfer ke:</strong> ' + esc(bank.bankName) + ' · <span class="fin-number">' + esc(bank.accountNumber)
            + '</span>' + (bank.accountHolder ? ' a.n. ' + esc(bank.accountHolder) : '')
            + (bank.note ? '<div>' + esc(bank.note) + '</div>' : '') + '</div>';
    }

    function historyHtml(payments) {
        if (!payments.length) return '';
        var rows = payments.map(function (p) {
            return '<tr><td>' + esc(p.paidDate ? ELIPS.formatDate(p.paidDate) : ELIPS.formatDate(p.createdAt)) + '</td><td>' + rp(p.amount) + '</td><td>' + esc(p.method)
                + '</td><td>' + badge(p.status)
                + (p.status === 'rejected' && p.rejectReason ? '<div class="bill-sub">Alasan: ' + esc(p.rejectReason) + '</div>' : '')
                + '</td><td>' + (p.hasProof ? '<a href="' + base + '/api/index.php?action=payment.proof&id=' + p.id + '" target="_blank" rel="noopener noreferrer">Bukti</a>' : '') + '</td></tr>';
        }).join('');
        return '<div class="bill-history"><strong>Riwayat pembayaran</strong><table><thead><tr><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Status</th><th></th></tr></thead><tbody>'
            + rows + '</tbody></table></div>';
    }

    function cardHtml(inv, bank) {
        var paid = inv.status === 'paid';
        var pendingNote = inv.pendingCount > 0
            ? '<div class="bill-sub">' + inv.pendingCount + ' bukti pembayaran sedang diverifikasi admin.</div>' : '';
        var pct = inv.amountDue > 0 ? Math.max(0, Math.min(100, Math.round(inv.amountPaid / inv.amountDue * 100))) : 0;
        var bar = '<div class="bill-progress" title="' + pct + '% terbayar"><span style="width:' + pct + '%"></span></div>';
        var html = '<div class="bill-card' + (paid ? ' is-paid' : '') + '"><div class="bill-head"><div>'
            + '<div class="bill-title">' + esc(inv.courseTitle) + '</div>'
            + '<div class="bill-sub">' + esc(inv.className) + ' · Total ' + rp(inv.amountDue) + ' · Terbayar ' + rp(inv.amountPaid) + '</div>'
            + pendingNote + '</div>';
        if (paid) {
            html += '<span class="fin-badge fin-badge-paid">Lunas</span></div>' + bar;
            if (inv.studentNumber) {
                html += '<div class="bill-number"><i class="bi bi-person-vcard"></i> No. Siswa: <span class="fin-number">' + esc(inv.studentNumber) + '</span></div>';
            }
        } else {
            html += '<div style="text-align:right"><div class="bill-sub">Sisa tagihan</div><div class="bill-remaining">' + rp(inv.remaining) + '</div></div></div>' + bar
                + bankHtml(bank)
                + '<div style="margin-top:.75rem"><button type="button" class="btn btn-primary" data-upload="' + inv.id + '"><i class="bi bi-upload"></i> Unggah bukti pembayaran</button></div>';
        }
        return html + historyHtml(inv.payments || []) + '</div>';
    }

    function render(container) {
        var invoices = state.invoices;
        if (!invoices.length) {
            container.innerHTML = '';
            return;
        }
        // Tagihan belum lunas tampil lebih dulu.
        invoices = invoices.slice().sort(function (a, b) { return (a.status === 'paid') - (b.status === 'paid'); });
        container.innerHTML = '<h2 class="dashboard-section-title" style="margin-bottom:.75rem">Tagihan &amp; Pembayaran</h2>'
            + invoices.map(function (i) { return cardHtml(i, state.bank); }).join('');
    }

    // ---------- Unggah bukti ----------

    function ensureModal() {
        if (document.getElementById('billModal')) return;
        var wrap = document.createElement('div');
        wrap.className = 'attendance-modal';
        wrap.id = 'billModal';
        wrap.style.display = 'none';
        wrap.innerHTML = '<div class="attendance-modal-card" style="max-width:480px">'
            + '<div class="attendance-modal-header"><div><div class="attendance-modal-title">Unggah Bukti Pembayaran</div>'
            + '<div class="attendance-modal-subtitle" id="billModalSub"></div></div>'
            + '<button class="attendance-modal-close" type="button" id="billClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button></div>'
            + '<form id="billForm"><div class="attendance-modal-body">'
            + '<p class="reg-inline-error" id="billError" role="alert" hidden></p>'
            + '<div class="form-group"><label class="form-label" for="billAmount">Nominal yang dibayar (Rp)</label><input class="form-input" id="billAmount" inputmode="numeric" required>'
            + '<div class="form-hint" id="billHint"></div></div>'
            + '<div class="form-group"><label class="form-label" for="billDate">Tanggal bayar</label><input class="form-input" type="date" id="billDate" required></div>'
            + '<div class="form-group"><label class="form-label" for="billMethod">Metode</label><select class="form-input" id="billMethod"></select></div>'
            + '<div class="form-group"><label class="form-label" for="billNote">Catatan (opsional)</label><input class="form-input" id="billNote" maxlength="500"></div>'
            + '<div class="form-group"><label class="form-label" for="billFile">Bukti pembayaran (JPG, PNG, atau PDF, maks. 2 MB)</label>'
            + '<input class="form-input" type="file" id="billFile" accept="image/jpeg,image/png,application/pdf" required></div>'
            + '</div><div class="attendance-modal-actions" style="padding:0 1.5rem 1.5rem">'
            + '<button class="btn btn-outline" type="button" id="billCancel">Batal</button>'
            + '<button class="btn btn-primary" type="submit" id="billSubmit">Kirim bukti</button></div></form></div>';
        document.body.appendChild(wrap);
        document.getElementById('billClose').addEventListener('click', closeModal);
        document.getElementById('billCancel').addEventListener('click', closeModal);
        document.getElementById('billForm').addEventListener('submit', submit);
    }

    function setError(message) {
        var el = document.getElementById('billError');
        el.textContent = message || '';
        el.hidden = !message;
    }

    function openModal(invoiceId) {
        var inv = state.invoices.filter(function (i) { return i.id === Number(invoiceId); })[0];
        if (!inv) return;
        uploadFor = inv;
        ensureModal();
        setError('');
        document.getElementById('billModalSub').textContent = inv.courseTitle + ' — ' + inv.className;
        document.getElementById('billAmount').value = String(Math.round(inv.remaining));
        document.getElementById('billHint').textContent = 'Sisa tagihan ' + rp(inv.remaining) + '. Boleh membayar sebagian.';
        document.getElementById('billDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('billMethod').innerHTML = (state.methods || ['transfer']).map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join('');
        document.getElementById('billNote').value = '';
        document.getElementById('billFile').value = '';
        document.getElementById('billModal').style.display = 'flex';
    }

    function closeModal() {
        var modal = document.getElementById('billModal');
        if (modal) modal.style.display = 'none';
        uploadFor = null;
    }

    function readAsDataUrl(blob, cb) {
        var reader = new FileReader();
        reader.onload = function () { cb(String(reader.result || '')); };
        reader.onerror = function () { cb(''); };
        reader.readAsDataURL(blob);
    }

    // Foto dari ponsel sering >2 MB: perkecil ke maks. 1600 px sebagai JPEG.
    function prepareFile(file, cb) {
        if (file.type === 'application/pdf' || file.size <= 1.5 * 1024 * 1024 || file.type.indexOf('image/') !== 0) {
            if (file.size > MAX_BYTES) { cb(null, 'Ukuran berkas maksimal 2 MB.'); return; }
            readAsDataUrl(file, function (data) { cb(data, data ? '' : 'Berkas tidak dapat dibaca.'); });
            return;
        }
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var scale = Math.min(1, 1600 / Math.max(img.width, img.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob(function (blob) {
                if (!blob || blob.size > MAX_BYTES) { cb(null, 'Ukuran berkas terlalu besar. Gunakan gambar yang lebih kecil.'); return; }
                readAsDataUrl(blob, function (data) { cb(data, data ? '' : 'Berkas tidak dapat dibaca.'); });
            }, 'image/jpeg', 0.82);
        };
        img.onerror = function () { URL.revokeObjectURL(url); cb(null, 'Gambar tidak dapat dibaca.'); };
        img.src = url;
    }

    function submit(event) {
        event.preventDefault();
        if (!uploadFor) return;
        var amount = String(document.getElementById('billAmount').value).replace(/[^\d]/g, '');
        var file = document.getElementById('billFile').files[0];
        if (!amount || Number(amount) <= 0) { setError('Isi nominal pembayaran.'); return; }
        if (Number(amount) > uploadFor.remaining) { setError('Nominal melebihi sisa tagihan (' + rp(uploadFor.remaining) + ').'); return; }
        if (!file) { setError('Pilih berkas bukti pembayaran.'); return; }
        var button = document.getElementById('billSubmit');
        button.disabled = true;
        prepareFile(file, function (data, problem) {
            if (!data) { button.disabled = false; setError(problem); return; }
            var result = ELIPS.api.call('payment.submit', {
                invoiceId: uploadFor.id,
                amount: amount,
                paidDate: document.getElementById('billDate').value,
                method: document.getElementById('billMethod').value,
                note: document.getElementById('billNote').value.trim(),
                proof: { data: data }
            });
            button.disabled = false;
            if (!result.ok) { setError(result.message || 'Bukti gagal dikirim.'); return; }
            closeModal();
            ELIPS.toast(result.message);
            load();
        });
    }

    function load() {
        var container = document.getElementById('billingSection');
        if (!container) return;
        var result = ELIPS.api.call('payment.mine', {});
        if (!result.ok) { container.innerHTML = ''; return; }
        state = result.data;
        render(container);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('billingSection');
        if (!container) return;
        container.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-upload]');
            if (btn) openModal(btn.getAttribute('data-upload'));
        });
        load();
    });
})(window.ELIPS);