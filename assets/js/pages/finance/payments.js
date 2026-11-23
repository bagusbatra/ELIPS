// Direktori: assets/js/pages/finance/payments.js
// ELIPS LMS - skrip halaman Pembayaran (admin/payments.php & employee/keuangan/payments.php)
// Verifikasi bukti, catat pembayaran sebagian, "Lunas Pembayaran", batalkan (void).
document.addEventListener('DOMContentLoaded', function () {
    var user = ELIPS.getSessionUser();
    if (!user || !(user.role === 'admin' || (user.role === 'karyawan' && user.division === 'keuangan'))) return;

    var $ = function (id) { return document.getElementById(id); };
    var invoices = [];
    var current = null;     // tagihan yang sedang dibuka
    var action = null;      // { type, payment }
    var base = (typeof window.ELIPS_BASE === 'string' ? window.ELIPS_BASE : '');

    function esc(v) { return ELIPS.escapeHtml(v === null || v === undefined ? '' : String(v)); }
    function rp(n) { return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID'); }
    function parseAmount(text) { return String(text || '').replace(/[^\d]/g, ''); }

    var STATUS = {
        unpaid: ['Belum bayar', 'fin-badge-unpaid'],
        partial: ['Sebagian', 'fin-badge-partial'],
        paid: ['Lunas', 'fin-badge-paid']
    };
    var PAY_STATUS = {
        pending: ['Menunggu verifikasi', 'fin-badge-pending'],
        verified: ['Terverifikasi', 'fin-badge-verified'],
        rejected: ['Ditolak', 'fin-badge-rejected'],
        void: ['Dibatalkan', 'fin-badge-void']
    };

    function badge(map, key) {
        var m = map[key] || [key, ''];
        return '<span class="fin-badge ' + m[1] + '">' + esc(m[0]) + '</span>';
    }

    function show(id) { $(id).style.display = 'flex'; }
    function hide(id) { $(id).style.display = 'none'; }
    function setError(id, message) {
        var el = $(id);
        el.textContent = message || '';
        el.hidden = !message;
    }

    // ---------- Daftar ----------

    function load() {
        var result = ELIPS.api.call('payment.invoices', {
            status: $('finFilterStatus').value,
            q: $('finSearch').value.trim()
        });
        if (!result.ok) {
            ELIPS.toast(result.message || 'Gagal memuat tagihan.');
            invoices = [];
        } else {
            invoices = result.data || [];
        }
        render();
        loadStats();
    }

    // Statistik memakai seluruh tagihan, tidak terpengaruh filter tabel.
    function loadStats() {
        var all = ELIPS.api.call('payment.invoices', { status: 'all' });
        if (!all.ok) return;
        var pending = 0, open = 0, paid = 0, remaining = 0;
        (all.data || []).forEach(function (i) {
            pending += i.pendingCount;
            if (i.status === 'paid') paid += 1;
            else { open += 1; remaining += i.remaining; }
        });
        $('finStatPending').textContent = pending;
        $('finStatOpen').textContent = open;
        $('finStatRemaining').textContent = rp(remaining);
        $('finStatPaid').textContent = paid;
    }

    function render() {
        var tbody = $('finTable');
        if (!invoices.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="fin-empty">Tidak ada tagihan yang cocok.</td></tr>';
            return;
        }
        tbody.innerHTML = invoices.map(function (i) {
            return '<tr>'
                + '<td><strong>' + esc(i.studentName) + '</strong><div class="table-course-meta">' + esc(i.studentEmail) + '</div>'
                + '<div class="table-course-meta">' + esc(i.studentPhone) + '</div></td>'
                + '<td>' + esc(i.courseTitle) + '<div class="table-course-meta">' + esc(i.className) + '</div></td>'
                + '<td>' + rp(i.amountDue) + '</td>'
                + '<td>' + rp(i.amountPaid) + '</td>'
                + '<td>' + rp(i.remaining) + '</td>'
                + '<td>' + badge(STATUS, i.status)
                + (i.pendingCount > 0 ? '<div class="table-course-meta">' + i.pendingCount + ' bukti menunggu</div>' : '') + '</td>'
                + '<td>' + (i.studentNumber ? '<span class="fin-number">' + esc(i.studentNumber) + '</span>' : '-') + '</td>'
                + '<td><button class="btn btn-primary" type="button" data-open="' + i.id + '"><i class="bi bi-clipboard-check"></i> '
                + (i.status === 'paid' ? 'Detail' : 'Verifikasi pembayaran') + '</button></td>'
                + '</tr>';
        }).join('');
    }

    // ---------- Detail tagihan ----------

    function openInvoice(id) {
        var result = ELIPS.api.call('payment.invoice', { invoiceId: Number(id) });
        if (!result.ok) {
            ELIPS.toast(result.message || 'Tagihan tidak ditemukan.');
            return;
        }
        current = result.data;
        renderDetail();
        show('finModal');
    }

    function renderDetail() {
        var i = current;
        setError('finError', '');
        $('finModalTitle').textContent = i.studentName;
        $('finModalSubtitle').textContent = i.courseTitle + ' — ' + i.className;
        $('finSummary').innerHTML =
            '<div><span>Tagihan</span><strong>' + rp(i.amountDue) + '</strong></div>'
            + '<div><span>Terbayar</span><strong>' + rp(i.amountPaid) + '</strong></div>'
            + '<div><span>Sisa</span><strong>' + rp(i.remaining) + '</strong></div>'
            + '<div><span>Status</span><strong>' + badge(STATUS, i.status) + '</strong></div>'
            + '<div><span>No. Siswa</span><strong class="fin-number">' + esc(i.studentNumber || '-') + '</strong></div>';

        var methods = i.methods || ['transfer', 'tunai', 'e-wallet', 'lainnya'];
        $('finMethod').innerHTML = methods.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join('');
        $('finDate').value = new Date().toISOString().slice(0, 10);
        $('finAmount').value = '';
        $('finNote').value = '';
        $('finRecordWrap').hidden = i.remaining <= 0;

        var list = i.payments || [];
        $('finPayments').innerHTML = list.length ? list.map(paymentHtml).join('') : '<div class="fin-empty">Belum ada pembayaran.</div>';
    }

    function paymentHtml(p) {
        var meta = [p.method, p.paidDate ? ELIPS.formatDate(p.paidDate) : '', p.source === 'student' ? 'dikirim siswa' : 'dicatat petugas'];
        var lines = '<div class="fin-pay-meta">' + esc(meta.filter(Boolean).join(' · ')) + '</div>';
        if (p.note) lines += '<div class="fin-pay-meta">Catatan: ' + esc(p.note) + '</div>';
        if (p.status === 'rejected') lines += '<div class="fin-pay-meta">Alasan ditolak: ' + esc(p.rejectReason) + '</div>';
        if (p.status === 'void') lines += '<div class="fin-pay-meta">Alasan dibatalkan: ' + esc(p.voidReason) + '</div>';
        if (p.verifiedBy && (p.status === 'verified' || p.status === 'rejected')) lines += '<div class="fin-pay-meta">Oleh: ' + esc(p.verifiedBy) + '</div>';

        var buttons = '';
        if (p.hasProof) {
            buttons += '<a class="btn btn-ghost" target="_blank" rel="noopener noreferrer" href="' + base + '/api/index.php?action=payment.proof&id=' + p.id + '"><i class="bi bi-image"></i> Lihat bukti</a>';
        }
        if (p.status === 'pending') {
            buttons += '<button class="btn btn-primary" type="button" data-approve="' + p.id + '"><i class="bi bi-check-lg"></i> Setujui</button>'
                + '<button class="btn btn-outline" type="button" data-reject="' + p.id + '"><i class="bi bi-x-lg"></i> Tolak</button>';
        }
        if (p.status === 'verified') {
            buttons += '<button class="btn btn-ghost" type="button" data-void="' + p.id + '"><i class="bi bi-slash-circle"></i> Batalkan</button>';
        }
        return '<div class="fin-pay"><div class="fin-pay-head"><strong>' + rp(p.amount) + '</strong>' + badge(PAY_STATUS, p.status) + '</div>'
            + lines + (buttons ? '<div class="fin-pay-actions">' + buttons + '</div>' : '') + '</div>';
    }

    function applyInvoiceResult(result, okMessage) {
        if (!result.ok) {
            setError('finError', result.message || 'Gagal memproses.');
            return false;
        }
        current = result.data;
        renderDetail();
        ELIPS.toast(okMessage || result.message);
        load();
        return true;
    }

    // ---------- Catat langsung / lunas ----------

    $('finRecordBtn').addEventListener('click', function () {
        var amount = parseAmount($('finAmount').value);
        if (!amount) { setError('finError', 'Isi nominal pembayaran.'); return; }
        applyInvoiceResult(ELIPS.api.call('payment.record', {
            invoiceId: current.id, amount: amount, method: $('finMethod').value,
            paidDate: $('finDate').value, note: $('finNote').value.trim()
        }));
    });

    $('finPaidBtn').addEventListener('click', function () {
        if (!window.confirm('Catat sisa tagihan ' + rp(current.remaining) + ' sebagai LUNAS? Nomor siswa akan diterbitkan.')) return;
        applyInvoiceResult(ELIPS.api.call('payment.markPaid', {
            invoiceId: current.id, method: $('finMethod').value, paidDate: $('finDate').value, note: $('finNote').value.trim()
        }));
    });

    // ---------- Setujui / tolak / batalkan ----------

    function findPayment(id) {
        return (current.payments || []).filter(function (p) { return p.id === Number(id); })[0] || null;
    }

    function openAction(type, payment) {
        action = { type: type, payment: payment };
        setError('finActionError', '');
        $('finActionAmountGroup').hidden = type !== 'approve';
        $('finActionReasonGroup').hidden = type === 'approve';
        $('finActionReason').value = '';
        $('finActionAmount').value = type === 'approve' ? String(Math.round(payment.amount)) : '';
        var titles = { approve: 'Setujui Pembayaran', reject: 'Tolak Pembayaran', void: 'Batalkan Pembayaran' };
        $('finActionTitle').textContent = titles[type];
        $('finActionSubtitle').textContent = rp(payment.amount) + ' — ' + current.studentName;
        $('finActionReasonLabel').textContent = type === 'reject' ? 'Alasan penolakan (wajib)' : 'Alasan pembatalan (wajib)';
        $('finActionSubmit').textContent = type === 'approve' ? 'Setujui' : (type === 'reject' ? 'Tolak' : 'Batalkan pembayaran');
        show('finActionModal');
    }

    function closeAction() { hide('finActionModal'); action = null; }

    $('finActionForm').addEventListener('submit', function (event) {
        event.preventDefault();
        if (!action) return;
        var p = action.payment;
        var result;
        if (action.type === 'approve') {
            var amount = parseAmount($('finActionAmount').value);
            if (!amount) { setError('finActionError', 'Isi nominal yang disetujui.'); return; }
            result = ELIPS.api.call('payment.verify', { paymentId: p.id, decision: 'approve', amount: amount });
        } else if (action.type === 'reject') {
            result = ELIPS.api.call('payment.verify', { paymentId: p.id, decision: 'reject', reason: $('finActionReason').value.trim() });
        } else {
            result = ELIPS.api.call('payment.void', { paymentId: p.id, reason: $('finActionReason').value.trim() });
        }
        if (!result.ok) {
            setError('finActionError', result.message || 'Gagal memproses.');
            return;
        }
        closeAction();
        applyInvoiceResult(result);
    });

    $('finPayments').addEventListener('click', function (event) {
        var btn = event.target.closest('button');
        if (!btn) return;
        var type = btn.hasAttribute('data-approve') ? 'approve' : btn.hasAttribute('data-reject') ? 'reject' : btn.hasAttribute('data-void') ? 'void' : '';
        if (!type) return;
        var payment = findPayment(btn.getAttribute('data-' + type));
        if (payment) openAction(type, payment);
    });

    $('finActionClose').addEventListener('click', closeAction);
    $('finActionCancel').addEventListener('click', closeAction);
    $('finModalClose').addEventListener('click', function () { hide('finModal'); current = null; });

    // ---------- Rekening (admin) ----------

    if (user.role === 'admin') {
        $('finBankBtn').hidden = false;
        $('finBankBtn').addEventListener('click', function () {
            var result = ELIPS.api.call('payment.settings.get', {});
            var s = result.ok ? result.data : {};
            $('finBankName').value = s.bankName || '';
            $('finBankNumber').value = s.accountNumber || '';
            $('finBankHolder').value = s.accountHolder || '';
            $('finBankNote').value = s.note || '';
            setError('finBankError', '');
            show('finBankModal');
        });
        $('finBankForm').addEventListener('submit', function (event) {
            event.preventDefault();
            var result = ELIPS.api.call('payment.settings.save', {
                bankName: $('finBankName').value, accountNumber: $('finBankNumber').value,
                accountHolder: $('finBankHolder').value, note: $('finBankNote').value
            });
            if (!result.ok) { setError('finBankError', result.message); return; }
            hide('finBankModal');
            ELIPS.toast(result.message);
        });
        $('finBankClose').addEventListener('click', function () { hide('finBankModal'); });
        $('finBankCancel').addEventListener('click', function () { hide('finBankModal'); });
    }

    // ---------- Pemicu ----------

    $('finTable').addEventListener('click', function (event) {
        var btn = event.target.closest('[data-open]');
        if (btn) openInvoice(btn.getAttribute('data-open'));
    });
    $('finRefreshBtn').addEventListener('click', load);
    $('finFilterStatus').addEventListener('change', load);
    var timer = null;
    $('finSearch').addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(load, 300);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if ($('finActionModal').style.display === 'flex') closeAction();
        else if ($('finModal').style.display === 'flex') { hide('finModal'); current = null; }
    });

    load();
});