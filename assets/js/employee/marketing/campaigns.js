// ============================================================
// ELIPS LMS - employee-campaigns.js
// Halaman Campaigns karyawan Marketing: daftar campaign, ringkasan
// capaian dari lead tertaut, serta CRUD campaign. Mendukung mode
// server dan localStorage dengan aturan validasi yang sama.
// ============================================================

(function (ELIPS) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const STATUS_FILTERS = ['draft', 'aktif', 'selesai', 'batal'];

    const state = {
        campaigns: [],
        loading: false,
        status: '',
        search: ''
    };

    function el(id) {
        return document.getElementById(id);
    }

    function setText(id, value) {
        const node = el(id);
        if (node) node.textContent = String(value);
    }

    function escapeHtml(value) {
        return ELIPS.escapeHtml(value === undefined || value === null ? '' : value);
    }

    function showToast(message) {
        if (typeof ELIPS.toast === 'function') ELIPS.toast(message);
    }

    function formatDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value || '-';
        return Number(parts[2]) + ' ' + (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function formatPeriod(campaign) {
        if (campaign.startDate && campaign.endDate) {
            return formatDate(campaign.startDate) + ' - ' + formatDate(campaign.endDate);
        }
        if (campaign.startDate) return 'Mulai ' + formatDate(campaign.startDate);
        if (campaign.endDate) return 'Selesai ' + formatDate(campaign.endDate);
        return 'Periode belum ditentukan';
    }

    function formatRupiah(value) {
        return 'Rp' + Math.round(Number(value || 0)).toLocaleString('id-ID');
    }

    function statusLabel(status) {
        return typeof ELIPS.marketingCampaignStatusLabel === 'function'
            ? ELIPS.marketingCampaignStatusLabel(status)
            : status;
    }

    function channelLabel(channel) {
        return typeof ELIPS.marketingCampaignChannelLabel === 'function'
            ? ELIPS.marketingCampaignChannelLabel(channel)
            : channel;
    }

    function statusBadge(campaign) {
        const status = String(campaign.status || 'draft');
        let className = 'badge badge-primary';
        if (status === 'aktif') className = 'badge badge-success';
        else if (status === 'batal') className = 'badge marketing-badge-canceled';
        return '<span class="' + className + '">' + escapeHtml(statusLabel(status)) + '</span>';
    }

    function percent(actual, target) {
        const total = Number(target || 0);
        if (total <= 0) return 0;
        return Math.max(0, Math.min(100, Math.round((Number(actual || 0) / total) * 100)));
    }

    function progressRow(label, actual, target, tone) {
        const value = Number(actual || 0);
        const goal = Number(target || 0);
        const width = percent(value, goal);
        return '<div class="campaign-progress">'
            + '<div class="campaign-progress-head">'
            + '<span>' + escapeHtml(label) + '</span>'
            + '<strong>' + value + ' / ' + goal + '</strong>'
            + '</div>'
            + '<div class="campaign-progress-track">'
            + '<div class="campaign-progress-bar' + (tone ? ' ' + tone : '') + '" style="width: ' + width + '%"></div>'
            + '</div>'
            + '</div>';
    }

    function renderStats(campaigns) {
        const active = campaigns.filter(function (campaign) { return campaign.status === 'aktif'; }).length;
        const leads = campaigns.reduce(function (total, campaign) {
            return total + Number(campaign.leadCount || 0);
        }, 0);
        const enrollments = campaigns.reduce(function (total, campaign) {
            return total + Number(campaign.enrolledCount || 0);
        }, 0);
        setText('campaignStatTotal', campaigns.length);
        setText('campaignStatActive', active);
        setText('campaignStatLeads', leads);
        setText('campaignStatEnrollments', enrollments);
    }

    function filteredCampaigns() {
        const keyword = state.search.trim().toLowerCase();
        return state.campaigns.filter(function (campaign) {
            if (state.status && campaign.status !== state.status) return false;
            if (!keyword) return true;
            const haystack = [
                campaign.name,
                campaign.notes,
                channelLabel(campaign.channel),
                statusLabel(campaign.status)
            ].join(' ').toLowerCase();
            return haystack.indexOf(keyword) !== -1;
        });
    }

    function renderCampaigns() {
        const container = el('campaignList');
        if (!container) return;
        renderStats(state.campaigns);

        const campaigns = filteredCampaigns();
        if (campaigns.length === 0) {
            const empty = state.campaigns.length === 0
                ? 'Belum ada campaign. Mulai dengan menambahkan campaign pertama Anda.'
                : 'Tidak ada campaign yang cocok dengan filter.';
            container.innerHTML = '<div class="campaign-empty">'
                + '<i class="bi bi-megaphone" style="font-size: 2rem; display: block; opacity: .45; margin-bottom: .5rem;"></i>'
                + escapeHtml(empty) + '</div>';
            return;
        }

        container.innerHTML = campaigns.map(function (campaign) {
            const notes = campaign.notes
                ? '<p class="campaign-card-notes">' + escapeHtml(campaign.notes) + '</p>'
                : '';
            return '<article class="campaign-card">'
                + '<div class="campaign-card-head">'
                + '<div>'
                + '<h3 class="campaign-card-title">' + escapeHtml(campaign.name) + '</h3>'
                + '<div class="campaign-card-meta">'
                + '<span class="campaign-chip"><i class="bi bi-broadcast"></i> ' + escapeHtml(channelLabel(campaign.channel)) + '</span>'
                + '<span class="campaign-chip"><i class="bi bi-calendar3"></i> ' + escapeHtml(formatPeriod(campaign)) + '</span>'
                + '</div>'
                + '</div>'
                + statusBadge(campaign)
                + '</div>'
                + '<div class="campaign-card-body">'
                + progressRow('Capaian lead', campaign.leadCount, campaign.targetLeads)
                + progressRow('Capaian pendaftaran', campaign.enrolledCount, campaign.targetEnrollments, 'blue')
                + '<div class="campaign-card-facts">'
                + '<div><span>Budget</span><strong>' + escapeHtml(formatRupiah(campaign.budget)) + '</strong></div>'
                + '<div><span>Pendapatan</span><strong>' + escapeHtml(formatRupiah(campaign.revenue)) + '</strong></div>'
                + '</div>'
                + notes
                + '</div>'
                + '<div class="campaign-card-actions">'
                + '<button class="btn btn-ghost" type="button" data-campaign-action="edit" data-campaign-id="' + campaign.id + '">'
                + '<i class="bi bi-pencil"></i> Ubah</button>'
                + '<button class="btn btn-ghost campaign-delete" type="button" data-campaign-action="delete" data-campaign-id="' + campaign.id + '">'
                + '<i class="bi bi-trash"></i> Hapus</button>'
                + '</div>'
                + '</article>';
        }).join('');
    }

    function loadCampaigns(user, silent) {
        if (!user) return;
        state.loading = true;
        if (!silent) {
            const container = el('campaignList');
            if (container) {
                container.innerHTML = '<div class="campaign-empty">Memuat campaign&hellip;</div>';
            }
        }
        const result = ELIPS.marketingCampaigns(user.id);
        state.loading = false;
        if (result && result.ok && result.data && Array.isArray(result.data.campaigns)) {
            state.campaigns = result.data.campaigns;
            renderCampaigns();
        } else {
            state.campaigns = [];
            renderStats(state.campaigns);
            const container = el('campaignList');
            if (container) {
                container.innerHTML = '<div class="campaign-empty">'
                    + escapeHtml((result && result.message) || 'Campaign gagal dimuat.') + '</div>';
            }
        }
    }

    function findCampaign(id) {
        return state.campaigns.find(function (campaign) {
            return Number(campaign.id) === Number(id);
        }) || null;
    }

    function openModal(campaign) {
        const form = el('campaignForm');
        if (form) form.reset();
        setText('campaignModalTitle', campaign ? 'Ubah Campaign' : 'Tambah Campaign');
        const idEl = el('campaignId');
        if (idEl) idEl.value = campaign ? campaign.id : '';
        const statusEl = el('campaignStatus');
        if (statusEl) statusEl.value = campaign ? campaign.status : 'draft';
        const channelEl = el('campaignChannel');
        if (channelEl) channelEl.value = campaign ? campaign.channel : 'sosial_media';
        const values = {
            campaignName: campaign ? campaign.name : '',
            campaignStartDate: campaign ? (campaign.startDate || '') : '',
            campaignEndDate: campaign ? (campaign.endDate || '') : '',
            campaignBudget: campaign ? (campaign.budget || 0) : 0,
            campaignTargetLeads: campaign ? (campaign.targetLeads || 0) : 0,
            campaignTargetEnrollments: campaign ? (campaign.targetEnrollments || 0) : 0,
            campaignNotes: campaign ? (campaign.notes || '') : ''
        };
        Object.keys(values).forEach(function (key) {
            const node = el(key);
            if (node) node.value = values[key];
        });
        const modal = el('campaignModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('attendance-modal-open');
        }
        const nameInput = el('campaignName');
        if (nameInput) nameInput.focus();
    }

    function closeModal() {
        const modal = el('campaignModal');
        if (modal) modal.style.display = 'none';
        document.body.classList.remove('attendance-modal-open');
    }

    function handleAction(action, id) {
        const campaign = findCampaign(id);
        if (!campaign) {
            showToast('Campaign tidak ditemukan.');
            return;
        }
        if (action === 'edit') {
            openModal(campaign);
            return;
        }
        if (action === 'delete') {
            if (!window.confirm('Hapus campaign "' + campaign.name
                + '"? Lead yang tertaut akan dilepas dari campaign ini.')) {
                return;
            }
            const result = ELIPS.deleteMarketingCampaign(campaign.id);
            showToast(result.message || (result.ok ? 'Campaign dihapus.' : 'Gagal menghapus campaign.'));
            if (result.ok) loadCampaigns(ELIPS.getSessionUser(), true);
        }
    }

    function bind(user) {
        const addButton = el('campaignAddButton');
        if (addButton) addButton.addEventListener('click', function () { openModal(null); });

        const refreshButton = el('campaignRefresh');
        if (refreshButton) refreshButton.addEventListener('click', function () {
            loadCampaigns(user, false);
            showToast('Campaign dimuat ulang.');
        });

        const searchInput = el('campaignSearch');
        if (searchInput) searchInput.addEventListener('input', function () {
            state.search = searchInput.value || '';
            renderCampaigns();
        });

        const statusFilter = el('campaignStatusFilter');
        if (statusFilter) statusFilter.addEventListener('change', function () {
            state.status = statusFilter.value || '';
            renderCampaigns();
        });

        const list = el('campaignList');
        if (list) list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-campaign-action]');
            if (!button) return;
            handleAction(button.getAttribute('data-campaign-action'), button.getAttribute('data-campaign-id'));
        });

        const form = el('campaignForm');
        if (form) form.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                id: Number((el('campaignId') || {}).value || 0) || undefined,
                name: (el('campaignName') || {}).value || '',
                channel: (el('campaignChannel') || {}).value || 'lainnya',
                status: (el('campaignStatus') || {}).value || 'draft',
                startDate: (el('campaignStartDate') || {}).value || '',
                endDate: (el('campaignEndDate') || {}).value || '',
                budget: Number((el('campaignBudget') || {}).value || 0),
                targetLeads: Number((el('campaignTargetLeads') || {}).value || 0),
                targetEnrollments: Number((el('campaignTargetEnrollments') || {}).value || 0),
                notes: (el('campaignNotes') || {}).value || ''
            };
            const result = ELIPS.saveMarketingCampaign(payload);
            if (!result || !result.ok) {
                showToast((result && result.message) || 'Gagal menyimpan campaign.');
                return;
            }
            closeModal();
            showToast(result.message || 'Campaign disimpan.');
            loadCampaigns(user, true);
        });

        [
            ['campaignModalClose', closeModal],
            ['campaignCancel', closeModal]
        ].forEach(function (pair) {
            const button = el(pair[0]);
            if (button) button.addEventListener('click', pair[1]);
        });

        const modal = el('campaignModal');
        if (modal) modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeModal();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const user = ELIPS.requireRole('karyawan');
        if (!user) return;
        if (user.division !== 'marketing') {
            window.location.replace('dashboard.html');
            return;
        }

        const divisionLabel = ELIPS.divisionLabel(user.division) || 'Marketing';
        const initials = (user.name || 'K').substring(0, 2).toUpperCase();

        const nameEl = el('employeeName');
        if (nameEl) nameEl.textContent = user.name;
        const avatarEl = el('employeeAvatar');
        if (avatarEl) avatarEl.textContent = initials;
        const divisionEl = el('employeeDivision');
        if (divisionEl) divisionEl.textContent = 'Karyawan • ' + divisionLabel;
        const greetingEl = el('campaignGreeting');
        if (greetingEl) {
            greetingEl.textContent = 'Kelola campaign dan pantau capaian lead yang tertaut, ' + user.name + '.';
        }

        bind(user);
        loadCampaigns(user, false);

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key === ELIPS.KEYS.session
                || event.key === ELIPS.KEYS.marketingCampaigns
                || event.key === ELIPS.KEYS.marketingLeads) {
                window.location.reload();
            }
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) window.location.reload();
        });
    });
})(window.ELIPS);
