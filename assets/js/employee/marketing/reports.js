// ============================================================
// ELIPS LMS - employee-reports.js
// Laporan Marketing lengkap untuk karyawan divisi Marketing.
// Admin dapat memantau semua atau memilih karyawan. Mendukung
// filter periode/status/campaign, ringkasan, grafik sederhana,
// tabel, dan tombol ekspor (placeholder).
// ============================================================

(function (ELIPS) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const state = {
        session: null,
        scopeUserId: null,
        dashboard: null,
        campaigns: [],
        tasks: [],
        clients: []
    };

    function el(id) { return document.getElementById(id); }
    function escapeHtml(value) { return ELIPS.escapeHtml(value === undefined || value === null ? '' : value); }
    function showToast(message) { if (typeof ELIPS.toast === 'function') ELIPS.toast(message); }

    function todayString() {
        if (ELIPS._ && typeof ELIPS._.todayString === 'function') return ELIPS._.todayString();
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function currentPeriod() {
        const t = todayString();
        return t ? t.slice(0, 7) : '';
    }

    function formatDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value || '-';
        return Number(parts[2]) + ' ' + (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function formatRupiah(value) {
        return 'Rp' + Math.round(Number(value || 0)).toLocaleString('id-ID');
    }

    function startOfWeek(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return { from: '', to: '' };
        const day = d.getDay();
        const diff = (day === 0 ? -6 : 1 - day);
        d.setDate(d.getDate() + diff);
        const from = d.toISOString().slice(0, 10);
        const d2 = new Date(d);
        d2.setDate(d2.getDate() + 6);
        return { from: from, to: d2.toISOString().slice(0, 10) };
    }

    function weekToRange(weekValue) {
        // format YYYY-Www
        const m = String(weekValue || '').match(/^(\d{4})-W(\d{2})$/);
        if (!m) return { from: '', to: '' };
        const year = Number(m[1]);
        const week = Number(m[2]);
        const jan4 = new Date(year, 0, 4);
        const day = jan4.getDay() || 7;
        const monday = new Date(jan4);
        monday.setDate(jan4.getDate() - day + 1 + (week - 1) * 7);
        const from = monday.toISOString().slice(0, 10);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        return { from: from, to: sunday.toISOString().slice(0, 10) };
    }

    function getPeriodRange() {
        const mode = (el('reportPeriodMode') || {}).value || 'month';
        if (mode === 'day') {
            const day = (el('reportDay') || {}).value || todayString();
            return { from: day, to: day, label: formatDate(day), type: 'day' };
        }
        if (mode === 'week') {
            const w = (el('reportWeek') || {}).value;
            const r = weekToRange(w);
            if (!r.from) {
                const t = todayString();
                const wr = startOfWeek(t);
                return { from: wr.from, to: wr.to, label: 'Minggu ' + formatDate(wr.from) + ' - ' + formatDate(wr.to), type: 'week' };
            }
            return { from: r.from, to: r.to, label: 'Minggu ' + formatDate(r.from) + ' - ' + formatDate(r.to), type: 'week' };
        }
        if (mode === 'range') {
            let from = (el('reportFrom') || {}).value || '';
            let to = (el('reportTo') || {}).value || '';
            if (!from && !to) {
                const p = currentPeriod();
                from = p + '-01';
                to = todayString();
            }
            if (from && !to) to = from;
            if (!from && to) from = to;
            if (from > to) { const tmp = from; from = to; to = tmp; }
            return { from: from, to: to, label: formatDate(from) + ' - ' + formatDate(to), type: 'range' };
        }
        // month
        const month = (el('reportMonth') || {}).value || currentPeriod();
        const from = month + '-01';
        const parts = month.split('-');
        let to = from;
        if (parts.length === 2) {
            const last = new Date(Number(parts[0]), Number(parts[1]), 0).getDate();
            to = month + '-' + String(last).padStart(2, '0');
        }
        const monthLabel = parts.length === 2 ? (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0] : month;
        return { from: from, to: to, label: monthLabel, type: 'month', period: month };
    }

    function inRange(dateValue, range) {
        if (!range || !range.from || !range.to) return true;
        const d = String(dateValue || '').slice(0, 10);
        if (!d) return false;
        return d >= range.from && d <= range.to;
    }

    function leadDate(lead) {
        // untuk filter periode: gunakan createdAt/updatedAt/convertedAt/nextFollowupDate fallback
        return String(lead.createdAt || lead.updatedAt || lead.convertedAt || lead.nextFollowupDate || '').slice(0, 10);
    }

    function taskDate(task) {
        return String(task.dueDate || task.completedAt || task.updatedAt || task.createdAt || '').slice(0, 10);
    }

    function clientDate(client) {
        return String(client.updatedAt || client.createdAt || '').slice(0, 10);
    }

    function campaignDate(campaign) {
        return String(campaign.startDate || campaign.updatedAt || campaign.createdAt || '').slice(0, 10);
    }

    function followupDate(followup) {
        return String(followup.followupDate || followup.updatedAt || followup.createdAt || '').slice(0, 10);
    }

    function getScopeUserId() {
        const user = state.session;
        if (!user) return null;
        if (user.role === 'admin') {
            const val = (el('reportOwner') || {}).value;
            if (val === '' || val === undefined) return null; // semua
            const n = Number(val);
            return n > 0 ? n : null;
        }
        return user.id;
    }

    function loadData() {
        const scope = getScopeUserId();
        state.scopeUserId = scope;
        const range = getPeriodRange();
        const period = range.period || (range.from ? range.from.slice(0, 7) : currentPeriod());

        const dash = ELIPS.marketingDashboard(scope, period);
        state.dashboard = (dash && dash.ok) ? dash.data : { leads: [], followups: [], stats: {}, target: null, today: todayString(), period: period };

        const camp = ELIPS.marketingCampaigns(scope);
        state.campaigns = (camp && camp.ok && camp.data && Array.isArray(camp.data.campaigns)) ? camp.data.campaigns : [];

        const tasks = ELIPS.marketingTasks(scope);
        state.tasks = (tasks && tasks.ok && tasks.data && Array.isArray(tasks.data.tasks)) ? tasks.data.tasks : [];

        const clients = ELIPS.marketingClients(scope);
        state.clients = (clients && clients.ok && clients.data && Array.isArray(clients.data.clients)) ? clients.data.clients : [];
    }

    function applyFilters() {
        const range = getPeriodRange();
        const campaignId = (el('reportCampaign') || {}).value;
        const leadStatus = (el('reportLeadStatus') || {}).value;
        const taskStatus = (el('reportTaskStatus') || {}).value;

        let leads = (state.dashboard && state.dashboard.leads ? state.dashboard.leads : []).filter(function (lead) {
            if (!inRange(leadDate(lead), range)) return false;
            if (campaignId && String(lead.campaignId || '') !== String(campaignId)) return false;
            if (leadStatus && String(lead.status) !== leadStatus) return false;
            return true;
        });

        let followups = (state.dashboard && state.dashboard.followups ? state.dashboard.followups : []).filter(function (f) {
            if (!inRange(followupDate(f), range)) return false;
            // campaign filter via lead
            if (campaignId) {
                const lead = leads.find(function (l) { return Number(l.id) === Number(f.leadId); })
                    || (state.dashboard.leads || []).find(function (l) { return Number(l.id) === Number(f.leadId); });
                if (!lead || String(lead.campaignId || '') !== String(campaignId)) return false;
            }
            return true;
        });

        let campaigns = state.campaigns.filter(function (c) {
            if (!inRange(campaignDate(c), range) && !(range.type === 'month' && (String(c.startDate||'').slice(0,7) === (range.period||'') || String(c.endDate||'').slice(0,7) === (range.period||'')))) {
                // long-running campaigns may overlap; keep if overlap
                if (c.startDate && c.endDate) {
                    if (c.endDate < range.from || c.startDate > range.to) return false;
                } else {
                    return false;
                }
            }
            if (campaignId && String(c.id) !== String(campaignId)) return false;
            return true;
        });

        let tasks = state.tasks.filter(function (t) {
            if (!inRange(taskDate(t), range)) return false;
            if (campaignId && String(t.campaignId || '') !== String(campaignId)) return false;
            if (taskStatus && String(t.status) !== taskStatus) return false;
            return true;
        });

        let clients = state.clients.filter(function (c) {
            if (!inRange(clientDate(c), range)) return false;
            if (campaignId && String(c.campaignId || '') !== String(campaignId)) return false;
            return true;
        });

        return { range: range, leads: leads, followups: followups, campaigns: campaigns, tasks: tasks, clients: clients };
    }

    function renderSummaryCards(data) {
        const container = el('reportSummaryCards');
        if (!container) return;
        const leads = data.leads;
        const tasks = data.tasks;
        const clients = data.clients;
        const followups = data.followups;
        const today = todayString();
        const pipeline = { baru: 0, dihubungi: 0, prospek: 0, terdaftar: 0, batal: 0 };
        let revenue = 0;
        leads.forEach(function (l) {
            if (pipeline[l.status] !== undefined) pipeline[l.status]++;
            if (l.status === 'terdaftar') revenue += Number(l.enrollmentValue || 0);
        });
        const openTasks = tasks.filter(function (t) { return t.status === 'belum' || t.status === 'proses'; }).length;
        const doneTasks = tasks.filter(function (t) { return t.status === 'selesai'; }).length;
        const overdueTasks = tasks.filter(function (t) { return t.dueDate && t.dueDate < today && t.status !== 'selesai' && t.status !== 'batal'; }).length;
        const todayFu = followups.filter(function (f) { return String(f.followupDate || '') === today; }).length;
        const overdueFu = followups.filter(function (f) { return String(f.followupDate || '') < today && String(f.followupDate || '') !== ''; }).length;
        const stats = (state.dashboard && state.dashboard.stats) ? state.dashboard.stats : {};
        const target = state.dashboard ? state.dashboard.target : null;

        const cards = [
            { label: 'Total Lead', value: leads.length, hint: 'Baru: ' + pipeline.baru, icon: 'bi-person-plus', color: 'orange' },
            { label: 'Lead Terdaftar', value: pipeline.terdaftar, hint: 'Pendapatan: ' + formatRupiah(revenue), icon: 'bi-patch-check', color: 'green' },
            { label: 'Follow-up', value: followups.length, hint: 'Hari ini: ' + todayFu + ' • Terlambat: ' + overdueFu, icon: 'bi-telephone', color: 'blue' },
            { label: 'Campaign', value: data.campaigns.length, hint: 'Aktif: ' + data.campaigns.filter(function(c){return c.status==='aktif';}).length, icon: 'bi-megaphone', color: 'purple' },
            { label: 'Task', value: tasks.length, hint: 'Terbuka: ' + openTasks + ' • Selesai: ' + doneTasks, icon: 'bi-check2-square', color: 'orange' },
            { label: 'Task Terlambat', value: overdueTasks, hint: 'Perlu tindak lanjut', icon: 'bi-exclamation-triangle', color: 'blue' },
            { label: 'Client', value: clients.length, hint: 'Aktif: ' + clients.filter(function(c){return c.status==='aktif';}).length, icon: 'bi-people', color: 'green' },
            { label: 'Target Pendaftaran', value: (target ? Number(target.enrollmentsTarget||0) : 0), hint: 'Capaian periode: ' + (stats.monthEnrollments || 0), icon: 'bi-bullseye', color: 'purple' }
        ];
        container.innerHTML = cards.map(function (c) {
            return '<div class="stat-card">'
                + '<div class="stat-card-icon ' + c.color + '"><i class="bi ' + c.icon + '"></i></div>'
                + '<div class="stat-card-info">'
                + '<div class="stat-card-value">' + escapeHtml(c.value) + '</div>'
                + '<div class="stat-card-label">' + escapeHtml(c.label) + '</div>'
                + '<div class="form-hint" style="margin: .15rem 0 0;">' + escapeHtml(c.hint) + '</div>'
                + '</div></div>';
        }).join('');
    }

    function renderBarChart(containerId, items, valueKey, labelKey, color) {
        const container = el(containerId);
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="form-hint">Tidak ada data.</div>';
            return;
        }
        const max = Math.max.apply(null, items.map(function (i) { return Number(i[valueKey] || 0); }).concat([1]));
        container.innerHTML = items.map(function (i) {
            const val = Number(i[valueKey] || 0);
            const width = Math.round((val / max) * 100);
            return '<div style="margin-bottom: .5rem;">'
                + '<div style="display:flex; justify-content:space-between; font-size:.85rem; margin-bottom:.2rem;">'
                + '<span>' + escapeHtml(i[labelKey]) + '</span><strong>' + val + '</strong></div>'
                + '<div style="height:10px; background:var(--bg-tertiary, #eef1f5); border-radius:999px; overflow:hidden;">'
                + '<div style="height:100%; width:' + width + '%; background:' + (color || 'var(--primary)') + '; border-radius:999px;"></div>'
                + '</div></div>';
        }).join('');
    }

    function renderCampaignChart(containerId, campaigns) {
        const container = el(containerId);
        if (!container) return;
        if (!campaigns || campaigns.length === 0) {
            container.innerHTML = '<div class="form-hint">Tidak ada data.</div>';
            return;
        }
        const top = campaigns.slice(0, 6);
        const max = Math.max.apply(null, top.map(function (c) { return Math.max(Number(c.leadCount||0), Number(c.enrolledCount||0)); }).concat([1]));
        container.innerHTML = top.map(function (c) {
            const leads = Number(c.leadCount || 0);
            const enrolled = Number(c.enrolledCount || 0);
            const wl = Math.round((leads / max) * 100);
            const we = Math.round((enrolled / max) * 100);
            return '<div style="margin-bottom:.65rem;">'
                + '<div style="font-size:.85rem; font-weight:600; margin-bottom:.2rem;">' + escapeHtml(c.name) + '</div>'
                + '<div style="display:flex; align-items:center; gap:.4rem; margin-bottom:.2rem;"><span style="width:80px; font-size:.75rem; color:var(--text-secondary);">Lead ' + leads + '</span>'
                + '<div style="flex:1; height:8px; background:var(--bg-tertiary,#eef1f5); border-radius:999px; overflow:hidden;"><div style="height:100%; width:' + wl + '%; background:#3b82f6; border-radius:999px;"></div></div></div>'
                + '<div style="display:flex; align-items:center; gap:.4rem;"><span style="width:80px; font-size:.75rem; color:var(--text-secondary);">Daftar ' + enrolled + '</span>'
                + '<div style="flex:1; height:8px; background:var(--bg-tertiary,#eef1f5); border-radius:999px; overflow:hidden;"><div style="height:100%; width:' + we + '%; background:#22c55e; border-radius:999px;"></div></div></div>'
                + '</div>';
        }).join('');
    }

    function renderCharts(data) {
        const pipelineCounts = { baru: 0, dihubungi: 0, prospek: 0, terdaftar: 0, batal: 0 };
        data.leads.forEach(function (l) { if (pipelineCounts[l.status] !== undefined) pipelineCounts[l.status]++; });
        renderBarChart('reportChartPipeline', [
            { label: 'Baru', value: pipelineCounts.baru },
            { label: 'Dihubungi', value: pipelineCounts.dihubungi },
            { label: 'Prospek', value: pipelineCounts.prospek },
            { label: 'Terdaftar', value: pipelineCounts.terdaftar },
            { label: 'Batal', value: pipelineCounts.batal }
        ], 'value', 'label', '#e98c1e');

        const taskCounts = { belum: 0, proses: 0, selesai: 0, batal: 0 };
        data.tasks.forEach(function (t) { if (taskCounts[t.status] !== undefined) taskCounts[t.status]++; });
        renderBarChart('reportChartTasks', [
            { label: 'Belum', value: taskCounts.belum },
            { label: 'Proses', value: taskCounts.proses },
            { label: 'Selesai', value: taskCounts.selesai },
            { label: 'Batal', value: taskCounts.batal }
        ], 'value', 'label', '#3b82f6');

        const clientCounts = { prospek: 0, aktif: 0, nonaktif: 0 };
        data.clients.forEach(function (c) { if (clientCounts[c.status] !== undefined) clientCounts[c.status]++; });
        renderBarChart('reportChartClients', [
            { label: 'Prospek', value: clientCounts.prospek },
            { label: 'Aktif', value: clientCounts.aktif },
            { label: 'Nonaktif', value: clientCounts.nonaktif }
        ], 'value', 'label', '#22c55e');

        renderCampaignChart('reportChartCampaigns', data.campaigns);
    }

    function emptyRow(cols, text) {
        return '<tr><td colspan="' + cols + '" style="text-align:center; color: var(--text-secondary);">' + escapeHtml(text) + '</td></tr>';
    }

    function renderTables(data) {
        const leadBody = el('reportLeadBody');
        if (leadBody) {
            el('reportLeadCount').textContent = data.leads.length + ' data';
            leadBody.innerHTML = data.leads.length ? data.leads.map(function (l) {
                const contact = [l.phone, l.email].filter(Boolean).join(' / ') || '-';
                const next = l.nextFollowupDate ? formatDate(l.nextFollowupDate) : '-';
                return '<tr><td>' + escapeHtml(l.name) + '</td><td>' + escapeHtml(contact) + '</td><td>'
                    + escapeHtml((ELIPS.marketingSourceLabel ? ELIPS.marketingSourceLabel(l.source) : l.source) || '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingStatusLabel ? ELIPS.marketingStatusLabel(l.status) : l.status) || '-') + '</td><td>'
                    + escapeHtml(l.campaignName || '-') + '</td><td>' + escapeHtml(next) + '</td><td>'
                    + escapeHtml(l.status === 'terdaftar' ? formatRupiah(l.enrollmentValue) : '-') + '</td></tr>';
            }).join('') : emptyRow(7, 'Tidak ada data lead pada filter ini.');
        }

        const campBody = el('reportCampaignBody');
        if (campBody) {
            el('reportCampaignCount').textContent = data.campaigns.length + ' data';
            campBody.innerHTML = data.campaigns.length ? data.campaigns.map(function (c) {
                const period = c.startDate || c.endDate ? (formatDate(c.startDate) + ' - ' + formatDate(c.endDate)) : '-';
                return '<tr><td>' + escapeHtml(c.name) + '</td><td>'
                    + escapeHtml((ELIPS.marketingCampaignChannelLabel ? ELIPS.marketingCampaignChannelLabel(c.channel) : c.channel) || '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingCampaignStatusLabel ? ELIPS.marketingCampaignStatusLabel(c.status) : c.status) || '-') + '</td><td>'
                    + escapeHtml(period) + '</td><td>' + escapeHtml(c.leadCount || 0) + '</td><td>' + escapeHtml(c.enrolledCount || 0) + '</td><td>'
                    + escapeHtml(formatRupiah(c.revenue || 0)) + '</td></tr>';
            }).join('') : emptyRow(7, 'Tidak ada data campaign pada filter ini.');
        }

        const taskBody = el('reportTaskBody');
        if (taskBody) {
            el('reportTaskCount').textContent = data.tasks.length + ' data';
            taskBody.innerHTML = data.tasks.length ? data.tasks.map(function (t) {
                return '<tr><td>' + escapeHtml(t.title) + '</td><td>'
                    + escapeHtml((ELIPS.marketingTaskTypeLabel ? ELIPS.marketingTaskTypeLabel(t.type) : t.type) || '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingTaskPriorityLabel ? ELIPS.marketingTaskPriorityLabel(t.priority) : t.priority) || '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingTaskStatusLabel ? ELIPS.marketingTaskStatusLabel(t.status) : t.status) || '-') + '</td><td>'
                    + escapeHtml(t.dueDate ? formatDate(t.dueDate) : '-') + '</td><td>'
                    + escapeHtml(t.campaignName || '-') + '</td><td>' + escapeHtml(t.leadName || '-') + '</td></tr>';
            }).join('') : emptyRow(7, 'Tidak ada data task pada filter ini.');
        }

        const clientBody = el('reportClientBody');
        if (clientBody) {
            el('reportClientCount').textContent = data.clients.length + ' data';
            clientBody.innerHTML = data.clients.length ? data.clients.map(function (c) {
                const contact = [c.phone, c.email].filter(Boolean).join(' / ') || '-';
                const name = c.company ? (c.name + ' (' + c.company + ')') : c.name;
                return '<tr><td>' + escapeHtml(name) + '</td><td>' + escapeHtml(c.pic || '-') + '</td><td>'
                    + escapeHtml(contact) + '</td><td>' + escapeHtml(c.city || '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingClientStatusLabel ? ELIPS.marketingClientStatusLabel(c.status) : c.status) || '-') + '</td><td>'
                    + escapeHtml(c.campaignName || '-') + '</td></tr>';
            }).join('') : emptyRow(6, 'Tidak ada data client pada filter ini.');
        }

        const fuBody = el('reportFollowupBody');
        if (fuBody) {
            el('reportFollowupCount').textContent = data.followups.length + ' data';
            fuBody.innerHTML = data.followups.length ? data.followups.map(function (f) {
                const lead = (state.dashboard && state.dashboard.leads ? state.dashboard.leads : []).find(function (l) { return Number(l.id) === Number(f.leadId); });
                const leadName = (lead && lead.name) || f.leadName || '-';
                return '<tr><td>' + escapeHtml(leadName) + '</td><td>' + escapeHtml(f.followupDate ? formatDate(f.followupDate) : '-') + '</td><td>'
                    + escapeHtml((ELIPS.marketingChannelLabel ? ELIPS.marketingChannelLabel(f.channel) : f.channel) || '-') + '</td><td>'
                    + escapeHtml(f.outcome || '-') + '</td><td>' + escapeHtml(f.nextDate ? formatDate(f.nextDate) : '-') + '</td><td>'
                    + escapeHtml(f.note || '-') + '</td></tr>';
            }).join('') : emptyRow(6, 'Tidak ada data follow-up pada filter ini.');
        }
    }

    function render() {
        const data = applyFilters();
        const label = el('reportPeriodLabel');
        if (label) label.textContent = 'Periode: ' + data.range.label + ' • Data: ' + (data.leads.length + data.campaigns.length + data.tasks.length + data.clients.length + data.followups.length) + ' entri terfilter';
        renderSummaryCards(data);
        renderCharts(data);
        renderTables(data);
        renderCampaignFilterOptions();
    }

    function renderCampaignFilterOptions() {
        const select = el('reportCampaign');
        if (!select) return;
        const current = select.value;
        const campaigns = state.campaigns;
        const opts = ['<option value="">Semua campaign</option>'];
        campaigns.forEach(function (c) {
            opts.push('<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>');
        });
        select.innerHTML = opts.join('');
        if (current) select.value = current;
    }

    function populateOwnerOptions() {
        const user = state.session;
        const group = el('reportOwnerGroup');
        const select = el('reportOwner');
        if (!group || !select) return;
        if (user.role !== 'admin') {
            group.style.display = 'none';
            return;
        }
        group.style.display = '';
        const opts = ['<option value="">Semua Karyawan Marketing</option>'];
        if (typeof ELIPS.getUsers === 'function') {
            const users = ELIPS.getUsers().filter(function (u) {
                return u && u.role === 'karyawan' && u.division === 'marketing';
            });
            users.forEach(function (u) {
                opts.push('<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>');
            });
        } else {
            opts.push('<option value="' + user.id + '">' + escapeHtml(user.name) + '</option>');
        }
        select.innerHTML = opts.join('');
    }

    function updatePeriodFields() {
        const mode = (el('reportPeriodMode') || {}).value || 'month';
        document.querySelectorAll('.report-period-field').forEach(function (node) {
            const modes = String(node.getAttribute('data-mode') || '').split(',');
            node.style.display = modes.indexOf(mode) !== -1 ? '' : 'none';
        });
    }

    function initDefaults() {
        const month = el('reportMonth');
        if (month) month.value = currentPeriod();
        const day = el('reportDay');
        if (day) day.value = todayString();
        const from = el('reportFrom');
        const to = el('reportTo');
        if (from) from.value = currentPeriod() + '-01';
        if (to) to.value = todayString();
        updatePeriodFields();
    }

    function bind() {
        ['reportOwner', 'reportPeriodMode', 'reportMonth', 'reportDay', 'reportWeek', 'reportFrom', 'reportTo', 'reportCampaign', 'reportLeadStatus', 'reportTaskStatus'].forEach(function (id) {
            const node = el(id);
            if (!node) return;
            node.addEventListener('change', function () {
                if (id === 'reportPeriodMode') updatePeriodFields();
                if (id === 'reportOwner') {
                    const scopeLabel = el('reportScopeLabel');
                    if (scopeLabel) scopeLabel.textContent = getScopeUserId() ? 'Data Karyawan Terpilih' : 'Semua Data Marketing';
                    loadData();
                }
                render();
            });
        });

        const refresh = el('reportRefresh');
        if (refresh) refresh.addEventListener('click', function () {
            loadData();
            render();
            showToast('Laporan dimuat ulang.');
        });

        [['reportExportCsv', 'CSV'], ['reportExportExcel', 'Excel'], ['reportExportPdf', 'PDF']].forEach(function (pair) {
            const btn = el(pair[0]);
            if (btn) btn.addEventListener('click', function () {
                showToast('Ekspor ' + pair[1] + ' akan tersedia segera (placeholder).');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const user = ELIPS.requireRole('karyawan');
        if (!user) return;
        if (user.division !== 'marketing' && user.role !== 'admin') {
            window.location.replace(ELIPS.basePathPrefix() + 'employee/shared/dashboard.php');
            return;
        }
        state.session = user;

        const divisionLabel = ELIPS.divisionLabel(user.division) || (user.role === 'admin' ? 'Admin' : 'Marketing');
        const initials = (user.name || 'K').substring(0, 2).toUpperCase();
        const nameEl = el('employeeName'); if (nameEl) nameEl.textContent = user.name;
        const avatarEl = el('employeeAvatar'); if (avatarEl) avatarEl.textContent = initials;
        const divisionEl = el('employeeDivision'); if (divisionEl) divisionEl.textContent = (user.role === 'admin' ? 'Admin' : 'Karyawan') + ' • ' + divisionLabel;
        const greetingEl = el('reportGreeting'); if (greetingEl) greetingEl.textContent = 'Laporan lengkap kinerja marketing, ' + user.name + '.';

        initDefaults();
        populateOwnerOptions();
        bind();
        loadData();
        render();

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key.indexOf('marketing') !== -1 || event.key === ELIPS.KEYS.session) {
                loadData();
                render();
            }
        });
    });
})(window.ELIPS);
