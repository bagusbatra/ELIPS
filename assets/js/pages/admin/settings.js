// Direktori: assets/js/pages/admin/settings.js
// ELIPS LMS - skrip halaman admin/settings.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let editingBranchId = null;

function currentActorId() {
    const user = ELIPS.getCurrentUser();
    return user ? user.id : null;
}

function branchClassCount(id) {
    return ELIPS.getClasses().filter(function (c) {
        return Number(c.branchId) === Number(id);
    }).length;
}

function branchStatusBadge(status) {
    if (status === 'active') return '<span class="badge badge-success">Aktif</span>';
    return '<span class="badge" style="background:#e5e7eb;color:#374151;">Nonaktif</span>';
}

function renderBranches() {
    const tbody = document.getElementById('branchTable');
    const branches = ELIPS.getBranches().slice().sort(function (a, b) {
        return Number(a.id) - Number(b.id);
    });

    if (branches.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Belum ada cabang.</td></tr>';
        return;
    }

    tbody.innerHTML = branches.map(function (item) {
        const nextStatus = item.status === 'active' ? 'inactive' : 'active';
        const statusLabel = nextStatus === 'active' ? 'Aktifkan' : 'Nonaktifkan';
        const statusIcon = nextStatus === 'active' ? 'bi-toggle-on' : 'bi-toggle-off';
        return '<tr>' +
            '<td><div class="table-course-title">' + ELIPS.escapeHtml(item.name) + '</div><div class="table-course-meta">' + ELIPS.escapeHtml(item.code) + '</div></td>' +
            '<td>' + ELIPS.escapeHtml(item.city || '-') + '</td>' +
            '<td>' + ELIPS.escapeHtml(item.manager || '-') + '</td>' +
            '<td>' + ELIPS.escapeHtml(item.phone || '-') + '</td>' +
            '<td>' + branchClassCount(item.id) + '</td>' +
            '<td>' + branchStatusBadge(item.status) + '</td>' +
            '<td><div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Edit cabang" onclick="openBranchModal(' + item.id + ')"><i class="bi bi-pencil"></i></button>' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="' + statusLabel + '" onclick="changeBranchStatus(' + item.id + ', \'' + nextStatus + '\')"><i class="bi ' + statusIcon + '"></i></button>' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" title="Hapus cabang" onclick="removeBranch(' + item.id + ')"><i class="bi bi-trash"></i></button>' +
            '</div></td>' +
            '</tr>';
    }).join('');
}

function openBranchModal(id) {
    editingBranchId = id ? Number(id) : null;
    const item = id ? ELIPS.getBranchById(id) : null;
    document.getElementById('branchModalTitle').textContent = item ? 'Edit Cabang' : 'Tambah Cabang';
    document.getElementById('branchId').value = item ? item.id : '';
    document.getElementById('branchCode').value = item ? item.code : '';
    document.getElementById('branchName').value = item ? item.name : '';
    document.getElementById('branchCity').value = item ? item.city : '';
    document.getElementById('branchAddress').value = item ? item.address : '';
    document.getElementById('branchPhone').value = item ? item.phone : '';
    document.getElementById('branchManager').value = item ? item.manager : '';
    document.getElementById('branchStatus').value = item ? item.status : 'active';
    document.getElementById('branchNotes').value = item ? item.notes : '';
    document.getElementById('branchModal').style.display = 'flex';
}

function closeBranchModal() {
    document.getElementById('branchModal').style.display = 'none';
}

function saveBranch() {
    const data = {
        name: document.getElementById('branchName').value.trim(),
        code: document.getElementById('branchCode').value.trim(),
        city: document.getElementById('branchCity').value.trim(),
        address: document.getElementById('branchAddress').value.trim(),
        phone: document.getElementById('branchPhone').value.trim(),
        manager: document.getElementById('branchManager').value.trim(),
        status: document.getElementById('branchStatus').value,
        notes: document.getElementById('branchNotes').value.trim(),
        actorId: currentActorId()
    };
    if (!data.name) {
        alert('Nama cabang wajib diisi.');
        return;
    }
    const result = editingBranchId
        ? ELIPS.updateBranch(editingBranchId, data)
        : ELIPS.createBranch(data);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    closeBranchModal();
    renderBranches();
    ELIPS.toast(result.message);
}

