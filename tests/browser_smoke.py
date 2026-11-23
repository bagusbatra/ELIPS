"""Smoke test browser: login lewat UI per role, buka tiap halaman, laporkan error JS/jaringan.
Pemakaian: python3 tests/browser_smoke.py http://127.0.0.1:8099
Butuh: pip install playwright (+ Chrome/Chromium). Env CHROME_PATH bila perlu."""
import os, sys, re
from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8099').rstrip('/')
PASS = 'elips123'
PLAN = {
    'admin@example.com': ['admin/dashboard.php','admin/users.php','admin/courses.php','admin/academic.php','admin/schedule.php','admin/enrollment.php','admin/certificates.php','admin/reports.php','admin/attendance-recap.php','admin/shifts.php','admin/letters.php','admin/audit.php','admin/notifications.php','admin/settings.php'],
    'ahmad@example.com': ['instructor/dashboard.php','instructor/manage-course.php','instructor/students.php','instructor/certificates.php','instructor/profile.php'],
    'ani@example.com': ['student/dashboard.php','student/courses.php','student/certificates.php','student/profile.php','learning.php'],
    'dewi.karyawan@example.com': ['employee/shared/dashboard.php','employee/shared/attendance.php','employee/marketing/campaigns.php','employee/marketing/clients.php','employee/marketing/tasks.php','employee/marketing/reports.php'],
    'agus.karyawan@example.com': ['employee/shared/dashboard.php','employee/operasional/akademik.php','employee/operasional/jadwal.php','employee/operasional/siswa.php','employee/operasional/tentor.php'],
    'sri.karyawan@example.com': ['employee/shared/dashboard.php','employee/keuangan/dashboard.php'],
}
PUBLIC = ['index.php','login.php','register.php','verify-certificate.php','course-detail.php?id=1']
IGNORE = re.compile(r'(fonts\.g|cdn\.jsdelivr|unsplash|ERR_BLOCKED|ERR_NAME_NOT_RESOLVED|ERR_CONNECTION|ERR_TUNNEL|net::ERR_FAILED)', re.I)

problems = []
def watch(page, where):
    page.on('pageerror', lambda e: problems.append((where(), 'pageerror', str(e)[:200])))
    page.on('console', lambda m: m.type == 'error' and not IGNORE.search(m.text + (m.location.get('url','') if m.location else '')) and problems.append((where(), 'console', m.text[:200])))
    def resp(r):
        if r.status >= 400 and not IGNORE.search(r.url):
            problems.append((where(), 'http%d' % r.status, r.url))
    page.on('response', resp)

with sync_playwright() as p:
    exe = os.environ.get('CHROME_PATH') or '/usr/bin/google-chrome'
    browser = p.chromium.launch(executable_path=exe, args=['--no-sandbox'])
    cur = ['']
    ctx = browser.new_context(); page = ctx.new_page(); watch(page, lambda: cur[0])
    for u in PUBLIC:
        cur[0] = u; page.goto(f'{BASE}/{u}', wait_until='networkidle')
    ctx.close()
    for email, pages in PLAN.items():
        ctx = browser.new_context(); page = ctx.new_page(); watch(page, lambda: cur[0])
        cur[0] = 'login:' + email
        page.goto(f'{BASE}/login.php', wait_until='networkidle')
        page.fill('#email', email); page.fill('#password', PASS)
        page.click('#loginForm button[type=submit]')
        try:
            page.wait_for_url(re.compile(r'dashboard\.php|attendance\.php'), timeout=10000)
        except Exception:
            problems.append((cur[0], 'login', 'tidak diarahkan ke dashboard: ' + page.url))
        print(email, '->', page.url.replace(BASE, ''))
        for u in pages:
            cur[0] = f'{email}:{u}'
            resp = page.goto(f'{BASE}/{u}', wait_until='networkidle')
            page.wait_for_timeout(300)
            if not page.url.split('?')[0].endswith(u.split('?')[0]):
                problems.append((cur[0], 'redirect', page.url))
            nreq = page.evaluate("performance.getEntriesByType('resource').filter(r=>r.name.split('?')[0].endsWith('.js')).length")
            who = page.evaluate("(window.ELIPS && ELIPS.getSessionUser && ELIPS.getSessionUser()) ? ELIPS.getSessionUser().role : null")
            if u != 'learning.php' and who is None:
                problems.append((cur[0], 'session', 'ELIPS.getSessionUser() kosong'))
            print('  ', u, 'js-files:', nreq, 'role:', who)
        ctx.close()
    browser.close()

print('\nMASALAH:', len(problems))
for pr in problems: print(' ', pr)
sys.exit(1 if problems else 0)
