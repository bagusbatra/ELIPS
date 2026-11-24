// Direktori: assets/js/pages/activate-tentor.js
// ELIPS LMS - skrip halaman activate-tentor.php
// Alur: data & berkas (semua wajib; berkas diunggah langsung) -> buat sandi -> dashboard tentor.
document.addEventListener('DOMContentLoaded', function () {
    var token = new URLSearchParams(window.location.search).get('token') || '';
    var $ = function (id) { return document.getElementById(id); };
    var esc = ELIPS.escapeHtml;
    var FIXED = [
        { kind: 'cv', label: 'CV', icon: 'bi-file-earmark-person' },
        { kind: 'ktp', label: 'KTP', icon: 'bi-person-vcard' },
        { kind: 'ijazah', label: 'Ijazah terakhir', icon: 'bi-mortarboard' }
    ];
    var limits = { maxBytes: 3 * 1024 * 1024, maxCertificates: 10 };
    var documents = [];
    var pending = []; // baris sertifikat baru yang belum diunggah: {key, name}
    var pendingSeq = 0;

    function showError(message) {
        $('actIntro').textContent = '';
        $('actBody').hidden = true;
        $('actError').hidden = false;
        $('actErrorText').textContent = message;
    }

    function setError(el, message) {
        el.textContent = message || '';
        el.hidden = !message;
    }

    function sizeLabel(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function docOf(kind) {
        return documents.filter(function (d) { return d.kind === kind; })[0] || null;
    }

    function certs() {
        return documents.filter(function (d) { return d.kind === 'sertifikat'; });
    }

    function syncPendingNames() {
        pending.forEach(function (row) {
            var input = document.querySelector('[data-pending-name="' + row.key + '"]');
            if (input) row.name = input.value;
        });
    }

    function fileRow(opts) {
        // opts: {icon, title, subtitle, done, extra, buttons}
        return '<div class="tut-file' + (opts.done ? ' is-done' : '') + '" data-row="' + esc(opts.id) + '">'
            + '<span class="tut-file-icon"><i class="bi ' + (opts.done ? 'bi-check2-circle' : opts.icon) + '" aria-hidden="true"></i></span>'
            + '<span class="tut-file-info">' + opts.title + '<small>' + esc(opts.subtitle) + '</small></span>'
            + opts.buttons + '</div>';
    }

    function render() {
        $('tutFixedDocs').innerHTML = FIXED.map(function (f) {
            var d = docOf(f.kind);
            return fileRow({
                id: f.kind, icon: f.icon, done: !!d,
                title: '<strong>' + esc(f.label) + ' *</strong>',
                subtitle: d ? d.fileName + ' · ' + sizeLabel(d.size) : 'Belum diunggah',
                buttons: '<button type="button" class="tut-btn' + (d ? ' is-ghost' : '') + '" data-pick="' + f.kind + '">' + (d ? 'Ganti' : 'Pilih berkas') + '</button>'
            });
        }).join('');

        var html = certs().map(function (d) {
            return fileRow({
                id: 'cert-' + d.id, icon: 'bi-award', done: true,
                title: '<strong>' + esc(d.label || 'Sertifikat') + '</strong>',
                subtitle: d.fileName + ' · ' + sizeLabel(d.size),
                buttons: '<button type="button" class="tut-btn is-danger" data-remove="' + d.id + '" aria-label="Hapus sertifikat"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            });
        }).join('');
        html += pending.map(function (row) {
            return fileRow({
                id: 'pending-' + row.key, icon: 'bi-award', done: false,
                title: '<input type="text" class="act-plain" maxlength="150" placeholder="Nama sertifikat *" data-pending-name="' + row.key + '" value="' + esc(row.name) + '">',
                subtitle: 'Isi nama, lalu pilih berkas',
                buttons: '<button type="button" class="tut-btn" data-pick="sertifikat" data-key="' + row.key + '">Pilih berkas</button>'
                    + '<button type="button" class="tut-btn is-danger" data-drop="' + row.key + '" aria-label="Batalkan"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
            });
        }).join('');
        $('tutCertList').innerHTML = html;
        $('tutAddCert').disabled = certs().length + pending.length >= limits.maxCertificates;
    }

    function readAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result || '')); };
            reader.onerror = function () { reject(new Error('Berkas tidak bisa dibaca.')); };
            reader.readAsDataURL(file);
        });
    }

    function pickFile(kind, key) {
        var input = document.createElement('input');
        input.type = 'file';
        input.className = 'tut-file-input';
        input.accept = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (file) uploadFile(kind, key, file);
        });
        input.click();
    }

    function uploadFile(kind, key, file) {
        syncPendingNames();
        var label = '';
        if (kind === 'sertifikat') {
            var row = pending.filter(function (r) { return r.key === key; })[0];
            label = row ? row.name.trim() : '';
            if (!label) {
                setError($('actBioError'), 'Isi nama sertifikat terlebih dahulu.');
                var nameInput = document.querySelector('[data-pending-name="' + key + '"]');
                if (nameInput) nameInput.focus();
                return;
            }
        }
        if (file.size > limits.maxBytes) {
            setError($('actBioError'), 'Ukuran berkas maksimal ' + sizeLabel(limits.maxBytes) + '.');
            return;
        }
        if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) {
            setError($('actBioError'), 'Format berkas harus PDF, JPG, atau PNG.');
            return;
        }
        setError($('actBioError'), '');
        var rowEl = document.querySelector('[data-row="' + (kind === 'sertifikat' ? 'pending-' + key : kind) + '"]');
        if (rowEl) rowEl.classList.add('is-busy');
        readAsDataUrl(file).then(function (dataUrl) {
            var result = ELIPS.api.call('tutor.document.upload', {
                token: token, kind: kind, label: label, fileName: file.name, dataUrl: dataUrl
            });
            if (!result.ok) {
                setError($('actBioError'), result.message || 'Gagal mengunggah berkas.');
                if (rowEl) rowEl.classList.remove('is-busy');
                return;
            }
            documents = (result.data && result.data.documents) || documents;
            if (kind === 'sertifikat') pending = pending.filter(function (r) { return r.key !== key; });
            render();
        }).catch(function (err) {
            setError($('actBioError'), err.message);
            if (rowEl) rowEl.classList.remove('is-busy');
        });
    }

    function removeDoc(id) {
        var result = ELIPS.api.call('tutor.document.remove', { token: token, id: id });
        if (!result.ok) {
            setError($('actBioError'), result.message || 'Gagal menghapus berkas.');
            return;
        }
        documents = (result.data && result.data.documents) || documents.filter(function (d) { return String(d.id) !== String(id); });
        render();
    }

    function readProfile() {
        return {
            fullName: $('fullName').value.trim(),
            birthPlace: $('birthPlace').value.trim(),
            birthDate: $('birthDate').value,
            address: $('address').value.trim(),
            phone: $('phone').value.trim(),
            email: $('email').value.trim()
        };
    }

    // Validasi ringan di browser; aturan lengkap tetap dijalankan server.
    function validateStep1() {
        var p = readProfile();
        if (!p.fullName) return ['Nama lengkap wajib diisi.', 'fullName'];
        if (!p.birthPlace) return ['Tempat lahir wajib diisi.', 'birthPlace'];
        if (!p.birthDate) return ['Tanggal lahir wajib diisi.', 'birthDate'];
        if (!p.address) return ['Alamat lengkap wajib diisi.', 'address'];
        if (!p.phone) return ['No. telepon/WhatsApp wajib diisi.', 'phone'];
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(p.email)) return ['Email aktif wajib diisi dengan benar.', 'email'];
        for (var i = 0; i < FIXED.length; i++) {
            if (!docOf(FIXED[i].kind)) return ['Unggah ' + FIXED[i].label + ' terlebih dahulu.', null];
        }
        if (!certs().length) return ['Unggah minimal satu sertifikat pendukung.', null];
        if (pending.length) return ['Selesaikan atau batalkan baris sertifikat yang belum diunggah.', null];
        return null;
    }

    function goStep(step) {
        $('actBioForm').hidden = step !== 1;
        $('actPwForm').hidden = step !== 2;
        $('actStep1').className = step === 1 ? 'is-active' : 'is-done';
        $('actStep2').className = step === 2 ? 'is-active' : '';
        window.scrollTo(0, 0);
        if (step === 2) $('password').focus();
    }

    if (!token) {
        showError('Link aktivasi tidak lengkap. Gunakan link persis seperti yang dikirim admin.');
        return;
    }

    var info = ELIPS.api.call('tutor.activationInfo', { token: token });
    if (!info.ok) {
        showError(info.message || 'Link aktivasi tidak valid atau sudah kedaluwarsa.');
        return;
    }

    var data = info.data || {};
    limits = data.limits || limits;
    documents = data.documents || [];
    $('actIntro').textContent = 'Halo ' + (data.name || '') + '! Lengkapi data dan berkas di bawah, lalu buat sandi.';
    $('actBody').hidden = false;
    var pre = data.prefill || {};
    ['fullName', 'birthPlace', 'birthDate', 'address', 'phone', 'email'].forEach(function (key) {
        if (pre[key]) $(key).value = pre[key];
    });
    render();

    document.addEventListener('click', function (event) {
        var btn = event.target.closest && event.target.closest('[data-pick],[data-remove],[data-drop]');
        if (!btn || $('actBioForm').hidden) return;
        if (btn.hasAttribute('data-pick')) pickFile(btn.getAttribute('data-pick'), btn.getAttribute('data-key') || '');
        else if (btn.hasAttribute('data-remove')) removeDoc(btn.getAttribute('data-remove'));
        else if (btn.hasAttribute('data-drop')) {
            syncPendingNames();
            pending = pending.filter(function (r) { return r.key !== btn.getAttribute('data-drop'); });
            render();
        }
    });

    $('tutAddCert').addEventListener('click', function () {
        syncPendingNames();
        pending.push({ key: 'n' + (++pendingSeq), name: '' });
        render();
        var inputs = document.querySelectorAll('[data-pending-name]');
        if (inputs.length) inputs[inputs.length - 1].focus();
    });

    ELIPS.passwordRules.attach($('password'), $('pwRules'));
    ELIPS.passwordRules.bindToggles($('actPwForm'));

    $('actBioForm').addEventListener('submit', function (event) {
        event.preventDefault();
        syncPendingNames();
        var problem = validateStep1();
        if (problem) {
            setError($('actBioError'), problem[0]);
            if (problem[1]) $(problem[1]).focus();
            return;
        }
        setError($('actBioError'), '');
        goStep(2);
    });

    $('actBack').addEventListener('click', function () { goStep(1); });

    $('actPwForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var password = $('password').value;
        var confirm = $('passwordConfirm').value;
        if (!ELIPS.passwordRules.allValid(password)) {
            setError($('actPwError'), 'Sandi belum memenuhi semua aturan.');
            $('password').focus();
            return;
        }
        if (password !== confirm) {
            setError($('actPwError'), 'Konfirmasi sandi tidak sama.');
            $('passwordConfirm').focus();
            return;
        }
        setError($('actPwError'), '');
        $('actSubmit').disabled = true;

        var result = ELIPS.api.call('tutor.activationComplete', {
            token: token,
            profile: readProfile(),
            password: password,
            passwordConfirm: confirm
        });
        if (!result.ok) {
            $('actSubmit').disabled = false;
            if (/sandi|password/i.test(result.message) && !/email|telepon|nomor/i.test(result.message)) {
                setError($('actPwError'), result.message);
            } else {
                setError($('actBioError'), result.message);
                goStep(1);
            }
            return;
        }
        ELIPS.toast('Akun aktif. Selamat datang, tentor!');
        window.location.href = ELIPS.basePathPrefix() + ((result.data && result.data.redirect) || 'instructor/dashboard.php');
    });
});