function changeBranchStatus(id, status) {
    const result = ELIPS.setBranchStatus(id, status);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    renderBranches();
    ELIPS.toast(result.message);
}

function removeBranch(id) {
    if (!confirm('Hapus cabang ini? Cabang yang masih dipakai kelas tidak dapat dihapus.')) return;
    const result = ELIPS.deleteBranch(id);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    renderBranches();
    ELIPS.toast(result.message);
}

// ---------- Predikat nilai (Phase 6) ----------
function predicateRowHtml(range, index) {
    const item = range || {};
    return [
        '<tr data-predicate-row>',
        '<td><input type="text" class="form-input" data-field="letter" maxlength="4" style="width:70px;" value="' + ELIPS.escapeHtml(item.letter || '') + '"></td>',
        '<td><input type="text" class="form-input" data-field="label" value="' + ELIPS.escapeHtml(item.label || '') + '" placeholder="Label predikat"></td>',
        '<td><input type="number" class="form-input" data-field="minScore" min="0" max="100" step="0.01" style="width:110px;" value="' + (item.minScore === undefined ? '' : Number(item.minScore)) + '" oninput="updatePredicateCoverage()"></td>',
        '<td><input type="number" class="form-input" data-field="maxScore" min="0" max="100" step="0.01" style="width:110px;" value="' + (item.maxScore === undefined ? '' : Number(item.maxScore)) + '" oninput="updatePredicateCoverage()"></td>',
        '<td style="text-align:center;"><input type="checkbox" data-field="isActive"' + (item.isActive === false ? '' : ' checked') + ' onchange="updatePredicateCoverage()"></td>',
        '<td><button type="button" class="btn btn-ghost" style="padding:0.25rem 0.5rem; color: var(--danger);" title="Hapus rentang" onclick="removePredicateRow(this)"><i class="bi bi-trash"></i></button></td>',
        '</tr>'
    ].join('');
}

function renderPredicates() {
    const body = document.getElementById('predicateTableBody');
    if (!body) return;
    const ranges = ELIPS.getPredicateRanges().slice().sort(function (a, b) {
        return Number(b.minScore) - Number(a.minScore);
    });
    body.innerHTML = ranges.map(predicateRowHtml).join('') ||
        '<tr><td colspan="6" style="text-align:center; color: var(--text-secondary);">Belum ada rentang predikat.</td></tr>';
    updatePredicateCoverage();
}

function addPredicateRow() {
    const body = document.getElementById('predicateTableBody');
    if (!body) return;
    const empty = body.querySelector('td[colspan]');
    if (empty) body.innerHTML = '';
    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = predicateRowHtml({ letter: '', label: '', minScore: '', maxScore: '', isActive: true });
    body.appendChild(wrapper.firstElementChild);
    updatePredicateCoverage();
}

function removePredicateRow(button) {
    const row = button.closest('[data-predicate-row]');
    if (row) row.parentNode.removeChild(row);
    updatePredicateCoverage();
}

function readPredicateForm() {
    const rows = [];
    document.querySelectorAll('#predicateTableBody [data-predicate-row]').forEach(function (row, index) {
        rows.push({
            letter: row.querySelector('[data-field="letter"]').value.trim(),
            label: row.querySelector('[data-field="label"]').value.trim(),
            minScore: row.querySelector('[data-field="minScore"]').value === '' ? null : Number(row.querySelector('[data-field="minScore"]').value),
            maxScore: row.querySelector('[data-field="maxScore"]').value === '' ? null : Number(row.querySelector('[data-field="maxScore"]').value),
            sortOrder: index + 1,
            isActive: row.querySelector('[data-field="isActive"]').checked
        });
    });
    return rows;
}

