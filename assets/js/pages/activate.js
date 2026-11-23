// Direktori: assets/js/pages/activate.js
// ELIPS LMS - skrip halaman activate.php
// Alur: biodata (dengan data pendaftaran terisi otomatis) -> buat sandi -> akun aktif.
document.addEventListener('DOMContentLoaded', function () {
    var token = new URLSearchParams(window.location.search).get('token') || '';
    var $ = function (id) { return document.getElementById(id); };
    var options = null;

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

    function fillSelect(select, items, placeholder, valueKey, labelKey) {
        var html = '<option value="">' + ELIPS.escapeHtml(placeholder) + '</option>';
        items.forEach(function (item) {
            var value = valueKey ? item[valueKey] : item;
            var label = labelKey ? item[labelKey] : item;
            html += '<option value="' + ELIPS.escapeHtml(value) + '">' + ELIPS.escapeHtml(label) + '</option>';
        });
        select.innerHTML = html;
    }

    function levelConfig(code) {
        return (options.educationLevels || []).filter(function (l) { return l.code === code; })[0] || null;
    }

    // Isian pendidikan menyesuaikan jenjang yang dipilih.
    function applyLevel() {
        var cfg = levelConfig($('educationLevel').value);
        $('eduInstitutionWrap').hidden = !cfg;
        $('eduYearWrap').hidden = !cfg;
        $('eduMajorWrap').hidden = !cfg || !cfg.majorLabel;
        if (!cfg) return;
        $('eduInstitutionLabel').textContent = cfg.institutionLabel;
        $('eduYearLabel').textContent = cfg.yearLabel;
        if (cfg.majorLabel) $('eduMajorLabel').textContent = cfg.majorLabel;
        else $('educationMajor').value = '';
    }

    function applyOccupation() {
        var other = $('occupation').value === 'Lainnya';
        $('occupationOtherWrap').hidden = !other;
        if (!other) $('occupationOther').value = '';
    }

    function readProfile() {
        return {
            fullName: $('fullName').value.trim(),
            address: $('address').value.trim(),
            phone: $('phone').value.trim(),
            birthPlace: $('birthPlace').value.trim(),
            birthDate: $('birthDate').value,
            religion: $('religion').value,
            educationLevel: $('educationLevel').value,
            educationInstitution: $('educationInstitution').value.trim(),
            educationMajor: $('educationMajor').value.trim(),
            educationYear: $('educationYear').value.trim(),
            occupation: $('occupation').value,
            occupationOther: $('occupationOther').value.trim()
        };
    }

    // Validasi ringan di browser; aturan lengkap tetap dijalankan server.
    function validateProfile(p) {
        if (!p.fullName) return ['Nama lengkap wajib diisi.', 'fullName'];
        if (!p.address) return ['Alamat wajib diisi.', 'address'];
        if (!p.phone) return ['No. telp/WhatsApp wajib diisi.', 'phone'];
        if (!p.birthPlace) return ['Tempat lahir wajib diisi.', 'birthPlace'];
        if (!p.birthDate) return ['Tanggal lahir wajib diisi.', 'birthDate'];
        if (!p.religion) return ['Pilih agama.', 'religion'];
        var cfg = levelConfig(p.educationLevel);
        if (!cfg) return ['Pilih pendidikan terakhir.', 'educationLevel'];
        if (!p.educationInstitution) return [cfg.institutionLabel + ' wajib diisi.', 'educationInstitution'];
        if (cfg.majorLabel && cfg.majorRequired && !p.educationMajor) return [cfg.majorLabel + ' wajib diisi.', 'educationMajor'];
        if (!/^\d{4}$/.test(p.educationYear)) return [cfg.yearLabel + ' harus 4 digit.', 'educationYear'];
        if (!p.occupation) return ['Pilih pekerjaan.', 'occupation'];
        if (p.occupation === 'Lainnya' && !p.occupationOther) return ['Sebutkan pekerjaanmu.', 'occupationOther'];
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

    var info = ELIPS.api.call('activation.info', { token: token });
    if (!info.ok) {
        showError(info.message || 'Link aktivasi tidak valid.');
        return;
    }

    var data = info.data;
    options = data.options;
    $('actIntro').textContent = 'Lengkapi biodata dan buat sandi untuk mengaktifkan akunmu.';
    $('actBody').hidden = false;
    $('actSummary').innerHTML = 'Program <strong>' + ELIPS.escapeHtml(data.courseTitle) + '</strong>'
        + (data.className ? ' · Kelas <strong>' + ELIPS.escapeHtml(data.className) + '</strong>' : '');
    $('actEmail').value = data.email;

    fillSelect($('religion'), options.religions, 'Pilih agama');
    fillSelect($('educationLevel'), options.educationLevels, 'Pilih pendidikan terakhir', 'code', 'label');
    fillSelect($('occupation'), options.occupations, 'Pilih pekerjaan');

    // Data yang sudah ada (pendaftaran / biodata tersimpan) diisikan otomatis.
    var pre = data.prefill || {};
    ['fullName', 'address', 'phone', 'birthPlace', 'birthDate', 'religion', 'educationLevel',
        'educationInstitution', 'educationMajor', 'educationYear', 'occupation', 'occupationOther'].forEach(function (key) {
        if (pre[key] !== undefined && pre[key] !== null && $(key)) $(key).value = pre[key];
    });
    applyLevel();
    applyOccupation();

    $('educationLevel').addEventListener('change', applyLevel);
    $('occupation').addEventListener('change', applyOccupation);

    ELIPS.passwordRules.attach($('password'), $('pwRules'));
    ELIPS.passwordRules.bindToggles($('actPwForm'));

    $('actBioForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var problem = validateProfile(readProfile());
        if (problem) {
            setError($('actBioError'), problem[0]);
            $(problem[1]).focus();
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

        var result = ELIPS.api.call('activation.complete', {
            token: token,
            profile: readProfile(),
            password: password,
            passwordConfirm: confirm
        });
        if (!result.ok) {
            $('actSubmit').disabled = false;
            // Kesalahan biodata dari server: kembali ke langkah 1.
            if (/lengkap|alamat|telp|lahir|agama|pendidikan|pekerjaan|sekolah|studi|jurusan|tahun|nomor/i.test(result.message)) {
                setError($('actBioError'), result.message);
                goStep(1);
            } else {
                setError($('actPwError'), result.message);
            }
            return;
        }
        ELIPS.toast('Akun aktif. Selamat datang!');
        window.location.href = ELIPS.basePathPrefix() + 'student/dashboard.php';
    });
});