// Direktori: assets/js/pages/admin/notifications.js
// ELIPS LMS - skrip halaman admin/notifications.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
function roleLabel(role) {
    if (role === 'admin') return 'Admin';
    if (role === 'instruktur') return 'Tentor';
    if (role === 'peserta') return 'Peserta';
    return 'Sistem';
}

function formatDateTime(value) {
    const date = new Date(value);
    if (isNaN(date.getTime())) return { time: '-', date: '-' };
    return {
        time: date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
        date: date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    };
}

function localDateKey(value) {
    const date = value ? new Date(value) : new Date();
    if (isNaN(date.getTime())) return '';
    return date.getFullYear() + '-' +
        String(date.getMonth() + 1).padStart(2, '0') + '-' +
        String(date.getDate()).padStart(2, '0');
}

function option(value, label) {
    const item = document.createElement('option');
    item.value = value;
    item.textContent = label;
    return item;
}

function populateSelects() {
    const typeSelect = document.getElementById('filterType');
    Object.keys(ELIPS.NOTIFICATION_TYPE_LABELS).forEach(function (type) {
        typeSelect.appendChild(option(type, ELIPS.NOTIFICATION_TYPE_LABELS[type]));
    });
    refreshRecipientOptions();
}

function refreshRecipientOptions() {
    const recipientSelect = document.getElementById('filterRecipient');
    const current = recipientSelect.value;
    recipientSelect.innerHTML = '';
    recipientSelect.appendChild(option('', 'Semua penerima'));
    ELIPS.getNotificationRecipients().forEach(function (recipient) {
        recipientSelect.appendChild(option(
            recipient.id === null ? recipient.email : String(recipient.id),
            recipient.name + ' — ' + recipient.email
        ));
    });
    recipientSelect.value = current;
    if (recipientSelect.value !== current) recipientSelect.value = '';
}

function readFilters() {
    return {
        search: document.getElementById('notificationSearch').value.trim(),
        type: document.getElementById('filterType').value,
        recipient: document.getElementById('filterRecipient').value,
        from: document.getElementById('filterFrom').value,
        to: document.getElementById('filterTo').value,
        limit: Number(document.getElementById('filterLimit').value) || 200
    };
}

function renderStats(allNotifications) {
    const todayKey = localDateKey();
    const recipients = {};
    let today = 0;

    allNotifications.forEach(function (item) {
        if (localDateKey(item.createdAt) === todayKey) today++;
        const key = item.recipientId === null || item.recipientId === undefined
            ? String(item.recipientEmail || '')
            : String(item.recipientId);
        if (key) recipients[key] = true;
    });

    document.getElementById('statNotifTotal').textContent = allNotifications.length;
    document.getElementById('statNotifToday').textContent = today;
    document.getElementById('statNotifRecipients').textContent = Object.keys(recipients).length;
}

function renderNotifications() {
    const tbody = document.getElementById('notificationTable');
    const filters = readFilters();
    const notifications = ELIPS.getNotifications(filters);
    const allNotifications = ELIPS.getNotifications();

    renderStats(allNotifications);
    refreshRecipientOptions();
    document.getElementById('notificationCount').textContent =
        'Menampilkan ' + notifications.length + ' dari ' + allNotifications.length + ' notifikasi';

    tbody.innerHTML = '';
    if (notifications.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Belum ada notifikasi. Notifikasi tercatat otomatis saat kursus dipublikasikan atau sertifikat disetujui Admin.</td></tr>';
        return;
    }

    notifications.forEach(function (item) {
        const when = formatDateTime(item.createdAt);
        const typeClass = item.type === 'certificate_published' ? 'badge-success' : 'badge-primary';
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td>',
            '  <div style="font-weight: 600;">' + ELIPS.escapeHtml(when.time) + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(when.date) + '</div>',
            '</td>',
            '<td>',
            '  <div style="font-weight: 500;">' + ELIPS.escapeHtml(item.recipientName || 'Penerima') + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(item.recipientEmail || '-') + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(roleLabel(item.recipientRole)) + '</div>',
            '</td>',
            '<td><span class="badge ' + typeClass + '">' + ELIPS.escapeHtml(ELIPS.notificationTypeLabel(item.type)) + '</span></td>',
            '<td>',
            '  <details>',
            '    <summary style="cursor: pointer; font-weight: 500;">' + ELIPS.escapeHtml(item.subject || '(tanpa subjek)') + '</summary>',
            '    <div style="white-space: pre-line; color: var(--text-secondary); margin-top: 0.5rem; font-size: 0.875rem;">' + ELIPS.escapeHtml(item.body || '-') + '</div>',
            '  </details>',
            '</td>',
            '<td><span class="badge badge-success">Terkirim (simulasi)</span></td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function resetFilters() {
    document.getElementById('notificationFilterForm').reset();
    document.getElementById('filterLimit').value = '200';
    renderNotifications();
}

function csvCell(value) {
    return '"' + String(value === undefined || value === null ? '' : value).replace(/"/g, '""') + '"';
}

function exportCsv() {
    const notifications = ELIPS.getNotifications(readFilters());
    if (notifications.length === 0) {
        ELIPS.toast('Tidak ada data notifikasi untuk diekspor.');
        return;
    }

    const rows = [['Waktu', 'Penerima', 'Email', 'Role', 'Jenis', 'Subjek', 'Isi']];
    notifications.forEach(function (item) {
        rows.push([
            item.createdAt,
            item.recipientName,
            item.recipientEmail,
            roleLabel(item.recipientRole),
            ELIPS.notificationTypeLabel(item.type),
            item.subject,
            item.body
        ]);
    });

    const csv = '\ufeff' + rows.map(function (row) {
        return row.map(csvCell).join(',');
    }).join('\r\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'elips-notifikasi-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    ELIPS.toast(notifications.length + ' notifikasi diekspor.');
}

function clearOutbox() {
    if (!confirm('Bersihkan seluruh outbox notifikasi? Tindakan ini tidak dapat dibatalkan.')) return;
    const result = ELIPS.clearNotifications();
    ELIPS.toast(result.message);
    renderNotifications();
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    populateSelects();
    renderNotifications();

    document.getElementById('notificationFilterForm').addEventListener('submit', function (event) {
        event.preventDefault();
        renderNotifications();
    });
    document.getElementById('resetNotificationFilter').addEventListener('click', resetFilters);
    document.getElementById('filterLimit').addEventListener('change', renderNotifications);
    document.getElementById('exportNotifications').addEventListener('click', exportCsv);
    document.getElementById('clearNotifications').addEventListener('click', clearOutbox);
});