const { chromium } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.UI_AUDIT_URL || 'http://127.0.0.1:8000';
const output = path.resolve(process.env.UI_AUDIT_EVIDENCE_DIR || 'docs/ui-ux-evidence/baseline');
const approvedDatabase = 'dtc_ui_testing';
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
    const identityResponse = await fetch(`${base}/__ui-test/database`);
    if (!identityResponse.ok) throw new Error(`DB safety check failed: HTTP ${identityResponse.status}`);
    const identity = await identityResponse.json();
    if (identity.environment !== 'ui-testing' || identity.database !== approvedDatabase) {
        throw new Error(`DB safety check failed: environment=${identity.environment}, database=${identity.database}`);
    }
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true });
    const results = [];
    for (const pageSpec of pages) {
        for (const theme of ['light', 'dark']) {
            for (const viewport of viewports) {
                const context = await browser.newContext({ viewport });
                const page = await context.newPage();
                const consoleErrors = [];
                const failedRequests = [];
                page.on('pageerror', error => consoleErrors.push(error.message));
                page.on('console', message => {
                    if (message.type() === 'error') consoleErrors.push(message.text());
                });
                page.on('requestfailed', request => failedRequests.push({ url: request.url(), error: request.failure()?.errorText }));
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
                const axe = await new AxeBuilder({ page })
                    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
                    .analyze();
                const layout = await page.evaluate(() => ({
                    title: document.title,
                    viewportWidth: innerWidth,
                    documentWidth: document.documentElement.scrollWidth,
                    bodyWidth: document.body.scrollWidth,
                    horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
                    themeApplied: document.documentElement.classList.contains('dtc-dark') || document.body.classList.contains('dtc-dark'),
                    fontFamily: getComputedStyle(document.body).fontFamily,
                    missingImageCount: [...document.images].filter(image => !image.complete || image.naturalWidth === 0).length,
                    unlabeledControls: [...document.querySelectorAll('button,input,select,textarea')].filter(control => {
                        const visible = control.getClientRects().length > 0 && getComputedStyle(control).visibility !== 'hidden';
                        const name = control.getAttribute('aria-label') || control.getAttribute('aria-labelledby') || control.labels?.length || control.getAttribute('title') || control.placeholder || (control.matches('button') ? control.innerText.trim() : '');
                        return visible && control.type !== 'hidden' && !name;
                    }).length,
                }));
                results.push({ page: pageSpec.name, route: pageSpec.route, theme, viewport, status: response?.status(), consoleErrors, failedRequests, axeViolations: axe.violations.map(({ id, impact, description, help, nodes }) => ({ id, impact, description, help, targets: nodes.map(node => node.target), summary: nodes.map(node => node.failureSummary) })), ...layout });
                console.log(`${pageSpec.name} ${theme} ${viewport.width}x${viewport.height}: HTTP ${response?.status()}, overflow=${layout.horizontalOverflow}`);
                await context.close();
            }
        }
    }
    await browser.close();
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
    const bad = results.filter(result => result.status !== 200 || result.horizontalOverflow || result.consoleErrors.some(error => !error.includes('ERR_NETWORK_ACCESS_DENIED')) || result.themeApplied !== (result.theme === 'dark'));
    const blockedAssets = results.flatMap(result => result.failedRequests).filter(request => request.error === 'net::ERR_NETWORK_ACCESS_DENIED');
    console.log(JSON.stringify({ runs: results.length, badRuns: bad.length, blockedAssetRequests: blockedAssets.length, axeScans: results.length, axeViolations: results.reduce((count, result) => count + result.axeViolations.length, 0), output, bad }, null, 2));
    if (bad.length) process.exitCode = 1;
})().catch(error => { console.error(error); process.exit(1); });
