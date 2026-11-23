(function (ELIPS) {
    'use strict';

    function guardEmployee(allowedDivisions) {
        var user = ELIPS.getSessionUser();
        if (!user) {
            window.location.replace(ELIPS.basePathPrefix() + 'login.html');
            return null;
        }
        if (user.role === 'admin') return user;
        if (user.role !== 'karyawan') {
            window.location.replace(ELIPS.dashboardPathForRole(user.role));
            return null;
        }
        if (allowedDivisions && allowedDivisions.length && allowedDivisions.indexOf(user.division) === -1) {
            window.location.replace(ELIPS.dashboardPathForRole(user.role));
            return null;
        }
        return user;
    }

    function initEmployeeShell(user, opts) {
        opts = opts || {};
        if (!user) return;
        var name = user.name || 'Karyawan';
        var initials = name.substring(0, 2).toUpperCase();
        var div = ELIPS.divisionLabel ? (ELIPS.divisionLabel(user.division) || user.division || '') : (user.division || '');
        var divisionLabel = (user.role === 'admin' ? 'Admin' : 'Karyawan') + (div ? (' • ' + div) : '');
        var set = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        set('employeeName', name);
        set('employeeSidebarName', name);
        set('employeeAvatar', initials);
        set('employeeDivision', divisionLabel);

        var sidebar = document.querySelector('.dashboard-sidebar');
        var toggle = document.getElementById(opts.toggleId || 'opsSidebarToggle') || document.getElementById('keuSidebarToggle');
        var backdrop = document.getElementById(opts.backdropId || 'opsSidebarBackdrop') || document.getElementById('keuSidebarBackdrop');
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
        if (sidebar) sidebar.addEventListener('click', function (e) {
            if (e.target.closest('a')) closeSidebar();
        });
    }

    ELIPS.employee = ELIPS.employee || {};
    ELIPS.employee.guardEmployee = guardEmployee;
    ELIPS.employee.initEmployeeShell = initEmployeeShell;
})(window.ELIPS);
