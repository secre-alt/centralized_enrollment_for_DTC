const { chromium } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.UI_AUDIT_URL || 'http://127.0.0.1:8000';
const output = path.resolve('docs/ui-ux-evidence/baseline');
const pages = [
    { name: 'home', route: '/' },
    { name: 'login', route: '/login' },
    { name: 'application', route: '/apply' },
    { name: 'application-status', route: '/apply/status' },
    { name: 'forgot-password', route: '/forgot-password' },
    { name: 'request-activation', route: '/request-activation' },
];
const viewports = [
    { width: 360, height: 800 },
    { width: 390, height: 844 },
    { width: 768, height: 1024 },
    { width: 1366, height: 768 },
    { width: 1920, height: 1080 },
];

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true });
    const results = [];
    for (const pageSpec of pages) {
        for (const theme of ['light', 'dark']) {
            for (const viewport of viewports) {
                const page = await browser.newPage({ viewport });
                const consoleErrors = [];
                page.on('pageerror', error => consoleErrors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'error') consoleErrors.push(message.text());
                });
                page.setDefaultNavigationTimeout(12000);
                await page.addInitScript(value => localStorage.setItem('dtc-ems-theme', value), theme);
                let response;
                try {
                    response = await page.goto(`${base}${pageSpec.route}`, { waitUntil: 'domcontentloaded', timeout: 12000 });
                } catch (error) {
                    consoleErrors.push(`navigation: ${error.message}`);
                }
                await page.waitForTimeout(350);
                await page.screenshot({ path: path.join(output, `${pageSpec.name}-${theme}-${viewport.width}x${viewport.height}.png`), fullPage: true });
                const layout = await page.evaluate(() => ({
                    title: document.title,
                    viewportWidth: innerWidth,
                    documentWidth: document.documentElement.scrollWidth,
                    bodyWidth: document.body.scrollWidth,
                    horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
                    themeApplied: document.documentElement.classList.contains('dtc-dark') || document.body.classList.contains('dtc-dark'),
                    fontFamily: getComputedStyle(document.body).fontFamily,
                    missingImageCount: [...document.images].filter(image => !image.complete || image.naturalWidth === 0).length,
                    unlabeledControls: [...document.querySelectorAll('button,input,select,textarea')].filter(control => !control.labels?.length && !control.getAttribute('aria-label') && !control.getAttribute('aria-labelledby') && control.type !== 'hidden').length,
                }));
                results.push({ page: pageSpec.name, route: pageSpec.route, theme, viewport, status: response?.status(), consoleErrors, ...layout });
                await page.close();
            }
        }
    }
    await browser.close();
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
    const bad = results.filter(result => result.status !== 200 || result.horizontalOverflow || result.consoleErrors.length || !result.themeApplied);
    console.log(JSON.stringify({ runs: results.length, badRuns: bad.length, output, bad }, null, 2));
    if (bad.length) process.exitCode = 1;
})().catch(error => { console.error(error); process.exit(1); });
