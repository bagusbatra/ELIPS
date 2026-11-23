// ELIPS LMS - skrip halaman admin/letters.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    ELIPS.letterArchive.init();
});

