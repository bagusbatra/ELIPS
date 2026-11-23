// ELIPS LMS - skrip halaman employee/shared/attendance.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const user = ELIPS.attendance.init({ role: 'karyawan' });
    if (!user) return;
    const nameEl = document.getElementById('employeeName');
    if (nameEl) nameEl.textContent = user.name;
    const greeting = document.getElementById('employeeGreeting');
    if (greeting) greeting.textContent = 'Selamat datang, ' + user.name
        + '. Catat kehadiran harian Anda langsung dari perangkat ini.';
    if (user.division === 'marketing') {
        const clientsLink = document.getElementById('attendanceClientsLink');
        if (clientsLink) clientsLink.style.display = '';
        const reportsLink = document.getElementById('attendanceReportsLink');
        if (reportsLink) reportsLink.style.display = '';
    }
});

