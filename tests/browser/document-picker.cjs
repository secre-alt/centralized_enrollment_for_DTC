// Static rendered testing fixtures only: no authenticated server or form submissions.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('@playwright/test');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');

(async () => {
    const browser = await chromium.launch({ executablePath: process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        for (const role of ['student', 'alumni']) {
            const source = fs.readFileSync(path.join(testing, 'document-picker-' + role + '.html'), 'utf8');
            const form = source.match(/<form\b[^>]*data-document-submission[\s\S]*?<\/form>/)?.[0];
            assert.ok(form, 'Run the request-form PHP test first.');
            for (const width of [1280, 390]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 900 } });
                    await page.route('**/*', route => route.abort());
                    await page.setContent('<!doctype html><meta charset="utf-8"><body class="' + (dark ? 'dtc-dark' : '') + '"><div class="wrapper"><div class="content-wrapper"><main class="dr dr-create dtc-booking-page">' + form + '</main></div><footer class="main-footer">Danao Technological College</footer></div>');
                    for (const file of ['dtc-theme.css', 'document-request-flow.css', 'booking-create.css']) {
                        await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'public/css', file), 'utf8').replace(/@import[^;]+;/g, '') });
                    }
                    await page.addStyleTag({ content: 'body{margin:0;padding:24px;font:14px Arial;background:var(--dtc-bg);color:var(--dtc-text)}*{box-sizing:border-box}' });
                    await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'public/js/document-submission.js'), 'utf8') });
                    const copyScript = fs.readFileSync(path.join(root, 'public/js/dtc-app.js'), 'utf8').match(/\(function initPaymentCopyControls\(\) \{[\s\S]*?\n\}\)\(\);/)[0];
                    await page.addScriptTag({ content: copyScript });
                    await page.evaluate(() => {
                        document.execCommand = () => {
                            window.lastCopied = document.querySelector('textarea[readonly]').value;
                            return !window.copyDenied;
                        };
                    });
                    assert.equal(await page.locator('[data-request-step]:visible').count(), 1);
                    assert.equal(await page.locator('[data-request-step-indicator]').first().evaluate(el => getComputedStyle(el, '::before').content), 'none');
                    assert.equal(await page.locator('.main-footer').isVisible(), false);
                    for (const details of await page.locator('[data-request-details]').all()) {
                        assert.equal(await details.evaluate(el => el.open), width >= 992);
                    }
                    if (width < 992) {
                        assert.equal(await page.locator('.dr-slip-foot').evaluate(el => getComputedStyle(el).position), 'fixed');
                        const bar = await page.locator('.dr-slip-foot').boundingBox();
                        assert.ok(bar.y + bar.height <= 901 && bar.height <= 144);
                    }
                    const first = page.locator('[data-document-option]').first();
                    await first.locator('.dr-document-choice').click();
                    await first.locator('[data-document-copy-step="1"]').click();
                    assert.equal(await first.locator('[data-document-copies]').inputValue(), '2');
                    if (width < 992) await page.locator('[data-mobile-request-total]').getByText(/1 document.*100/).waitFor();
                    assert.ok(await first.getByText('Requirements from the Citizen\'s Charter:').isVisible());
                    assert.ok(await first.getByText(/Clearance for a first request or transfer purposes/).isVisible());
                    const copies = await first.locator('.dr-copy-row').boundingBox();
                    assert.ok(copies.height < 130, 'Copy controls should remain compact');
                    await page.locator('[data-request-next]').click();
                    assert.ok(await page.locator('[data-request-step="1"]').isVisible());
                    await page.locator('[data-request-next]').click();
                    assert.ok(await page.locator('[data-request-step="2"]').isVisible());
                    assert.equal(await page.locator('[data-document-payment-section]:visible').count(), 1);
                    const payment = page.locator('[data-document-payment-section]:visible');
                    assert.match(await payment.locator('[data-document-payment-amount]').textContent(), /2 copies.*100.00/);
                    assert.equal(await page.locator('[data-document-gcash]:visible').count(), 0);
                    assert.equal(await page.locator('.dr-payment-qr:visible').count(), 0);
                    if (role === 'alumni' && dark && width === 390) await page.screenshot({ path: path.join(testing, 'document-payment-cash.png'), fullPage: true });
                    await payment.locator('[data-document-payment][value="gcash_qr"]').check();
                    const copyNumber = payment.locator('[data-copy-label="GCash number"]');
                    await copyNumber.click();
                    await copyNumber.getByText('Copied', { exact: true }).waitFor();
                    assert.equal(await page.evaluate(() => window.lastCopied), '09170000000');
                    const copyAmount = payment.locator('[data-document-copy-amount]');
                    await copyAmount.click();
                    assert.equal(await page.evaluate(() => window.lastCopied), '100.00');
                    await page.evaluate(() => { window.copyDenied = true; });
                    await copyNumber.click();
                    await copyNumber.getByText('Copy failed', { exact: true }).waitFor();
                    await page.evaluate(() => { window.copyDenied = false; });
                    const file = payment.locator('input[type="file"]');
                    await file.setInputFiles({ name: '<receipt>.png', mimeType: 'image/png', buffer: Buffer.from('test-proof') });
                    assert.equal(await payment.locator('[data-upload-filename]').textContent(), '<receipt>.png');
                    assert.equal(await payment.locator('[data-upload-choice]').textContent(), 'Replace file');
                    await file.setInputFiles({ name: 'replacement.pdf', mimeType: 'application/pdf', buffer: Buffer.from('replacement') });
                    assert.equal(await payment.locator('[data-upload-filename]').textContent(), 'replacement.pdf');
                    await payment.locator('[data-upload-remove]').click();
                    assert.equal(await file.evaluate(el => el.files.length), 0);
                    assert.equal(await payment.locator('[data-upload-filename]').textContent(), 'No file selected');
                    assert.equal(await payment.locator('[data-upload-remove]').isVisible(), false);
                    assert.equal(await payment.locator('.dr-payment-qr:visible').count(), 1);
                    assert.equal(await payment.locator('[data-document-proof]:enabled').count(), 2);
                    assert.ok(await payment.locator('[data-document-proof]').first().evaluate(el => el.required));
                    await payment.locator('input[name$="[reference_number]"]').fill('KEEP-MY-REFERENCE');
                    if (role === 'alumni' && dark && width === 390) await page.screenshot({ path: path.join(testing, 'document-payment-gcash.png'), fullPage: true });
                    await payment.locator('[data-document-payment][value="walk_in"]').check();
                    assert.equal(await page.locator('.dr-payment-qr:visible').count(), 0);
                    assert.equal(await payment.locator('[data-document-proof]:enabled').count(), 0);
                    assert.equal(await page.locator('form').evaluate(form => new FormData(form).has('documents[0][reference_number]')), false);
                    await payment.locator('[data-document-payment][value="gcash_qr"]').check();
                    assert.equal(await payment.locator('input[name$="[reference_number]"]').inputValue(), 'KEEP-MY-REFERENCE');
                    await payment.locator('[data-document-payment][value="walk_in"]').check();
                    await page.locator('[data-request-back]').click();
                    await page.locator('[data-request-back]').click();
                    assert.equal(await first.locator('[data-document-copies]').inputValue(), '2');
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No horizontal overflow');
                    if (width < 992) {
                        await page.evaluate(() => scrollTo(0, document.body.scrollHeight));
                        await page.locator('[data-request-details]').first().locator('summary').click();
                        assert.ok(await page.locator('[data-document-summary]').isVisible());
                        await page.locator('[data-request-details]').first().locator('summary').click();
                        await page.evaluate(() => scrollTo(0, 0));
                    }
                    if (role === 'alumni' && dark) await page.screenshot({ path: path.join(testing, 'document-picker-' + width + '.png'), fullPage: true });
                    await page.locator('[data-document-option]').nth(1).locator('.dr-document-choice').click();
                    await page.locator('[data-request-next]').click();
                    await page.locator('[data-request-next]').click();
                    const payments = page.locator('[data-document-payment-section]:visible');
                    assert.equal(await payments.count(), 2);
                    await payments.nth(0).locator('[value="gcash_qr"]').check();
                    assert.equal(await payments.nth(0).locator('[data-document-proof]:enabled').count(), 2);
                    assert.equal(await payments.nth(1).locator('[data-document-proof]:enabled').count(), 0);
                    assert.equal(await payments.nth(1).locator('[value="walk_in"]').isChecked(), true);
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Payment choices must fit the viewport');
                    await page.close();
                }
            }
        }
        console.log('Passed: both portals, desktop/mobile, light/dark, compact copies and three-step navigation (8 cases).');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
