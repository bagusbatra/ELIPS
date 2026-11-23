// ELIPS LMS - skrip halaman admin/dashboard.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const user = ELIPS.attendance.init({ role: 'admin' });
    if (!user) return;
    const nameEl = document.querySelector('.dashboard-user-name');
    if (nameEl) nameEl.textContent = user.name;
    const avatar = document.querySelector('.dashboard-avatar');
    if (avatar) {
        const parts = String(user.name || 'A').trim().split(/\s+/).filter(Boolean);
        const initials = parts.length > 1
            ? parts[0].charAt(0) + parts[1].charAt(0)
            : (parts[0] || 'A').slice(0, 2);
        avatar.textContent = initials.toUpperCase();
    }
});

