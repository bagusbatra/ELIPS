// ELIPS LMS - skrip halaman admin/audit.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
const ACTION_BADGE = {
    create: 'badge-success',
    publish: 'badge-success',
    approve: 'badge-success',
    update: 'badge-primary',
    grade: 'badge-primary',
    submit: 'badge-primary',
    assign: 'badge-primary',
    status: 'badge-warning',
    delete: 'badge-warning',
    unpublish: 'badge-warning'
};

function roleLabel(role) {
    if (role === 'admin') return 'Admin';
    if (role === 'instruktur') return 'Instruktur';
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

function option(value, label) {
    const item = document.createElement('option');
    item.value = value;
    item.textContent = label;
    return item;
}

function populateSelects() {
    const actionSelect = document.getElementById('filterAction');
    Object.keys(ELIPS.AUDIT_ACTION_LABELS).forEach(function (action) {
        actionSelect.appendChild(option(action, ELIPS.AUDIT_ACTION_LABELS[action]));
    });

    const entitySelect = document.getElementById('filterEntity');
    Object.keys(ELIPS.AUDIT_ENTITY_LABELS).forEach(function (entity) {
        entitySelect.appendChild(option(entity, ELIPS.AUDIT_ENTITY_LABELS[entity]));
    });

    const actorSelect = document.getElementById('filterActor');
    ELIPS.getAuditActors().forEach(function (actor) {
        actorSelect.appendChild(option(
            actor.id === null ? 'system' : String(actor.id),
            actor.name + ' (' + roleLabel(actor.role) + ')'
        ));
    });
}

function refreshActorOptions() {
    const actorSelect = document.getElementById('filterActor');
    const current = actorSelect.value;
    actorSelect.innerHTML = '';
    actorSelect.appendChild(option('', 'Semua aktor'));
    ELIPS.getAuditActors().forEach(function (actor) {
        actorSelect.appendChild(option(
            actor.id === null ? 'system' : String(actor.id),
            actor.name + ' (' + roleLabel(actor.role) + ')'
        ));
    });
    actorSelect.value = current;
    if (actorSelect.value !== current) actorSelect.value = '';
}

function readFilters() {
    return {
        search: document.getElementById('auditSearch').value.trim(),
        action: document.getElementById('filterAction').value,
        entity: document.getElementById('filterEntity').value,
        actor: document.getElementById('filterActor').value,
        from: document.getElementById('filterFrom').value,
        to: document.getElementById('filterTo').value,
        limit: Number(document.getElementById('filterLimit').value) || 200
    };
}

function localDateKey(value) {
    const date = value ? new Date(value) : new Date();
    if (isNaN(date.getTime())) return '';
    return date.getFullYear() + '-' +
        String(date.getMonth() + 1).padStart(2, '0') + '-' +
        String(date.getDate()).padStart(2, '0');
}

function renderStats(logs) {
    const todayKey = localDateKey();
    const actors = {};
    let today = 0;

    logs.forEach(function (log) {
        if (localDateKey(log.createdAt) === todayKey) today++;
        actors[(log.actorId === null || log.actorId === undefined ? 'system' : log.actorId)] = true;
    });

    document.getElementById('statAuditTotal').textContent = logs.length;
    document.getElementById('statAuditToday').textContent = today;
    document.getElementById('statAuditActors').textContent = Object.keys(actors).length;
}

function renderAudit() {
    const tbody = document.getElementById('auditTable');
    const filters = readFilters();
    const logs = ELIPS.getAuditLogs(filters);
    const allLogs = ELIPS.getAuditLogs();

    renderStats(allLogs);
    document.getElementById('auditCount').textContent = 'Menampilkan ' + logs.length + ' dari ' + allLogs.length + ' aktivitas';

    tbody.innerHTML = '';
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Belum ada aktivitas yang cocok dengan filter.</td></tr>';
        return;
    }

    logs.forEach(function (log) {
        const when = formatDateTime(log.createdAt);
        const actionClass = ACTION_BADGE[log.action] || 'badge-primary';
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td>',
            '  <div style="font-weight: 600;">' + ELIPS.escapeHtml(when.time) + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(when.date) + '</div>',
            '</td>',
            '<td>',
            '  <div style="font-weight: 500;">' + ELIPS.escapeHtml(log.actorName || 'Sistem') + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(roleLabel(log.actorRole)) + '</div>',
            '</td>',
            '<td><span class="badge ' + actionClass + '">' + ELIPS.escapeHtml(ELIPS.auditActionLabel(log.action)) + '</span></td>',
            '<td>',
            '  <div style="font-weight: 500;">' + ELIPS.escapeHtml(ELIPS.auditEntityLabel(log.entity)) + '</div>',
            '  <div class="table-course-meta">' + ELIPS.escapeHtml(log.entityLabel || ('#' + (log.entityId || '-'))) + '</div>',
            '</td>',
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(log.detail || '-') + '</span></td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function resetFilters() {
    document.getElementById('auditFilterForm').reset();
    document.getElementById('filterLimit').value = '200';
    renderAudit();
}

function csvCell(value) {
    return '"' + String(value === undefined || value === null ? '' : value).replace(/"/g, '""') + '"';
}

function exportCsv() {
    const logs = ELIPS.getAuditLogs(readFilters());
    if (logs.length === 0) {
        ELIPS.toast('Tidak ada data audit untuk diekspor.');
        return;
    }

    const rows = [['Waktu', 'Aktor', 'Role', 'Aksi', 'Entitas', 'Objek', 'Detail']];
    logs.forEach(function (log) {
        rows.push([
            log.createdAt,
            log.actorName,
            roleLabel(log.actorRole),
            ELIPS.auditActionLabel(log.action),
            ELIPS.auditEntityLabel(log.entity),
            log.entityLabel,
            log.detail
        ]);
    });

    const csv = '\ufeff' + rows.map(function (row) {
        return row.map(csvCell).join(',');
    }).join('\r\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'elips-audit-trail-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    ELIPS.toast(logs.length + ' entri audit diekspor.');
}

function clearLogs() {
    if (!confirm('Bersihkan seluruh audit trail? Tindakan ini tidak dapat dibatalkan.')) return;
    const result = ELIPS.clearAuditLogs();
    ELIPS.toast(result.message);
    refreshActorOptions();
    renderAudit();
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    populateSelects();
    renderAudit();

    document.getElementById('auditFilterForm').addEventListener('submit', function (event) {
        event.preventDefault();
        renderAudit();
    });
    document.getElementById('resetAuditFilter').addEventListener('click', resetFilters);
    document.getElementById('filterLimit').addEventListener('change', renderAudit);
    document.getElementById('exportAudit').addEventListener('click', exportCsv);
    document.getElementById('clearAudit').addEventListener('click', clearLogs);
});

