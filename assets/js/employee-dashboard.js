// ============================================================
// ELIPS LMS - employee-dashboard.js
// Dashboard karyawan: status absensi hari ini, rekap bulan berjalan,
// riwayat terbaru, dan area Marketing (lead, follow-up, target) untuk
// karyawan divisi Marketing. Mendukung mode server dan localStorage.
// ============================================================

(function (ELIPS) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const MARKETING_STATUSES = [
        ['baru', 'Baru'],
        ['dihubungi', 'Dihubungi'],
        ['prospek', 'Prospek'],
        ['terdaftar', 'Terdaftar'],
        ['batal', 'Batal']
    ];

    const marketingState = {
        data: null,
        loading: false
    };

    function el(id) {
        return document.getElementById(id);
    }

    function setText(id, value) {
        const node = el(id);
        if (node) node.textContent = String(value);
    }

    function formatDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value || '-';
        return Number(parts[2]) + ' ' + (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function formatTime(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatRupiah(value) {
        const number = Number(value || 0);
        return 'Rp' + Math.round(number).toLocaleString('id-ID');
    }

    function todayString() {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return now.getFullYear() + '-' + month + '-' + day;
    }

    function currentPeriod() {
        return todayString().slice(0, 7);
    }

    function greetingForNow() {
        const hour = new Date().getHours();
        if (hour < 11) return 'Selamat pagi';
        if (hour < 15) return 'Selamat siang';
        if (hour < 19) return 'Selamat sore';
        return 'Selamat malam';
    }

    function escapeHtml(value) {
        return ELIPS.escapeHtml(value === undefined || value === null ? '' : value);
    }

    function showToast(message) {
        if (typeof ELIPS.toast === 'function') ELIPS.toast(message);
    }

    function loadRecords(user) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('staffAttendance.list', { limit: 100 });
            if (result && result.ok && Array.isArray(result.data)) {
                return { records: result.data, error: '' };
            }
            return {
                records: [],
                error: (result && result.message) || 'Riwayat absensi gagal dimuat.'
            };
        }
        return { records: ELIPS.getStaffAttendancesByUser(user.id), error: '' };
    }

    function findKind(records, date, kind) {
        return records.find(function (record) {
            return record.attendanceDate === date && record.kind === kind;
        }) || null;
    }

    function renderRecent(records, error) {
        const tbody = document.getElementById('recentAttendanceBody');
        if (!tbody) return;

        if (error) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">' +
                ELIPS.escapeHtml(error) + '</td></tr>';
            return;
        }

        const recent = records.slice(0, 5);
        if (recent.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem 1rem; color: var(--text-secondary);">' +
                '<i class="bi bi-calendar-x" style="display: block; font-size: 2rem; opacity: .45; margin-bottom: .5rem;"></i>' +
                'Belum ada catatan absensi. Mulai dengan absen masuk hari ini.</td></tr>';
            return;
        }

        tbody.innerHTML = recent.map(function (record) {
            const isIn = record.kind === 'in';
            return '<tr>' +
                '<td>' + ELIPS.escapeHtml(formatDate(record.attendanceDate)) + '</td>' +
                '<td><span class="badge ' + (isIn ? 'badge-success' : 'badge-warning') + '">' +
                    '<i class="bi ' + (isIn ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right') + '"></i> ' +
                    (isIn ? 'Masuk' : 'Pulang') + '</span></td>' +
                '<td>' + ELIPS.escapeHtml(formatTime(record.takenAt)) + '</td>' +
                '<td>' + (record.locationLabel
                    ? ELIPS.escapeHtml(record.locationLabel)
                    : '<span style="color: var(--text-secondary);">-</span>') + '</td>' +
                '</tr>';
        }).join('');
    }

    function render(user) {
        const today = todayString();
        const monthPrefix = today.slice(0, 7);
        const loaded = loadRecords(user);
        const records = loaded.records || [];

        const todayIn = findKind(records, today, 'in');
        const todayOut = findKind(records, today, 'out');
        const monthRecords = records.filter(function (record) {
            return String(record.attendanceDate || '').slice(0, 7) === monthPrefix;
        });
        const monthDays = new Set(monthRecords.map(function (record) {
            return record.attendanceDate;
        })).size;

        const statToday = document.getElementById('statToday');
        const statTodayLabel = document.getElementById('statTodayLabel');
        if (statToday) {
            statToday.textContent = todayIn && todayOut
                ? 'Lengkap'
                : (todayIn ? 'Sudah Masuk' : (todayOut ? 'Sudah Pulang' : 'Belum Absen'));
        }
        if (statTodayLabel) {
            const parts = [];
            if (todayIn) parts.push('Masuk ' + formatTime(todayIn.takenAt));
            if (todayOut) parts.push('Pulang ' + formatTime(todayOut.takenAt));
            statTodayLabel.textContent = parts.length > 0 ? parts.join(' • ') : 'Absen masuk belum tercatat';
        }

        const statMonthDays = document.getElementById('statMonthDays');
        if (statMonthDays) statMonthDays.textContent = String(monthDays);

        const statMonthRecords = document.getElementById('statMonthRecords');
        if (statMonthRecords) statMonthRecords.textContent = String(monthRecords.length);

        renderRecent(records, loaded.error);
    }

    // ---------- Marketing ----------

    function statusLabel(status) {
        for (let i = 0; i < MARKETING_STATUSES.length; i++) {
            if (MARKETING_STATUSES[i][0] === status) return MARKETING_STATUSES[i][1];
        }
        return status || '-';
    }

    function sourceLabel(source) {
        return typeof ELIPS.marketingSourceLabel === 'function'
            ? ELIPS.marketingSourceLabel(source)
            : source;
    }

    function statusBadge(status) {
        const className = {
            baru: 'badge badge-primary',
            dihubungi: 'badge badge-warning',
            prospek: 'badge marketing-badge-prospect',
            terdaftar: 'badge badge-success',
            batal: 'badge marketing-badge-canceled'
        }[status] || 'badge';
        return '<span class="' + className + '">' + escapeHtml(statusLabel(status)) + '</span>';
    }

    function whatsappUrl(phone) {
        let digits = String(phone || '').replace(/[^\d]/g, '');
        if (!digits) return '';
        if (digits.charAt(0) === '0') digits = '62' + digits.slice(1);
        return 'https://wa.me/' + digits;
    }

    function findLead(leadId) {
        if (!marketingState.data || !Array.isArray(marketingState.data.leads)) return null;
        return marketingState.data.leads.find(function (lead) {
            return Number(lead.id) === Number(leadId);
        }) || null;
    }

    function renderMarketingStats(data) {
        const stats = data.stats || {};
        const active = Number(stats.activeLeads || 0);
        const todayCount = Number(stats.todayFollowups || 0);
        const overdue = Number(stats.overdueFollowups || 0);
        const enrollments = Number(stats.monthEnrollments || 0);
        const enrollmentsTarget = Number(stats.enrollmentsTarget || 0);
        const revenue = Number(stats.monthRevenue || 0);
        const revenueTarget = Number(stats.revenueTarget || 0);

        setText('marketingStatActive', active);
        setText('marketingStatActiveHint', Number(stats.newLeads || 0) + ' baru • '
            + Number(stats.prospectLeads || 0) + ' prospek');
        setText('marketingStatFollowups', todayCount);
        setText('marketingStatFollowupsHint', overdue > 0
            ? overdue + ' follow-up terlambat'
            : 'Tidak ada jadwal terlambat');
        setText('marketingStatEnrollments', enrollments);
        setText('marketingStatEnrollmentsHint', enrollmentsTarget > 0
            ? 'Target ' + enrollmentsTarget + ' pendaftaran'
            : 'Target belum ditetapkan');

        const enrollmentPercent = enrollmentsTarget > 0
            ? Math.min(100, Math.round((enrollments / enrollmentsTarget) * 100))
            : 0;
        const enrollmentBar = el('marketingProgressEnrollment');
        if (enrollmentBar) enrollmentBar.style.width = enrollmentPercent + '%';
        setText('marketingProgressEnrollmentLabel', enrollments + ' / '
            + (enrollmentsTarget > 0 ? enrollmentsTarget : '—'));

        const revenuePercent = revenueTarget > 0
            ? Math.min(100, Math.round((revenue / revenueTarget) * 100))
            : 0;
        const revenueBar = el('marketingProgressRevenue');
        if (revenueBar) revenueBar.style.width = revenuePercent + '%';
        setText('marketingProgressRevenueLabel', formatRupiah(revenue) + ' / '
            + (revenueTarget > 0 ? formatRupiah(revenueTarget) : '—'));
    }

    function renderMarketingActions(data) {
        const container = el('marketingActionList');
        if (!container) return;

        const overdue = Array.isArray(data.overdueFollowups) ? data.overdueFollowups : [];
        const today = Array.isArray(data.todayFollowups) ? data.todayFollowups : [];
        const rows = overdue.concat(today);
        setText('marketingActionCount', rows.length + ' follow-up');

        if (rows.length === 0) {
            container.innerHTML = '<div class="marketing-action-empty">'
                + '<i class="bi bi-check2-circle" style="font-size: 2rem; display: block; opacity: .5; margin-bottom: .5rem;"></i>'
                + 'Tidak ada follow-up yang menunggu hari ini. Tambahkan jadwal pada lead agar muncul di sini.'
                + '</div>';
            return;
        }

        container.innerHTML = rows.map(function (lead) {
            const nextDate = lead.nextFollowupDate;
            const isOverdue = !!nextDate && nextDate < data.today;
            const wa = whatsappUrl(lead.phone);
            return '<div class="marketing-action-item' + (isOverdue ? ' overdue' : '') + '">'
                + '<div class="marketing-action-main">'
                + '<div class="marketing-action-name">' + escapeHtml(lead.name) + statusBadge(lead.status) + '</div>'
                + '<div class="marketing-action-meta">'
                + (lead.phone ? '<span><i class="bi bi-telephone"></i> ' + escapeHtml(lead.phone) + '</span>' : '')
                + (lead.interest ? '<span><i class="bi bi-book"></i> ' + escapeHtml(lead.interest) + '</span>' : '')
                + '<span class="' + (isOverdue ? 'overdue-text' : '') + '"><i class="bi bi-calendar-event"></i> '
                + (isOverdue ? 'Terlambat: ' : 'Hari ini: ') + escapeHtml(formatDate(nextDate)) + '</span>'
                + '</div></div>'
                + '<div class="marketing-action-buttons">'
                + '<button class="btn btn-primary" type="button" data-marketing-action="followup" data-lead-id="' + lead.id + '">'
                + '<i class="bi bi-chat-dots"></i> Follow-up</button>'
                + (wa ? '<a class="btn btn-outline" href="' + wa + '" target="_blank" rel="noopener">'
                    + '<i class="bi bi-whatsapp"></i> WhatsApp</a>' : '')
                + '</div></div>';
        }).join('');
    }

    function renderMarketingPipeline(data) {
        const container = el('marketingPipeline');
        if (!container) return;
        const stats = data.stats || {};
        const counts = {
            baru: Number(stats.newLeads || 0),
            dihubungi: Number(stats.contactedLeads || 0),
            prospek: Number(stats.prospectLeads || 0),
            terdaftar: Number(stats.registeredLeads || 0),
            batal: Number(stats.canceledLeads || 0)
        };
        container.innerHTML = MARKETING_STATUSES.map(function (pair) {
            const status = pair[0];
            let extra = '';
            if (status === 'prospek') extra = ' is-prospect';
            if (status === 'terdaftar') extra = ' is-registered';
            if (status === 'batal') extra = ' is-canceled';
            return '<div class="marketing-pipeline-item' + extra + '">'
                + '<div class="marketing-pipeline-value">' + counts[status] + '</div>'
                + '<div class="marketing-pipeline-label">' + escapeHtml(pair[1]) + '</div>'
                + '</div>';
        }).join('');
    }

    function renderMarketingLeads(data) {
        const tbody = el('marketingLeadBody');
        if (!tbody) return;
        const leads = Array.isArray(data.leads) ? data.leads : [];
        if (leads.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5"><div class="marketing-lead-empty">'
                + '<i class="bi bi-person-plus" style="font-size: 2rem; display: block; opacity: .5; margin-bottom: .5rem;"></i>'
                + 'Belum ada lead. Mulai dengan menambahkan calon peserta baru.'
                + '</div></td></tr>';
            return;
        }

        tbody.innerHTML = leads.map(function (lead) {
            const nextDate = lead.nextFollowupDate;
            const isOverdue = !!nextDate && nextDate < data.today
                && lead.status !== 'terdaftar' && lead.status !== 'batal';
            const contacts = [];
            if (lead.phone) contacts.push('<i class="bi bi-telephone"></i> ' + escapeHtml(lead.phone));
            if (lead.email) contacts.push('<i class="bi bi-envelope"></i> ' + escapeHtml(lead.email));
            return '<tr>'
                + '<td><div class="marketing-lead-name">' + escapeHtml(lead.name) + '</div>'
                + '<span class="marketing-lead-contact">' + (contacts.length ? contacts.join('<br>') : '-') + '</span></td>'
                + '<td>' + escapeHtml(sourceLabel(lead.source)) + '</td>'
                + '<td>' + statusBadge(lead.status) + '</td>'
                + '<td>' + (nextDate
                    ? '<span class="' + (isOverdue ? 'marketing-date-overdue' : '') + '">' + escapeHtml(formatDate(nextDate)) + '</span>'
                    : '<span style="color: var(--text-secondary);">-</span>') + '</td>'
                + '<td><div class="marketing-row-actions">'
                + '<button class="btn btn-ghost" type="button" title="Catat follow-up" data-marketing-action="followup" data-lead-id="' + lead.id + '">'
                + '<i class="bi bi-chat-dots"></i></button>'
                + '<button class="btn btn-ghost" type="button" title="Ubah lead" data-marketing-action="edit" data-lead-id="' + lead.id + '">'
                + '<i class="bi bi-pencil"></i></button>'
                + '<button class="btn btn-ghost" type="button" title="Hapus lead" data-marketing-action="delete" data-lead-id="' + lead.id + '">'
                + '<i class="bi bi-trash"></i></button>'
                + '</div></td></tr>';
        }).join('');
    }

    function renderMarketing(data) {
        marketingState.data = data || {};
        renderMarketingStats(marketingState.data);
        renderMarketingActions(marketingState.data);
        renderMarketingPipeline(marketingState.data);
        renderMarketingLeads(marketingState.data);
    }

    function renderMarketingError(message) {
        const text = escapeHtml(message || 'Data Marketing gagal dimuat.');
        const actionList = el('marketingActionList');
        if (actionList) actionList.innerHTML = '<div class="marketing-action-empty">' + text + '</div>';
        const tbody = el('marketingLeadBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary);">'
                + text + '</td></tr>';
        }
        setText('marketingActionCount', '0 follow-up');
    }

    function loadMarketing(user, silent) {
        if (!user) return;
        marketingState.loading = true;
        if (!silent) {
            const tbody = el('marketingLeadBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary);">'
                    + 'Memuat lead&hellip;</td></tr>';
            }
        }
        const result = ELIPS.marketingDashboard(user.id, currentPeriod());
        marketingState.loading = false;
        if (result && result.ok) {
            renderMarketing(result.data || {});
        } else {
            renderMarketingError((result && result.message) || 'Data Marketing gagal dimuat.');
        }
    }

    function openModal(id) {
        const modal = el(id);
        if (modal) modal.style.display = 'flex';
        document.body.classList.add('attendance-modal-open');
    }

    function closeModal(id) {
        const modal = el(id);
        if (modal) modal.style.display = 'none';
        const anyOpen = ['marketingLeadModal', 'marketingFollowupModal', 'marketingTargetModal'].some(function (modalId) {
            const node = el(modalId);
            return node && node.style.display !== 'none';
        });
        if (!anyOpen) document.body.classList.remove('attendance-modal-open');
    }

    function resetLeadForm() {
        const form = el('marketingLeadForm');
        if (form) form.reset();
        setText('marketingLeadModalTitle', 'Tambah Lead');
        const idEl = el('marketingLeadId');
        if (idEl) idEl.value = '';
        const statusEl = el('marketingLeadStatus');
        if (statusEl) statusEl.value = 'baru';
        const sourceEl = el('marketingLeadSource');
        if (sourceEl) sourceEl.value = 'lainnya';
        const valueEl = el('marketingLeadValue');
        if (valueEl) valueEl.value = '0';
    }

    function openLeadModal(lead) {
        resetLeadForm();
        if (lead) {
            setText('marketingLeadModalTitle', 'Ubah Lead');
            const values = {
                marketingLeadId: lead.id,
                marketingLeadName: lead.name,
                marketingLeadPhone: lead.phone,
                marketingLeadEmail: lead.email,
                marketingLeadCity: lead.city,
                marketingLeadSource: lead.source,
                marketingLeadStatus: lead.status,
                marketingLeadNext: lead.nextFollowupDate || '',
                marketingLeadInterest: lead.interest,
                marketingLeadValue: lead.enrollmentValue || 0,
                marketingLeadNotes: lead.notes
            };
            Object.keys(values).forEach(function (key) {
                const node = el(key);
                if (node) node.value = values[key];
            });
        }
        openModal('marketingLeadModal');
        const nameInput = el('marketingLeadName');
        if (nameInput) nameInput.focus();
    }

    function openFollowupModal(lead) {
        if (!lead) return;
        const form = el('marketingFollowupForm');
        if (form) form.reset();
        const idEl = el('marketingFollowupId');
        if (idEl) idEl.value = '';
        const leadIdEl = el('marketingFollowupLeadId');
        if (leadIdEl) leadIdEl.value = lead.id;
        const dateEl = el('marketingFollowupDate');
        if (dateEl) dateEl.value = todayString();
        const nextEl = el('marketingFollowupNext');
        if (nextEl) nextEl.value = lead.nextFollowupDate || '';
        setText('marketingFollowupLeadName', lead.name + (lead.phone ? ' • ' + lead.phone : ''));
        openModal('marketingFollowupModal');
        const outcome = el('marketingFollowupOutcome');
        if (outcome) outcome.focus();
    }

    function openTargetModal() {
        const data = marketingState.data || {};
        const target = data.target || {};
        const periodEl = el('marketingTargetPeriod');
        if (periodEl) periodEl.value = target.period || data.period || currentPeriod();
        const values = {
            marketingTargetLeads: target.leadsTarget || 0,
            marketingTargetFollowups: target.followupsTarget || 0,
            marketingTargetEnrollments: target.enrollmentsTarget || 0,
            marketingTargetRevenue: target.revenueTarget || 0
        };
        Object.keys(values).forEach(function (key) {
            const node = el(key);
            if (node) node.value = values[key];
        });
        openModal('marketingTargetModal');
        const periodInput = el('marketingTargetPeriod');
        if (periodInput) periodInput.focus();
    }

    function handleMarketingAction(action, leadId) {
        const lead = findLead(leadId);
        if (!lead) {
            showToast('Lead tidak ditemukan.');
            return;
        }
        if (action === 'followup') {
            openFollowupModal(lead);
        } else if (action === 'edit') {
            openLeadModal(lead);
        } else if (action === 'delete') {
            if (!window.confirm('Hapus lead "' + lead.name + '"? Seluruh follow-up terkait juga akan dihapus.')) {
                return;
            }
            const result = ELIPS.deleteMarketingLead(lead.id);
            showToast(result.message || (result.ok ? 'Lead dihapus.' : 'Gagal menghapus lead.'));
            if (result.ok) loadMarketing(ELIPS.getSessionUser(), true);
        }
    }

    function handleMarketingWrite(result, fallbackMessage, modalId, user) {
        if (!result || !result.ok) {
            showToast((result && result.message) || 'Operasi gagal.');
            return;
        }
        closeModal(modalId);
        showToast(result.message || fallbackMessage);
        loadMarketing(user, true);
    }

    function bindMarketing(user) {
        const addButton = el('marketingLeadAddButton');
        if (addButton) addButton.addEventListener('click', function () { openLeadModal(null); });

        const refreshButton = el('marketingLeadRefresh');
        if (refreshButton) refreshButton.addEventListener('click', function () {
            loadMarketing(user, false);
            showToast('Data Marketing dimuat ulang.');
        });

        const targetButton = el('marketingTargetButton');
        if (targetButton) targetButton.addEventListener('click', openTargetModal);

        const actionList = el('marketingActionList');
        if (actionList) actionList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-marketing-action]');
            if (!button) return;
            handleMarketingAction(button.getAttribute('data-marketing-action'), button.getAttribute('data-lead-id'));
        });

        const leadBody = el('marketingLeadBody');
        if (leadBody) leadBody.addEventListener('click', function (event) {
            const button = event.target.closest('[data-marketing-action]');
            if (!button) return;
            handleMarketingAction(button.getAttribute('data-marketing-action'), button.getAttribute('data-lead-id'));
        });

        const leadForm = el('marketingLeadForm');
        if (leadForm) leadForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                id: Number((el('marketingLeadId') || {}).value || 0) || undefined,
                name: (el('marketingLeadName') || {}).value || '',
                phone: (el('marketingLeadPhone') || {}).value || '',
                email: (el('marketingLeadEmail') || {}).value || '',
                city: (el('marketingLeadCity') || {}).value || '',
                source: (el('marketingLeadSource') || {}).value || 'lainnya',
                status: (el('marketingLeadStatus') || {}).value || 'baru',
                interest: (el('marketingLeadInterest') || {}).value || '',
                nextFollowupDate: (el('marketingLeadNext') || {}).value || '',
                enrollmentValue: (el('marketingLeadValue') || {}).value || 0,
                notes: (el('marketingLeadNotes') || {}).value || ''
            };
            handleMarketingWrite(ELIPS.saveMarketingLead(payload), 'Lead disimpan.', 'marketingLeadModal', user);
        });

        const followupForm = el('marketingFollowupForm');
        if (followupForm) followupForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                id: Number((el('marketingFollowupId') || {}).value || 0) || undefined,
                leadId: Number((el('marketingFollowupLeadId') || {}).value || 0),
                channel: (el('marketingFollowupChannel') || {}).value || 'telepon',
                followupDate: (el('marketingFollowupDate') || {}).value || '',
                outcome: (el('marketingFollowupOutcome') || {}).value || '',
                nextDate: (el('marketingFollowupNext') || {}).value || '',
                note: (el('marketingFollowupNote') || {}).value || ''
            };
            handleMarketingWrite(ELIPS.saveMarketingFollowup(payload), 'Follow-up dicatat.', 'marketingFollowupModal', user);
        });

        const targetForm = el('marketingTargetForm');
        if (targetForm) targetForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                ownerId: user.id,
                period: (el('marketingTargetPeriod') || {}).value || currentPeriod(),
                leadsTarget: Number((el('marketingTargetLeads') || {}).value || 0),
                followupsTarget: Number((el('marketingTargetFollowups') || {}).value || 0),
                enrollmentsTarget: Number((el('marketingTargetEnrollments') || {}).value || 0),
                revenueTarget: Number((el('marketingTargetRevenue') || {}).value || 0)
            };
            handleMarketingWrite(ELIPS.saveMarketingTarget(payload), 'Target disimpan.', 'marketingTargetModal', user);
        });

        [
            ['marketingLeadModalClose', 'marketingLeadModal'],
            ['marketingLeadCancel', 'marketingLeadModal'],
            ['marketingFollowupModalClose', 'marketingFollowupModal'],
            ['marketingFollowupCancel', 'marketingFollowupModal'],
            ['marketingTargetModalClose', 'marketingTargetModal'],
            ['marketingTargetCancel', 'marketingTargetModal']
        ].forEach(function (pair) {
            const button = el(pair[0]);
            if (button) button.addEventListener('click', function () { closeModal(pair[1]); });
        });

        ['marketingLeadModal', 'marketingFollowupModal', 'marketingTargetModal'].forEach(function (modalId) {
            const modal = el(modalId);
            if (modal) modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal(modalId);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            ['marketingLeadModal', 'marketingFollowupModal', 'marketingTargetModal'].forEach(function (modalId) {
                closeModal(modalId);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const user = ELIPS.requireRole('karyawan');
        if (!user) return;

        const divisionLabel = ELIPS.divisionLabel(user.division) || 'Tanpa divisi';
        const initials = (user.name || 'K').substring(0, 2).toUpperCase();

        const nameEl = document.getElementById('employeeName');
        if (nameEl) nameEl.textContent = user.name;
        const avatarEl = document.getElementById('employeeAvatar');
        if (avatarEl) avatarEl.textContent = initials;
        const divisionEl = document.getElementById('employeeDivision');
        if (divisionEl) divisionEl.textContent = 'Karyawan • ' + divisionLabel;
        const greetingEl = document.getElementById('dashboardGreeting');
        if (greetingEl) {
            greetingEl.textContent = greetingForNow() + ', ' + user.name
                + '. Berikut ringkasan kehadiran dan aktivitas Anda hari ini.';
        }
        const divisionStat = document.getElementById('statDivision');
        if (divisionStat) divisionStat.textContent = divisionLabel;

        render(user);

        if (user.division === 'marketing') {
            const area = el('marketingArea');
            if (area) area.style.display = 'block';
            const navLink = el('marketingNavLink');
            if (navLink) navLink.style.display = '';
            bindMarketing(user);
            loadMarketing(user, false);
        }

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key === ELIPS.KEYS.session || event.key === ELIPS.KEYS.staffAttendances
                || event.key === ELIPS.KEYS.marketingLeads || event.key === ELIPS.KEYS.marketingFollowups
                || event.key === ELIPS.KEYS.marketingTargets) {
                window.location.reload();
            }
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) window.location.reload();
        });
    });
})(window.ELIPS);
