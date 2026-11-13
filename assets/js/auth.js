// ============================================================
// ELIPS LMS - auth.js
// Session pengguna, navigasi berbasis role, dan guard halaman.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const read = ELIPS.read;
    const write = ELIPS.write;

    // User aktif HANYA berasal dari sesi. Tidak ada fallback berdasarkan path
    // halaman, sehingga halaman role tidak bisa dibuka tanpa login.
    function getCurrentUser() {
        return getSessionUser();
    }

    function getSessionUser() {
        const session = read(KEYS.session, null);
        const id = session && (session.userId || session);
        if (!id) return null;
        return ELIPS.getUserById(id);
    }

    function basePathPrefix() {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function dashboardPathForRole(role) {
        const prefix = basePathPrefix();
        if (role === 'admin') return prefix + 'admin/dashboard.html';
        if (role === 'instruktur') return prefix + 'instructor/dashboard.html';
        if (role === 'karyawan') return prefix + 'employee/dashboard.html';
        return prefix + 'student/dashboard.html';
    }

    function requireRole(role) {
        let user = getSessionUser();
        if (!user) user = resumeSession();
        if (!user) {
            window.location.href = basePathPrefix() + 'login.html';
            return null;
        }
        if (user.role !== role) {
            window.location.href = dashboardPathForRole(user.role);
            return null;
        }
        return user;
    }

    // Cadangan bila api.js dimuat tanpa proses resume otomatis.
    function resumeSession() {
        if (!(ELIPS.api && ELIPS.api.enabled)) return null;
        const result = ELIPS.api.call('auth.resume', {});
        if (!result || !result.ok) return null;
        return getSessionUser();
    }

    function setCurrentUser(userId) {
        write(KEYS.session, { userId: Number(userId), loggedInAt: new Date().toISOString() });
        return ELIPS.getUserById(userId);
    }

    function logout() {
        if (ELIPS.api && ELIPS.api.enabled) {
            ELIPS.api.call('auth.logout', {});
            return;
        }
        localStorage.removeItem(KEYS.session);
    }

    Object.assign(ELIPS, {
        getCurrentUser: getCurrentUser,
        getSessionUser: getSessionUser,
        basePathPrefix: basePathPrefix,
        dashboardPathForRole: dashboardPathForRole,
        requireRole: requireRole,
        resumeSession: resumeSession,
        setCurrentUser: setCurrentUser,
        logout: logout
    });
})(window.ELIPS);
