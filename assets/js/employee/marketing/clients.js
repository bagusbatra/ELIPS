// ============================================================
// ELIPS LMS - employee-clients.js
// Halaman Clients karyawan Marketing: daftar klien/pelanggan,
// filter status, tautan ke campaign, serta CRUD client. Mendukung
// mode server dan localStorage dengan aturan validasi yang sama.
// ============================================================

(function (ELIPS) {
    'use strict';

    const state = {
        clients: [],
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

    function statusLabel(status) {
        return typeof ELIPS.marketingClientStatusLabel === 'function'
            ? ELIPS.marketingClientStatusLabel(status)
            : status;
    }

    function statusBadge(client) {
        const status = String(client.status || 'prospek');
        let className = 'badge badge-warning';
        if (status === 'aktif') className = 'badge badge-success';
        else if (status === 'nonaktif') className = 'badge marketing-badge-canceled';
        return '<span class="' + className + '">' + escapeHtml(statusLabel(status)) + '</span>';
    }

    function renderStats(clients) {
        const active = clients.filter(function (client) { return client.status === 'aktif'; }).length;
        const prospect = clients.filter(function (client) { return client.status === 'prospek'; }).length;
        const inactive = clients.filter(function (client) { return client.status === 'nonaktif'; }).length;
        setText('clientStatTotal', clients.length);
        setText('clientStatActive', active);
        setText('clientStatProspect', prospect);
        setText('clientStatInactive', inactive);
    }

    function filteredClients() {
        const keyword = state.search.trim().toLowerCase();
        return state.clients.filter(function (client) {
            if (state.status && client.status !== state.status) return false;
            if (!keyword) return true;
            const haystack = [
                client.name,
                client.company,
                client.pic,
                client.phone,
                client.email,
                client.city,
                client.address,
                client.campaignName,
                client.notes,
                statusLabel(client.status)
            ].join(' ').toLowerCase();
            return haystack.indexOf(keyword) !== -1;
        });
    }

    function contactLine(client) {
        const parts = [];
        if (client.phone) parts.push('<span><i class="bi bi-telephone"></i> ' + escapeHtml(client.phone) + '</span>');
        if (client.email) parts.push('<span><i class="bi bi-envelope"></i> ' + escapeHtml(client.email) + '</span>');
        return parts.length ? '<div class="task-card-meta">' + parts.join('') + '</div>' : '';
    }

    function renderClients() {
        const container = el('clientList');
        if (!container) return;
        renderStats(state.clients);

        const clients = filteredClients();
        if (clients.length === 0) {
            const empty = state.clients.length === 0
                ? 'Belum ada client. Tambahkan client pertama untuk mulai mengelola pelanggan.'
                : 'Tidak ada client yang cocok dengan filter.';
            container.innerHTML = '<div class="task-empty">'
                + '<i class="bi bi-people" style="font-size: 2rem; display: block; opacity: .45; margin-bottom: .5rem;"></i>'
                + escapeHtml(empty) + '</div>';
            return;
        }

        container.innerHTML = clients.map(function (client) {
            const subtitle = [];
            if (client.company) subtitle.push(client.company);
            if (client.pic) subtitle.push('PIC: ' + client.pic);
            const subtitleHtml = subtitle.length
                ? '<p class="task-card-desc" style="margin-bottom: .25rem;">' + escapeHtml(subtitle.join(' • ')) + '</p>'
                : '';
            const notes = client.notes
                ? '<p class="task-card-desc">' + escapeHtml(client.notes) + '</p>'
                : '';
            const chips = [];
            if (client.city) {
                chips.push('<span class="task-chip"><i class="bi bi-geo-alt"></i> ' + escapeHtml(client.city) + '</span>');
            }
            if (client.campaignName) {
                chips.push('<span class="task-chip"><i class="bi bi-megaphone"></i> ' + escapeHtml(client.campaignName) + '</span>');
            }
            const chipRow = chips.length ? '<div class="task-card-links">' + chips.join('') + '</div>' : '';
            return '<article class="task-card">'
                + '<div class="task-card-head">'
                + '<div>'
                + '<h3 class="task-card-title">' + escapeHtml(client.name) + '</h3>'
                + subtitleHtml
                + contactLine(client)
                + '</div>'
                + '<div class="task-badges">' + statusBadge(client) + '</div>'
                + '</div>'
                + '<div class="task-card-body">'
                + chipRow
                + notes
                + '</div>'
                + '<div class="task-card-actions">'
                + '<button class="btn btn-ghost" type="button" data-client-action="edit" data-client-id="' + client.id + '">'
                + '<i class="bi bi-pencil"></i> Ubah</button>'
                + '<button class="btn btn-ghost task-delete" type="button" data-client-action="delete" data-client-id="' + client.id + '">'
                + '<i class="bi bi-trash"></i> Hapus</button>'
                + '</div>'
                + '</article>';
        }).join('');
    }

    function loadClients(user, silent) {
        if (!user) return;
        state.loading = true;
        if (!silent) {
            const container = el('clientList');
            if (container) container.innerHTML = '<div class="task-empty">Memuat client&hellip;</div>';
        }
        const result = ELIPS.marketingClients(user.id);
        state.loading = false;
        if (result && result.ok && result.data && Array.isArray(result.data.clients)) {
            state.clients = result.data.clients;
            renderClients();
        } else {
            state.clients = [];
            renderStats(state.clients);
            const container = el('clientList');
            if (container) {
                container.innerHTML = '<div class="task-empty">'
                    + escapeHtml((result && result.message) || 'Client gagal dimuat.') + '</div>';
            }
        }
    }

    function loadCampaignOptions(user) {
        if (!user) return;
        const campaigns = ELIPS.marketingCampaigns(user.id);
        if (campaigns && campaigns.ok && campaigns.data && Array.isArray(campaigns.data.campaigns)) {
            state.campaigns = campaigns.data.campaigns;
        } else {
            state.campaigns = [];
        }
    }

    function renderCampaignOptions(selectedCampaign) {
        const select = el('clientCampaign');
        if (!select) return;
        const options = ['<option value="">Tanpa campaign</option>'];
        state.campaigns.forEach(function (campaign) {
            options.push('<option value="' + campaign.id + '">' + escapeHtml(campaign.name) + '</option>');
        });
        select.innerHTML = options.join('');
        select.value = selectedCampaign ? String(selectedCampaign) : '';
    }

    function findClient(id) {
        return state.clients.find(function (client) {
            return Number(client.id) === Number(id);
        }) || null;
    }

    function openModal(client) {
        const form = el('clientForm');
        if (form) form.reset();
        setText('clientModalTitle', client ? 'Ubah Client' : 'Tambah Client');
        const idEl = el('clientId');
        if (idEl) idEl.value = client ? client.id : '';
        const values = {
            clientName: client ? client.name : '',
            clientCompany: client ? (client.company || '') : '',
            clientPic: client ? (client.pic || '') : '',
            clientPhone: client ? (client.phone || '') : '',
            clientEmail: client ? (client.email || '') : '',
            clientCity: client ? (client.city || '') : '',
            clientAddress: client ? (client.address || '') : '',
            clientStatus: client ? client.status : 'prospek',
            clientNotes: client ? (client.notes || '') : ''
        };
        Object.keys(values).forEach(function (key) {
            const node = el(key);
            if (node) node.value = values[key];
        });
        renderCampaignOptions(client ? client.campaignId : '');
        const modal = el('clientModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('attendance-modal-open');
        }
        const nameInput = el('clientName');
        if (nameInput) nameInput.focus();
    }

    function closeModal() {
        const modal = el('clientModal');
        if (modal) modal.style.display = 'none';
        document.body.classList.remove('attendance-modal-open');
    }

    function handleAction(action, id, user) {
        const client = findClient(id);
        if (!client) {
            showToast('Client tidak ditemukan.');
            return;
        }
        if (action === 'edit') {
            openModal(client);
            return;
        }
        if (action === 'delete') {
            if (!window.confirm('Hapus client "' + client.name + '"?')) {
                return;
            }
            const result = ELIPS.deleteMarketingClient(client.id);
            showToast(result.message || (result.ok ? 'Client dihapus.' : 'Gagal menghapus client.'));
            if (result.ok) loadClients(user, true);
        }
    }

    function bind(user) {
        const addButton = el('clientAddButton');
        if (addButton) addButton.addEventListener('click', function () { openModal(null); });

        const refreshButton = el('clientRefresh');
        if (refreshButton) refreshButton.addEventListener('click', function () {
            loadCampaignOptions(user);
            loadClients(user, false);
            showToast('Client dimuat ulang.');
        });

        const searchInput = el('clientSearch');
        if (searchInput) searchInput.addEventListener('input', function () {
            state.search = searchInput.value || '';
            renderClients();
        });

        const statusFilter = el('clientStatusFilter');
        if (statusFilter) statusFilter.addEventListener('change', function () {
            state.status = statusFilter.value || '';
            renderClients();
        });

        const list = el('clientList');
        if (list) list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-client-action]');
            if (!button) return;
            handleAction(
                button.getAttribute('data-client-action'),
                button.getAttribute('data-client-id'),
                user
            );
        });

        const form = el('clientForm');
        if (form) form.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                id: Number((el('clientId') || {}).value || 0) || undefined,
                name: (el('clientName') || {}).value || '',
                company: (el('clientCompany') || {}).value || '',
                pic: (el('clientPic') || {}).value || '',
                phone: (el('clientPhone') || {}).value || '',
                email: (el('clientEmail') || {}).value || '',
                city: (el('clientCity') || {}).value || '',
                address: (el('clientAddress') || {}).value || '',
                status: (el('clientStatus') || {}).value || 'prospek',
                campaignId: Number((el('clientCampaign') || {}).value || 0),
                notes: (el('clientNotes') || {}).value || ''
            };
            const result = ELIPS.saveMarketingClient(payload);
            if (!result || !result.ok) {
                showToast((result && result.message) || 'Gagal menyimpan client.');
                return;
            }
            closeModal();
            showToast(result.message || 'Client disimpan.');
            loadCampaignOptions(user);
            loadClients(user, true);
        });

        [
            ['clientModalClose', closeModal],
            ['clientCancel', closeModal]
        ].forEach(function (pair) {
            const button = el(pair[0]);
            if (button) button.addEventListener('click', pair[1]);
        });

        const modal = el('clientModal');
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
        const greetingEl = el('clientGreeting');
        if (greetingEl) {
            greetingEl.textContent = 'Kelola data klien/pelanggan dan hubungannya dengan campaign, ' + user.name + '.';
        }

        bind(user);
        loadCampaignOptions(user);
        loadClients(user, false);

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key === ELIPS.KEYS.session
                || event.key === ELIPS.KEYS.marketingClients
                || event.key === ELIPS.KEYS.marketingCampaigns) {
                window.location.reload();
            }
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) window.location.reload();
        });
    });
})(window.ELIPS);
