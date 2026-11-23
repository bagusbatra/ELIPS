// ELIPS LMS - skrip halaman employee/marketing/tasks.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const user = ELIPS.getSessionUser();
    if (user) {
        const sidebarName = document.getElementById('employeeSidebarName');
        if (sidebarName) sidebarName.textContent = user.name;
    }

    const sidebar = document.querySelector('.dashboard-sidebar');
    const toggle = document.getElementById('taskSidebarToggle');
    const backdrop = document.getElementById('taskSidebarBackdrop');
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
    }
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
            if (backdrop) backdrop.classList.toggle('active');
        });
    }
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    if (sidebar) {
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a')) closeSidebar();
        });
    }
});

