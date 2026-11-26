// Rendered dtc_testing form and existing assets; no application or database access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');
const source = fs.readFileSync(path.join(testing, 'document-request-ui.html'), 'utf8');
const form = source.match(/<form\b[^>]*data-document-submission[\s\S]*?<\/form>/)?.[0];
assert.ok(form, 'Run DocumentRequestUiTest to render the fixture.');
const css = ['dtc-theme.css', 'document-request-flow.css', 'booking-create.css']
    .map(name => fs.readFileSync(path.join(root, 'public/css', name), 'utf8')).join('\n');
(async () => {
    const browser = await chromium.launch({ executablePath: process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        for (const [width, theme] of [[1280, 'light'], [390, 'light'], [390, 'dark']]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            await page.route('**/*', route => route.abort());
            await page.setContent('<!doctype html><meta charset="utf-8"><style>body{margin:0;padding:16px;box-sizing:border-box}button,input,select,textarea{font:inherit}label{display:block}</style><style>'
                + css + '</style><body class="' + (theme === 'dark' ? 'dtc-dark' : '') + '"><main class="dr dtc-booking-page">' + form + '</main>');
            await page.addScriptTag({ path: path.join(root, 'public/js/document-submission.js') });
            const toggles = page.locator('[data-document-toggle]');
            await toggles.first().focus();
            await page.keyboard.press('Space');
            await page.locator('[data-document-copies]').first().fill('3');
            assert.match(await page.locator('[data-document-summary]').textContent(), /36.75/);
            await page.locator('.dr-document-choice').nth(2).click();
            assert.match(await page.locator('[data-document-summary]').textContent(), /No payment required/);
            await page.evaluate(() => {
                const form = document.querySelector('form');
                const data = new FormData(form);
                if (data.get('documents[0][copies]') !== '3' || data.has('documents[1][copies]')) throw Error('Incorrect submitted selection');
                const copies = form.querySelector('[data-document-copies]');
                copies.value = '11'; if (form.checkValidity()) throw Error('Missing quantity validation'); copies.value = '3';
                if (!form.checkValidity()) throw Error('Valid cash request rejected');
                for (const item of form.querySelectorAll('.dr-opt, .dr-slip, .dr-collection')) {
                    const rect = item.getBoundingClientRect();
                    if (rect.left < 0 || rect.right > innerWidth) throw Error('Content clipped beyond viewport');
                }
                if (getComputedStyle(form.querySelector('.dr-slip')).position === 'fixed') throw Error('Summary inaccessible');
                if (innerWidth < 992 && getComputedStyle(form).gridTemplateColumns.split(' ').length !== 1) throw Error('Mobile requires one column');
            });
            await page.screenshot({ path: path.join(testing, 'document-request-' + width + '-' + theme + '.png'), fullPage: true });
            console.log('Edge rendered form: ' + width + 'px ' + theme + ' passed.');
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