function updatePredicateCoverage() {
    const target = document.getElementById('predicateCoverage');
    if (!target) return;
    const rows = readPredicateForm().filter(function (row) { return row.isActive; });
    if (!rows.length) {
        target.textContent = 'Tidak ada rentang aktif.';
        target.style.color = 'var(--danger)';
        return;
    }
    rows.sort(function (a, b) { return Number(a.minScore) - Number(b.minScore); });
    let covered = 0;
    let gap = false;
    let overlap = false;
    rows.forEach(function (row, index) {
        if (row.minScore === null || row.maxScore === null || isNaN(row.minScore) || isNaN(row.maxScore)) {
            gap = true;
            return;
        }
        if (index === 0) {
            covered = row.maxScore;
            if (row.minScore > 0) gap = true;
            return;
        }
        if (row.minScore <= rows[index - 1].maxScore) overlap = true;
        else if (Math.round((row.minScore - rows[index - 1].maxScore) * 100) / 100 > 0.01) gap = true;
        covered = Math.max(covered, row.maxScore);
    });
    const complete = !gap && !overlap && covered >= 100;
    target.textContent = complete
        ? 'Rentang predikat menutup skala 0-100 tanpa celah.'
        : (overlap ? 'Rentang tumpang tindih.' : (gap ? 'Masih ada celah atau nilai kosong pada rentang.' : 'Rentang belum mencapai skor 100.'));
    target.style.color = complete ? 'var(--success)' : 'var(--danger)';
}

function savePredicates() {
    const result = ELIPS.savePredicateRanges({
        ranges: readPredicateForm(),
        actorId: currentActorId()
    });
    ELIPS.toast(result.message);
    if (result.ok) renderPredicates();
}

function resetPredicateRanges() {
    if (!window.confirm('Kembalikan rentang predikat ke default A-E?')) return;
    const defaults = ELIPS.defaultPredicateRanges();
    const result = ELIPS.savePredicateRanges({ ranges: defaults, actorId: currentActorId() });
    ELIPS.toast(result.message);
    if (result.ok) renderPredicates();
}

// ---------- FUNGSI SEMENTARA: reset seluruh data (api/maintenance.php) ----------
var RESET_PHRASE = 'HAPUS SEMUA DATA';

// Buang sisa data demo lama di browser (localStorage "elips_*") agar tidak
// tampil kembali bila aplikasi sempat jatuh ke mode lokal.
function clearStaleLocalData() {
    try {
        Object.keys(localStorage).forEach(function (key) {
            if (key.indexOf('elips_') === 0 && key !== 'elips_session') {
                localStorage.removeItem(key);
            }
        });
    } catch (err) { /* abaikan */ }
}

function initResetData() {
    var modal = document.getElementById('resetDataModal');
    if (!modal) return;
    var phrase = document.getElementById('resetPhrase');
    var password = document.getElementById('resetPassword');
    var confirmBtn = document.getElementById('resetDataConfirm');
    var error = document.getElementById('resetDataError');

    function showError(message) {
        error.textContent = message || '';
        error.hidden = !message;
    }
    function refresh() {
        confirmBtn.disabled = phrase.value.trim() !== RESET_PHRASE || password.value === '';
    }
    function open() {
        phrase.value = '';
        password.value = '';
        document.getElementById('resetKeepSettings').checked = false;
        showError('');
        refresh();
        modal.hidden = false;
        password.focus();
    }
    function close() { modal.hidden = true; }

    document.getElementById('resetDataOpen').addEventListener('click', open);
    document.getElementById('resetDataCancel').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
    phrase.addEventListener('input', refresh);
    password.addEventListener('input', refresh);

    confirmBtn.addEventListener('click', function () {
        showError('');
        confirmBtn.disabled = true;
        var result = ELIPS.api.call('system.resetData', {
            password: password.value,
            confirm: phrase.value.trim(),
            keepSettings: document.getElementById('resetKeepSettings').checked
        });
        if (!result.ok) {
            showError(result.message);
            refresh();
            return;
        }
        close();
        ELIPS.toast(result.message);
        clearStaleLocalData();
        setTimeout(function () { window.location.reload(); }, 1200);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    ELIPS.requireRole('admin');
    renderBranches();
    renderPredicates();
    initResetData();
});