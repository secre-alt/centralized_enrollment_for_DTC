// Uses owner-scoped fixtures rendered by DocumentRequestUiTest; no server access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');
const css = ['dtc-theme.css', 'document-request-flow.css', 'dtc-filters.css', 'alumni-request-history.css', 'dtc-layout.css']
    .map(file => fs.readFileSync(path.join(root, 'public/css', file), 'utf8')).join('\n');

(async () => {
    const browser = await chromium.launch({ executablePath: process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        for (const role of ['student', 'alumni']) {
            const source = fs.readFileSync(path.join(testing, `document-history-${role}.html`), 'utf8');
            const content = source.match(/<div class="dr dr-index[\s\S]*?(?=<div class="modal)/)?.[0];
            assert.ok(content, 'Run DocumentRequestUiTest to render history fixtures.');
            for (const width of [320, 390, 1280]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 900 } });
                    await page.route('**/*', route => route.abort());
                    await page.setContent('<!doctype html><meta charset="utf-8"><style>body{margin:0;padding:16px}*{box-sizing:border-box}.collapse:not(.show){display:none}</style><style>' + css + '</style><body class="' + (dark ? 'dtc-dark' : '') + '">' + content + '</body>');
                    await page.addScriptTag({ path: path.join(root, 'public/js/dtc-filters.js') });
                    await page.addScriptTag({ path: path.join(root, 'public/js/document-request.js') });
                    assert.equal(await page.locator('.dr-req-top > .dtc-status-badge:visible').count(), 6);
                    assert.equal(await page.locator('.dr-document-track li:visible').count(), 30);
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'History must fit the viewport');
                    await page.locator('[data-filter="ready"]').click();
                    await expect(page.locator('.dr-req:visible')).toHaveCount(4);
                    await page.locator('[data-filter="cancelled"]').click();
                    await expect(page.locator('.dr-req:visible')).toHaveCount(0);
                    assert.ok(await page.locator('#drFilterEmpty').isVisible());
                    await page.locator('[data-filter="all"]').click();
                    await expect(page.locator('.dr-req:visible')).toHaveCount(6);
                    await page.screenshot({ path: path.join(testing, `document-history-${role}-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                    await page.close();
                }
            }
        }
        console.log('Passed: document history layout and filters, both roles, desktop/mobile, light/dark (12 cases).');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
