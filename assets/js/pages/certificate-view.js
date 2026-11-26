// Direktori: assets/js/pages/certificate-view.js
// ELIPS LMS - skrip halaman certificate-view.php (gambar sertifikat dirender server).
document.addEventListener('DOMContentLoaded', function () {
    const id = Number(new URLSearchParams(window.location.search).get('id')) || 0;
    const frame = document.getElementById('certFrame');
    const unavailable = document.getElementById('certUnavailable');
    const actions = document.getElementById('certActions');
    const base = (ELIPS.api && ELIPS.api.base) || 'api/index.php';

    function url(format, side) {
        return base + '?action=certificate.file&id=' + id + '&format=' + format + (side ? '&side=' + side : '');
    }

    if (!id) {
        frame.style.display = 'none';
        actions.style.display = 'none';
        unavailable.style.display = 'block';
        return;
    }
    document.getElementById('certFront').src = url('jpg', 'front') + '&inline=1';
    document.getElementById('certBack').src = url('jpg', 'back') + '&inline=1';
    document.getElementById('dlPdf').href = url('pdf');
    document.getElementById('dlJpg').href = url('jpg', 'front');
    document.getElementById('dlPng').href = url('png', 'front');
    document.getElementById('dlJpgB').href = url('jpg', 'back');
    document.getElementById('dlPngB').href = url('png', 'back');
    document.getElementById('certFront').addEventListener('error', function () {
        frame.style.display = 'none';
        actions.style.display = 'none';
        unavailable.style.display = 'block';
    });
});