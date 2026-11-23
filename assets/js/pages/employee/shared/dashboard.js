// ELIPS LMS - skrip halaman employee/shared/dashboard.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const user = ELIPS.getSessionUser();
    if (!user) return;
    const sidebarName = document.getElementById('employeeSidebarName');
    if (sidebarName) sidebarName.textContent = user.name;
});

