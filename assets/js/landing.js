// ============================================================
// ELIPS LMS - landing.js
// Perilaku khusus halaman awal (index.html):
//  1. Navbar berubah menjadi bar oren melengkung saat di-scroll.
//  2. Menu hamburger: aria-expanded, tutup saat tautan dipilih,
//     tombol Esc, klik di luar, atau layar melebar.
//  3. Filter lokasi pada daftar pelatihan.
//  4. Hero carousel: tiga foto bergantian tiap 10 detik, jeda saat di-hover.
//  5. Animasi masuk/keluar konten saat scroll atas/bawah (IntersectionObserver).
// Dimuat SETELAH ui.js, karena ui.js yang merender kartu kursus
// dan membuka/menutup menu lewat class "active" pada #navMenu.
// ============================================================

(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        // ---------- 1. Navbar saat di-scroll ----------
        var nav = document.getElementById('lpNav');
        if (nav) {
            var onScroll = function () {
                nav.classList.toggle('is-scrolled', (window.scrollY || document.documentElement.scrollTop) > 24);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        // ---------- 2. Menu hamburger ----------
        var toggle = document.getElementById('navToggle');
        var menu = document.getElementById('navMenu');
        if (toggle && menu) {
            // ui.js membuka/menutup menu lewat class "active"; sinkronkan aria + label tombol.
            var syncMenuState = function () {
                var open = menu.classList.contains('active');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            };
            new MutationObserver(syncMenuState).observe(menu, { attributes: true, attributeFilter: ['class'] });
            syncMenuState();

            var closeMenu = function () {
                menu.classList.remove('active');
            };

            // Tautan di dalam menu (termasuk Dashboard/Keluar yang ditambahkan ui.js)
            menu.addEventListener('click', function (e) {
                if (e.target.closest('a')) closeMenu();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && menu.classList.contains('active')) {
                    closeMenu();
                    toggle.focus();
                }
            });
            document.addEventListener('click', function (e) {
                if (menu.classList.contains('active') && !menu.contains(e.target) && !toggle.contains(e.target)) {
                    closeMenu();
                }
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth > 980) closeMenu();
            });
        }

        // ---------- 3. Filter lokasi ----------
        var grid = document.getElementById('coursesGrid');
        var wrap = document.getElementById('lokasiWrap');
        var select = document.getElementById('lokasiFilter');
        var emptyMsg = document.getElementById('coursesEmpty');
        if (grid && wrap && select) {
            var cards = Array.prototype.slice.call(grid.querySelectorAll('.lp-card'));
            var locations = [];
            cards.forEach(function (card) {
                var loc = card.getAttribute('data-lokasi');
                if (loc && locations.indexOf(loc) === -1) locations.push(loc);
            });
            locations.sort(function (a, b) { return a.localeCompare(b, 'id'); });

            // Filter hanya tampil jika ada data lokasi pada kursus.
            if (locations.length > 0) {
                locations.forEach(function (loc) {
                    var option = document.createElement('option');
                    option.value = loc;
                    option.textContent = loc;
                    select.appendChild(option);
                });
                wrap.hidden = false;

                select.addEventListener('change', function () {
                    var chosen = select.value;
                    var visible = 0;
                    cards.forEach(function (card) {
                        var show = !chosen || card.getAttribute('data-lokasi') === chosen;
                        card.hidden = !show;
                        if (show) visible += 1;
                    });
                    if (emptyMsg) emptyMsg.hidden = visible !== 0;
                });
            }
        }

        // ---------- 4. Hero carousel: 3 foto, crossfade tiap 10 detik ----------
        var heroStage = document.querySelector('[data-hero-slides]');
        var heroSlides = heroStage
            ? Array.prototype.slice.call(heroStage.querySelectorAll('.lp-hero-slide'))
            : [];
        if (heroSlides.length > 1) {
            var slideIndex = 0;
            var slideTimer = null;
            var slideDelay = 10000;
            var reduceMotion = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var showSlide = function (next) {
                heroSlides[slideIndex].classList.remove('is-active');
                slideIndex = (next + heroSlides.length) % heroSlides.length;
                heroSlides[slideIndex].classList.add('is-active');
            };
            var startSlides = function () {
                if (slideTimer !== null || reduceMotion) return;
                slideTimer = window.setInterval(function () {
                    showSlide(slideIndex + 1);
                }, slideDelay);
            };
            var stopSlides = function () {
                if (slideTimer === null) return;
                window.clearInterval(slideTimer);
                slideTimer = null;
            };

            heroStage.addEventListener('mouseenter', stopSlides);
            heroStage.addEventListener('mouseleave', startSlides);
            heroStage.addEventListener('focusin', stopSlides);
            heroStage.addEventListener('focusout', startSlides);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) stopSlides();
                else startSlides();
            });
            startSlides();
        }

        // ---------- 5. Animasi masuk/keluar saat scroll ----------
        var revealSelector = [
            '.lp-pel-head',
            '.lp-grid',
            '.lp-unggulan > .lp-container > .lp-h2',
            '.lp-unggulan > .lp-container > .lp-lead',
            '.lp-tracks > .lp-track',
            '.lp-h3',
            '.lp-steps > .lp-step',
            '.lp-unggulan-cta',
            '.lp-cert-panel',
            '.lp-profil-copy',
            '.lp-profil-media',
            '.lp-detail-reveal'
        ].join(',');
        var revealItems = Array.prototype.slice.call(document.querySelectorAll(revealSelector));
        if (revealItems.length > 0) {
            if (!('IntersectionObserver' in window)) {
                revealItems.forEach(function (el) {
                    el.classList.add('lp-reveal', 'is-revealed');
                });
            } else {
                revealItems.forEach(function (el, i) {
                    el.classList.add('lp-reveal');
                    el.style.setProperty('--reveal-delay', (i % 4) * 60 + 'ms');
                });
                var revealObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        entry.target.classList.toggle('is-revealed', entry.isIntersecting);
                    });
                }, { threshold: 0.05, rootMargin: '0px 0px -6% 0px' });
                revealItems.forEach(function (el) { revealObserver.observe(el); });
            }
        }
    });
})();
