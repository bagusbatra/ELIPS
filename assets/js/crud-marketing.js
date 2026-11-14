// ============================================================
// ELIPS LMS - crud-marketing.js
// Logika bisnis Marketing: lead, follow-up, target bulanan, dan
// agregasi dashboard. Mode server memakai aksi marketing.*; mode
// lokal menyimpan ke localStorage dengan aturan validasi yang sama.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString, isValidEmail } = ELIPS._;

    const LEAD_STATUSES = ['baru', 'dihubungi', 'prospek', 'terdaftar', 'batal'];
    const LEAD_SOURCES = ['walk_in', 'referensi', 'sekolah', 'sosial_media', 'pameran', 'telemarketing', 'lainnya'];
    const FOLLOWUP_CHANNELS = ['telepon', 'whatsapp', 'email', 'kunjungan', 'lainnya'];
    const CAMPAIGN_STATUSES = ['draft', 'aktif', 'selesai', 'batal'];
    const CAMPAIGN_CHANNELS = ['sosial_media', 'email', 'pameran', 'sekolah', 'komunitas', 'telemarketing', 'lainnya'];
    const TASK_STATUSES = ['belum', 'proses', 'selesai', 'batal'];
    const TASK_PRIORITIES = ['rendah', 'sedang', 'tinggi'];
    const TASK_TYPES = ['follow_up', 'campaign', 'administrasi', 'lainnya'];

    const STATUS_LABELS = {
        baru: 'Baru',
        dihubungi: 'Dihubungi',
        prospek: 'Prospek',
        terdaftar: 'Terdaftar',
        batal: 'Batal'
    };

    const SOURCE_LABELS = {
        walk_in: 'Walk-in',
        referensi: 'Referensi',
        sekolah: 'Sekolah',
        sosial_media: 'Sosial Media',
        pameran: 'Pameran',
        telemarketing: 'Telemarketing',
        lainnya: 'Lainnya'
    };

    const CHANNEL_LABELS = {
        telepon: 'Telepon',
        whatsapp: 'WhatsApp',
        email: 'Email',
        kunjungan: 'Kunjungan',
        lainnya: 'Lainnya'
    };

    const CAMPAIGN_STATUS_LABELS = {
        draft: 'Draft',
        aktif: 'Aktif',
        selesai: 'Selesai',
        batal: 'Batal'
    };

    const CAMPAIGN_CHANNEL_LABELS = {
        sosial_media: 'Sosial Media',
        email: 'Email',
        pameran: 'Pameran',
        sekolah: 'Sekolah',
        komunitas: 'Komunitas',
        telemarketing: 'Telemarketing',
        lainnya: 'Lainnya'
    };

    const TASK_STATUS_LABELS = {
        belum: 'Belum',
        proses: 'Proses',
        selesai: 'Selesai',
        batal: 'Batal'
    };

    const TASK_PRIORITY_LABELS = {
        rendah: 'Rendah',
        sedang: 'Sedang',
        tinggi: 'Tinggi'
    };

    const TASK_TYPE_LABELS = {
        follow_up: 'Follow-up',
        campaign: 'Campaign',
        administrasi: 'Administrasi',
        lainnya: 'Lainnya'
    };

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function sessionUser() {
        return typeof ELIPS.getSessionUser === 'function' ? ELIPS.getSessionUser() : null;
    }

    function currentPeriod() {
        return todayString().slice(0, 7);
    }

    function normalizeDate(value) {
        const text = String(value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return null;
        const parts = text.split('-');
        const year = Number(parts[0]);
        const month = Number(parts[1]);
        const day = Number(parts[2]);
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }
        return text;
    }

    function validPeriod(value) {
        return /^\d{4}-(0[1-9]|1[0-2])$/.test(String(value || ''));
    }

    function ownerName(ownerId) {
        const user = typeof ELIPS.getUserById === 'function' ? ELIPS.getUserById(ownerId) : null;
        return user ? user.name : '';
    }

    function statusLabel(status) {
        return STATUS_LABELS[status] || status || '-';
    }

    function sourceLabel(source) {
        return SOURCE_LABELS[source] || source || '-';
    }

    function channelLabel(channel) {
        return CHANNEL_LABELS[channel] || channel || '-';
    }

    function campaignStatusLabel(status) {
        return CAMPAIGN_STATUS_LABELS[status] || status || '-';
    }

    function campaignChannelLabel(channel) {
        return CAMPAIGN_CHANNEL_LABELS[channel] || channel || '-';
    }

    function taskStatusLabel(status) {
        return TASK_STATUS_LABELS[status] || status || '-';
    }

    function taskPriorityLabel(priority) {
        return TASK_PRIORITY_LABELS[priority] || priority || '-';
    }

    function taskTypeLabel(type) {
        return TASK_TYPE_LABELS[type] || type || '-';
    }

    function resolveOwnerId(data, existing) {
        const user = sessionUser();
        if (existing) return Number(existing.ownerId);
        const requested = Number(data.ownerId || 0);
        if (user && user.role === 'admin') {
            return requested > 0 ? requested : Number(user.id);
        }
        if (user && user.id) return Number(user.id);
        return requested;
    }

    function normalizeLeadPayload(data, existing) {
        data = data || {};
        const ownerId = resolveOwnerId(data, existing);
        const name = String(data.name !== undefined ? data.name : (existing ? existing.name : '')).trim();
        const phone = String(data.phone !== undefined ? data.phone : (existing ? existing.phone : '')).trim();
        const email = String(data.email !== undefined ? data.email : (existing ? existing.email : '')).trim();
        const city = String(data.city !== undefined ? data.city : (existing ? existing.city : '')).trim();
        const source = String(data.source !== undefined ? data.source : (existing ? existing.source : 'lainnya')).trim();
        const status = String(data.status !== undefined ? data.status : (existing ? existing.status : 'baru')).trim();
        const interest = String(data.interest !== undefined ? data.interest : (existing ? existing.interest : '')).trim();
        const notes = String(data.notes !== undefined ? data.notes : (existing ? existing.notes : '')).trim();
        const nextRaw = data.nextFollowupDate !== undefined
            ? data.nextFollowupDate
            : (existing ? existing.nextFollowupDate : '');
        const nextFollowupDate = nextRaw ? normalizeDate(nextRaw) : null;
        const enrollmentRaw = data.enrollmentValue !== undefined
            ? data.enrollmentValue
            : (existing ? existing.enrollmentValue : 0);
        const enrollmentValue = Number(enrollmentRaw || 0);
        const campaignRaw = data.campaignId !== undefined
            ? data.campaignId
            : (existing ? existing.campaignId : '');
        const campaignId = Number(campaignRaw || 0);
        let campaign = null;
        if (campaignId > 0) {
            campaign = typeof ELIPS.getMarketingCampaign === 'function'
                ? ELIPS.getMarketingCampaign(campaignId)
                : null;
            if (!campaign) return { error: 'Campaign tidak ditemukan.' };
            if (Number(campaign.ownerId) !== Number(ownerId)) {
                return { error: 'Campaign harus milik pemilik lead yang sama.' };
            }
        }

        if (!ownerId) return { error: 'Pemilik lead tidak valid.' };
        if (!name) return { error: 'Nama lead wajib diisi.' };
        if (name.length > 150) return { error: 'Nama lead maksimal 150 karakter.' };
        if (!phone && !email) return { error: 'Isi minimal salah satu kontak: telepon atau email.' };
        if (phone.length > 30) return { error: 'Nomor telepon maksimal 30 karakter.' };
        if (email && (!isValidEmail(email) || email.length > 190)) {
            return { error: 'Alamat email lead tidak valid.' };
        }
        if (city.length > 100) return { error: 'Kota maksimal 100 karakter.' };
        if (LEAD_SOURCES.indexOf(source) === -1) return { error: 'Sumber lead tidak valid.' };
        if (LEAD_STATUSES.indexOf(status) === -1) return { error: 'Status lead tidak valid.' };
        if (interest.length > 150) return { error: 'Minat kursus maksimal 150 karakter.' };
        if (notes.length > 1000) return { error: 'Catatan lead maksimal 1000 karakter.' };
        if (nextRaw && !nextFollowupDate) return { error: 'Tanggal follow-up berikutnya tidak valid.' };
        if (!isFinite(enrollmentValue) || enrollmentValue < 0) {
            return { error: 'Nilai pendaftaran harus berupa angka tidak negatif.' };
        }

        return {
            ownerId: ownerId,
            campaignId: campaignId > 0 ? campaignId : null,
            name: name,
            phone: phone,
            email: email,
            city: city,
            source: source,
            status: status,
            interest: interest,
            notes: notes,
            nextFollowupDate: nextFollowupDate,
            enrollmentValue: Math.round(enrollmentValue * 100) / 100,
            convertedAt: status === 'terdaftar'
                ? (existing && existing.convertedAt ? existing.convertedAt : new Date().toISOString())
                : null
        };
    }

    function saveMarketingLead(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.lead.save', data || {});
        }

        data = data || {};
        const id = Number(data.id || 0);
        const leads = ELIPS.getMarketingLeads();
        const existing = id > 0
            ? leads.find(function (lead) { return Number(lead.id) === id; })
            : null;
        if (id > 0 && !existing) return failure('Lead tidak ditemukan.');

        const user = sessionUser();
        if (existing && user && user.role !== 'admin' && Number(existing.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat mengubah lead milik Anda.');
        }

        const normalized = normalizeLeadPayload(data, existing);
        if (normalized.error) return failure(normalized.error);

        const now = new Date().toISOString();
        let lead;
        if (existing) {
            lead = Object.assign(existing, normalized, { updatedAt: now });
            ELIPS.setMarketingLeads(leads);
        } else {
            lead = Object.assign({}, normalized, {
                id: ELIPS.nextId(leads),
                ownerName: ownerName(normalized.ownerId),
                createdAt: now,
                updatedAt: now
            });
            leads.push(lead);
            ELIPS.setMarketingLeads(leads);
        }

        recordAudit({
            action: existing ? 'update' : 'create',
            entity: 'marketing_lead',
            entityId: lead.id,
            entityLabel: lead.name,
            actorId: user ? user.id : undefined,
            detail: (existing ? 'Mengubah' : 'Menambahkan') + ' lead "' + lead.name + '"'
                + ' sumber ' + sourceLabel(lead.source)
                + ' status ' + statusLabel(lead.status)
                + (lead.nextFollowupDate ? ' follow-up ' + lead.nextFollowupDate : '') + '.'
        });
        return success(existing ? 'Lead diperbarui.' : 'Lead ditambahkan.', lead);
    }

    function deleteMarketingLead(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.lead.delete', { id: id });
        }

        const leads = ELIPS.getMarketingLeads();
        const lead = leads.find(function (row) { return Number(row.id) === Number(id); });
        if (!lead) return failure('Lead tidak ditemukan.');

        const user = sessionUser();
        if (user && user.role !== 'admin' && Number(lead.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat menghapus lead milik Anda.');
        }

        ELIPS.setMarketingLeads(leads.filter(function (row) { return Number(row.id) !== Number(id); }));
        ELIPS.setMarketingFollowups(ELIPS.getMarketingFollowups().filter(function (row) {
            return Number(row.leadId) !== Number(id);
        }));
        const tasks = ELIPS.getMarketingTasks();
        let unlinked = 0;
        tasks.forEach(function (task) {
            if (Number(task.leadId) === Number(id)) {
                task.leadId = null;
                task.leadName = '';
                unlinked++;
            }
        });
        if (unlinked > 0) ELIPS.setMarketingTasks(tasks);

        recordAudit({
            action: 'delete',
            entity: 'marketing_lead',
            entityId: lead.id,
            entityLabel: lead.name,
            actorId: user ? user.id : undefined,
            detail: 'Menghapus lead "' + lead.name + '" sumber ' + sourceLabel(lead.source)
                + ' status ' + statusLabel(lead.status) + '.'
        });
        return success('Lead dihapus.');
    }

    function saveMarketingFollowup(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.followup.save', data || {});
        }

        data = data || {};
        const id = Number(data.id || 0);
        const followups = ELIPS.getMarketingFollowups();
        const existing = id > 0
            ? followups.find(function (row) { return Number(row.id) === id; })
            : null;
        if (id > 0 && !existing) return failure('Follow-up tidak ditemukan.');

        const user = sessionUser();
        if (existing && user && user.role !== 'admin' && Number(existing.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat mengubah follow-up milik Anda.');
        }

        const leadId = Number(data.leadId || (existing ? existing.leadId : 0));
        const leads = ELIPS.getMarketingLeads();
        const lead = leads.find(function (row) { return Number(row.id) === leadId; });
        if (!lead) return failure('Lead tidak ditemukan.');
        if (user && user.role !== 'admin' && Number(lead.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat mencatat follow-up untuk lead milik Anda.');
        }

        const channel = String(data.channel !== undefined ? data.channel : (existing ? existing.channel : 'telepon')).trim();
        const followupDate = normalizeDate(data.followupDate !== undefined
            ? data.followupDate
            : (existing ? existing.followupDate : ''));
        const note = String(data.note !== undefined ? data.note : (existing ? existing.note : '')).trim();
        const outcome = String(data.outcome !== undefined ? data.outcome : (existing ? existing.outcome : '')).trim();
        const nextRaw = data.nextDate !== undefined ? data.nextDate : (existing ? existing.nextDate : '');
        const nextDate = nextRaw ? normalizeDate(nextRaw) : null;

        if (FOLLOWUP_CHANNELS.indexOf(channel) === -1) return failure('Kanal follow-up tidak valid.');
        if (!followupDate) return failure('Tanggal follow-up wajib diisi dengan format yang valid.');
        if (note.length > 1000) return failure('Catatan follow-up maksimal 1000 karakter.');
        if (outcome.length > 200) return failure('Hasil follow-up maksimal 200 karakter.');
        if (nextRaw && !nextDate) return failure('Tanggal follow-up berikutnya tidak valid.');

        const now = new Date().toISOString();
        const payload = {
            leadId: leadId,
            leadName: lead.name,
            ownerId: lead.ownerId,
            channel: channel,
            followupDate: followupDate,
            note: note,
            outcome: outcome,
            nextDate: nextDate
        };

        let followup;
        if (existing) {
            followup = Object.assign(existing, payload, { updatedAt: now });
            ELIPS.setMarketingFollowups(followups);
        } else {
            followup = Object.assign({}, payload, {
                id: ELIPS.nextId(followups),
                createdAt: now,
                updatedAt: now
            });
            followups.push(followup);
            ELIPS.setMarketingFollowups(followups);
        }

        lead.nextFollowupDate = nextDate;
        if (lead.status === 'baru') lead.status = 'dihubungi';
        lead.updatedAt = now;
        ELIPS.setMarketingLeads(leads);

        recordAudit({
            action: existing ? 'update' : 'create',
            entity: 'marketing_followup',
            entityId: followup.id,
            entityLabel: lead.name + ' — ' + channelLabel(channel),
            actorId: user ? user.id : undefined,
            detail: (existing ? 'Mengubah' : 'Mencatat') + ' follow-up ' + channelLabel(channel)
                + ' untuk lead "' + lead.name + '" tanggal ' + followupDate
                + (outcome ? ' hasil: ' + outcome : '')
                + (nextDate ? ' berikutnya ' + nextDate : '') + '.'
        });
        return success(existing ? 'Follow-up diperbarui.' : 'Follow-up dicatat.', followup);
    }

    function deleteMarketingFollowup(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.followup.delete', { id: id });
        }

        const followups = ELIPS.getMarketingFollowups();
        const followup = followups.find(function (row) { return Number(row.id) === Number(id); });
        if (!followup) return failure('Follow-up tidak ditemukan.');

        const user = sessionUser();
        if (user && user.role !== 'admin' && Number(followup.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat menghapus follow-up milik Anda.');
        }

        ELIPS.setMarketingFollowups(followups.filter(function (row) {
            return Number(row.id) !== Number(id);
        }));

        const leads = ELIPS.getMarketingLeads();
        const lead = leads.find(function (row) { return Number(row.id) === Number(followup.leadId); });
        if (lead) {
            const remaining = ELIPS.getMarketingFollowupsByLead(lead.id);
            const nextDates = remaining.map(function (row) { return row.nextDate; }).filter(Boolean).sort();
            lead.nextFollowupDate = nextDates.length ? nextDates[nextDates.length - 1] : null;
            lead.updatedAt = new Date().toISOString();
            ELIPS.setMarketingLeads(leads);
        }

        recordAudit({
            action: 'delete',
            entity: 'marketing_followup',
            entityId: followup.id,
            entityLabel: (followup.leadName || 'Lead') + ' — ' + channelLabel(followup.channel),
            actorId: user ? user.id : undefined,
            detail: 'Menghapus follow-up ' + channelLabel(followup.channel)
                + ' untuk lead "' + (followup.leadName || '') + '" tanggal ' + followup.followupDate + '.'
        });
        return success('Follow-up dihapus.');
    }

    function saveMarketingTarget(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.target.save', data || {});
        }

        data = data || {};
        const user = sessionUser();
        const ownerId = Number(data.ownerId || (user ? user.id : 0));
        const period = String(data.period || currentPeriod());
        const leadsTarget = Number(data.leadsTarget || 0);
        const followupsTarget = Number(data.followupsTarget || 0);
        const enrollmentsTarget = Number(data.enrollmentsTarget || 0);
        const revenueTarget = Number(data.revenueTarget || 0);

        if (!ownerId) return failure('Pemilik target tidak valid.');
        if (!validPeriod(period)) return failure('Periode target tidak valid (format YYYY-MM).');
        if (!isFinite(leadsTarget) || leadsTarget < 0
            || !isFinite(followupsTarget) || followupsTarget < 0
            || !isFinite(enrollmentsTarget) || enrollmentsTarget < 0) {
            return failure('Target jumlah tidak boleh negatif.');
        }
        if (!isFinite(revenueTarget) || revenueTarget < 0) {
            return failure('Target pendapatan harus berupa angka tidak negatif.');
        }

        const targets = ELIPS.getMarketingTargets();
        const existing = targets.find(function (row) {
            return Number(row.userId) === ownerId && String(row.period) === period;
        });
        const now = new Date().toISOString();
        let target;
        if (existing) {
            target = Object.assign(existing, {
                leadsTarget: Math.round(leadsTarget),
                followupsTarget: Math.round(followupsTarget),
                enrollmentsTarget: Math.round(enrollmentsTarget),
                revenueTarget: Math.round(revenueTarget * 100) / 100,
                updatedAt: now
            });
            ELIPS.setMarketingTargets(targets);
        } else {
            target = {
                id: ELIPS.nextId(targets),
                userId: ownerId,
                period: period,
                leadsTarget: Math.round(leadsTarget),
                followupsTarget: Math.round(followupsTarget),
                enrollmentsTarget: Math.round(enrollmentsTarget),
                revenueTarget: Math.round(revenueTarget * 100) / 100,
                createdAt: now,
                updatedAt: now
            };
            targets.push(target);
            ELIPS.setMarketingTargets(targets);
        }

        recordAudit({
            action: existing ? 'update' : 'create',
            entity: 'marketing_target',
            entityId: target.id,
            entityLabel: 'Target ' + period,
            actorId: user ? user.id : undefined,
            detail: 'Menyimpan target Marketing periode ' + period
                + ': lead ' + target.leadsTarget
                + ', follow-up ' + target.followupsTarget
                + ', pendaftaran ' + target.enrollmentsTarget
                + ', pendapatan ' + target.revenueTarget + '.'
        });
        return success('Target Marketing disimpan.', target);
    }

    function normalizeCampaignPayload(data, existing) {
        data = data || {};
        const ownerId = resolveOwnerId(data, existing);
        const name = String(data.name !== undefined ? data.name : (existing ? existing.name : '')).trim();
        const channel = String(data.channel !== undefined ? data.channel : (existing ? existing.channel : 'lainnya')).trim();
        const status = String(data.status !== undefined ? data.status : (existing ? existing.status : 'draft')).trim();
        const startRaw = data.startDate !== undefined
            ? data.startDate
            : (existing ? existing.startDate : '');
        const endRaw = data.endDate !== undefined
            ? data.endDate
            : (existing ? existing.endDate : '');
        const startDate = startRaw ? normalizeDate(startRaw) : null;
        const endDate = endRaw ? normalizeDate(endRaw) : null;
        const budget = Number(data.budget !== undefined ? data.budget : (existing ? existing.budget : 0));
        const targetLeads = Number(data.targetLeads !== undefined
            ? data.targetLeads
            : (existing ? existing.targetLeads : 0));
        const targetEnrollments = Number(data.targetEnrollments !== undefined
            ? data.targetEnrollments
            : (existing ? existing.targetEnrollments : 0));
        const notes = String(data.notes !== undefined ? data.notes : (existing ? existing.notes : '')).trim();

        if (!ownerId) return { error: 'Pemilik campaign tidak valid.' };
        if (!name) return { error: 'Nama campaign wajib diisi.' };
        if (name.length > 150) return { error: 'Nama campaign maksimal 150 karakter.' };
        if (CAMPAIGN_CHANNELS.indexOf(channel) === -1) return { error: 'Kanal campaign tidak valid.' };
        if (CAMPAIGN_STATUSES.indexOf(status) === -1) return { error: 'Status campaign tidak valid.' };
        if (startRaw && !startDate) return { error: 'Tanggal mulai campaign tidak valid.' };
        if (endRaw && !endDate) return { error: 'Tanggal selesai campaign tidak valid.' };
        if (startDate && endDate && endDate < startDate) {
            return { error: 'Tanggal selesai tidak boleh sebelum tanggal mulai.' };
        }
        if (!isFinite(budget) || budget < 0) {
            return { error: 'Budget campaign harus berupa angka tidak negatif.' };
        }
        if (!isFinite(targetLeads) || targetLeads < 0
            || !isFinite(targetEnrollments) || targetEnrollments < 0) {
            return { error: 'Target campaign tidak boleh negatif.' };
        }
        if (notes.length > 1000) return { error: 'Catatan campaign maksimal 1000 karakter.' };

        return {
            ownerId: ownerId,
            name: name,
            channel: channel,
            status: status,
            startDate: startDate,
            endDate: endDate,
            budget: Math.round(budget * 100) / 100,
            targetLeads: Math.round(targetLeads),
            targetEnrollments: Math.round(targetEnrollments),
            notes: notes
        };
    }

    function saveMarketingCampaign(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.campaign.save', data || {});
        }

        data = data || {};
        const id = Number(data.id || 0);
        const campaigns = ELIPS.getMarketingCampaigns();
        const existing = id > 0
            ? campaigns.find(function (campaign) { return Number(campaign.id) === id; })
            : null;
        if (id > 0 && !existing) return failure('Campaign tidak ditemukan.');

        const user = sessionUser();
        if (existing && user && user.role !== 'admin' && Number(existing.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat mengubah campaign milik Anda.');
        }

        const normalized = normalizeCampaignPayload(data, existing);
        if (normalized.error) return failure(normalized.error);

        const now = new Date().toISOString();
        let campaign;
        if (existing) {
            campaign = Object.assign(existing, normalized, { updatedAt: now });
            ELIPS.setMarketingCampaigns(campaigns);
        } else {
            campaign = Object.assign({}, normalized, {
                id: ELIPS.nextId(campaigns),
                ownerName: ownerName(normalized.ownerId),
                leadCount: 0,
                enrolledCount: 0,
                revenue: 0,
                createdAt: now,
                updatedAt: now
            });
            campaigns.push(campaign);
            ELIPS.setMarketingCampaigns(campaigns);
        }

        recordAudit({
            action: existing ? 'update' : 'create',
            entity: 'marketing_campaign',
            entityId: campaign.id,
            entityLabel: campaign.name,
            actorId: user ? user.id : undefined,
            detail: (existing ? 'Mengubah' : 'Menambahkan') + ' campaign "' + campaign.name + '"'
                + ' kanal ' + campaignChannelLabel(campaign.channel)
                + ' status ' + campaignStatusLabel(campaign.status)
                + (campaign.startDate ? ' mulai ' + campaign.startDate : '')
                + (campaign.endDate ? ' selesai ' + campaign.endDate : '') + '.'
        });
        return success(existing ? 'Campaign diperbarui.' : 'Campaign ditambahkan.', campaign);
    }

    function deleteMarketingCampaign(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.campaign.delete', { id: id });
        }

        const campaigns = ELIPS.getMarketingCampaigns();
        const campaign = campaigns.find(function (row) { return Number(row.id) === Number(id); });
        if (!campaign) return failure('Campaign tidak ditemukan.');

        const user = sessionUser();
        if (user && user.role !== 'admin' && Number(campaign.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat menghapus campaign milik Anda.');
        }

        ELIPS.setMarketingCampaigns(campaigns.filter(function (row) {
            return Number(row.id) !== Number(id);
        }));

        const leads = ELIPS.getMarketingLeads();
        let linked = 0;
        leads.forEach(function (lead) {
            if (Number(lead.campaignId) === Number(id)) {
                lead.campaignId = null;
                linked++;
            }
        });
        if (linked > 0) ELIPS.setMarketingLeads(leads);

        const tasks = ELIPS.getMarketingTasks();
        let unlinkedTasks = 0;
        tasks.forEach(function (task) {
            if (Number(task.campaignId) === Number(id)) {
                task.campaignId = null;
                task.campaignName = '';
                unlinkedTasks++;
            }
        });
        if (unlinkedTasks > 0) ELIPS.setMarketingTasks(tasks);

        recordAudit({
            action: 'delete',
            entity: 'marketing_campaign',
            entityId: campaign.id,
            entityLabel: campaign.name,
            actorId: user ? user.id : undefined,
            detail: 'Menghapus campaign "' + campaign.name + '" kanal '
                + campaignChannelLabel(campaign.channel)
                + ' status ' + campaignStatusLabel(campaign.status)
                + (linked > 0 ? '. ' + linked + ' lead dilepas dari campaign.' : '.')
                + (unlinkedTasks > 0 ? ' ' + unlinkedTasks + ' task dilepas dari campaign.' : '')
        });
        return success('Campaign dihapus.');
    }

    function normalizeTaskPayload(data, existing) {
        data = data || {};
        const ownerId = resolveOwnerId(data, existing);
        const title = String(data.title !== undefined ? data.title : (existing ? existing.title : '')).trim();
        const description = String(data.description !== undefined
            ? data.description
            : (existing ? existing.description : '')).trim();
        const type = String(data.type !== undefined ? data.type : (existing ? existing.type : 'lainnya')).trim();
        const priority = String(data.priority !== undefined
            ? data.priority
            : (existing ? existing.priority : 'sedang')).trim();
        const status = String(data.status !== undefined ? data.status : (existing ? existing.status : 'belum')).trim();
        const dueRaw = data.dueDate !== undefined ? data.dueDate : (existing ? existing.dueDate : '');
        const dueDate = dueRaw ? normalizeDate(dueRaw) : null;

        const campaignRaw = data.campaignId !== undefined
            ? data.campaignId
            : (existing ? existing.campaignId : '');
        const campaignId = Number(campaignRaw || 0);
        let campaign = null;
        if (campaignId > 0) {
            campaign = typeof ELIPS.getMarketingCampaign === 'function'
                ? ELIPS.getMarketingCampaign(campaignId)
                : null;
            if (!campaign) return { error: 'Campaign tidak ditemukan.' };
            if (Number(campaign.ownerId) !== Number(ownerId)) {
                return { error: 'Campaign harus milik pemilik task yang sama.' };
            }
        }

        const leadRaw = data.leadId !== undefined ? data.leadId : (existing ? existing.leadId : '');
        const leadId = Number(leadRaw || 0);
        let lead = null;
        if (leadId > 0) {
            lead = ELIPS.getMarketingLeads().find(function (row) {
                return Number(row.id) === leadId;
            }) || null;
            if (!lead) return { error: 'Lead tidak ditemukan.' };
            if (Number(lead.ownerId) !== Number(ownerId)) {
                return { error: 'Lead harus milik pemilik task yang sama.' };
            }
        }

        if (!ownerId) return { error: 'Pemilik task tidak valid.' };
        if (!title) return { error: 'Judul task wajib diisi.' };
        if (title.length > 150) return { error: 'Judul task maksimal 150 karakter.' };
        if (description.length > 1000) return { error: 'Deskripsi task maksimal 1000 karakter.' };
        if (TASK_TYPES.indexOf(type) === -1) return { error: 'Jenis task tidak valid.' };
        if (TASK_PRIORITIES.indexOf(priority) === -1) return { error: 'Prioritas task tidak valid.' };
        if (TASK_STATUSES.indexOf(status) === -1) return { error: 'Status task tidak valid.' };
        if (dueRaw && !dueDate) return { error: 'Tenggat task tidak valid.' };

        return {
            ownerId: ownerId,
            campaignId: campaignId > 0 ? campaignId : null,
            campaignName: campaign ? campaign.name : '',
            leadId: leadId > 0 ? leadId : null,
            leadName: lead ? lead.name : '',
            title: title,
            description: description,
            type: type,
            priority: priority,
            status: status,
            dueDate: dueDate
        };
    }

    function saveMarketingTask(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.task.save', data || {});
        }

        data = data || {};
        const id = Number(data.id || 0);
        const tasks = ELIPS.getMarketingTasks();
        const existing = id > 0
            ? tasks.find(function (task) { return Number(task.id) === id; })
            : null;
        if (id > 0 && !existing) return failure('Task tidak ditemukan.');

        const user = sessionUser();
        if (existing && user && user.role !== 'admin' && Number(existing.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat mengubah task milik Anda.');
        }

        const normalized = normalizeTaskPayload(data, existing);
        if (normalized.error) return failure(normalized.error);

        const now = new Date().toISOString();
        let task;
        if (existing) {
            task = Object.assign(existing, normalized, {
                completedAt: normalized.status === 'selesai'
                    ? (existing.completedAt || now)
                    : null,
                updatedAt: now
            });
            ELIPS.setMarketingTasks(tasks);
        } else {
            task = Object.assign({}, normalized, {
                id: ELIPS.nextId(tasks),
                ownerName: ownerName(normalized.ownerId),
                completedAt: normalized.status === 'selesai' ? now : null,
                createdAt: now,
                updatedAt: now
            });
            tasks.push(task);
            ELIPS.setMarketingTasks(tasks);
        }

        recordAudit({
            action: existing ? 'update' : 'create',
            entity: 'marketing_task',
            entityId: task.id,
            entityLabel: task.title,
            actorId: user ? user.id : undefined,
            detail: (existing ? 'Mengubah' : 'Menambahkan') + ' task "' + task.title + '"'
                + ' jenis ' + taskTypeLabel(task.type)
                + ' prioritas ' + taskPriorityLabel(task.priority)
                + ' status ' + taskStatusLabel(task.status)
                + (task.dueDate ? ' tenggat ' + task.dueDate : '') + '.'
        });
        return success(existing ? 'Task diperbarui.' : 'Task ditambahkan.', task);
    }

    function deleteMarketingTask(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('marketing.task.delete', { id: id });
        }

        const tasks = ELIPS.getMarketingTasks();
        const task = tasks.find(function (row) { return Number(row.id) === Number(id); });
        if (!task) return failure('Task tidak ditemukan.');

        const user = sessionUser();
        if (user && user.role !== 'admin' && Number(task.ownerId) !== Number(user.id)) {
            return failure('Anda hanya dapat menghapus task milik Anda.');
        }

        ELIPS.setMarketingTasks(tasks.filter(function (row) {
            return Number(row.id) !== Number(id);
        }));

        recordAudit({
            action: 'delete',
            entity: 'marketing_task',
            entityId: task.id,
            entityLabel: task.title,
            actorId: user ? user.id : undefined,
            detail: 'Menghapus task "' + task.title + '" jenis ' + taskTypeLabel(task.type)
                + ' prioritas ' + taskPriorityLabel(task.priority)
                + ' status ' + taskStatusLabel(task.status) + '.'
        });
        return success('Task dihapus.');
    }

    function localTaskList(userId) {
        const user = sessionUser();
        const scope = (userId === undefined || userId === null || userId === '')
            ? (user && user.role !== 'admin' ? Number(user.id) : 0)
            : Number(userId);
        const statusOrder = { belum: 0, proses: 1, selesai: 2, batal: 3 };
        const priorityOrder = { tinggi: 0, sedang: 1, rendah: 2 };
        const leads = ELIPS.getMarketingLeads();

        const tasks = ELIPS.getMarketingTasks()
            .filter(function (task) { return scope === 0 || Number(task.ownerId) === scope; })
            .map(function (task) {
                const campaign = Number(task.campaignId) > 0 && typeof ELIPS.getMarketingCampaign === 'function'
                    ? ELIPS.getMarketingCampaign(task.campaignId)
                    : null;
                const lead = Number(task.leadId) > 0
                    ? leads.find(function (row) { return Number(row.id) === Number(task.leadId); })
                    : null;
                return Object.assign({}, task, {
                    ownerName: task.ownerName || ownerName(task.ownerId),
                    campaignName: campaign ? campaign.name : (task.campaignName || ''),
                    leadName: lead ? lead.name : (task.leadName || '')
                });
            })
            .sort(function (a, b) {
                const statusDiff = (statusOrder[a.status] ?? 3) - (statusOrder[b.status] ?? 3);
                if (statusDiff !== 0) return statusDiff;
                const priorityDiff = (priorityOrder[a.priority] ?? 3) - (priorityOrder[b.priority] ?? 3);
                if (priorityDiff !== 0) return priorityDiff;
                const dueA = a.dueDate || '9999-12-31';
                const dueB = b.dueDate || '9999-12-31';
                if (dueA !== dueB) return String(dueA).localeCompare(String(dueB));
                return String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
            });

        return success('Task Marketing dimuat.', { tasks: tasks });
    }

    function marketingTasks(userId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = {};
            if (userId !== undefined && userId !== null && userId !== '') {
                payload.userId = Number(userId);
            }
            return ELIPS.api.call('marketing.task.list', payload);
        }
        return localTaskList(userId);
    }

    function localCampaignList(userId) {
        const user = sessionUser();
        const scope = (userId === undefined || userId === null || userId === '')
            ? (user && user.role !== 'admin' ? Number(user.id) : 0)
            : Number(userId);
        const leads = ELIPS.getMarketingLeads();

        const campaigns = ELIPS.getMarketingCampaigns()
            .filter(function (campaign) { return scope === 0 || Number(campaign.ownerId) === scope; })
            .map(function (campaign) {
                const linked = leads.filter(function (lead) {
                    return Number(lead.campaignId) === Number(campaign.id);
                });
                const enrolled = linked.filter(function (lead) { return lead.status === 'terdaftar'; });
                return Object.assign({}, campaign, {
                    ownerName: campaign.ownerName || ownerName(campaign.ownerId),
                    leadCount: linked.length,
                    enrolledCount: enrolled.length,
                    revenue: enrolled.reduce(function (total, lead) {
                        return total + Number(lead.enrollmentValue || 0);
                    }, 0)
                });
            })
            .sort(function (a, b) {
                return String(b.startDate || b.updatedAt || b.createdAt || '')
                    .localeCompare(String(a.startDate || a.updatedAt || a.createdAt || ''));
            });

        return success('Campaign Marketing dimuat.', { campaigns: campaigns });
    }

    function marketingCampaigns(userId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = {};
            if (userId !== undefined && userId !== null && userId !== '') {
                payload.userId = Number(userId);
            }
            return ELIPS.api.call('marketing.campaign.list', payload);
        }
        return localCampaignList(userId);
    }

    function localDashboard(userId, period) {
        const today = todayString();
        const activePeriod = validPeriod(period) ? String(period) : currentPeriod();
        const user = sessionUser();
        const scope = (userId === undefined || userId === null || userId === '')
            ? (user && user.role !== 'admin' ? Number(user.id) : 0)
            : Number(userId);

        const leads = ELIPS.getMarketingLeads()
            .filter(function (lead) { return scope === 0 || Number(lead.ownerId) === scope; })
            .map(function (lead) {
                return Object.assign({}, lead, { ownerName: lead.ownerName || ownerName(lead.ownerId) });
            })
            .sort(function (a, b) {
                return String(b.updatedAt || b.createdAt || '').localeCompare(String(a.updatedAt || a.createdAt || ''));
            });
        const followups = ELIPS.getMarketingFollowups()
            .filter(function (followup) { return scope === 0 || Number(followup.ownerId) === scope; })
            .sort(function (a, b) {
                return String(b.followupDate || '').localeCompare(String(a.followupDate || ''));
            });

        const counts = { baru: 0, dihubungi: 0, prospek: 0, terdaftar: 0, batal: 0 };
        let monthEnrollments = 0;
        let monthRevenue = 0;
        const todayFollowups = [];
        const overdueFollowups = [];

        leads.forEach(function (lead) {
            const status = String(lead.status || 'baru');
            if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status]++;
            if (status === 'terdaftar' && lead.convertedAt && String(lead.convertedAt).slice(0, 7) === activePeriod) {
                monthEnrollments++;
                monthRevenue += Number(lead.enrollmentValue || 0);
            }
            const nextDate = lead.nextFollowupDate;
            if (nextDate && status !== 'terdaftar' && status !== 'batal') {
                if (nextDate === today) todayFollowups.push(lead);
                else if (nextDate < today) overdueFollowups.push(lead);
            }
        });

        const monthFollowups = followups.filter(function (followup) {
            return String(followup.followupDate || '').slice(0, 7) === activePeriod;
        }).length;
        const target = scope > 0 ? ELIPS.getMarketingTarget(scope, activePeriod) : null;
        const owner = scope > 0 && typeof ELIPS.getUserById === 'function' ? ELIPS.getUserById(scope) : null;

        return success('Dashboard Marketing dimuat.', {
            period: activePeriod,
            today: today,
            ownerId: scope > 0 ? scope : null,
            ownerName: owner ? owner.name : 'Semua Karyawan Marketing',
            leads: leads,
            followups: followups,
            target: target,
            todayFollowups: todayFollowups,
            overdueFollowups: overdueFollowups,
            stats: {
                totalLeads: leads.length,
                activeLeads: counts.baru + counts.dihubungi + counts.prospek,
                newLeads: counts.baru,
                contactedLeads: counts.dihubungi,
                prospectLeads: counts.prospek,
                registeredLeads: counts.terdaftar,
                canceledLeads: counts.batal,
                todayFollowups: todayFollowups.length,
                overdueFollowups: overdueFollowups.length,
                monthFollowups: monthFollowups,
                monthEnrollments: monthEnrollments,
                monthRevenue: monthRevenue,
                leadsTarget: target ? Number(target.leadsTarget || 0) : 0,
                followupsTarget: target ? Number(target.followupsTarget || 0) : 0,
                enrollmentsTarget: target ? Number(target.enrollmentsTarget || 0) : 0,
                revenueTarget: target ? Number(target.revenueTarget || 0) : 0
            }
        });
    }

    function marketingDashboard(userId, period) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = { period: period || currentPeriod() };
            if (userId !== undefined && userId !== null && userId !== '') {
                payload.userId = Number(userId);
            }
            return ELIPS.api.call('marketing.dashboard', payload);
        }
        return localDashboard(userId, period);
    }

    ELIPS.LEAD_STATUSES = LEAD_STATUSES;
    ELIPS.LEAD_SOURCES = LEAD_SOURCES;
    ELIPS.FOLLOWUP_CHANNELS = FOLLOWUP_CHANNELS;
    ELIPS.CAMPAIGN_STATUSES = CAMPAIGN_STATUSES;
    ELIPS.CAMPAIGN_CHANNELS = CAMPAIGN_CHANNELS;
    ELIPS.TASK_STATUSES = TASK_STATUSES;
    ELIPS.TASK_PRIORITIES = TASK_PRIORITIES;
    ELIPS.TASK_TYPES = TASK_TYPES;
    ELIPS.marketingStatusLabel = statusLabel;
    ELIPS.marketingSourceLabel = sourceLabel;
    ELIPS.marketingChannelLabel = channelLabel;
    ELIPS.marketingCampaignStatusLabel = campaignStatusLabel;
    ELIPS.marketingCampaignChannelLabel = campaignChannelLabel;
    ELIPS.marketingTaskStatusLabel = taskStatusLabel;
    ELIPS.marketingTaskPriorityLabel = taskPriorityLabel;
    ELIPS.marketingTaskTypeLabel = taskTypeLabel;
    ELIPS.marketingCampaigns = marketingCampaigns;
    ELIPS.saveMarketingCampaign = saveMarketingCampaign;
    ELIPS.deleteMarketingCampaign = deleteMarketingCampaign;
    ELIPS.marketingTasks = marketingTasks;
    ELIPS.saveMarketingTask = saveMarketingTask;
    ELIPS.deleteMarketingTask = deleteMarketingTask;
    ELIPS.marketingDashboard = marketingDashboard;
    ELIPS.saveMarketingLead = saveMarketingLead;
    ELIPS.deleteMarketingLead = deleteMarketingLead;
    ELIPS.saveMarketingFollowup = saveMarketingFollowup;
    ELIPS.deleteMarketingFollowup = deleteMarketingFollowup;
    ELIPS.saveMarketingTarget = saveMarketingTarget;
})(window.ELIPS);
