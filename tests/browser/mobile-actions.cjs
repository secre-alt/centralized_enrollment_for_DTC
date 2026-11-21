// Layout coverage only; authenticated workflows are tested separately.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('@playwright/test');

(async () => {
    const root = path.resolve(__dirname, '../..');
    const directory = path.join(root, 'storage/framework/testing/mobile-actions');
    fs.mkdirSync(directory, { recursive: true });
    const styles = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css',
        'css/dtc-layout.css', 'css/dtc-filters.css', 'css/payment-history.css',
        'css/registrar-workspace.css', 'css/admin-users.css', 'css/notifications.css']
        .map(file => `<link rel="stylesheet" href="${pathToFileURL(path.join(root, 'public', file)).href}">`).join('');
    const icon = '<svg width="18" height="18" aria-hidden="true"></svg>';
    const action = (label, extra = '') => `<button class="dtc-icon-btn ${extra}">${icon}<span class="dtc-mobile-action-label">${label}</span></button>`;
    const markup = `<div class="content-wrapper" style="margin:0;padding:16px">
        <section class="content-header"><div class="d-flex flex-wrap" style="gap:12px">
            <h4>Academic management</h4><button class="dtc-btn dtc-btn-primary dtc-header-btn">${icon}<span class="dtc-header-btn-label">Manage Slots</span></button>
        </div></section>
        <div class="dtc-fl-toolbar"><div class="dtc-fl-search"><input class="form-control" placeholder="Search"></div><button class="dtc-fl-toggle">${icon}<span>Filters</span></button></div>
        <div class="ph-toolbar"><div class="ph-search"><input class="form-control" placeholder="Search payments"></div><button class="ph-filter-toggle">${icon}<span>Filters</span></button></div>
        <table class="dtc-subjects-table"><tbody><tr><td><div class="u-actions-gap">
            ${action('Edit subject')}${action('Add section')}${action('Edit section BSTM 1A')}${action('Equivalency')}<form>${action('Remove subject', 'danger')}</form>
        </div></td></tr></tbody></table>
        <div class="u-actions-gap d-flex">${action('Edit slot')}${action('Close slot')}<form>${action('Delete slot')}</form></div>
        <button class="btn-edit-user">${icon}<span class="dtc-mobile-action-label">Edit user</span></button>
        <button class="notice-delete">${icon}<span class="dtc-mobile-action-label">Delete</span></button>
        <a href="#" class="ph-view">${icon}<span class="dtc-mobile-action-label">Details</span></a>
        <button class="dtc-icon-btn rg-booking-confirm">${icon}<span class="rg-appointment-action-label">Confirm</span></button>
        </div>`;
    const fixture = path.join(directory, 'fixture.html');
    const browser = await chromium.launch({ channel: process.platform === 'win32' ? 'msedge' : 'chromium',
        headless: true, args: ['--disable-gpu', '--in-process-gpu'] });
    try {
        const page = await browser.newPage();
        for (const theme of ['', 'dtc-dark']) {
            fs.writeFileSync(fixture, `<!doctype html><meta charset="utf-8">${styles}<body class="${theme}">${markup}</body>`);
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: 1000 });
                await page.goto(pathToFileURL(fixture).href);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}: page overflow`);
                if (width < 768) {
                    for (const label of await page.locator('.dtc-mobile-action-label, .rg-appointment-action-label, .dtc-header-btn-label, .dtc-fl-toggle span, .ph-filter-toggle span').all()) {
                        assert.equal(await label.isVisible(), true, `${width}: hidden label`);
                        const box = await label.locator('..').boundingBox();
                        assert.ok(box.height >= 44 && box.x >= 0 && box.x + box.width <= width, `${width}: action clipped or too short`);
                    }
                }
                if (width === 390) await page.screenshot({ path: path.join(directory, `${theme || 'light'}.png`), fullPage: true });
                console.log(`PASS ${width}px ${theme || 'light'}`);
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
