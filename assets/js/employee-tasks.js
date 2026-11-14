// ============================================================
// ELIPS LMS - employee-tasks.js
// Halaman Task karyawan Marketing: daftar pekerjaan, filter status
// dan prioritas, tautan ke campaign/lead, serta CRUD task. Mendukung
// mode server dan localStorage dengan aturan validasi yang sama.
// ============================================================

(function (ELIPS) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const state = {
        tasks: [],
        campaigns: [],
        leads: [],
        loading: false,
        status: '',
        priority: '',
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

    function today() {
        return typeof ELIPS._.todayString === 'function' ? ELIPS._.todayString() : '';
    }

    function formatDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value || '-';
        return Number(parts[2]) + ' ' + (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function isOverdue(task) {
        const due = task.dueDate;
        if (!due) return false;
        if (task.status === 'selesai' || task.status === 'batal') return false;
        return due < today();
    }

    function statusLabel(status) {
        return typeof ELIPS.marketingTaskStatusLabel === 'function'
            ? ELIPS.marketingTaskStatusLabel(status)
            : status;
    }

    function priorityLabel(priority) {
        return typeof ELIPS.marketingTaskPriorityLabel === 'function'
            ? ELIPS.marketingTaskPriorityLabel(priority)
            : priority;
    }

    function typeLabel(type) {
        return typeof ELIPS.marketingTaskTypeLabel === 'function'
            ? ELIPS.marketingTaskTypeLabel(type)
            : type;
    }

    function statusBadge(task) {
        const status = String(task.status || 'belum');
        let className = 'badge badge-primary';
        if (status === 'proses') className = 'badge badge-warning';
        else if (status === 'selesai') className = 'badge badge-success';
        else if (status === 'batal') className = 'badge marketing-badge-canceled';
        return '<span class="' + className + '">' + escapeHtml(statusLabel(status)) + '</span>';
    }

    function priorityBadge(task) {
        const priority = String(task.priority || 'sedang');
        let className = 'badge badge-primary';
        if (priority === 'sedang') className = 'badge badge-warning';
        else if (priority === 'tinggi') className = 'badge marketing-badge-canceled';
        return '<span class="' + className + '">' + escapeHtml(priorityLabel(priority)) + '</span>';
    }

    function renderStats(tasks) {
        const open = tasks.filter(function (task) {
            return task.status === 'belum' || task.status === 'proses';
        }).length;
        const done = tasks.filter(function (task) { return task.status === 'selesai'; }).length;
        const overdue = tasks.filter(isOverdue).length;
        setText('taskStatTotal', tasks.length);
        setText('taskStatOpen', open);
        setText('taskStatDone', done);
        setText('taskStatOverdue', overdue);
    }

    function filteredTasks() {
        const keyword = state.search.trim().toLowerCase();
        return state.tasks.filter(function (task) {
            if (state.status && task.status !== state.status) return false;
            if (state.priority && task.priority !== state.priority) return false;
            if (!keyword) return true;
            const haystack = [
                task.title,
                task.description,
                task.campaignName,
                task.leadName,
                typeLabel(task.type),
                statusLabel(task.status),
                priorityLabel(task.priority)
            ].join(' ').toLowerCase();
            return haystack.indexOf(keyword) !== -1;
        });
    }

    function renderTasks() {
        const container = el('taskList');
        if (!container) return;
        renderStats(state.tasks);

        const tasks = filteredTasks();
        if (tasks.length === 0) {
            const empty = state.tasks.length === 0
                ? 'Belum ada task. Tambahkan task pertama untuk mulai mengatur pekerjaan.'
                : 'Tidak ada task yang cocok dengan filter.';
            container.innerHTML = '<div class="task-empty">'
                + '<i class="bi bi-check2-square" style="font-size: 2rem; display: block; opacity: .45; margin-bottom: .5rem;"></i>'
                + escapeHtml(empty) + '</div>';
            return;
        }

        container.innerHTML = tasks.map(function (task) {
            const overdue = isOverdue(task);
            const description = task.description
                ? '<p class="task-card-desc">' + escapeHtml(task.description) + '</p>'
                : '';
            const links = [];
            if (task.campaignName) {
                links.push('<span class="task-chip"><i class="bi bi-megaphone"></i> '
                    + escapeHtml(task.campaignName) + '</span>');
            }
            if (task.leadName) {
                links.push('<span class="task-chip"><i class="bi bi-person"></i> '
                    + escapeHtml(task.leadName) + '</span>');
            }
            const linkRow = links.length
                ? '<div class="task-card-links">' + links.join('') + '</div>'
                : '';
            const dueText = task.dueDate
                ? (overdue ? 'Terlambat: ' : 'Tenggat: ') + formatDate(task.dueDate)
                : 'Tanpa tenggat';
            const toggleLabel = task.status === 'selesai' ? 'Buka Lagi' : 'Selesai';
            const toggleIcon = task.status === 'selesai' ? 'bi-arrow-counterclockwise' : 'bi-check2';
            return '<article class="task-card' + (overdue ? ' is-overdue' : '') + '">'
                + '<div class="task-card-head">'
                + '<div>'
                + '<h3 class="task-card-title">' + escapeHtml(task.title) + '</h3>'
                + '<div class="task-card-meta">'
                + '<span class="task-chip"><i class="bi bi-tag"></i> ' + escapeHtml(typeLabel(task.type)) + '</span>'
                + '<span class="task-chip' + (overdue ? ' due-overdue' : '') + '">'
                + '<i class="bi bi-calendar3"></i> ' + escapeHtml(dueText) + '</span>'
                + '</div>'
                + '</div>'
                + '<div class="task-badges">' + priorityBadge(task) + statusBadge(task) + '</div>'
                + '</div>'
                + '<div class="task-card-body">'
                + description
                + linkRow
                + '</div>'
                + '<div class="task-card-actions">'
                + '<button class="btn btn-ghost" type="button" data-task-action="toggle" data-task-id="' + task.id + '">'
                + '<i class="bi ' + toggleIcon + '"></i> ' + toggleLabel + '</button>'
                + '<button class="btn btn-ghost" type="button" data-task-action="edit" data-task-id="' + task.id + '">'
                + '<i class="bi bi-pencil"></i> Ubah</button>'
                + '<button class="btn btn-ghost task-delete" type="button" data-task-action="delete" data-task-id="' + task.id + '">'
                + '<i class="bi bi-trash"></i> Hapus</button>'
                + '</div>'
                + '</article>';
        }).join('');
    }

    function loadTasks(user, silent) {
        if (!user) return;
        state.loading = true;
        if (!silent) {
            const container = el('taskList');
            if (container) {
                container.innerHTML = '<div class="task-empty">Memuat task&hellip;</div>';
            }
        }
        const result = ELIPS.marketingTasks(user.id);
        state.loading = false;
        if (result && result.ok && result.data && Array.isArray(result.data.tasks)) {
            state.tasks = result.data.tasks;
            renderTasks();
        } else {
            state.tasks = [];
            renderStats(state.tasks);
            const container = el('taskList');
            if (container) {
                container.innerHTML = '<div class="task-empty">'
                    + escapeHtml((result && result.message) || 'Task gagal dimuat.') + '</div>';
            }
        }
    }

    function loadOptions(user) {
        if (!user) return;
        const campaigns = ELIPS.marketingCampaigns(user.id);
        if (campaigns && campaigns.ok && campaigns.data && Array.isArray(campaigns.data.campaigns)) {
            state.campaigns = campaigns.data.campaigns;
        } else {
            state.campaigns = [];
        }

        const dashboard = ELIPS.marketingDashboard(user.id);
        if (dashboard && dashboard.ok && dashboard.data && Array.isArray(dashboard.data.leads)) {
            state.leads = dashboard.data.leads;
        } else {
            state.leads = [];
        }
    }

    function renderOptions(selectedCampaign, selectedLead) {
        const campaignSelect = el('taskCampaign');
        if (campaignSelect) {
            const options = ['<option value="">Tanpa campaign</option>'];
            state.campaigns.forEach(function (campaign) {
                options.push('<option value="' + campaign.id + '">' + escapeHtml(campaign.name) + '</option>');
            });
            campaignSelect.innerHTML = options.join('');
            campaignSelect.value = selectedCampaign ? String(selectedCampaign) : '';
        }

        const leadSelect = el('taskLead');
        if (leadSelect) {
            const options = ['<option value="">Tanpa lead</option>'];
            state.leads.forEach(function (lead) {
                options.push('<option value="' + lead.id + '">' + escapeHtml(lead.name) + '</option>');
            });
            leadSelect.innerHTML = options.join('');
            leadSelect.value = selectedLead ? String(selectedLead) : '';
        }
    }

    function findTask(id) {
        return state.tasks.find(function (task) {
            return Number(task.id) === Number(id);
        }) || null;
    }

    function openModal(task) {
        const form = el('taskForm');
        if (form) form.reset();
        setText('taskModalTitle', task ? 'Ubah Task' : 'Tambah Task');
        const idEl = el('taskId');
        if (idEl) idEl.value = task ? task.id : '';
        const typeEl = el('taskType');
        if (typeEl) typeEl.value = task ? task.type : 'follow_up';
        const priorityEl = el('taskPriority');
        if (priorityEl) priorityEl.value = task ? task.priority : 'sedang';
        const statusEl = el('taskStatus');
        if (statusEl) statusEl.value = task ? task.status : 'belum';
        const values = {
            taskTitle: task ? task.title : '',
            taskDueDate: task ? (task.dueDate || '') : '',
            taskDescription: task ? (task.description || '') : ''
        };
        Object.keys(values).forEach(function (key) {
            const node = el(key);
            if (node) node.value = values[key];
        });
        renderOptions(task ? task.campaignId : '', task ? task.leadId : '');
        const modal = el('taskModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('attendance-modal-open');
        }
        const titleInput = el('taskTitle');
        if (titleInput) titleInput.focus();
    }

    function closeModal() {
        const modal = el('taskModal');
        if (modal) modal.style.display = 'none';
        document.body.classList.remove('attendance-modal-open');
    }

    function payloadFor(task, overrides) {
        return Object.assign({
            id: task.id,
            title: task.title,
            description: task.description || '',
            type: task.type,
            priority: task.priority,
            status: task.status,
            dueDate: task.dueDate || '',
            campaignId: task.campaignId || '',
            leadId: task.leadId || ''
        }, overrides || {});
    }

    function handleAction(action, id, user) {
        const task = findTask(id);
        if (!task) {
            showToast('Task tidak ditemukan.');
            return;
        }
        if (action === 'edit') {
            openModal(task);
            return;
        }
        if (action === 'toggle') {
            const nextStatus = task.status === 'selesai' ? 'belum' : 'selesai';
            const result = ELIPS.saveMarketingTask(payloadFor(task, { status: nextStatus }));
            showToast(result.message || (result.ok ? 'Task diperbarui.' : 'Gagal memperbarui task.'));
            if (result.ok) loadTasks(user, true);
            return;
        }
        if (action === 'delete') {
            if (!window.confirm('Hapus task "' + task.title + '"?')) {
                return;
            }
            const result = ELIPS.deleteMarketingTask(task.id);
            showToast(result.message || (result.ok ? 'Task dihapus.' : 'Gagal menghapus task.'));
            if (result.ok) loadTasks(user, true);
        }
    }

    function bind(user) {
        const addButton = el('taskAddButton');
        if (addButton) addButton.addEventListener('click', function () { openModal(null); });

        const refreshButton = el('taskRefresh');
        if (refreshButton) refreshButton.addEventListener('click', function () {
            loadOptions(user);
            loadTasks(user, false);
            showToast('Task dimuat ulang.');
        });

        const searchInput = el('taskSearch');
        if (searchInput) searchInput.addEventListener('input', function () {
            state.search = searchInput.value || '';
            renderTasks();
        });

        const statusFilter = el('taskStatusFilter');
        if (statusFilter) statusFilter.addEventListener('change', function () {
            state.status = statusFilter.value || '';
            renderTasks();
        });

        const priorityFilter = el('taskPriorityFilter');
        if (priorityFilter) priorityFilter.addEventListener('change', function () {
            state.priority = priorityFilter.value || '';
            renderTasks();
        });

        const list = el('taskList');
        if (list) list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-task-action]');
            if (!button) return;
            handleAction(
                button.getAttribute('data-task-action'),
                button.getAttribute('data-task-id'),
                user
            );
        });

        const form = el('taskForm');
        if (form) form.addEventListener('submit', function (event) {
            event.preventDefault();
            const payload = {
                id: Number((el('taskId') || {}).value || 0) || undefined,
                title: (el('taskTitle') || {}).value || '',
                description: (el('taskDescription') || {}).value || '',
                type: (el('taskType') || {}).value || 'lainnya',
                priority: (el('taskPriority') || {}).value || 'sedang',
                status: (el('taskStatus') || {}).value || 'belum',
                dueDate: (el('taskDueDate') || {}).value || '',
                campaignId: Number((el('taskCampaign') || {}).value || 0),
                leadId: Number((el('taskLead') || {}).value || 0)
            };
            const result = ELIPS.saveMarketingTask(payload);
            if (!result || !result.ok) {
                showToast((result && result.message) || 'Gagal menyimpan task.');
                return;
            }
            closeModal();
            showToast(result.message || 'Task disimpan.');
            loadOptions(user);
            loadTasks(user, true);
        });

        [
            ['taskModalClose', closeModal],
            ['taskCancel', closeModal]
        ].forEach(function (pair) {
            const button = el(pair[0]);
            if (button) button.addEventListener('click', pair[1]);
        });

        const modal = el('taskModal');
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
        const greetingEl = el('taskGreeting');
        if (greetingEl) {
            greetingEl.textContent = 'Kelola pekerjaan harian Marketing dan pantau tenggatnya, ' + user.name + '.';
        }

        bind(user);
        loadOptions(user);
        loadTasks(user, false);

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key === ELIPS.KEYS.session
                || event.key === ELIPS.KEYS.marketingTasks
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
