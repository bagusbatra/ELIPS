// Direktori: assets/js/pages/admin/registrations.js
// ELIPS LMS - skrip halaman admin/registrations.php
// Pendaftaran publik: kirim link aktivasi (WhatsApp manual), kirim ulang, batalkan.
document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;

    let registrations = [];
    let currentDetail = null;
    let currentCancel = null;
    let currentLink = null;

    const STATUS_META = {
        baru: { label: 'Baru', className: 'reg-badge-baru' },
        dihubungi: { label: 'Dihubungi', className: 'badge-primary' },
        menunggu: { label: 'Menunggu aktivasi', className: 'reg-badge-menunggu' },
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
            classId: row.classId ? Number(row.classId) : null,
            className: row.className || '',
            amountDue: Number(row.amountDue) || 0,
            activationSentAt: row.activationSentAt || null,
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

        registrations = [];
        ELIPS.toast('Mode server wajib untuk menu ini.');
    }

    function findById(id) {
        return registrations.find(function (row) {
            return row.id === Number(id);
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
            action = '<span class="table-course-meta">Akun aktif · siswa #' + esc(row.studentId || '-') + '</span>';
        } else if (row.status === 'batal') {
            action = '<span class="table-course-meta">Dibatalkan</span>';
        } else if (row.status === 'menunggu') {
            action = '<button class="btn btn-primary" type="button" onclick="sendRegActivation(' + row.id + ')">'
                + '<i class="bi bi-arrow-repeat"></i> Kirim ulang</button>'
                + '<button class="btn btn-ghost" type="button" onclick="openRegCancelModal(' + row.id + ')">'
                + '<i class="bi bi-x-circle"></i> Batalkan</button>';
        } else {
            action = '<button class="btn btn-primary" type="button" onclick="sendRegActivation(' + row.id + ')">'
                + '<i class="bi bi-send"></i> Kirim link aktivasi</button>'
                + '<button class="btn btn-ghost" type="button" onclick="openRegCancelModal(' + row.id + ')">'
                + '<i class="bi bi-x-circle"></i> Batalkan</button>';
        }

        return '<tr>'
            + '<td>'
            + '<strong>' + esc(row.name) + '</strong>'
            + '<div class="table-course-meta">' + esc(row.email) + '</div>'
            + '<div class="table-course-meta">' + esc(row.phone) + '</div>'
            + '</td>'
            + '<td>' + esc(row.courseTitle || ('Pelatihan #' + row.courseId))
            + '<div class="table-course-meta">' + esc(row.className ? 'Kelas: ' + row.className : 'Kelas belum dipilih') + '</div></td>'
            + '<td>' + esc(row.birthPlace || '-')
            + '<div class="table-course-meta">' + esc(ELIPS.formatDate(row.birthDate)) + '</div></td>'
            + '<td style="max-width: 260px;">' + esc(row.address || '-') + '</td>'
            + '<td>' + statusBadge(row.status)
            + (row.status === 'menunggu' && row.activationSentAt
                ? '<div class="table-course-meta">Link dikirim ' + esc(ELIPS.formatDate(row.activationSentAt)) + '</div>' : '')
            + '</td>'
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
        html += detailItem('Kelas', row.className || 'Belum dipilih');
        html += detailItem('Tagihan', row.amountDue > 0 ? 'Rp ' + row.amountDue.toLocaleString('id-ID') : 'Gratis');
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

    function copyText(text, done) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        const input = document.getElementById('regLinkInput');
        input.focus();
        input.select();
        try { document.execCommand('copy'); } catch (err) { /* abaikan */ }
        done();
    }

    function showLinkModal(row, data) {
        currentLink = data;
        const hasLink = !!data.link;
        document.getElementById('regLinkTitle').textContent = hasLink ? 'Link Aktivasi' : 'Siswa Sudah Punya Akun';
        document.getElementById('regLinkSubtitle').textContent = row.name + ' — ' + (row.courseTitle || '');
        const info = document.getElementById('regLinkInfo');
        if (hasLink) {
            info.innerHTML = 'Kirim link ini ke WhatsApp <strong>' + esc(row.phone) + '</strong>. '
                + 'Berlaku 7 hari dan hanya bisa dipakai sekali. Kursi kelas <strong>' + esc(row.className) + '</strong> ditahan untuk siswa ini.';
        } else {
            info.innerHTML = 'Email ini sudah memiliki akun aktif, jadi tidak ada akun baru. Siswa sudah didaftarkan ke kelas '
                + '<strong>' + esc(row.className) + '</strong> dan tagihannya dibuat. Beri tahu siswa lewat WhatsApp.';
        }
        document.getElementById('regLinkGroup').hidden = !hasLink;
        document.getElementById('regLinkInput').value = data.link || '';
        const wa = document.getElementById('regLinkWa');
        wa.href = data.waUrl || '#';
        wa.hidden = !data.waUrl;
        document.getElementById('regLinkCopy').hidden = !hasLink;
        showModal('regLinkModal');
    }

    window.sendRegActivation = function (id) {
        const row = findById(id);
        if (!row) return;
        if (row.status === 'menunggu'
            && !window.confirm('Buat link aktivasi baru? Link lama tidak akan berlaku lagi.')) {
            return;
        }
        const result = ELIPS.api.call('registration.sendActivation', { id: row.id });
        if (!result.ok) {
            ELIPS.toast(result.message || 'Gagal membuat link aktivasi.');
            return;
        }
        refreshRegistrations();
        showLinkModal(row, result.data || {});
    };

    window.closeRegLinkModal = function () {
        hideModal('regLinkModal');
        document.getElementById('regLinkInput').value = '';
        currentLink = null;
    };

    document.getElementById('regLinkCopy').addEventListener('click', function () {
        if (!currentLink || !currentLink.link) return;
        copyText(currentLink.link, function () { ELIPS.toast('Link disalin.'); });
    });

    window.openRegCancelModal = function (id) {
        const row = findById(id);
        if (!row) return;
        currentCancel = row;
        document.getElementById('regCancelInfo').innerHTML = 'Batalkan pendaftaran <strong>' + esc(row.name)
            + '</strong> untuk ' + esc(row.courseTitle) + '?';
        document.getElementById('regCancelReason').value = '';
        const error = document.getElementById('regCancelError');
        error.hidden = true;
        showModal('regCancelModal');
        document.getElementById('regCancelReason').focus();
    };

    window.closeRegCancelModal = function () {
        hideModal('regCancelModal');
        currentCancel = null;
    };

    document.getElementById('regCancelForm').addEventListener('submit', function (event) {
        event.preventDefault();
        if (!currentCancel) return;
        const reason = document.getElementById('regCancelReason').value.trim();
        const error = document.getElementById('regCancelError');
        const result = ELIPS.api.call('registration.cancel', { id: currentCancel.id, reason: reason });
        if (!result.ok) {
            error.textContent = result.message || 'Gagal membatalkan pendaftaran.';
            error.hidden = false;
            return;
        }
        closeRegCancelModal();
        ELIPS.toast(result.message);
        refreshRegistrations();
    });

    ['regDetailModal', 'regLinkModal', 'regCancelModal'].forEach(function (id) {
        const modal = document.getElementById(id);
        modal.addEventListener('click', function (event) {
            if (event.target !== modal) return;
            if (id === 'regDetailModal') closeRegDetailModal();
            else if (id === 'regLinkModal') closeRegLinkModal();
            else closeRegCancelModal();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (document.getElementById('regDetailModal').style.display === 'flex') closeRegDetailModal();
        if (document.getElementById('regLinkModal').style.display === 'flex') closeRegLinkModal();
        if (document.getElementById('regCancelModal').style.display === 'flex') closeRegCancelModal();
    });

    refreshRegistrations();
});