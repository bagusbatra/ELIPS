// ELIPS LMS - skrip halaman admin/registrations.php
// Menampilkan pendaftaran publik dan mengonversinya menjadi akun peserta.
document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;

    let registrations = [];
    let currentDetail = null;
    let currentConvert = null;

    const STATUS_META = {
        baru: { label: 'Baru', className: '' },
        dihubungi: { label: 'Dihubungi', className: 'badge-primary' },
        terdaftar: { label: 'Terdaftar', className: 'badge-success' },
        batal: { label: 'Batal', className: 'reg-badge-batal' }
    };

    function esc(value) {
        return ELIPS.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function normalize(row) {
        row = row || {};
        return {
            id: Number(row.id || row.registrationId) || 0,
            courseId: Number(row.courseId || row.course_id) || 0,
            courseTitle: row.courseTitle || row.course_title || '',
            name: row.name || row.nama || '',
            birthPlace: row.birthPlace || row.tempatLahir || '',
            birthDate: row.birthDate || row.tanggalLahir || '',
            address: row.address || row.alamat || '',
            phone: row.phone || '',
            email: row.email || '',
            status: row.status || 'baru',
            studentId: row.studentId ? Number(row.studentId) : null,
            studentName: row.studentName || '',
            convertedAt: row.convertedAt || null,
            notes: row.notes || '',
            createdAt: row.createdAt || row.created_at || ''
        };
    }

    function loadRegistrations() {
        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('course.registration.list', {});
            if (!result.ok) {
                registrations = [];
                ELIPS.toast(result.message || 'Gagal memuat data pendaftaran.');
                return;
            }
            registrations = (result.data || []).map(normalize);
            return;
        }

        registrations = ELIPS.getCourseRegistrations().map(normalize);
    }

    function findById(id) {
        return registrations.find(function (row) {
            return row.id === Number(id);
        }) || null;
    }

    function findExistingStudent(email) {
        const target = String(email || '').toLowerCase();
        if (!target) return null;
        return ELIPS.getUsers().find(function (user) {
            return String(user.email || '').toLowerCase() === target && user.role === 'peserta';
        }) || null;
    }

    function statusBadge(status) {
        const meta = STATUS_META[status] || STATUS_META.baru;
        return '<span class="badge ' + meta.className + '">' + esc(meta.label) + '</span>';
    }

    function detailItem(label, value, full) {
        return '<div class="reg-detail-item' + (full ? ' is-full' : '') + '">'
            + '<span class="reg-detail-label">' + esc(label) + '</span>'
            + '<div class="reg-detail-value">' + esc(value || '-') + '</div>'
            + '</div>';
    }

    function updateStats() {
        const courses = {};
        let baru = 0;
        let terdaftar = 0;

        registrations.forEach(function (row) {
            const key = row.courseId || row.courseTitle || '0';
            courses[key] = true;
            if (row.status === 'baru') baru += 1;
            if (row.status === 'terdaftar') terdaftar += 1;
        });

        document.getElementById('regStatTotal').textContent = registrations.length;
        document.getElementById('regStatBaru').textContent = baru;
        document.getElementById('regStatTerdaftar').textContent = terdaftar;
        document.getElementById('regStatKursus').textContent = Object.keys(courses).length;
    }

    function populateCourseFilter() {
        const select = document.getElementById('filterRegCourse');
        if (!select) return;

        const previous = select.value;
        const seen = {};
        const options = [];

        registrations.forEach(function (row) {
            const key = String(row.courseId || row.courseTitle || '0');
            if (seen[key]) return;
            seen[key] = true;
            options.push({
                id: key,
                title: row.courseTitle || ('Pelatihan #' + key)
            });
        });

        let html = '<option value="all">Semua Pelatihan</option>';
        options.forEach(function (option) {
            html += '<option value="' + esc(option.id) + '">' + esc(option.title) + '</option>';
        });
        select.innerHTML = html;

        const stillExists = options.some(function (option) { return option.id === previous; });
        select.value = stillExists ? previous : 'all';
    }

    function filteredRegistrations() {
        const courseFilter = document.getElementById('filterRegCourse').value;
        const statusFilter = document.getElementById('filterRegStatus').value;
        const search = document.getElementById('searchRegistrations').value.trim().toLowerCase();

        return registrations.filter(function (row) {
            if (courseFilter !== 'all' && String(row.courseId || row.courseTitle || '0') !== courseFilter) {
                return false;
            }
            if (statusFilter !== 'all' && row.status !== statusFilter) {
                return false;
            }
            if (!search) return true;

            return [row.name, row.email, row.phone, row.courseTitle]
                .join(' ')
                .toLowerCase()
                .indexOf(search) !== -1;
        });
    }

    function rowHtml(row) {
        let action = '';
        if (row.status === 'terdaftar') {
            action = '<div style="display: flex; flex-direction: column; gap: 0.35rem;">'
                + '<span class="table-course-meta">Siswa #' + esc(row.studentId || '-') + '</span>'
                + '<a class="btn btn-ghost" href="enrollment.php"><i class="bi bi-person-check"></i> Penugasan</a>'
                + '</div>';
        } else if (row.status === 'batal') {
            action = '<span class="table-course-meta">Dibatalkan</span>';
        } else {
            action = '<button class="btn btn-primary" type="button" onclick="openRegConvertModal(' + row.id + ')">'
                + '<i class="bi bi-person-plus"></i> Jadikan Siswa'
                + '</button>';
        }

        return '<tr>'
            + '<td>'
            + '<strong>' + esc(row.name) + '</strong>'
            + '<div class="table-course-meta">' + esc(row.email) + '</div>'
            + '<div class="table-course-meta">' + esc(row.phone) + '</div>'
            + '</td>'
            + '<td>' + esc(row.courseTitle || ('Pelatihan #' + row.courseId))
            + '<div class="table-course-meta">ID #' + esc(row.courseId || '-') + '</div></td>'
            + '<td>' + esc(row.birthPlace || '-')
            + '<div class="table-course-meta">' + esc(ELIPS.formatDate(row.birthDate)) + '</div></td>'
            + '<td style="max-width: 260px;">' + esc(row.address || '-') + '</td>'
            + '<td>' + statusBadge(row.status) + '</td>'
            + '<td>' + esc(ELIPS.formatDate(row.createdAt)) + '</td>'
            + '<td>'
            + '<div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">'
            + '<button class="btn btn-ghost" type="button" onclick="openRegDetail(' + row.id + ')">'
            + '<i class="bi bi-eye"></i> Detail'
            + '</button>'
            + action
            + '</div>'
            + '</td>'
            + '</tr>';
    }

    function renderRegistrations() {
        updateStats();
        populateCourseFilter();

        const tbody = document.getElementById('registrationsTable');
        const rows = filteredRegistrations();

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">'
                + 'Belum ada pendaftaran yang cocok.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(rowHtml).join('');
    }

    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'flex';
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }

    function showConvertError(message) {
        const error = document.getElementById('regConvertError');
        if (!error) return;
        error.textContent = message || 'Gagal memproses pendaftaran.';
        error.hidden = false;
    }

    function clearConvertError() {
        const error = document.getElementById('regConvertError');
        if (!error) return;
        error.textContent = '';
        error.hidden = true;
    }

    function convertLocally(registration, password) {
        const existing = findExistingStudent(registration.email);
        let studentId = existing ? existing.id : null;

        if (!existing) {
            const created = ELIPS.createUser({
                name: registration.name,
                email: registration.email,
                phone: registration.phone,
                password: password,
                role: 'peserta',
                status: 'aktif'
            });
            if (!created.ok) return created;
            studentId = created.data.id;
        }

        const list = ELIPS.getCourseRegistrations();
        let updated = false;
        list.forEach(function (item) {
            if (Number(item.id) === Number(registration.id)) {
                item.status = 'terdaftar';
                item.studentId = studentId;
                item.convertedAt = new Date().toISOString();
                updated = true;
            }
        });
        if (!updated) {
            list.push(Object.assign({}, registration, {
                status: 'terdaftar',
                studentId: studentId,
                convertedAt: new Date().toISOString()
            }));
        }
        ELIPS.setCourseRegistrations(list);

        return {
            ok: true,
            message: existing
                ? 'Pendaftaran berhasil dihubungkan ke akun peserta.'
                : 'Pendaftaran berhasil menjadi akun peserta.'
        };
    }

    window.refreshRegistrations = function () {
        loadRegistrations();
        renderRegistrations();
    };

    window.renderRegistrations = renderRegistrations;

    window.openRegDetail = function (id) {
        const row = findById(id);
        if (!row) return;
        currentDetail = row;

        document.getElementById('regDetailTitle').textContent = row.name;
        document.getElementById('regDetailSubtitle').textContent = row.courseTitle || '-';

        const status = STATUS_META[row.status] || STATUS_META.baru;
        let html = '<div class="reg-detail-grid">';
        html += detailItem('Status', status.label);
        html += detailItem('Pelatihan', row.courseTitle || ('Pelatihan #' + row.courseId));
        html += detailItem('Nama', row.name);
        html += detailItem('No Telpon/WhatsApp', row.phone);
        html += detailItem('Tempat Lahir', row.birthPlace);
        html += detailItem('Tanggal Lahir', ELIPS.formatDate(row.birthDate));
        html += detailItem('Email Aktif', row.email);
        html += detailItem('Dikirim', ELIPS.formatDate(row.createdAt));
        html += detailItem('Alamat Lengkap', row.address, true);
        if (row.studentId) {
            html += detailItem('Akun Siswa', (row.studentName || 'Siswa') + ' (#' + row.studentId + ')', true);
        }
        html += '</div>';

        document.getElementById('regDetailBody').innerHTML = html;
        showModal('regDetailModal');
    };

    window.closeRegDetailModal = function () {
        hideModal('regDetailModal');
        currentDetail = null;
    };

    window.openRegConvertModal = function (id) {
        const row = findById(id);
        if (!row) return;
        currentConvert = row;

        const existing = findExistingStudent(row.email);
        const info = document.getElementById('regConvertInfo');
        const group = document.getElementById('regPasswordGroup');
        const password = document.getElementById('regConvertPassword');

        document.getElementById('regConvertId').value = row.id;
        clearConvertError();
        password.value = '';

        if (existing) {
            info.innerHTML = 'Email <strong>' + esc(row.email) + '</strong> sudah digunakan oleh siswa '
                + '<strong>' + esc(existing.name) + '</strong>. Pendaftaran akan dihubungkan ke akun tersebut.';
            group.hidden = true;
            password.required = false;
        } else {
            info.innerHTML = 'Akun baru dengan role <strong>peserta</strong> akan dibuat untuk '
                + '<strong>' + esc(row.name) + '</strong> (' + esc(row.email) + ').';
            group.hidden = false;
            password.required = true;
        }

        showModal('regConvertModal');
        if (!existing) password.focus();
    };

    window.closeRegConvertModal = function () {
        hideModal('regConvertModal');
        currentConvert = null;
        clearConvertError();
    };

    const convertForm = document.getElementById('regConvertForm');
    convertForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!currentConvert) return;
        clearConvertError();

        const existing = findExistingStudent(currentConvert.email);
        const payload = { id: currentConvert.id };

        if (!existing) {
            const password = document.getElementById('regConvertPassword').value;
            if (password.length < 8) {
                showConvertError('Password awal minimal 8 karakter.');
                return;
            }
            payload.password = password;
        }

        let result;
        if (ELIPS.api && ELIPS.api.enabled) {
            result = ELIPS.api.call('course.registration.convert', payload);
        } else {
            result = convertLocally(currentConvert, payload.password);
        }

        if (!result.ok) {
            showConvertError(result.message);
            return;
        }

        closeRegConvertModal();
        ELIPS.toast(result.message);
        refreshRegistrations();
    });

    ['regDetailModal', 'regConvertModal'].forEach(function (id) {
        const modal = document.getElementById(id);
        modal.addEventListener('click', function (event) {
            if (event.target !== modal) return;
            if (id === 'regDetailModal') closeRegDetailModal();
            else closeRegConvertModal();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (document.getElementById('regDetailModal').style.display === 'flex') closeRegDetailModal();
        if (document.getElementById('regConvertModal').style.display === 'flex') closeRegConvertModal();
    });

    refreshRegistrations();
});
