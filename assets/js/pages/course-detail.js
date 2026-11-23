// Direktori: assets/js/pages/course-detail.js
// ELIPS LMS - skrip halaman course-detail.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const courseId = parseInt(params.get('id'), 10) || 1;
    const course = ELIPS.getCourseById(courseId);
    const user = ELIPS.getCurrentUser();
    const enrollBtn = document.getElementById('enrollBtn');
    const enrollBtnText = document.getElementById('enrollBtnText');
    const modulesList = document.getElementById('courseModulesList');

    const iconFolder = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
    const iconChevron = '<svg class="lp-module-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
    const iconLock = '<svg class="lp-module-lock" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>';
    const iconFile = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h4M9 13h6M9 17h6"/></svg>';
    const iconPlay = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 5l11 7-11 7z"/></svg>';
    const iconCalendar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
    const iconBoard = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h18v12H3z"/><path d="M12 16v4M8 20h8"/></svg>';
    const rupiah = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };

    if (!course) {
        document.getElementById('courseTitle').textContent = 'Kursus Tidak Ditemukan';
        document.getElementById('courseDesc').textContent = 'Kursus yang Anda cari tidak tersedia. Kembali ke daftar pelatihan untuk memilih kursus lain.';
        enrollBtn.hidden = true;
        modulesList.innerHTML = '<p class="lp-detail-empty">Kursus tidak tersedia.</p>';
        return;
    }

    // ---------- Popup formulir pendaftaran (pengunjung belum login) ----------
    const daftarModal = document.getElementById('daftarModal');
    const daftarDialog = daftarModal ? daftarModal.querySelector('.lp-modal-dialog') : null;
    const daftarForm = document.getElementById('daftarForm');
    const daftarError = document.getElementById('daftarError');
    const daftarCourse = document.getElementById('daftarModalCourse');
    const daftarCourseId = document.getElementById('daftarCourseId');
    const daftarCourseTitle = document.getElementById('daftarCourseTitle');
    const daftarKelasWrap = document.getElementById('daftarKelasWrap');
    const daftarKelasList = document.getElementById('daftarKelasList');
    const daftarKelasEmpty = document.getElementById('daftarKelasEmpty');
    const daftarSubmit = document.getElementById('daftarSubmit');
    let lastFocused = null;
    let classChoiceRequired = false;

    function fmtPeriod(item) {
        const start = item.periodStart ? ELIPS.formatDate(item.periodStart) : '';
        const end = item.periodEnd ? ELIPS.formatDate(item.periodEnd) : '';
        if (start && end) return start + ' – ' + end;
        return start || end || 'Jadwal menyusul';
    }

    // Memuat kelas yang dibuka untuk program ini beserta sisa kuotanya (mode server).
    function loadClassChoices() {
        classChoiceRequired = false;
        if (!daftarKelasWrap || !(ELIPS.api && ELIPS.api.enabled)) return;
        const result = ELIPS.api.call('course.classes', { courseId: course.id });
        daftarKelasWrap.hidden = false;
        daftarKelasList.innerHTML = '';
        daftarKelasEmpty.hidden = true;
        if (daftarSubmit) daftarSubmit.disabled = false;

        const items = result && result.ok ? (result.data || []) : [];
        if (!items.length) {
            daftarKelasEmpty.textContent = result && result.ok
                ? 'Belum ada kelas yang dibuka untuk program ini. Silakan hubungi kami.'
                : ((result && result.message) || 'Daftar kelas tidak dapat dimuat.');
            daftarKelasEmpty.hidden = false;
            if (daftarSubmit) daftarSubmit.disabled = true;
            return;
        }
        classChoiceRequired = true;
        items.forEach(function (item) {
            const label = document.createElement('label');
            label.className = 'lp-class-option' + (item.full ? ' is-full' : '');
            const seats = item.full
                ? 'Penuh'
                : (item.seatsLeft === null ? 'Kuota terbuka' : 'Sisa ' + item.seatsLeft + ' kursi');
            const place = item.branchName || item.location || '';
            label.innerHTML = '<input type="radio" name="classId" value="' + Number(item.id) + '"'
                + (item.full ? ' disabled' : '') + '>'
                + '<span class="lp-class-body">'
                + '<strong>' + ELIPS.escapeHtml(item.name) + '</strong>'
                + '<span>' + ELIPS.escapeHtml(fmtPeriod(item)) + (place ? ' • ' + ELIPS.escapeHtml(place) : '') + '</span>'
                + (item.scheduleNote ? '<span>' + ELIPS.escapeHtml(item.scheduleNote) + '</span>' : '')
                + '</span>'
                + '<em class="lp-class-seats">' + ELIPS.escapeHtml(seats) + '</em>';
            daftarKelasList.appendChild(label);
        });
        const firstFree = daftarKelasList.querySelector('input:not([disabled])');
        if (firstFree) firstFree.checked = true;
    }

    function openDaftarModal() {
        if (!daftarModal) return;
        lastFocused = document.activeElement;
        if (daftarCourse) daftarCourse.textContent = course.title;
        if (daftarCourseId) daftarCourseId.value = course.id;
        if (daftarCourseTitle) daftarCourseTitle.value = course.title;
        if (daftarError) daftarError.hidden = true;
        loadClassChoices();
        daftarModal.hidden = false;
        document.body.classList.add('lp-modal-open');
        const firstInput = daftarForm ? daftarForm.querySelector('input:not([type="hidden"])') : null;
        if (firstInput) firstInput.focus();
    }

    function closeDaftarModal() {
        if (!daftarModal || daftarModal.hidden) return;
        daftarModal.hidden = true;
        document.body.classList.remove('lp-modal-open');
        if (daftarForm) daftarForm.reset();
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        lastFocused = null;
    }

    if (daftarModal && daftarForm) {
        daftarModal.querySelectorAll('[data-modal-close]').forEach(function (element) {
            element.addEventListener('click', closeDaftarModal);
        });

        document.addEventListener('keydown', function (e) {
            if (!daftarModal || daftarModal.hidden) return;
            if (e.key === 'Escape') {
                closeDaftarModal();
                return;
            }
            if (e.key !== 'Tab' || !daftarDialog) return;

            const focusable = Array.prototype.slice.call(daftarDialog.querySelectorAll(
                'button:not([disabled]), input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
            )).filter(function (element) { return element.offsetParent !== null; });
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        daftarForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (daftarError) daftarError.hidden = true;
            if (!daftarForm.checkValidity()) {
                daftarForm.reportValidity();
                return;
            }

            const websiteField = document.getElementById('daftarWebsite');
            const chosenClass = daftarKelasList ? daftarKelasList.querySelector('input[name="classId"]:checked') : null;
            if (classChoiceRequired && !chosenClass) {
                if (daftarError) {
                    daftarError.textContent = 'Pilih salah satu kelas yang masih tersedia.';
                    daftarError.hidden = false;
                }
                return;
            }
            const data = {
                classId: chosenClass ? Number(chosenClass.value) : 0,
                courseId: Number(daftarCourseId ? daftarCourseId.value : course.id) || course.id,
                courseTitle: daftarCourseTitle ? daftarCourseTitle.value : course.title,
                name: document.getElementById('daftarNama').value.trim(),
                birthPlace: document.getElementById('daftarTempatLahir').value.trim(),
                birthDate: document.getElementById('daftarTanggalLahir').value,
                address: document.getElementById('daftarAlamat').value.trim(),
                phone: document.getElementById('daftarPhone').value.trim(),
                email: document.getElementById('daftarEmail').value.trim().toLowerCase(),
                website: websiteField ? websiteField.value : ''
            };

            const result = typeof ELIPS.addCourseRegistration === 'function'
                ? ELIPS.addCourseRegistration(data)
                : null;

            if (result && result.ok === false) {
                if (daftarError) {
                    daftarError.textContent = result.message || 'Pendaftaran gagal disimpan.';
                    daftarError.hidden = false;
                } else {
                    ELIPS.toast(result.message || 'Pendaftaran gagal disimpan.');
                }
                return;
            }

            closeDaftarModal();
            ELIPS.toast((result && result.message) || 'Data pendaftaran berhasil dikirim. Kami akan menghubungimu.');
        });
    }

    document.title = course.title + ' - ELIPS LMS';
    document.getElementById('courseCategory').textContent = course.category || 'Kursus';
    document.getElementById('courseTitle').textContent = course.title;
    document.getElementById('courseDesc').textContent = course.description || '';
    document.getElementById('courseInstructor').textContent = course.instructorName || 'Belum ditugaskan';

    const structure = ELIPS.getCourseStructure(course.id);
    let totalLessons = 0;
    structure.forEach(function (item) { totalLessons += item.lessons.length; });
    document.getElementById('courseMeta').textContent = structure.length + ' Modul • ' + totalLessons + ' Pelajaran';

    // Informasi katalog tambahan dari courses (lihat seed-all.php).
    const heroImage = document.getElementById('courseImage');
    if (heroImage && course.image) heroImage.src = course.image;
    document.getElementById('courseLocation').textContent = course.location || '-';

    const startEl = document.getElementById('courseStartDate');
    if (course.startDate) {
        const startDate = new Date(course.startDate);
        startEl.textContent = isNaN(startDate.getTime())
            ? course.startDate
            : startDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    document.getElementById('courseMeetings').textContent = course.meetings
        ? Number(course.meetings) + ' Pertemuan' : '-';

    // Harga kini tampil di hero, bukan di kartu samping.
    const price = Number(course.price) || 0;
    const discountPrice = Number(course.discountPrice) || 0;
    const priceOld = document.getElementById('coursePriceOld');
    const priceNow = document.getElementById('coursePrice');
    const discountBadge = document.getElementById('courseDiscount');
    if (price > 0 && discountPrice > 0 && discountPrice < price) {
        priceOld.textContent = rupiah(price);
        priceOld.hidden = false;
        priceNow.textContent = rupiah(discountPrice);
        discountBadge.textContent = '-' + Math.round((1 - discountPrice / price) * 100) + '%';
        discountBadge.hidden = false;
    } else if (price > 0) {
        priceNow.textContent = rupiah(price);
    }

    // Hak akses materi: hanya peserta yang terdaftar aktif di kursus ini
    // (plus admin/instruktur) yang boleh membuka seluruh modul.
    const enrollment = user ? ELIPS.getEnrollment(user.id, course.id) : null;
    const isEnrolled = !!(enrollment && enrollment.status === 'active');
    const isAdmin = !!(user && user.role === 'admin');
    const isInstructor = !!(user && user.role === 'instruktur' && course.instructorId === user.id);
    const canOpenAllModules = (!!user && user.role === 'peserta' && isEnrolled) || isAdmin || isInstructor;
    const freeModuleCount = 2;

    // Modul & pelajaran: accordion. Pengunjung hanya melihat 2 modul pertama;
    // sisanya disembunyikan denganPlaceholder bergradasi agar jumlah modul tetap terlihat.
    const visibleStructure = canOpenAllModules ? structure : structure.slice(0, freeModuleCount);
    const hiddenModuleCount = structure.length - visibleStructure.length;

    const moduleHint = document.getElementById('moduleHint');
    if (moduleHint && hiddenModuleCount > 0) {
        moduleHint.innerHTML = '<strong>' + ELIPS.escapeHtml(course.title) + '</strong>';
    }

    if (structure.length === 0) {
        modulesList.innerHTML = '<p class="lp-detail-empty">Instruktur belum menambahkan modul untuk kursus ini.</p>';
    } else {
        const moduleHtml = visibleStructure.map(function (item, index) {
            const panelId = 'module-panel-' + index;
            const lessons = item.lessons.map(function (lesson) {
                const icon = lesson.type === 'video' ? iconPlay : iconFile;
                return '<div class="lp-lesson">' + icon + '<span>' + ELIPS.escapeHtml(lesson.title) + '</span></div>';
            }).join('');
            return '<div class="lp-module">' +
                '<button class="lp-module-head" type="button" aria-expanded="false" aria-controls="' + panelId + '">' +
                '<span class="lp-module-info">' + iconFolder + '<span class="lp-module-title">' + ELIPS.escapeHtml(item.module.title) + '</span></span>' +
                '<span class="lp-module-right"><span class="lp-module-count">' + item.lessons.length + ' Pelajaran</span>' + iconChevron + '</span>' +
                '</button>' +
                '<div class="lp-lessons" id="' + panelId + '" hidden>' + lessons + '</div>' +
                '</div>';
        }).join('');

        const hiddenModules = canOpenAllModules ? [] : structure.slice(freeModuleCount);
        const teaserHtml = hiddenModules.length > 0
            ? '<div class="lp-modules-teaser">' +
                hiddenModules.map(function (item) {
                    return '<div class="lp-ghost-module">' + iconFolder +
                        '<span class="lp-ghost-title">' + ELIPS.escapeHtml(item.module.title) + '</span>' +
                        '<span class="lp-ghost-count">' + item.lessons.length + ' Pelajaran</span>' +
                        '</div>';
                }).join('') +
                '</div>' +
                '<div class="lp-modules-lock-note">' + iconLock +
                '<span><strong>' + hiddenModuleCount + ' modul lainnya terkunci</strong>' +
                '<small>Ikuti pelatihan ini untuk membuka seluruh materi.</small></span>' +
                '</div>'
            : '';

        modulesList.innerHTML = moduleHtml + teaserHtml;
    }

    modulesList.addEventListener('click', function (event) {
        const head = event.target.closest('.lp-module-head');
        if (!head) return;
        const panel = document.getElementById(head.getAttribute('aria-controls'));
        const willOpen = !head.closest('.lp-module').classList.contains('is-open');
        head.closest('.lp-module').classList.toggle('is-open', willOpen);
        head.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (panel) panel.hidden = !willOpen;
    });

    // Tombol aksi: belum login -> Daftar Sekarang.
    if (!user) {
        enrollBtnText.textContent = 'Daftar Sekarang';
        enrollBtn.onclick = openDaftarModal;
    } else {
        if (!isEnrolled && !isAdmin && !isInstructor) {
            enrollBtnText.textContent = 'Belum Ditugaskan';
            enrollBtn.disabled = true;
            enrollBtn.onclick = function () {
                ELIPS.toast('Siswa hanya dapat mengakses kursus yang ditugaskan oleh Admin.');
            };
            const note = document.createElement('p');
            note.className = 'lp-detail-note';
            note.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg> Penugasan siswa ke kursus dilakukan oleh Admin melalui menu Penugasan Siswa.';
            enrollBtn.parentElement.appendChild(note);
            return;
        }

        if (course.status !== 'published' && !isAdmin && !isInstructor) {
            enrollBtnText.textContent = 'Kursus Belum Dibuka';
            enrollBtn.disabled = true;
            return;
        }

        enrollBtnText.textContent = isEnrolled || isAdmin || isInstructor ? 'Lanjutkan Belajar' : 'Mulai Belajar';
        enrollBtn.onclick = function () {
            window.location.href = 'learning.php?id=' + course.id;
        };
    }

    // Kartu pelatihan lain di samping (komponen .lp-card sama seperti index).
    const published = ELIPS.getCourses().filter(function (c) {
        return c.status === 'published' && c.id !== course.id;
    }).slice(0, 3);
    const related = document.getElementById('relatedCourses');
    if (published.length === 0) {
        related.innerHTML = '<p class="lp-related-empty">Belum ada pelatihan lain yang tersedia.</p>';
    } else {
    related.innerHTML = published.map(function (item) {
        const chip = item.location || item.category || '';
        const image = item.image || 'assets/img/course-laptop.svg';
        const meta = [];
        if (item.startDate) {
            const d = new Date(item.startDate);
            if (!isNaN(d.getTime())) {
                meta.push('<span>' + iconCalendar + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + '</span>');
            }
        }
        if (item.meetings) meta.push('<span>' + iconBoard + Number(item.meetings) + ' pertemuan</span>');

        const p = Number(item.price) || 0;
        const disc = Number(item.discountPrice) || 0;
        let priceHtml = '';
        if (p > 0 && disc > 0 && disc < p) {
            priceHtml = '<div class="lp-price"><div class="lp-price-col">' +
                '<span class="lp-price-old">' + rupiah(p) + '</span>' +
                '<span class="lp-price-now">' + rupiah(disc) + '</span></div>' +
                '<span class="lp-badge">-' + Math.round((1 - disc / p) * 100) + '%</span></div>';
        } else if (p > 0) {
            priceHtml = '<div class="lp-price"><div class="lp-price-col"><span class="lp-price-now">' + rupiah(p) + '</span></div></div>';
        }

        return '<article class="lp-card">' +
            '<div class="lp-card-media"><img src="' + ELIPS.escapeHtml(image) + '" alt="" loading="lazy" width="600" height="400">' +
            (chip ? '<span class="lp-chip">' + ELIPS.escapeHtml(chip) + '</span>' : '') + '</div>' +
            '<div class="lp-card-body">' +
            '<h3 class="lp-card-title">' + ELIPS.escapeHtml(item.title) + '</h3>' +
            (meta.length ? '<div class="lp-card-meta">' + meta.join('') + '</div>' : '') +
            priceHtml +
            '<a class="lp-btn lp-btn-slide" href="course-detail.php?id=' + item.id + '" aria-label="Lihat detail ' + ELIPS.escapeHtml(item.title) + '">' +
            '<span class="lp-slide-txt"><span class="lp-slide-in"><span>Lihat Detail</span><span aria-hidden="true">Selengkapnya</span></span></span>' +
            '</a></div></article>';
    }).join('');
    }

    // Navigasi sebelumnya / selanjutnya berurutan sesuai katalog.
    const catalog = ELIPS.getCourses().filter(function (c) { return c.status === 'published'; });
    const position = catalog.findIndex(function (c) { return c.id === course.id; });
    const prevBtn = document.getElementById('prevCourse');
    const nextBtn = document.getElementById('nextCourse');
    const prev = position > 0 ? catalog[position - 1] : null;
    const next = position !== -1 && position < catalog.length - 1 ? catalog[position + 1] : null;

    [
        { button: prevBtn, target: prev, label: 'Sebelumnya' },
        { button: nextBtn, target: next, label: 'Selanjutnya' }
    ].forEach(function (entry) {
        if (!entry.button) return;
        if (!entry.target) {
            entry.button.disabled = true;
            return;
        }
        entry.button.setAttribute('title', entry.target.title);
        entry.button.setAttribute('aria-label', entry.label + ': ' + entry.target.title);
        entry.button.onclick = function () {
            window.location.href = 'course-detail.php?id=' + entry.target.id;
        };
    });
});