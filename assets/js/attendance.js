// ============================================================
// ELIPS LMS - attendance.js
// Modul absensi harian bersama untuk dashboard Admin dan halaman
// Karyawan. Menangani kamera, foto, GPS, aturan masuk/pulang,
// riwayat pribadi, detail, dan penghapusan oleh Admin.
// ============================================================

(function (ELIPS) {
    'use strict';

    const MAX_HISTORY = 30;
    const MAX_PHOTO_EDGE = 640;
    const PHOTO_QUALITY = 0.72;
    const LOCAL_KEEP_PER_USER = 20;
    const LOCATION_TIMEOUT = 10000;
    const SAVE_LOCATION_TIMEOUT = 8000;
    const ATTENDANCE_TIMEZONE = 'Asia/Jakarta';
    const GPS_REQUIRED_MESSAGE = 'Lokasi GPS wajib aktif untuk melakukan absensi.';
    const ROLE_LABELS = {
        admin: 'Admin',
        instruktur: 'Instruktur',
        peserta: 'Peserta',
        karyawan: 'Karyawan'
    };
    const SAVE_BTN_HTML = '<i class="bi bi-check2-circle"></i> Simpan Absensi';

    const state = {
        kind: 'in',
        stream: null,
        photo: '',
        photoMime: '',
        takenAt: null,
        records: [],
        loaded: false,
        loadError: '',
        saving: false,
        openToken: 0,
        location: { lat: null, lng: null, accuracy: null }
    };

    // Hak aksi ditentukan role pengguna aktif; karyawan tidak dapat menghapus.
    const permissions = { role: '', canDelete: false };

    function $(id) {
        return document.getElementById(id);
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    // Tanggal acuan absensi memakai zona waktu yang sama dengan server,
    // bukan zona waktu perangkat, agar tombol harian tidak salah status.
    function todayAttendanceDate(date) {
        const value = date instanceof Date ? date : new Date();
        try {
            return new Intl.DateTimeFormat('en-CA', {
                timeZone: ATTENDANCE_TIMEZONE,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(value);
        } catch (err) {
            return value.getFullYear() + '-' + pad(value.getMonth() + 1) + '-' + pad(value.getDate());
        }
    }

    function formatTimeOnly(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return String(value);
        return date.toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }

    function formatCoords(lat, lng, accuracy) {
        const latitude = Number(lat);
        const longitude = Number(lng);
        if (!isFinite(latitude) || !isFinite(longitude)) return '-';
        let text = latitude.toFixed(6) + ', ' + longitude.toFixed(6);
        if (accuracy !== null && accuracy !== undefined && isFinite(Number(accuracy))) {
            text += ' (±' + Math.round(Number(accuracy)) + ' m)';
        }
        return text;
    }

    function hasCoords(record) {
        return record.latitude !== null && record.latitude !== undefined
            && record.longitude !== null && record.longitude !== undefined;
    }

    function collectEnvironment() {
        const nav = window.navigator || {};
        const conn = nav.connection || nav.mozConnection || nav.webkitConnection || null;
        let timezone = '';
        try {
            timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (err) {
            timezone = '';
        }
        const scr = window.screen || {};
        return {
            timezone: timezone,
            userAgent: nav.userAgent || '',
            platform: nav.platform || '',
            language: nav.language || '',
            screen: scr.width && scr.height ? scr.width + 'x' + scr.height : '',
            deviceMemory: typeof nav.deviceMemory === 'number' ? nav.deviceMemory : null,
            hardwareConcurrency: typeof nav.hardwareConcurrency === 'number' ? nav.hardwareConcurrency : null,
            connectionType: conn ? (conn.effectiveType || conn.type || '') : '',
            online: nav.onLine !== false
        };
    }

    // Panel status kamera sudah tidak ditampilkan; hanya error yang
    // disampaikan lewat toast agar tampilan modal tetap ringkas.
    function setCameraStatus(message, tone) {
        if (tone === 'error' && message) ELIPS.toast(message);
    }

    // ---------- Aturan absensi harian ----------
    function currentUserId() {
        const user = ELIPS.getCurrentUser ? ELIPS.getCurrentUser() : null;
        return user ? Number(user.id) : null;
    }

    function todayRecords() {
        const today = todayAttendanceDate();
        const uid = currentUserId();
        return (state.records || []).filter(function (record) {
            if (String(record.attendanceDate || '').slice(0, 10) !== today) return false;
            return record.userId === undefined || record.userId === null || Number(record.userId) === uid;
        });
    }

    // 'in'  : belum absen sama sekali, atau hanya ada catatan pulang lama
    // 'out' : sudah absen masuk, belum pulang
    // null  : absensi hari ini sudah lengkap
    function allowedKind() {
        const list = todayRecords();
        const hasIn = list.some(function (r) { return r.kind === 'in'; });
        const hasOut = list.some(function (r) { return r.kind === 'out'; });
        if (hasIn && hasOut) return null;
        if (hasIn) return 'out';
        return 'in';
    }

    // Aturan yang sama diterapkan pada mode lokal; server tetap menjadi
    // penentu akhir lewat validasi API dan unique key database.
    function localRuleError(kind, userId) {
        const today = todayAttendanceDate();
        const list = ELIPS.getStaffAttendancesByUser(userId).filter(function (record) {
            return String(record.attendanceDate || '').slice(0, 10) === today;
        });
        if (list.some(function (record) { return record.kind === kind; })) {
            return kind === 'in'
                ? 'Anda sudah mencatat absen masuk hari ini.'
                : 'Anda sudah mencatat absen pulang hari ini.';
        }
        if (kind === 'out' && !list.some(function (record) { return record.kind === 'in'; })) {
            return 'Absen pulang hanya dapat dilakukan setelah absen masuk hari ini.';
        }
        return '';
    }

    // ---------- Lokasi (otomatis, tanpa input manual) ----------
    function fetchPosition(timeout) {
        return new Promise(function (resolve) {
            const geo = window.navigator ? window.navigator.geolocation : null;
            if (!geo || typeof geo.getCurrentPosition !== 'function') {
                resolve(null);
                return;
            }
            try {
                geo.getCurrentPosition(function (position) {
                    const accuracy = Number(position.coords.accuracy);
                    resolve({
                        lat: Number(position.coords.latitude),
                        lng: Number(position.coords.longitude),
                        accuracy: isFinite(accuracy) ? accuracy : null
                    });
                }, function () {
                    resolve(null);
                }, { enableHighAccuracy: true, timeout: timeout, maximumAge: 0 });
            } catch (err) {
                resolve(null);
            }
        });
    }

    function prefetchLocation() {
        const token = state.openToken;
        fetchPosition(LOCATION_TIMEOUT).then(function (position) {
            if (position && token === state.openToken) state.location = position;
        });
    }

    function ensureLocation() {
        if (state.location.lat !== null && state.location.lng !== null) {
            return Promise.resolve(state.location);
        }
        return fetchPosition(SAVE_LOCATION_TIMEOUT).then(function (position) {
            if (position) state.location = position;
            return state.location;
        });
    }

    // ---------- Kamera ----------
    function stopAttendanceCamera() {
        const stream = state.stream;
        state.stream = null;
        if (stream && typeof stream.getTracks === 'function') {
            stream.getTracks().forEach(function (track) {
                try { track.stop(); } catch (err) { /* ignore */ }
            });
        }
        const video = $('attendanceVideo');
        if (video) {
            try { video.pause(); } catch (err) { /* ignore */ }
            video.srcObject = null;
            video.style.display = 'none';
        }
        const marker = $('attendanceCameraMarker');
        if (marker) marker.style.display = 'none';
        const capture = $('attendanceCaptureBtn');
        if (capture) capture.disabled = true;
    }

    function startAttendanceCamera() {
        if (state.photo) {
            ELIPS.toast('Foto sudah diambil. Klik Ulangi untuk mengambil ulang.');
            return Promise.resolve();
        }
        const media = window.navigator ? window.navigator.mediaDevices : null;
        if (!media || typeof media.getUserMedia !== 'function') {
            setCameraStatus('Kamera tidak tersedia di perangkat/browser ini. Gunakan tombol Unggah Foto.', 'error');
            return Promise.resolve();
        }
        if (window.isSecureContext === false) {
            setCameraStatus('Kamera memerlukan HTTPS atau localhost. Gunakan tombol Unggah Foto.', 'error');
            return Promise.resolve();
        }
        const startBtn = $('attendanceStartBtn');
        if (startBtn) startBtn.disabled = true;
        if (state.stream) stopAttendanceCamera();
        const constraints = {
            video: { facingMode: 'user', width: { ideal: 1280 } },
            audio: false
        };
        let request;
        try {
            request = media.getUserMedia(constraints);
        } catch (err) {
            request = Promise.reject(err);
        }
        return Promise.resolve(request).then(function (stream) {
            if ($('attendanceModal') && $('attendanceModal').style.display === 'none') {
                // Modal ditutup saat izin kamera masih menunggu.
                stream.getTracks().forEach(function (track) { track.stop(); });
                return;
            }
            state.stream = stream;
            const video = $('attendanceVideo');
            if (video) {
                video.srcObject = stream;
                video.style.display = 'block';
                const play = video.play();
                if (play && typeof play.catch === 'function') play.catch(function () { });
            }
            if ($('attendanceCameraPlaceholder')) $('attendanceCameraPlaceholder').style.display = 'none';
            if ($('attendancePreview')) $('attendancePreview').style.display = 'none';
            if ($('attendanceCameraMarker')) $('attendanceCameraMarker').style.display = 'flex';
            if ($('attendanceCaptureBtn')) $('attendanceCaptureBtn').disabled = false;
        }).catch(function (error) {
            const name = error && error.name;
            let message = 'Kamera tidak dapat diakses. Gunakan tombol Unggah Foto.';
            if (name === 'NotAllowedError' || name === 'SecurityError') {
                message = 'Izin kamera ditolak. Gunakan Unggah Foto atau izinkan kamera di pengaturan browser.';
            } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                message = 'Kamera tidak ditemukan. Gunakan tombol Unggah Foto.';
            } else if (name === 'NotReadableError' || name === 'TrackStartError') {
                message = 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
            } else if (name === 'OverconstrainedError') {
                message = 'Kamera tidak mendukung mode yang diminta. Coba lagi atau unggah foto.';
            }
            setCameraStatus(message, 'error');
        }).then(function () {
            if (startBtn) startBtn.disabled = false;
        });
    }

    function setAttendancePhoto(dataUrl, mime) {
        state.photo = dataUrl;
        state.photoMime = mime || 'image/jpeg';
        state.takenAt = new Date().toISOString();
        const preview = $('attendancePreview');
        if (preview) {
            preview.src = dataUrl;
            preview.style.display = 'block';
        }
        if ($('attendanceVideo')) $('attendanceVideo').style.display = 'none';
        if ($('attendanceCameraPlaceholder')) $('attendanceCameraPlaceholder').style.display = 'none';
        if ($('attendanceCameraMarker')) $('attendanceCameraMarker').style.display = 'none';
        if ($('attendanceCaptureBtn')) $('attendanceCaptureBtn').disabled = true;
        if ($('attendanceRetakeBtn')) $('attendanceRetakeBtn').style.display = 'inline-flex';
        if ($('attendanceSaveBtn')) $('attendanceSaveBtn').disabled = false;
    }

    function captureAttendancePhoto() {
        const video = $('attendanceVideo');
        if (!video || !video.videoWidth) {
            setCameraStatus('Kamera belum siap. Tunggu sebentar atau gunakan Unggah Foto.', 'error');
            return;
        }
        const canvas = $('attendanceCanvas');
        const scale = Math.min(1, MAX_PHOTO_EDGE / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
        const context = canvas.getContext('2d');
        if (!context) {
            setCameraStatus('Browser tidak mendukung pengambilan foto. Gunakan Unggah Foto.', 'error');
            return;
        }
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        let dataUrl = '';
        try {
            dataUrl = canvas.toDataURL('image/jpeg', PHOTO_QUALITY);
        } catch (err) {
            dataUrl = '';
        }
        if (!dataUrl || dataUrl.indexOf('data:image/') !== 0) {
            setCameraStatus('Foto gagal diambil. Coba lagi atau gunakan Unggah Foto.', 'error');
            return;
        }
        stopAttendanceCamera();
        setAttendancePhoto(dataUrl, 'image/jpeg');
    }

    function retakeAttendancePhoto() {
        state.photo = '';
        state.photoMime = '';
        state.takenAt = null;
        if ($('attendancePreview')) {
            $('attendancePreview').style.display = 'none';
            $('attendancePreview').removeAttribute('src');
        }
        if ($('attendanceRetakeBtn')) $('attendanceRetakeBtn').style.display = 'none';
        if ($('attendanceSaveBtn')) $('attendanceSaveBtn').disabled = true;
        if ($('attendanceVideo')) $('attendanceVideo').style.display = 'none';
        if ($('attendanceCameraPlaceholder')) $('attendanceCameraPlaceholder').style.display = 'flex';
        startAttendanceCamera();
    }

    function compressAttendanceImage(dataUrl, maxEdge) {
        return new Promise(function (resolve) {
            try {
                const image = new Image();
                image.onload = function () {
                    try {
                        const scale = Math.min(1, maxEdge / Math.max(image.width || 1, image.height || 1));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round((image.width || 1) * scale));
                        canvas.height = Math.max(1, Math.round((image.height || 1) * scale));
                        const context = canvas.getContext('2d');
                        if (!context) {
                            resolve(dataUrl);
                            return;
                        }
                        context.drawImage(image, 0, 0, canvas.width, canvas.height);
                        resolve(canvas.toDataURL('image/jpeg', PHOTO_QUALITY));
                    } catch (err) {
                        resolve(dataUrl);
                    }
                };
                image.onerror = function () { resolve(dataUrl); };
                image.src = dataUrl;
            } catch (err) {
                resolve(dataUrl);
            }
        });
    }

    function handleAttendanceFile(event) {
        const input = event && event.target;
        const file = input && input.files && input.files[0];
        if (!file) return;
        if (!/^image\//.test(file.type || '')) {
            ELIPS.toast('File harus berupa gambar.');
            input.value = '';
            return;
        }
        if (file.size > 8 * 1024 * 1024) {
            ELIPS.toast('Ukuran file maksimal 8 MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            compressAttendanceImage(String(reader.result || ''), MAX_PHOTO_EDGE).then(function (dataUrl) {
                if (!dataUrl || dataUrl.indexOf('data:image/') !== 0) {
                    setCameraStatus('File foto tidak dapat dibaca. Coba file lain.', 'error');
                } else {
                    stopAttendanceCamera();
                    setAttendancePhoto(dataUrl, file.type || 'image/jpeg');
                }
                input.value = '';
            });
        };
        reader.onerror = function () {
            setCameraStatus('Gagal membaca file foto.', 'error');
            input.value = '';
        };
        reader.readAsDataURL(file);
    }

    // ---------- Simpan ----------
    // Tanggal, jam, dan lokasi dikunci pada saat tombol Simpan ditekan
    // (tidak ada input manual), lalu server mencatat ulang waktunya.
    function buildAttendancePayload(now) {
        const env = collectEnvironment();
        const lat = state.location.lat;
        const lng = state.location.lng;
        const withCoords = lat !== null && lng !== null && isFinite(lat) && isFinite(lng);
        const notesInput = $('attendanceNotes');
        return {
            kind: state.kind,
            attendanceDate: todayAttendanceDate(now),
            takenAt: now.toISOString(),
            timezone: env.timezone,
            photo: state.photo,
            latitude: withCoords ? lat : null,
            longitude: withCoords ? lng : null,
            accuracyM: withCoords ? state.location.accuracy : null,
            locationLabel: withCoords
                ? 'Koordinat ' + lat.toFixed(6) + ', ' + lng.toFixed(6) : '',
            locationSource: withCoords ? 'gps' : 'none',
            userAgent: env.userAgent,
            platform: env.platform,
            language: env.language,
            screen: env.screen,
            deviceMemory: env.deviceMemory,
            hardwareConcurrency: env.hardwareConcurrency,
            connectionType: env.connectionType,
            online: env.online,
            notes: notesInput ? notesInput.value.trim() : ''
        };
    }

    function trimLocalAttendance(list, userId) {
        const mine = list.filter(function (item) {
            return Number(item.userId) === Number(userId);
        });
        if (mine.length <= LOCAL_KEEP_PER_USER) return list;
        const keep = {};
        mine.sort(function (a, b) {
            return String(b.takenAt || '').localeCompare(String(a.takenAt || ''));
        }).slice(0, LOCAL_KEEP_PER_USER).forEach(function (item) {
            keep[item.id] = true;
        });
        return list.filter(function (item) {
            return Number(item.userId) !== Number(userId) || keep[item.id];
        });
    }

    function finishSaving(failed) {
        state.saving = false;
        const saveBtn = $('attendanceSaveBtn');
        if (saveBtn) {
            saveBtn.innerHTML = SAVE_BTN_HTML;
            if (failed) saveBtn.disabled = false;
        }
    }

    function persistAttendance(user) {
        const now = new Date();
        state.takenAt = now.toISOString();
        const payload = buildAttendancePayload(now);

        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('staffAttendance.create', payload);
            if (!result.ok) {
                ELIPS.toast(result.message || 'Absensi gagal disimpan.');
                finishSaving(true);
                return;
            }
            const fromServer = result.data && typeof result.data === 'object' && !Array.isArray(result.data)
                ? result.data : {};
            const record = Object.assign({}, payload, fromServer);
            finishSaving(false);
            closeAttendanceModal();
            loadAttendanceHistory();
            showAttendanceSuccess(record, user);
            return;
        }

        try {
            const ruleError = localRuleError(state.kind, user.id);
            if (ruleError) {
                ELIPS.toast(ruleError);
                finishSaving(true);
                loadAttendanceHistory();
                return;
            }
            const all = ELIPS.getStaffAttendances();
            const record = Object.assign({}, payload, {
                id: ELIPS.nextId(all),
                userId: user.id,
                userName: user.name,
                photoMime: state.photoMime || 'image/jpeg',
                ipAddress: 'lokal',
                createdAt: new Date().toISOString(),
                updatedAt: null
            });
            all.push(record);
            ELIPS.setStaffAttendances(trimLocalAttendance(all, user.id));
            if (typeof ELIPS.logAudit === 'function') {
                ELIPS.logAudit({
                    action: 'create',
                    entity: 'staff_attendance',
                    entityId: record.id,
                    entityLabel: (record.kind === 'in' ? 'Absensi Masuk' : 'Absensi Pulang')
                        + ' — ' + record.attendanceDate,
                    detail: 'Mencatat absensi ' + (record.kind === 'in' ? 'masuk' : 'pulang')
                        + ' pada ' + formatDateTime(record.takenAt)
                        + (record.locationLabel ? ' di ' + record.locationLabel : '') + '.',
                    actorId: user.id
                });
            }
            finishSaving(false);
            closeAttendanceModal();
            loadAttendanceHistory();
            showAttendanceSuccess(record, user);
        } catch (err) {
            ELIPS.toast('Absensi gagal disimpan: '
                + (err && err.message ? err.message : 'penyimpanan penuh'));
            finishSaving(true);
        }
    }

    function saveStaffAttendance(event) {
        if (event) event.preventDefault();
        if (state.saving) return false;
        const user = ELIPS.getCurrentUser ? ELIPS.getCurrentUser() : null;
        if (!user) {
            ELIPS.toast('Sesi berakhir. Silakan masuk kembali.');
            return false;
        }
        if (!state.photo) {
            ELIPS.toast('Ambil foto absensi terlebih dahulu.');
            return false;
        }
        if (allowedKind() !== state.kind) {
            ELIPS.toast('Status absensi hari ini sudah berubah. Halaman dimuat ulang.');
            closeAttendanceModal();
            loadAttendanceHistory();
            return false;
        }

        state.saving = true;
        const saveBtn = $('attendanceSaveBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan&hellip;';
        }

        // GPS wajib: bila belum ada koordinat, coba sekali lagi sebelum
        // menolak penyimpanan.
        ensureLocation().then(function (position) {
            if (!position || position.lat === null || position.lng === null) {
                state.saving = false;
                if (saveBtn) {
                    saveBtn.innerHTML = SAVE_BTN_HTML;
                    saveBtn.disabled = false;
                }
                ELIPS.toast(GPS_REQUIRED_MESSAGE);
                return;
            }
            persistAttendance(user);
        });
        return false;
    }

    // ---------- Tampilan riwayat ----------
    function deviceSummary(record) {
        const parts = [];
        if (record.platform) parts.push(record.platform);
        if (record.screen) parts.push(record.screen);
        if (record.connectionType) parts.push(record.connectionType);
        return parts.length ? parts.join(' • ') : 'Perangkat tidak tercatat';
    }

    function locationSummary(record) {
        if (record.locationLabel) return record.locationLabel;
        if (hasCoords(record)) {
            return formatCoords(record.latitude, record.longitude, record.accuracyM);
        }
        return 'Lokasi tidak tersedia';
    }

    function renderAttendanceHistory() {
        const container = $('attendanceHistory');
        const counter = $('attendanceHistoryCount');
        if (!container) return;
        const records = state.records || [];
        if (counter) counter.textContent = records.length + ' catatan';
        if (!records.length) {
            container.innerHTML = '<div class="attendance-empty">'
                + '<i class="bi bi-camera"></i>'
                + '<p>Belum ada catatan absensi. Klik Absen Masuk untuk memulai.</p>'
                + '</div>';
            return;
        }
        container.innerHTML = records.map(function (record) {
            const isIn = record.kind === 'in';
            const kindLabel = isIn ? 'Masuk' : 'Pulang';
            const kindClass = isIn ? 'is-in' : 'is-out';
            const date = ELIPS.formatDate(record.attendanceDate);
            const time = formatTimeOnly(record.takenAt);
            const photo = record.photo
                ? '<img src="' + ELIPS.escapeHtml(record.photo) + '" alt="Foto absensi '
                    + ELIPS.escapeHtml(kindLabel) + ' ' + ELIPS.escapeHtml(date) + '">'
                : '<i class="bi bi-image"></i>';
            const notes = record.notes
                ? '<p class="attendance-history-notes">' + ELIPS.escapeHtml(record.notes) + '</p>'
                : '';
            const deleteButton = permissions.canDelete
                ? '<button type="button" class="btn btn-ghost attendance-delete-btn" title="Hapus"'
                    + ' onclick="deleteStaffAttendance(' + record.id + ')">'
                    + '<i class="bi bi-trash"></i></button>'
                : '';
            return '<article class="attendance-history-item">'
                + '<div class="attendance-history-photo">' + photo + '</div>'
                + '<div class="attendance-history-info">'
                + '<div class="attendance-history-top">'
                + '<span class="attendance-kind ' + kindClass + '">'
                + '<i class="bi bi-' + (isIn ? 'box-arrow-in-right' : 'box-arrow-right') + '"></i> '
                + 'Absen ' + kindLabel + '</span>'
                + '<span class="attendance-history-date">'
                + ELIPS.escapeHtml(date) + ' • ' + ELIPS.escapeHtml(time) + '</span>'
                + '</div>'
                + '<div class="attendance-history-meta">'
                + '<span><i class="bi bi-geo-alt"></i> '
                + ELIPS.escapeHtml(locationSummary(record)) + '</span>'
                + '<span><i class="bi bi-phone"></i> '
                + ELIPS.escapeHtml(deviceSummary(record)) + '</span>'
                + '</div>'
                + notes
                + '</div>'
                + '<div class="attendance-history-actions">'
                + '<button type="button" class="btn btn-ghost" title="Lihat detail"'
                + ' onclick="openAttendanceDetail(' + record.id + ')">'
                + '<i class="bi bi-eye"></i></button>'
                + deleteButton
                + '</div>'
                + '</article>';
        }).join('');
    }

    // Tombol hanya sesuai status hari ini: belum absen -> Masuk,
    // sudah masuk -> Pulang, sudah keduanya -> tanpa tombol.
    function refreshAttendanceHero() {
        const list = todayRecords();
        const inRecord = list.filter(function (r) { return r.kind === 'in'; })[0] || null;
        const outRecord = list.filter(function (r) { return r.kind === 'out'; })[0] || null;
        const inPill = $('attendanceTodayIn');
        const outPill = $('attendanceTodayOut');
        const status = $('attendanceHeroStatus');
        if (inPill) {
            inPill.classList.toggle('has-value', !!inRecord);
            inPill.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Masuk: '
                + (inRecord ? ELIPS.escapeHtml(formatTimeOnly(inRecord.takenAt)) : '–');
        }
        if (outPill) {
            outPill.classList.toggle('has-value', !!outRecord);
            outPill.innerHTML = '<i class="bi bi-box-arrow-right"></i> Pulang: '
                + (outRecord ? ELIPS.escapeHtml(formatTimeOnly(outRecord.takenAt)) : '–');
        }

        const loadFailed = !!state.loadError;
        const next = loadFailed || !state.loaded ? null : allowedKind();
        if ($('attendanceBtnIn')) $('attendanceBtnIn').style.display = next === 'in' ? 'inline-flex' : 'none';
        if ($('attendanceBtnOut')) $('attendanceBtnOut').style.display = next === 'out' ? 'inline-flex' : 'none';
        if ($('attendanceDone')) {
            $('attendanceDone').style.display = state.loaded && !loadFailed && next === null ? 'inline-flex' : 'none';
        }

        if (status) {
            if (loadFailed) {
                status.textContent = 'Status absensi tidak dapat dimuat. Klik Muat Ulang pada riwayat.';
            } else if (!state.loaded) {
                status.textContent = 'Memuat status absensi hari ini…';
            } else if (!list.length) {
                status.textContent = 'Belum ada absensi hari ini. Mulai dengan Absen Masuk.';
            } else if (inRecord && !outRecord) {
                status.textContent = 'Anda sudah absen masuk pukul '
                    + formatTimeOnly(inRecord.takenAt) + '. Jangan lupa absen pulang.';
            } else if (!inRecord && outRecord) {
                status.textContent = 'Absen pulang tercatat pukul '
                    + formatTimeOnly(outRecord.takenAt) + '. Absen masuk belum tercatat.';
            } else {
                status.textContent = 'Absensi hari ini lengkap. Terima kasih.';
            }
        }
    }

    function loadAttendanceHistory() {
        const container = $('attendanceHistory');
        const user = ELIPS.getCurrentUser ? ELIPS.getCurrentUser() : null;
        if (!user) {
            if (container) {
                container.innerHTML = '<div class="attendance-empty">Sesi tidak ditemukan.</div>';
            }
            return;
        }
        if (container) {
            container.innerHTML = '<div class="attendance-empty">Memuat riwayat absensi&hellip;</div>';
        }
        let records = [];
        state.loadError = '';
        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('staffAttendance.list', { limit: MAX_HISTORY });
            if (!result.ok) {
                state.loadError = result.message || 'Riwayat absensi gagal dimuat.';
                if (container) {
                    container.innerHTML = '<div class="attendance-empty">'
                        + ELIPS.escapeHtml(state.loadError) + '</div>';
                }
                state.records = [];
                refreshAttendanceHero();
                return;
            }
            records = Array.isArray(result.data) ? result.data : [];
        } else {
            records = ELIPS.getStaffAttendancesByUser(user.id).slice(0, MAX_HISTORY);
        }
        state.records = records;
        state.loaded = true;
        renderAttendanceHistory();
        refreshAttendanceHero();
    }

    // ---------- Modal ambil absensi ----------
    function openAttendanceModal(kind) {
        const wanted = kind === 'out' ? 'out' : 'in';
        if (!state.loaded || state.loadError || allowedKind() !== wanted) {
            ELIPS.toast('Absensi ini tidak tersedia untuk hari ini.');
            refreshAttendanceHero();
            return;
        }
        stopAttendanceCamera();
        state.openToken += 1;
        state.kind = wanted;
        state.photo = '';
        state.photoMime = '';
        state.takenAt = null;
        state.saving = false;
        state.location = { lat: null, lng: null, accuracy: null };
        if ($('attendanceModalTitle')) {
            $('attendanceModalTitle').textContent = state.kind === 'in' ? 'Absen Masuk' : 'Absen Pulang';
        }
        if ($('attendanceModalSubtitle')) {
            $('attendanceModalSubtitle').textContent =
                'Ambil foto lalu simpan. Tanggal, jam, dan lokasi dikunci otomatis saat disimpan.';
        }
        if ($('attendanceNotes')) $('attendanceNotes').value = '';
        if ($('attendancePreview')) {
            $('attendancePreview').style.display = 'none';
            $('attendancePreview').removeAttribute('src');
        }
        if ($('attendanceCameraPlaceholder')) $('attendanceCameraPlaceholder').style.display = 'flex';
        if ($('attendanceRetakeBtn')) $('attendanceRetakeBtn').style.display = 'none';
        if ($('attendanceSaveBtn')) {
            $('attendanceSaveBtn').disabled = true;
            $('attendanceSaveBtn').innerHTML = SAVE_BTN_HTML;
        }
        if ($('attendanceCaptureBtn')) $('attendanceCaptureBtn').disabled = true;
        if ($('attendanceVideo')) $('attendanceVideo').style.display = 'none';
        if ($('attendanceCameraMarker')) $('attendanceCameraMarker').style.display = 'none';
        if ($('attendanceFile')) $('attendanceFile').value = '';
        if ($('attendanceModal')) $('attendanceModal').style.display = 'flex';
        document.body.classList.add('attendance-modal-open');
        startAttendanceCamera();
        prefetchLocation();
    }

    function closeAttendanceModal() {
        if (state.saving) return;
        state.openToken += 1;
        stopAttendanceCamera();
        if ($('attendanceModal')) $('attendanceModal').style.display = 'none';
        if (!anyAttendanceModalOpen()) document.body.classList.remove('attendance-modal-open');
        if ($('attendanceFile')) $('attendanceFile').value = '';
    }

    function anyAttendanceModalOpen() {
        return ['attendanceModal', 'attendanceDetailModal', 'attendanceSuccessModal'].some(function (id) {
            const el = $(id);
            return el && el.style.display !== 'none';
        });
    }

    // ---------- Detail & modal sukses ----------
    function recordRows(record) {
        return [
            ['Jenis absensi', record.kind === 'in' ? 'Absen Masuk' : 'Absen Pulang'],
            ['Tanggal', ELIPS.formatDate(record.attendanceDate)],
            ['Jam tercatat', formatDateTime(record.takenAt)],
            ['Zona waktu', record.timezone || '-'],
            ['Lokasi', locationSummary(record)],
            ['Koordinat', hasCoords(record)
                ? formatCoords(record.latitude, record.longitude, record.accuracyM) : '-'],
            ['Sumber lokasi', record.locationSource === 'gps' ? 'GPS perangkat'
                : record.locationSource === 'manual' ? 'Input manual' : 'Tidak tersedia'],
            ['Alamat IP', record.ipAddress || '-'],
            ['Perangkat', record.platform || '-'],
            ['Layar', record.screen || '-'],
            ['Bahasa', record.language || '-'],
            ['Koneksi', record.connectionType || 'Tidak diketahui'],
            ['Memori perangkat', record.deviceMemory ? record.deviceMemory + ' GB' : '-'],
            ['CPU core', record.hardwareConcurrency || '-'],
            ['Status jaringan', record.online === false ? 'Offline' : 'Online'],
            ['Catatan', record.notes || '-'],
            ['User agent', record.userAgent || '-'],
            ['ID catatan', record.id !== undefined && record.id !== null ? '#' + record.id : '-'],
            ['Dicatat pada', formatDateTime(record.createdAt || record.takenAt)]
        ];
    }

    function rowsHtml(rows) {
        return '<div class="attendance-detail-grid">'
            + rows.map(function (row) {
                return '<div class="attendance-detail-row">'
                    + '<span class="attendance-detail-label">' + ELIPS.escapeHtml(row[0]) + '</span>'
                    + '<span class="attendance-detail-value">' + ELIPS.escapeHtml(row[1]) + '</span>'
                    + '</div>';
            }).join('')
            + '</div>';
    }

    function photoHtml(record, label) {
        return record.photo
            ? '<div class="attendance-detail-photo"><img src="' + ELIPS.escapeHtml(record.photo)
                + '" alt="Foto ' + ELIPS.escapeHtml(label) + '"></div>'
            : '';
    }

    function showAttendanceSuccess(record, user) {
        const kindLabel = record.kind === 'in' ? 'Absen Masuk' : 'Absen Pulang';
        const account = [
            ['Nama akun', user.name || record.userName || '-'],
            ['Email', user.email || '-'],
            ['Role', ROLE_LABELS[user.role] || user.role || '-'],
            ['ID akun', user.id !== undefined ? '#' + user.id : '-']
        ];
        if ($('attendanceSuccessTitle')) {
            $('attendanceSuccessTitle').textContent = kindLabel + ' Berhasil Disimpan';
        }
        if ($('attendanceSuccessSubtitle')) {
            $('attendanceSuccessSubtitle').textContent = ELIPS.formatDate(record.attendanceDate)
                + ' • ' + formatTimeOnly(record.takenAt);
        }
        if ($('attendanceSuccessBody')) {
            $('attendanceSuccessBody').innerHTML = photoHtml(record, kindLabel)
                + rowsHtml(account.concat(recordRows(record)));
            $('attendanceSuccessBody').scrollTop = 0;
        }
        const modal = $('attendanceSuccessModal');
        if (modal) {
            modal.style.display = 'flex';
            const card = modal.querySelector('.attendance-modal-card');
            if (card) card.scrollTop = 0;
        }
        document.body.classList.add('attendance-modal-open');
    }

    function closeAttendanceSuccess() {
        if ($('attendanceSuccessModal')) $('attendanceSuccessModal').style.display = 'none';
        if (!anyAttendanceModalOpen()) document.body.classList.remove('attendance-modal-open');
    }

    function openAttendanceDetail(id) {
        const record = (state.records || []).filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!record) {
            ELIPS.toast('Catatan absensi tidak ditemukan.');
            return;
        }
        const kindLabel = record.kind === 'in' ? 'Absen Masuk' : 'Absen Pulang';
        const actions = [];
        if (permissions.canDelete) {
            actions.push('<button type="button" class="btn btn-danger"'
                + ' onclick="deleteStaffAttendance(' + record.id + ', true)">'
                + '<i class="bi bi-trash"></i> Hapus Catatan</button>');
        }
        actions.push('<button type="button" class="btn btn-primary" onclick="closeAttendanceDetail()">Tutup</button>');
        const detailHtml = photoHtml(record, kindLabel)
            + rowsHtml(recordRows(record))
            + '<div class="attendance-detail-actions">' + actions.join('') + '</div>';
        if ($('attendanceDetailTitle')) $('attendanceDetailTitle').textContent = kindLabel;
        if ($('attendanceDetailSubtitle')) {
            $('attendanceDetailSubtitle').textContent = ELIPS.formatDate(record.attendanceDate)
                + ' • ' + formatTimeOnly(record.takenAt);
        }
        if ($('attendanceDetailBody')) $('attendanceDetailBody').innerHTML = detailHtml;
        if ($('attendanceDetailModal')) $('attendanceDetailModal').style.display = 'flex';
        document.body.classList.add('attendance-modal-open');
    }

    function closeAttendanceDetail() {
        if ($('attendanceDetailModal')) $('attendanceDetailModal').style.display = 'none';
        if (!anyAttendanceModalOpen()) document.body.classList.remove('attendance-modal-open');
    }

    // Penghapusan hanya untuk Admin dan wajib beralasan. Alasan ikut
    // dikirim ke server serta dicatat pada Audit Trail mode lokal.
    function deleteStaffAttendance(id, fromDetail) {
        const record = (state.records || []).filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!record) return;
        if (!permissions.canDelete) {
            ELIPS.toast('Karyawan tidak dapat menghapus catatan absensi. Hubungi administrator.');
            return;
        }
        const label = (record.kind === 'in' ? 'absen masuk' : 'absen pulang')
            + ' ' + ELIPS.formatDate(record.attendanceDate);
        if (!window.confirm('Hapus catatan ' + label + '?')) return;

        let reason = null;
        try {
            reason = window.prompt('Alasan penghapusan (minimal 5 karakter):', '');
        } catch (err) {
            reason = null;
        }
        if (reason === null) return;
        const trimmed = String(reason).trim();
        if (trimmed.length < 5) {
            ELIPS.toast('Alasan penghapusan wajib diisi minimal 5 karakter.');
            return;
        }

        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('staffAttendance.delete', { id: Number(id), reason: trimmed });
            if (!result.ok) {
                ELIPS.toast(result.message || 'Gagal menghapus absensi.');
                return;
            }
            ELIPS.toast(result.message || 'Catatan absensi dihapus.');
        } else {
            const remaining = ELIPS.getStaffAttendances().filter(function (item) {
                return Number(item.id) !== Number(id);
            });
            ELIPS.setStaffAttendances(remaining);
            if (typeof ELIPS.logAudit === 'function') {
                ELIPS.logAudit({
                    action: 'delete',
                    entity: 'staff_attendance',
                    entityId: Number(id),
                    entityLabel: (record.kind === 'in' ? 'Absensi Masuk' : 'Absensi Pulang')
                        + ' — ' + record.attendanceDate,
                    detail: 'Menghapus catatan ' + label + '. Alasan: ' + trimmed + '.'
                });
            }
            ELIPS.toast('Catatan absensi dihapus.');
        }
        if (fromDetail) closeAttendanceDetail();
        loadAttendanceHistory();
    }

    function init(options) {
        const opts = options || {};
        const role = opts.role || 'admin';
        const user = ELIPS.requireRole(role);
        if (!user) return null;
        permissions.role = user.role;
        permissions.canDelete = user.role === 'admin';
        if (typeof opts.onReady === 'function') {
            try { opts.onReady(user); } catch (err) { /* halaman tetap berjalan */ }
        }
        loadAttendanceHistory();
        document.addEventListener('keydown', function (event) {
            // Modal sukses sengaja tidak bisa ditutup dengan Escape.
            if (event.key === 'Escape') {
                closeAttendanceModal();
                closeAttendanceDetail();
            }
        });
        return user;
    }

    // Handler dipakai oleh atribut onclick pada markup absensi.
    window.openAttendanceModal = openAttendanceModal;
    window.closeAttendanceModal = closeAttendanceModal;
    window.startAttendanceCamera = startAttendanceCamera;
    window.captureAttendancePhoto = captureAttendancePhoto;
    window.retakeAttendancePhoto = retakeAttendancePhoto;
    window.handleAttendanceFile = handleAttendanceFile;
    window.setAttendancePhoto = setAttendancePhoto;
    window.saveStaffAttendance = saveStaffAttendance;
    window.loadAttendanceHistory = loadAttendanceHistory;
    window.openAttendanceDetail = openAttendanceDetail;
    window.closeAttendanceDetail = closeAttendanceDetail;
    window.closeAttendanceSuccess = closeAttendanceSuccess;
    window.deleteStaffAttendance = deleteStaffAttendance;

    ELIPS.attendance = {
        init: init,
        refresh: loadAttendanceHistory,
        state: state,
        permissions: permissions,
        todayDate: todayAttendanceDate,
        timezone: ATTENDANCE_TIMEZONE
    };
})(window.ELIPS);
