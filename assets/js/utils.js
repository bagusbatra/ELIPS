// ============================================================
// ELIPS LMS - utils.js
// Helper umum: format nilai, nomor sertifikat, escape/sanitasi HTML,
// format tanggal, dan notifikasi toast.
// ============================================================

(function (ELIPS) {
    'use strict';

    function gradeLetter(score) {
        if (typeof ELIPS.predicateLetter === 'function') {
            const letter = ELIPS.predicateLetter(score);
            if (letter) return letter;
        }
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

    // Allowlist tag & atribut untuk konten materi teks. Konten dari editor
    // instruktur tetap dianggap tidak tepercaya (stored XSS).
    const ALLOWED_TAGS = {
        p: [], br: [], strong: [], b: [], em: [], i: [], u: [], s: [],
        ul: [], ol: [], li: [], blockquote: [], code: [], pre: [],
        h1: [], h2: [], h3: [], h4: [], h5: [], h6: [],
        div: [], span: [], hr: [],
        a: ['href', 'title', 'target', 'rel'],
        img: ['src', 'alt', 'title', 'width', 'height'],
        table: [], thead: [], tbody: [], tfoot: [], tr: [],
        th: ['colspan', 'rowspan'], td: ['colspan', 'rowspan']
    };

    const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'base', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'svg', 'math', 'template', 'noscript'
    ];

    function isSafeUrl(value) {
        const normalized = String(value || '')
            .replace(/[\u0000-\u0020]+/g, '')
            .toLowerCase();
        return normalized.indexOf('javascript:') !== 0 &&
            normalized.indexOf('vbscript:') !== 0 &&
            normalized.indexOf('data:') !== 0;
    }

    function sanitizeHtml(html) {
        const source = String(html === undefined || html === null ? '' : html);

        // Lingkungan non-DOM (mis. uji data layer) tidak dapat memakai
        // allowlist; biarkan data apa adanya dan sanitasi saat render.
        if (typeof document === 'undefined' || typeof document.createElement !== 'function') return source;

        const template = document.createElement('template');
        if (!template.content) return source;
        template.innerHTML = source;

        // Buang total elemen berbahaya beserta isinya.
        DROP_TAGS.forEach(function (tag) {
            Array.prototype.slice.call(template.content.querySelectorAll(tag)).forEach(function (node) {
                node.remove();
            });
        });

        const elements = Array.prototype.slice.call(template.content.querySelectorAll('*'));

        elements.forEach(function (el) {
            const tag = el.tagName.toLowerCase();
            const allowedAttrs = ALLOWED_TAGS[tag];

            if (!allowedAttrs) {
                // Tag tidak dikenal: pertahankan isi teks/child yang aman.
                const parent = el.parentNode;
                if (!parent) return;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
                return;
            }

            Array.prototype.slice.call(el.attributes).forEach(function (attr) {
                const name = attr.name.toLowerCase();
                if (name.indexOf('on') === 0 || allowedAttrs.indexOf(name) === -1) {
                    el.removeAttribute(attr.name);
                    return;
                }
                if ((name === 'href' || name === 'src') && !isSafeUrl(attr.value)) {
                    el.removeAttribute(attr.name);
                }
            });

            if (tag === 'a' && el.getAttribute('href')) {
                el.setAttribute('rel', 'noopener noreferrer');
                const target = el.getAttribute('target');
                if (target && target !== '_blank' && target !== '_self') el.removeAttribute('target');
            }
            if (tag === 'img' && !el.getAttribute('src')) el.remove();
        });

        return template.innerHTML;
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
    ELIPS.sanitizeHtml = sanitizeHtml;
    ELIPS.formatDate = formatDate;
    ELIPS.toast = showToast;
    window.showToast = showToast;
})(window.ELIPS);
