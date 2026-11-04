// ============================================================
// ELIPS LMS - config.js
// Menentukan lokasi API relatif terhadap halaman. File ini dimuat
// sebelum bundle JavaScript utama. Pada lingkungan uji JS DOM tanpa
// server, file ini tidak dieksekusi sehingga aplikasi otomatis
// berjalan dalam mode localStorage.
// ============================================================

(function (window) {
    'use strict';

    const path = (window.location.pathname || '').replace(/\\/g, '/');
    const prefix = /\/(admin|instructor|student)\/[^/]*$/.test(path) ? '../' : '';

    window.ELIPS_CONFIG = {
        apiBase: prefix + 'api/index.php'
    };
})(window);
