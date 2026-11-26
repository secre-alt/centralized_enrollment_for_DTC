const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');
const source = fs.readFileSync(path.join(testing, 'walk-in-release-form.html'), 'utf8');
const modal = source.match(/<div class="modal fade dq-release"[\s\S]*?<\/form>\s*<\/div>\s*<\/div>/)?.[0];
const triggers = source.match(/<button[^>]*data-release-document[^>]*>[\s\S]*?<\/button>/g);
assert.ok(modal && triggers, 'Run WalkInDocumentReleaseTest first.');
const css = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css', 'css/registrar-workspace.css']
    .map(file => fs.readFileSync(path.join(root, 'public', file), 'utf8')).join('\n');
(async () => {
    const browser = await chromium.launch({ executablePath: process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        for (const width of [390, 1280]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                await page.route('**/*', route => route.abort());
                await page.setContent('<!doctype html><meta charset="utf-8"><style>' + css + '</style><body class="' + (dark ? 'dtc-dark' : '') + '">' + triggers.join('') + modal + '</body>');
                for (const file of ['vendor/jquery/jquery.min.js', 'vendor/bootstrap/js/bootstrap.bundle.min.js', 'js/document-release.js']) {
                    await page.addScriptTag({ path: path.join(root, 'public', file) });
                }
                const first = page.locator('[data-release-document]').first();
                await first.click();
                await expect(page.locator('.dq-release')).toBeVisible();
                const selected = page.locator('[name="document_ids[]"]:checked');
                assert.equal(await selected.count(), 1);
                assert.equal(await selected.inputValue(), await first.getAttribute('data-release-document'));
                const submit = page.locator('[data-release-submit]');
                await expect(submit).toBeDisabled();
                await page.locator('[name="handover_confirmed"]').check();
                await expect(submit).toBeEnabled();
                const available = page.locator('[name="document_ids[]"]:enabled');
                assert.equal(await available.count(), 2);
                assert.equal(await page.locator('[name="document_ids[]"]:disabled').count(), 1);
                for (const input of await available.all()) await input.check();
                await expect(submit).toHaveText('Release 2 documents');
                for (const input of await available.all()) await input.uncheck();
                await expect(submit).toBeDisabled();
                await available.first().check();
                await page.screenshot({ path: path.join(testing, `release-modal-${width}-${dark ? 'dark' : 'light'}.png`) });
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                await page.getByRole('button', { name: 'Cancel', exact: true }).click();
                await expect(page.locator('.dq-release')).not.toBeVisible();
                await first.click();
                await expect(submit).toBeDisabled();
                assert.equal(await selected.count(), 1);
                await page.locator('[name="handover_confirmed"]').check();
                const result = await page.locator('form').evaluate(form => {
                    form.addEventListener('submit', event => event.preventDefault());
                    const first = new Event('submit', { bubbles: true, cancelable: true });
                    form.dispatchEvent(first);
                    const disabled = form.querySelector('[type="submit"]').disabled;
                    const repeat = new Event('submit', { bubbles: true, cancelable: true });
                    form.dispatchEvent(repeat);
                    return disabled && repeat.defaultPrevented && form.querySelector('[type="submit"]').disabled;
                });
                assert.ok(result, 'Submission must disable release and block repeat submission');
                await page.close();
            }
        }
        console.log('Passed: release modal, preselection, eligibility, counts, confirmation, cancel/reset and repeat submission (4 viewport/theme cases).');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
