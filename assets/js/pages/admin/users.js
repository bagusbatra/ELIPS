// Direktori: assets/js/pages/admin/users.js
// ELIPS LMS - skrip halaman admin/users.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let editingUserId = null;
let assigningInstructorId = null;
let passwordRequests = {};
let currentResetRequest = null;

function loadPasswordRequests() {
    passwordRequests = {};
    if (!(ELIPS.api && ELIPS.api.enabled)) return;
    const result = ELIPS.api.call('password.requests', {});
    if (!result.ok) return;
    (result.data || []).forEach(function (item) { passwordRequests[item.userId] = item; });
}

function userStatusBadge(status) {
    if (status === 'aktif') return '<span class="badge badge-success">Aktif</span>';
    if (status === 'menunggu') return '<span class="badge" style="background: #fef9c3; color: #854d0e;">Menunggu aktivasi</span>';
    return '<span class="badge badge-warning">Nonaktif</span>';
}

function roleBadge(role) {
    if (role === 'admin') return '<span class="badge" style="background: #fee2e2; color: #991b1b;">Admin</span>';
    if (role === 'instruktur') return '<span class="badge badge-warning">Instruktur</span>';
    if (role === 'karyawan') return '<span class="badge" style="background: #e0e7ff; color: #3730a3;">Karyawan</span>';
    return '<span class="badge badge-primary">Peserta</span>';
}

function userRoleLabel(role) {
    if (role === 'admin') return 'Admin';
    if (role === 'instruktur') return 'Instruktur';
    if (role === 'karyawan') return 'Karyawan';
    return 'Peserta';
}

function openUserTypeModal() {
    document.getElementById('userTypeModal').style.display = 'flex';
}

function closeUserTypeModal() {
    document.getElementById('userTypeModal').style.display = 'none';
}

function chooseUserRole(role) {
    if (['admin', 'instruktur', 'peserta', 'karyawan'].indexOf(role) === -1) role = 'peserta';
    closeUserTypeModal();
    openUserModal(null, role);
}

