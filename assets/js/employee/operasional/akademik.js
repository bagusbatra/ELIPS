(function (ELIPS) {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var user = ELIPS.employee && ELIPS.employee.guardEmployee ? ELIPS.employee.guardEmployee(['operasional']) : ELIPS.getSessionUser();
        if (!user) return;
        if (ELIPS.employee && ELIPS.employee.initEmployeeShell) {
            ELIPS.employee.initEmployeeShell(user, { toggleId: 'opsSidebarToggle', backdropId: 'opsSidebarBackdrop' });
        }
    });
})(window.ELIPS);
