// Run only through the disposable-database PHP browser harness.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, expect } = require('@playwright/test');

assert.equal(process.env.APP_ENV, 'testing');
assert.equal(process.env.DB_DATABASE, 'dtc_testing');
assert.match(process.env.BROWSER_BASE_URL, /^http:\/\/127\.0\.0\.1:\d+$/);
const base = process.env.BROWSER_BASE_URL;
const output = process.env.BROWSER_OUTPUT;
const errors = [], consoleErrors = [], requests = [], results = [], boundaries = [];
const pages = {
    admin: ['/admin/dashboard', '/admin/users', '/admin/programs', '/admin/settings', '/help'],
    registrar: ['/registrar/dashboard', '/registrar/enrollments', '/registrar/documents', '/registrar/appointments/slots', '/help'],
    cashier: ['/cashier/dashboard', '/cashier/payments', '/cashier/document-payments', '/reports/payments', '/help'],
    student: ['/portal/dashboard', '/portal/enrollment', '/portal/documents', '/portal/transactions', '/portal/profile'],
    alumni: ['/portal/dashboard', '/portal/documents', '/portal/documents/create', '/portal/transactions', '/portal/profile'],
    new_applicant: ['/portal/dashboard', '/portal/application', '/portal/enrollment/create', '/portal/profile', '/help'],
};

(async () => {
    const browser = await chromium.launch({
        channel: process.env.BROWSER_CHANNEL || (process.platform === 'win32' ? 'msedge' : 'chromium'),
        headless: true,
    });
    try {
        for (const [role, urls] of Object.entries(pages)) {
            const context = await browser.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
            const page = await context.newPage();
            page.setDefaultTimeout(15000);
            page.setDefaultNavigationTimeout(60000);
            page.on('pageerror', error => errors.push({ role, url: page.url(), message: error.message }));
            page.on('console', message => {
                if (message.type() === 'error') consoleErrors.push({ role, url: page.url(), message: message.text() });
            });
            page.on('requestfailed', request => requests.push({ role, url: request.url(), error: request.failure()?.errorText,
                cancelled: request.failure()?.errorText === 'net::ERR_ABORTED' }));
            page.on('response', response => {
                const expectedDenial = ['student', 'alumni', 'new_applicant'].includes(role)
                    && response.status() === 403 && response.url() === base + '/admin/users';
                if (response.status() >= 400 && !expectedDenial) requests.push({ role, url: response.url(), status: response.status() });
            });
            await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
            if (role === 'admin') {
                const forged = await page.request.post(base + '/login', {
                    headers: { Accept: 'application/json' },
                    data: { email: 'forgery@example.test', password: 'invalid' },
                });
                assert.equal(forged.status(), 419, 'Missing CSRF token must fail in production-like HTTP staging');
                boundaries.push({ role: 'guest', url: '/login', method: 'POST', status: forged.status() });
            }
            await page.locator('input[name="email"]:visible').fill(`browser-${role}@example.test`);
            await page.locator('input[name="password"]:visible').fill(process.env.BROWSER_PASSWORD);
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
                page.locator('form:has(input[name="password"]) button[type="submit"]').click(),
            ]);
            assert.ok(!new URL(page.url()).pathname.includes('login'), `${role}: login failed`);
            for (const url of urls) {
                const response = await page.goto(base + url, { waitUntil: 'domcontentloaded' });
                assert.equal(response.status(), 200, `${role}: ${url}`);
                assert.equal(new URL(page.url()).pathname, url, `${role}: unexpected redirect`);
                await expect(page.locator('.dtc-skeleton-preloader')).toBeHidden({ timeout: 30000 });
                for (const [theme, width, height] of [['light', 1366, 900], ['dark', 390, 844]]) {
                    await page.setViewportSize({ width, height });
                    await page.evaluate(theme => {
                        localStorage.setItem('dtc-ems-theme', theme);
                        if (typeof applyDtcTheme === 'function') applyDtcTheme(theme);
                        else document.documentElement.classList.toggle('dtc-dark', theme === 'dark');
                    }, theme);
                    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
                    assert.ok(await page.locator('h1, h2, h3').count(), `${role}: ${url} has no heading`);
                    await page.screenshot({ path: path.join(output, `${role}-${url.replaceAll('/', '_')}-${theme}-${width}.png`), fullPage: true });
                    results.push({ role, url, theme, width, status: response.status(), overflow: false });
                }
            }
            // A portal account must not reach staff administration through a crafted URL.
            if (['student', 'alumni', 'new_applicant'].includes(role)) {
                const denied = await page.goto(base + '/admin/users', { waitUntil: 'domcontentloaded' });
                assert.equal(denied.status(), 403, `${role}: staff boundary`);
                boundaries.push({ role, url: '/admin/users', status: denied.status() });
            }
            await context.close();
        }
        assert.deepEqual(errors, [], 'Browser JavaScript errors');
        assert.deepEqual(requests.filter(request => request.url.startsWith(base) && !request.cancelled), [], 'Local failed requests');
        console.log(`PASS: ${results.length} authenticated role/page/viewport checks`);
    } finally {
        fs.writeFileSync(path.join(output, 'role-pages-results.json'), JSON.stringify({ results, boundaries, errors, consoleErrors, requests }, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
