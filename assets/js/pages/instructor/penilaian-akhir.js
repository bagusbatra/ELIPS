// Direktori: assets/js/pages/instructor/penilaian-akhir.js
// ELIPS LMS - daftar siswa untuk penilaian akhir (instructor/penilaian-akhir.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };
    const classId = TP.id('id');
    let data = null;

    if (!classId) {
        $('tpSub').textContent = '';
        $('tpError').innerHTML = TP.errorBox('Pilih angkatan dari halaman Penilaian.');
        $('tpList').innerHTML = '';
        return;
    }

    load();

    function load() {
        TP.data('tp.final.list', { classId: classId }).then(function (res) {
            data = res.data;
            render();
        }).catch(function (err) {
            $('tpSub').textContent = '';
            $('tpList').innerHTML = '';
            $('tpBanner').innerHTML = '<div class="tp-note tp-note--amber"><i class="bi bi-lock"></i><span>' + TP.esc(err.message) + '</span></div>';
        });
    }

    function render() {
        const c = data.class;
        const k = data.counts;
        const sent = !!data.sentAt;
        document.title = 'Penilaian akhir ' + c.name + ' - ELIPS LMS';
        $('tpCrumb').innerHTML = '<i class="bi bi-arrow-left"></i> ' + TP.esc(c.name);
        $('tpCrumb').href = 'penilaian-angkatan.php?id=' + c.id;
        $('tpBadge').innerHTML = sent ? TP.badge('Terkirim', 'teal') : TP.badge('Semua sesi dikonfirmasi', 'teal');
        $('tpSub').textContent = c.courseTitle + ' · ' + TP.period(c.periodStart, c.periodEnd) + ' · ' + k.total + ' siswa · ' + data.template.items + ' sub materi · ' + TP.num(data.template.jp) + ' JP';

        $('tpBanner').innerHTML = sent ?
            '<div class="tp-note tp-note--teal" style="margin-bottom:1.25rem"><i class="bi bi-check2-circle"></i><span>Nilai akhir dikirim ke operasional pada ' + TP.date(data.sentAt) + '. Nilai tidak bisa diubah lagi.</span></div>' :
            '<div class="tp-note" style="margin-bottom:1.25rem"><i class="bi bi-info-circle"></i><span>Isi nilai tiap sub materi untuk setiap siswa. Materi, sub materi, dan JP yang Anda susun otomatis menjadi template untuk siswa lain dan angkatan berikutnya. Setelah dikirim, nilai tidak bisa diubah.</span></div>';

        const stats = [
            { v: k.complete + ' / ' + k.total, l: 'Siswa lengkap', bar: true },
            { v: k.draft, l: 'Masih draf' },
            { v: k.empty, l: 'Belum diisi' }
        ];
        $('tpStats').innerHTML = stats.map(function (s, i) {
            return '<div class="tp-card tp-stat tp-rise" style="animation-delay:' + (i * 50) + 'ms"><span class="tp-stat-label">' + s.l + '</span><span class="tp-stat-value">' + TP.esc(s.v) + '</span>' +
                (s.bar ? TP.progress(k.complete, k.total, 'teal') : '') + '</div>';
        }).join('');

        const send = $('tpSend');
        const ready = !sent && k.total > 0 && k.complete === k.total;
        send.disabled = !ready;
        send.classList.toggle('is-disabled', !ready);
        send.hidden = sent;
        send.title = ready ? '' : 'Aktif setelah semua siswa lengkap';

        if (!data.students.length) {
            $('tpList').innerHTML = '<div class="tp-card">' + TP.empty('bi-people', 'Belum ada siswa', 'Belum ada siswa aktif pada angkatan ini.') + '</div>';
            return;
        }
        $('tpList').innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>Siswa</th><th>Sub materi terisi</th><th class="is-num">Rata-rata</th><th>Keterangan</th><th>Status</th><th></th></tr></thead><tbody>' +
            data.students.map(function (s) {
                const status = s.status === 'lengkap' ? TP.badge('Lengkap', 'teal') : s.status === 'draf' ? TP.badge('Draf', 'amber') : TP.badge('Belum diisi', 'gray');
                const label = sent ? 'Lihat' : s.status === 'lengkap' ? 'Ubah' : s.status === 'draf' ? 'Lanjut isi' : 'Isi nilai';
                const total = s.count || data.template.items;
                return '<tr><td><div class="tp-row"><span class="tp-avatar">' + TP.esc(TP.initials(s.name)) + '</span><span class="tp-table-title">' + TP.esc(s.name) + '</span></div></td>' +
                    '<td style="min-width:9rem">' + TP.progress(s.filled, total, 'teal') + '<div class="tp-table-sub">' + s.filled + '/' + total + '</div></td>' +
                    '<td class="is-num">' + (s.average === null ? '-' : TP.num(s.average)) + '</td>' +
                    '<td>' + (s.predicate ? TP.badge(s.predicate, 'blue') : '-') + '</td><td>' + status + '</td>' +
                    '<td class="is-num"><a class="btn ' + (s.status === 'lengkap' || sent ? 'btn-ghost' : 'btn-primary') + ' tp-btn-sm" href="penilaian-rapor.php?id=' + c.id + '&student=' + s.enrollmentId + '">' + label + '</a></td></tr>';
            }).join('') + '</tbody></table></div></div>';
        TP.animateBars(document);
    }

    $('tpSend').addEventListener('click', function () {
        if (!data) return;
        TP.confirm('Kirim nilai akhir?', 'Nilai akhir ' + data.counts.total + ' siswa akan dikirim ke operasional dan tidak bisa diubah lagi.', 'Kirim sekarang').then(function (ok) {
            if (!ok) return;
            TP.data('tp.final.send', { classId: classId }).then(function (res) {
                TP.toast(res.message);
                load();
            }).catch(function (err) { TP.toast(err.message); });
        });
    });
});