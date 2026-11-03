// ============================================================
// ELIPS LMS - utils.js
// Helper umum: format nilai, nomor sertifikat, escape HTML,
// format tanggal, dan notifikasi toast.
// ============================================================

(function (ELIPS) {
    'use strict';

    function gradeLetter(score) {
        const value = Number(score);
        if (isNaN(value)) return '-';
        if (value >= 90) return 'A';
        if (value >= 80) return 'A-';
        if (value >= 75) return 'B+';
        if (value >= 70) return 'B';
        if (value >= 65) return 'B-';
        if (value >= 60) return 'C+';
        if (value >= 55) return 'C';
        if (value >= 50) return 'D';
        return 'E';
    }

    function generateCertificateNumber() {
        return 'CERT-' + new Date().getFullYear() + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
    }

    function escapeHtml(text) {
        return String(text === undefined || text === null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function showToast(message) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = [
            'position: fixed',
            'bottom: 2rem',
            'left: 50%',
            'transform: translateX(-50%)',
            'background: var(--text-primary, #111827)',
            'color: white',
            'padding: 0.75rem 1.5rem',
            'border-radius: 8px',
            'box-shadow: 0 10px 25px rgba(0,0,0,.15)',
            'z-index: 10000',
            'font-size: 0.9375rem',
            'animation: elipsSlideUp 0.3s ease'
        ].join(';');
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.animation = 'elipsSlideDown 0.3s ease';
            setTimeout(function () { toast.remove(); }, 300);
        }, 2500);
    }

    ELIPS.gradeLetter = gradeLetter;
    ELIPS.generateCertificateNumber = generateCertificateNumber;
    ELIPS.escapeHtml = escapeHtml;
    ELIPS.formatDate = formatDate;
    ELIPS.toast = showToast;
    window.showToast = showToast;
})(window.ELIPS);