function renderUsers() {
    const tbody = document.getElementById('usersTableBody');
    const users = ELIPS.getUsers();
    const courses = ELIPS.getCourses();
    loadPasswordRequests();
    tbody.innerHTML = '';

    users.forEach(function (user) {
        const assignedCourses = courses.filter(function (c) { return c.instructorId === user.id; });
        const tr = document.createElement('tr');
        tr.setAttribute('data-role', user.role);
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">' + ELIPS.escapeHtml((user.name || 'U').substring(0, 2).toUpperCase()) + '</div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(user.name) + '</div>',
            user.role === 'instruktur' ? '      <div class="table-course-meta">' + assignedCourses.length + ' kursus diampu</div>' : '',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml(user.email) + '</span></td>',
            '<td>' + roleBadge(user.role) + '</td>',
            '<td>' + (user.role === 'karyawan' && user.division
                ? '<span class="badge" style="background: #ecfccb; color: #3f6212;">' + ELIPS.escapeHtml(ELIPS.divisionLabel(user.division)) + '</span>'
                : '<span style="color: var(--text-secondary);">-</span>') + '</td>',
            '<td>' + userStatusBadge(user.status) + '</td>',
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(user.createdAt || '-') + '</span></td>',
            '<td>',
            '  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">',
            user.role === 'instruktur' ? '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Tugaskan ke kursus" onclick="openAssignModal(' + user.id + ')"><i class="bi bi-journal-check"></i></button>' : '',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" onclick="openUserModal(' + user.id + ')"><i class="bi bi-pencil"></i></button>',
            passwordRequests[user.id] ? '    <button class="btn btn-primary" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Siswa meminta reset sandi" onclick="openResetModal(' + user.id + ')"><i class="bi bi-key"></i> Reset Sandi</button>' : '',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Reset perangkat" onclick="resetUserDevice(' + user.id + ')"><i class="bi bi-phone"></i></button>',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" onclick="deleteUser(' + user.id + ')"><i class="bi bi-trash"></i></button>',
            '  </div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function openUserModal(userId, presetRole) {
    editingUserId = userId || null;
    const title = document.getElementById('userModalTitle');
    document.getElementById('userPassword').value = '';

    if (editingUserId) {
        const user = ELIPS.getUserById(editingUserId);
        title.textContent = 'Edit Pengguna';
        document.getElementById('userName').value = user.name || '';
        document.getElementById('userEmail').value = user.email || '';
        document.getElementById('userPhone').value = user.phone || '';
        document.getElementById('userRole').value = user.role || 'peserta';
        document.getElementById('userDivision').value = user.division || '';
        document.getElementById('userStatus').value = user.status || 'aktif';
    } else {
        const role = presetRole || 'peserta';
        title.textContent = 'Tambah ' + userRoleLabel(role);
        document.getElementById('userName').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userPhone').value = '';
        document.getElementById('userRole').value = role;
        document.getElementById('userDivision').value = '';
        document.getElementById('userStatus').value = 'aktif';
    }
    syncDivisionField();
    document.getElementById('userModal').style.display = 'flex';
}

function syncDivisionField() {
    const role = document.getElementById('userRole').value;
    const group = document.getElementById('divisionGroup');
    if (!group) return;
    const show = role === 'karyawan';
    group.style.display = show ? '' : 'none';
    if (!show) document.getElementById('userDivision').value = '';
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
    editingUserId = null;
}

function saveUser() {
    const name = document.getElementById('userName').value.trim();
    const email = document.getElementById('userEmail').value.trim().toLowerCase();
    const phone = document.getElementById('userPhone').value.trim();
    const role = document.getElementById('userRole').value;
    const division = role === 'karyawan' ? document.getElementById('userDivision').value : '';
    const status = document.getElementById('userStatus').value;
    const password = document.getElementById('userPassword').value;

    if (!editingUserId && !password) {
        ELIPS.toast('Password wajib diisi untuk pengguna baru.');
        return;
    }
    if (password && password.length < 8) {
        ELIPS.toast('Password minimal 8 karakter.');
        return;
    }

    const payload = {
        name: name,
        email: email,
        phone: phone,
        role: role,
        division: division,
        status: status,
        password: password || undefined
    };
    const result = editingUserId
        ? ELIPS.updateUser(editingUserId, payload)
        : ELIPS.createUser(payload);

    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    ELIPS.toast(result.message);
    closeUserModal();
    renderUsers();
}

function deleteUser(id) {
    const user = ELIPS.getUserById(id);
    if (!user) return;
    if (!confirm('Hapus pengguna "' + user.name + '"? Data penugasan terkait juga akan dihapus.')) return;

    const result = ELIPS.deleteUser(id);
    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    ELIPS.toast(result.message);
    renderUsers();
}

function resetUserDevice(id) {
    const user = ELIPS.getUserById(id);
    if (!user) return;
    if (!confirm('Reset perangkat untuk "' + user.name + '"? Pengguna harus masuk kembali di perangkatnya.')) return;

    const result = ELIPS.resetUserDevice(id);
    ELIPS.toast(result.message);
}

function openAssignModal(instructorId) {
    assigningInstructorId = instructorId;
    const instructor = ELIPS.getUserById(instructorId);
    document.getElementById('assignInstructorName').textContent = instructor ? instructor.name : '-';
    const courses = ELIPS.getCourses();
    const container = document.getElementById('assignCourseList');

    if (courses.length === 0) {
        container.innerHTML = '<span style="color: var(--text-secondary);">Belum ada kursus.</span>';
    } else {
        container.innerHTML = courses.map(function (course) {
            const checked = course.instructorId === instructorId ? 'checked' : '';
            const owner = course.instructorId && course.instructorId !== instructorId
                ? '<small style="color: var(--warning);"> (saat ini: ' + ELIPS.escapeHtml(course.instructorName || '-') + ')</small>'
                : '';
            return '<label style="display: flex; align-items: flex-start; gap: 0.5rem;">' +
                '<input type="checkbox" value="' + course.id + '" ' + checked + ' style="margin-top: 0.25rem;">' +
                '<span>' + ELIPS.escapeHtml(course.title) + ' <span class="badge ' + (course.status === 'published' ? 'badge-success' : 'badge-warning') + '" style="font-size: 0.6875rem;">' + (course.status === 'published' ? 'Published' : 'Draft') + '</span>' + owner + '</span>' +
                '</label>';
        }).join('');
    }
    document.getElementById('assignModal').style.display = 'flex';
}

function closeAssignModal() {
    document.getElementById('assignModal').style.display = 'none';
    assigningInstructorId = null;
}

function saveAssignment() {
    if (!assigningInstructorId) return;
    const selectedIds = Array.from(document.querySelectorAll('#assignCourseList input[type="checkbox"]:checked'))
        .map(function (input) { return parseInt(input.value, 10); });

    const result = ELIPS.assignInstructorToCourses(assigningInstructorId, selectedIds);
    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    ELIPS.toast(result.message);
    closeAssignModal();
    renderUsers();
}

function filterUsers(role) {
    const rows = document.querySelectorAll('#usersTableBody tr');
    const buttons = document.querySelectorAll('#filterAll, #filterAdmin, #filterInstruktur, #filterPeserta, #filterKaryawan');
    buttons.forEach(function (btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-ghost');
    });

    const activeBtn = role === 'all' ? 'filterAll' : 'filter' + role.charAt(0).toUpperCase() + role.slice(1);
    const el = document.getElementById(activeBtn);
    if (el) {
        el.classList.remove('btn-ghost');
        el.classList.add('btn-primary');
    }

    rows.forEach(function (row) {
        row.style.display = role === 'all' || row.getAttribute('data-role') === role ? '' : 'none';
    });
}

function searchUsers() {
    const input = document.getElementById('searchUser').value.toLowerCase();
    document.querySelectorAll('#usersTableBody tr').forEach(function (row) {
        const nameEl = row.querySelector('.table-course-title');
        const name = nameEl ? nameEl.textContent.toLowerCase() : '';
        const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        row.style.display = (name.includes(input) || email.includes(input)) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    renderUsers();
});



// ---------- Reset sandi (hanya untuk siswa yang menekan "Lupa sandi") ----------

function openResetModal(userId) {
    const request = passwordRequests[userId];
    if (!request) {
        ELIPS.toast('Tidak ada permintaan reset sandi untuk pengguna ini.');
        return;
    }
    currentResetRequest = request;
    document.getElementById('resetModalInfo').innerHTML = '<strong>' + ELIPS.escapeHtml(request.name) + '</strong> ('
        + ELIPS.escapeHtml(request.email) + ') meminta reset sandi pada ' + ELIPS.escapeHtml(ELIPS.formatDate(request.requestedAt)) + '.';
    document.getElementById('resetLinkGroup').hidden = true;
    document.getElementById('resetLinkInput').value = '';
    document.getElementById('resetIssueBtn').hidden = false;
    document.getElementById('resetCopyBtn').hidden = true;
    const wa = document.getElementById('resetWaBtn');
    wa.hidden = true;
    wa.href = '#';
    document.getElementById('resetModal').style.display = 'flex';
}

function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
    document.getElementById('resetLinkInput').value = '';
    currentResetRequest = null;
    renderUsers();
}

function issueResetLink() {
    if (!currentResetRequest) return;
    const result = ELIPS.api.call('password.reset.issue', { requestId: currentResetRequest.id });
    if (!result.ok) {
        ELIPS.toast(result.message || 'Gagal membuat link reset.');
        return;
    }
    const data = result.data || {};
    document.getElementById('resetLinkGroup').hidden = false;
    document.getElementById('resetLinkInput').value = data.link || '';
    document.getElementById('resetIssueBtn').hidden = true;
    document.getElementById('resetCopyBtn').hidden = false;
    const wa = document.getElementById('resetWaBtn');
    wa.hidden = !data.waUrl;
    wa.href = data.waUrl || '#';
    ELIPS.toast('Link reset dibuat. Kirim lewat WhatsApp.');
}

function copyResetLink() {
    const input = document.getElementById('resetLinkInput');
    if (!input.value) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(function () { ELIPS.toast('Link disalin.'); });
    } else {
        input.select();
        try { document.execCommand('copy'); } catch (err) { /* abaikan */ }
        ELIPS.toast('Link disalin.');
    }
}

function dismissResetRequest() {
    if (!currentResetRequest) return;
    if (!window.confirm('Tutup permintaan reset sandi ini tanpa mengirim link?')) return;
    const result = ELIPS.api.call('password.request.dismiss', { requestId: currentResetRequest.id });
    if (!result.ok) {
        ELIPS.toast(result.message || 'Gagal menutup permintaan.');
        return;
    }
    ELIPS.toast('Permintaan ditutup.');
    closeResetModal();
}