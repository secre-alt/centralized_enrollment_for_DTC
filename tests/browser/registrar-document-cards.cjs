const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');
const source = fs.readFileSync(path.join(testing, 'walk-in-release-form.html'), 'utf8');
const table = source.match(/<table id="registrar-table-1"[\s\S]*?<\/table>/)?.[0];
assert.ok(table, 'Run WalkInDocumentReleaseTest to render the fixture.');
const css = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css', 'css/registrar-workspace.css']
    .map(file => fs.readFileSync(path.join(root, 'public', file), 'utf8')).join('\n');
// Use the current view's inline responsive styles, rather than stale fixture styles.
const view = fs.readFileSync(path.join(root, 'resources/views/registrar/documents/index.blade.php'), 'utf8');
const inline = view.match(/<style>([\s\S]*?)<\/style>/)[1];
(async () => {
    const browser = await chromium.launch({ executablePath: process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        for (const width of [320, 390, 767, 1280]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.route('**/*', route => route.abort());
            await page.setContent('<!doctype html><meta charset="utf-8"><style>' + css + '</style><style>' + inline + '</style><body class="dtc-dark"><main class="registrar-workspace rg-documents" style="padding:16px"><div class="table-responsive">' + table + '</div></main></body>');
            await page.evaluate(() => {
                const row = document.querySelector('.dq-row');
                row.querySelector('.dq-name').textContent = 'John Mark Abis';
                row.querySelector('.dq-who .dq-sub').textContent = 'Abis12@gmail.com';
                row.cells[3].querySelector('.dq-badge').textContent = 'Released';
                row.cells[6].textContent = 'Completed';
                const note = document.createElement('div');
                note.className = 'dq-sub';
                note.textContent = 'Released Nov 25, 2026 10:38 PM by Eldren Trazo Dela Cruz';
                row.cells[6].append(note);
            });
            if (width < 768) {
                const sizes = await page.evaluate(() => {
                    const row = document.querySelector('.dq-row');
                    return {
                        name: row.querySelector('.dq-name').getBoundingClientRect().width,
                        action: row.cells[6].getBoundingClientRect().width,
                        card: row.getBoundingClientRect().width,
                        height: row.getBoundingClientRect().height,
                        overflow: document.documentElement.scrollWidth > innerWidth + 1,
                    };
                });
                assert.ok(sizes.name > 80, 'Requester must have room for readable text');
                assert.ok(sizes.action > sizes.card * 0.7, 'Release details need a full row');
                assert.ok(sizes.height < 450 && !sizes.overflow, JSON.stringify(sizes));
            }
            await page.screenshot({ path: path.join(testing, `registrar-document-cards-${width}.png`), fullPage: true });
            await page.close();
        }
        console.log('Passed: document table stays readable without a pickup column at 320, 390, 767 and 1280px.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
