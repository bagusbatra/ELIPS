// ============================================================
// ELIPS LMS - landing.js
// Perilaku khusus halaman awal (index.php):
//  1. Navbar berubah menjadi bar oren melengkung saat di-scroll.
//  2. Menu hamburger: aria-expanded, tutup saat tautan dipilih,
//     tombol Esc, klik di luar, atau layar melebar.
//  3. Tab menu aktif mengikuti bagian yang sedang dilihat (scrollspy).
//  4. Filter lokasi pada daftar pelatihan.
//  5. Hero carousel: tiga foto bergantian tiap 10 detik, jeda saat di-hover.
//  6. Animasi masuk/keluar konten saat scroll atas/bawah (IntersectionObserver).
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

    // Dropdown lokasi: select asli tetap jadi sumber nilai, tampilannya
    // diganti menjadi panel kustom yang lebih lembut dan mudah dipakai.
    function setupLocationDropdown(select, wrap) {
        var trigger = wrap.querySelector('[data-lok-trigger]');
        var valueLabel = wrap.querySelector('[data-lok-value]');
        var menu = wrap.querySelector('[role="listbox"]');
        if (!trigger || !valueLabel || !menu) return;

        var checkIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
        var options = [];
        var activeIndex = -1;

        function selectedText() {
            var selected = select.options[select.selectedIndex];
            return selected ? selected.text : 'Semua lokasi';
        }

        function syncValue() {
            var text = selectedText();
            valueLabel.textContent = text;
            trigger.setAttribute('aria-label', 'Filter lokasi pelatihan: ' + text);
            options.forEach(function (item) {
                var isSelected = item.getAttribute('data-value') === select.value;
                item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            });
        }

        function setActive(index) {
            if (!options.length) return;
            activeIndex = (index + options.length) % options.length;
            options.forEach(function (item, i) {
                item.classList.toggle('is-active', i === activeIndex);
            });
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function openMenu() {
            if (wrap.classList.contains('is-open')) return;
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.setAttribute('aria-hidden', 'false');
            var current = options.findIndex(function (item) {
                return item.getAttribute('data-value') === select.value;
            });
            setActive(current >= 0 ? current : 0);
            menu.focus();
        }

        function closeMenu(returnFocus) {
            if (!wrap.classList.contains('is-open')) return;
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-hidden', 'true');
            if (returnFocus) trigger.focus();
        }

        function choose(index) {
            var item = options[index];
            if (!item) return;
            select.value = item.getAttribute('data-value') || '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closeMenu(true);
        }

        Array.prototype.forEach.call(select.options, function (option, index) {
            var item = document.createElement('div');
            item.className = 'lp-lok-option';
            item.id = 'lokasi-option-' + index;
            item.setAttribute('role', 'option');
            item.setAttribute('tabindex', '-1');
            item.setAttribute('data-value', option.value);

            var icon = document.createElement('span');
            icon.className = 'lp-lok-option-check';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = checkIcon;

            var text = document.createElement('span');
            text.textContent = option.text;

            item.appendChild(icon);
            item.appendChild(text);
            menu.appendChild(item);
            options.push(item);
        });

        trigger.addEventListener('click', function () {
            if (wrap.classList.contains('is-open')) closeMenu(false);
            else openMenu();
        });

        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openMenu();
            }
        });

        menu.addEventListener('click', function (e) {
            var item = e.target.closest('.lp-lok-option');
            if (item) choose(options.indexOf(item));
        });

        menu.addEventListener('mousemove', function (e) {
            var item = e.target.closest('.lp-lok-option');
            if (item) {
                var index = options.indexOf(item);
                if (index !== activeIndex) setActive(index);
            }
        });

        menu.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Home') { e.preventDefault(); setActive(0); }
            else if (e.key === 'End') { e.preventDefault(); setActive(options.length - 1); }
            else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(activeIndex); }
            else if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); }
            else if (e.key === 'Tab') { closeMenu(false); }
        });

        select.addEventListener('change', syncValue);
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) closeMenu(false);
        });
        window.addEventListener('resize', function () { closeMenu(false); });

        // Select disembunyikan tetapi tetap dipakai sebagai penyimpan nilai.
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        wrap.classList.add('is-enhanced');
        syncValue();
    }

    // Menu aktif mengikuti bagian yang sedang dilihat, bukan selalu Beranda.
    function setupNavScrollSpy() {
        var menu = document.getElementById('navMenu');
        if (!menu) return;

        var links = Array.prototype.slice.call(menu.querySelectorAll('.lp-menu-link[data-section]'));
        if (!links.length) return;

        var entries = links.map(function (link) {
            var id = link.getAttribute('data-section');
            return {
                id: id,
                link: link,
                el: id === 'beranda' ? null : document.getElementById(id)
            };
        });

        // Hanya jalankan di halaman yang memang punya bagian-bagian tersebut.
        var hasSections = entries.some(function (entry) {
            return entry.id !== 'beranda' && entry.el;
        });
        if (!hasSections) return;

        var lockedId = null;
        var unlockTimer = null;

        function setActive(id) {
            links.forEach(function (link) {
                var isActive = link.getAttribute('data-section') === id;
                link.classList.toggle('is-current', isActive);
                if (isActive) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
        }

        function topOf(entry) {
            if (!entry.el) return 0;
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            return entry.el.getBoundingClientRect().top + scrollTop;
        }

        function currentSection() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            var doc = document.documentElement;
            var lastId = entries[entries.length - 1].id;

            // Di bagian paling bawah halaman, biarkan menu terakhir yang menyala.
            if (scrollTop + window.innerHeight >= doc.scrollHeight - 8) return lastId;

            var line = scrollTop + Math.min(160, window.innerHeight * 0.3);
            var active = entries[0].id;
            entries.forEach(function (entry) {
                if (line >= topOf(entry)) active = entry.id;
            });
            return active;
        }

        function findEntry(id) {
            return entries.filter(function (entry) { return entry.id === id; })[0] || null;
        }

        function refresh() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (lockedId) {
                var entry = findEntry(lockedId);
                var target = entry ? Math.max(0, topOf(entry) - 96) : 0;
                if (Math.abs(scrollTop - target) < 12) lockedId = null;
                else return;
            }
            setActive(currentSection());
        }

        menu.addEventListener('click', function (e) {
            var link = e.target.closest('.lp-menu-link[data-section]');
            if (!link) return;

            var id = link.getAttribute('data-section');
            setActive(id);
            lockedId = id;
            window.clearTimeout(unlockTimer);
            unlockTimer = window.setTimeout(function () {
                lockedId = null;
                refresh();
            }, 1500);
        });

        window.addEventListener('scroll', refresh, { passive: true });
        window.addEventListener('resize', refresh);
        window.addEventListener('load', refresh);
        window.addEventListener('hashchange', function () {
            var hash = (window.location.hash || '').replace('#', '');
            setActive(hash && findEntry(hash) && findEntry(hash).el ? hash : currentSection());
        });

        var initialHash = (window.location.hash || '').replace('#', '');
        var initialEntry = findEntry(initialHash);
        setActive(initialEntry && initialEntry.el ? initialHash : currentSection());
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

        // ---------- 3. Tab menu aktif mengikuti bagian yang dilihat ----------
        setupNavScrollSpy();

        // ---------- 4. Filter lokasi ----------
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
                setupLocationDropdown(select, wrap);

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

        // ---------- 5. Hero carousel: 3 foto, crossfade tiap 10 detik ----------
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

        // ---------- 6. Animasi masuk/keluar saat scroll ----------
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
