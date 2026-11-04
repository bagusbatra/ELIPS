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
            const authActions = document.querySelector('.nav-actions');
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

            published.forEach(function (course) {
                const lessons = ELIPS.getLessonsByCourse(course.id).length;
                const modules = ELIPS.getModulesByCourse(course.id).length;
                const isEnrolled = enrollments.some(function (e) { return e.courseId === course.id; });
                const card = document.createElement('a');
                card.href = 'course-detail.html?id=' + course.id;
                card.className = 'course-card';
                card.innerHTML = [
                    '<div class="course-card-img"></div>',
                    '<div class="course-card-content">',
                    '  <span class="course-card-category">' + ELIPS.escapeHtml(course.category) + '</span>',
                    '  <h3 class="course-card-title">' + ELIPS.escapeHtml(course.title) + '</h3>',
                    '  <p class="course-card-desc">' + ELIPS.escapeHtml(course.description) + '</p>',
                    '  <div class="course-card-meta">',
                    '    <span><i class="bi bi-collection"></i> ' + modules + ' Modul</span>',
                    '    <span><i class="bi bi-journal-text"></i> ' + lessons + ' Pelajaran</span>',
                    '  </div>',
                    isEnrolled ? '<div style="margin-top: 0.5rem;"><span class="badge badge-success">Ditugaskan</span></div>' : '',
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

                const result = ELIPS.authenticateUser(email, password);
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
