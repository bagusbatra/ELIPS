// Direktori: assets/js/password-rules.js
// ============================================================
// ELIPS LMS - password-rules.js
// Aturan sandi (sama dengan server): minimal 8 karakter, huruf besar,
// huruf kecil, angka, dan karakter khusus. Server tetap memvalidasi ulang.
// ============================================================
(function (ELIPS) {
    'use strict';

    var RULES = [
        { key: 'len', label: 'Minimal 8 karakter', test: function (v) { return v.length >= 8; } },
        { key: 'upper', label: 'Ada huruf besar (A-Z)', test: function (v) { return /[A-Z]/.test(v); } },
        { key: 'lower', label: 'Ada huruf kecil (a-z)', test: function (v) { return /[a-z]/.test(v); } },
        { key: 'digit', label: 'Ada angka (0-9)', test: function (v) { return /[0-9]/.test(v); } },
        { key: 'special', label: 'Ada karakter khusus (! @ # $ dst.)', test: function (v) { return /[^A-Za-z0-9]/.test(v); } }
    ];

    function allValid(value) {
        return RULES.every(function (rule) { return rule.test(value); });
    }

    /** Pasang daftar centang aturan pada <ul> dan perbarui saat mengetik. */
    function attach(input, list) {
        if (!input || !list) return;
        list.innerHTML = RULES.map(function (rule) {
            return '<li data-rule="' + rule.key + '"><i class="bi bi-circle" aria-hidden="true"></i><span>' + rule.label + '</span></li>';
        }).join('');
        var meter = document.createElement('div');
        meter.className = 'pw-meter';
        meter.setAttribute('aria-hidden', 'true');
        meter.innerHTML = '<span></span>';
        list.parentNode.insertBefore(meter, list);
        function update() {
            var score = 0;
            RULES.forEach(function (rule) {
                var item = list.querySelector('[data-rule="' + rule.key + '"]');
                var ok = rule.test(input.value);
                if (ok) score++;
                item.classList.toggle('is-ok', ok);
                item.querySelector('i').className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-circle');
            });
            meter.setAttribute('data-level', String(score));
            meter.firstChild.style.width = (score * 20) + '%';
        }
        input.addEventListener('input', update);
        update();
    }

    /** Tombol mata untuk menampilkan/menyembunyikan sandi: [data-toggle-password="idInput"]. */
    function bindToggles(root) {
        (root || document).querySelectorAll('[data-toggle-password]').forEach(function (button) {
            var target = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!target) return;
            button.addEventListener('click', function () {
                var show = target.type === 'password';
                target.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.innerHTML = '<i class="bi bi-' + (show ? 'eye-slash' : 'eye') + '" aria-hidden="true"></i>';
            });
        });
    }

    ELIPS.passwordRules = { attach: attach, allValid: allValid, bindToggles: bindToggles, rules: RULES };
})(window.ELIPS);