// ============================================================
// ELIPS LMS - ui.js
// Perilaku UI bersama: navigasi mobile, sidebar dashboard,
// katalog beranda, login, logout, dan style toast.
// ============================================================

(function (ELIPS) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Mobile navigation
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function () {
                navMenu.classList.toggle('active');
            });
        }

        // Session-aware navigation (public pages & mobile menu)
        const sessionUser = ELIPS.getSessionUser();
        if (sessionUser) {
            const dashboardPath = ELIPS.dashboardPathForRole(sessionUser.role);
            const authActions = document.querySelector('.nav-actions, .lp-nav-actions');
            if (authActions && authActions.querySelector('a[href$="login.html"]')) {
                authActions.innerHTML = [
                    '<a href="' + dashboardPath + '" class="btn btn-ghost"><i class="bi bi-speedometer2"></i> Dashboard</a>',
                    '<a href="' + ELIPS.basePathPrefix() + 'index.html" class="btn btn-outline" data-logout>Keluar</a>'
                ].join('');
            }

            if (navMenu && !navMenu.querySelector('[data-session-link]')) {
                const dashboardLink = document.createElement('a');
                dashboardLink.href = dashboardPath;
                dashboardLink.className = 'nav-link mobile-session-link';
                dashboardLink.setAttribute('data-session-link', '1');
                dashboardLink.textContent = 'Dashboard';

                const logoutLink = document.createElement('a');
                logoutLink.href = ELIPS.basePathPrefix() + 'index.html';
                logoutLink.className = 'nav-link mobile-session-link';
                logoutLink.setAttribute('data-session-link', '1');
                logoutLink.setAttribute('data-logout', '1');
                logoutLink.textContent = 'Keluar';

                navMenu.appendChild(dashboardLink);
                navMenu.appendChild(logoutLink);
            }
        }

        // Dashboard sidebar (mobile drawer)
        const dashboardLayout = document.querySelector('.dashboard-layout');
        const dashboardSidebar = dashboardLayout ? dashboardLayout.querySelector('.dashboard-sidebar') : null;
        if (dashboardLayout && dashboardSidebar && !document.getElementById('dashboardSidebarToggle')) {
            const sidebarButton = document.createElement('button');
            sidebarButton.type = 'button';
            sidebarButton.id = 'dashboardSidebarToggle';
            sidebarButton.className = 'dashboard-sidebar-toggle';
            sidebarButton.setAttribute('aria-label', 'Buka menu navigasi');
            sidebarButton.setAttribute('aria-expanded', 'false');
            sidebarButton.innerHTML = '<i class="bi bi-list"></i>';

            const backdrop = document.createElement('div');
            backdrop.className = 'dashboard-sidebar-backdrop';

            dashboardLayout.appendChild(sidebarButton);
            dashboardLayout.appendChild(backdrop);

            function setSidebarOpen(open) {
                dashboardSidebar.classList.toggle('active', open);
                backdrop.classList.toggle('active', open);
                sidebarButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                sidebarButton.innerHTML = '<i class="bi ' + (open ? 'bi-x-lg' : 'bi-list') + '"></i>';
            }

            sidebarButton.addEventListener('click', function () {
                setSidebarOpen(!dashboardSidebar.classList.contains('active'));
            });
            backdrop.addEventListener('click', function () {
                setSidebarOpen(false);
            });
            dashboardSidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 968) setSidebarOpen(false);
                });
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth > 968) setSidebarOpen(false);
            });
        }

        // Learning sidebar
        const sidebarToggle = document.getElementById('sidebarToggle');
        const learningSidebar = document.getElementById('learningSidebar');
        if (sidebarToggle && learningSidebar) {
            sidebarToggle.addEventListener('click', function () {
                learningSidebar.classList.toggle('active');
            });
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 968 && !learningSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    learningSidebar.classList.remove('active');
                }
            });
        }

        // Homepage course catalog (published only)
        const coursesGrid = document.getElementById('coursesGrid');
        if (coursesGrid) {
            const user = ELIPS.getCurrentUser();
            const enrollments = user ? ELIPS.getEnrollmentsByStudent(user.id) : [];
            coursesGrid.innerHTML = '';
            const published = ELIPS.getCourses().filter(function (c) { return c.status === 'published'; });

            if (published.length === 0) {
                coursesGrid.innerHTML = '<p style="color: var(--text-secondary);">Belum ada kursus yang dipublikasikan.</p>';
            }

            // Gambar kartu: pakai course.image bila ada, jika tidak gunakan
            // foto sementara dari internet (sama dengan data seeder PHP).
            const dummyImages = [
                'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=600&h=400&q=80',
                'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=600&h=400&q=80',
                'https://images.unsplash.com/photo-1497215728101-856f4ea42174?auto=format&fit=crop&w=600&h=400&q=80',
                'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&h=400&q=80'
            ];
            const calendarIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Tanggal mulai"><rect x="3" y="5" width="18" height="16" rx="3"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>';
            const boardIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Jumlah pertemuan"><path d="M3 4h18v12H3z"></path><path d="M12 16v4M8 20h8"></path></svg>';
            const rupiah = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };

            published.forEach(function (course, index) {
                const modules = ELIPS.getModulesByCourse(course.id).length;
                const lessons = ELIPS.getLessonsByCourse(course.id).length;
                const isEnrolled = enrollments.some(function (e) { return e.courseId === course.id; });

                // Kolom opsional pada data kursus: location, startDate, meetings, price, discountPrice, image.
                const location = course.location ? String(course.location) : '';
                const chip = location || course.category || '';
                const image = course.image || dummyImages[index % dummyImages.length];

                const metaParts = [];
                if (course.startDate) {
                    const d = new Date(course.startDate);
                    if (!isNaN(d.getTime())) {
                        metaParts.push('<span>' + calendarIcon + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + '</span>');
                    }
                }
                if (course.meetings) {
                    metaParts.push('<span>' + boardIcon + Number(course.meetings) + ' pertemuan</span>');
                } else if (metaParts.length === 0 && modules > 0) {
                    // Tanpa jadwal: tampilkan jumlah modul dan pelajaran sebagai gantinya.
                    metaParts.push('<span>' + boardIcon + modules + ' modul, ' + lessons + ' pelajaran</span>');
                }

                let priceHtml = '';
                const price = Number(course.price) || 0;
                const discountPrice = Number(course.discountPrice) || 0;
                if (price > 0 && discountPrice > 0 && discountPrice < price) {
                    const percent = Math.round((1 - discountPrice / price) * 100);
                    priceHtml = '<div class="lp-price"><div class="lp-price-col"><span class="lp-price-old">' + rupiah(price) +
                        '</span><span class="lp-price-now">' + rupiah(discountPrice) + '</span></div><span class="lp-badge">-' + percent + '%</span></div>';
                } else if (price > 0) {
                    priceHtml = '<div class="lp-price"><div class="lp-price-col"><span class="lp-price-now">' + rupiah(price) + '</span></div></div>';
                }

                const card = document.createElement('article');
                card.className = 'lp-card';
                if (location) card.setAttribute('data-lokasi', location);
                card.innerHTML = [
                    '<div class="lp-card-media">',
                    '  <img src="' + ELIPS.escapeHtml(image) + '" alt="" loading="lazy" width="600" height="400">',
                    chip ? '  <span class="lp-chip">' + ELIPS.escapeHtml(chip) + '</span>' : '',
                    '</div>',
                    '<div class="lp-card-body">',
                    '  <h3 class="lp-card-title">' + ELIPS.escapeHtml(course.title) + '</h3>',
                    metaParts.length ? '  <div class="lp-card-meta">' + metaParts.join('') + '</div>' : '',
                    priceHtml,
                    isEnrolled ? '  <span class="badge badge-success" style="align-self: flex-start;">Ditugaskan</span>' : '',
                    '  <a class="lp-btn lp-btn-slide" href="course-detail.html?id=' + course.id + '" aria-label="Lihat detail ' + ELIPS.escapeHtml(course.title) + '">',
                    '    <span class="lp-slide-txt"><span class="lp-slide-in"><span>Lihat Detail</span><span aria-hidden="true">Selengkapnya</span></span></span>',
                    '  </a>',
                    '</div>'
                ].join('');
                coursesGrid.appendChild(card);
            });
        }

        // Login handler: email + password diverifikasi melalui API CRUD.
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const emailInput = document.getElementById('email');
                const passwordInput = document.getElementById('password');
                const email = (emailInput ? emailInput.value : '').trim().toLowerCase();
                const password = passwordInput ? passwordInput.value : '';
                const rememberInput = document.getElementById('remember');

                const result = ELIPS.authenticateUser(email, password, {
                    remember: rememberInput ? rememberInput.checked : false
                });
                if (!result.ok) {
                    ELIPS.toast(result.message);
                    return;
                }

                const user = result.data;
                ELIPS.setCurrentUser(user.id);
                ELIPS.toast('Berhasil masuk sebagai ' + user.name);
                if (user.role === 'admin') {
                    window.location.href = 'admin/dashboard.html';
                } else if (user.role === 'instruktur') {
                    window.location.href = 'instructor/dashboard.html';
                } else if (user.role === 'karyawan') {
                    window.location.href = 'employee/shared/dashboard.html';
                } else {
                    window.location.href = 'student/dashboard.html';
                }
            });
        }

        // Logout links
        document.querySelectorAll('[data-logout]').forEach(function (link) {
            link.addEventListener('click', function () {
                ELIPS.logout();
                window.location.href = link.getAttribute('href') || 'index.html';
            });
        });

        // Toast keyframes (once)
        if (!document.getElementById('elipsToastStyles')) {
            const style = document.createElement('style');
            style.id = 'elipsToastStyles';
            style.textContent = [
                '@keyframes elipsSlideUp { from { opacity: 0; transform: translateX(-50%) translateY(1rem); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }',
                '@keyframes elipsSlideDown { from { opacity: 1; transform: translateX(-50%) translateY(0); } to { opacity: 0; transform: translateX(-50%) translateY(1rem); } }',
                '.learning-lesson.locked { opacity: .55; cursor: not-allowed; }',
                '.learning-lesson.locked:hover { background: transparent; color: var(--text-secondary); }',
                '.form-hint { color: var(--text-secondary); font-size: 0.8125rem; }'
            ].join('\n');
            document.head.appendChild(style);
        }
    });
})(window.ELIPS);
