// Run through tests/Browser/AdmissionEnrollmentBrowserTest.php, not a live DB.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
const { chromium, expect } = require('@playwright/test');

assert.equal(process.env.APP_ENV, 'testing');
assert.equal(process.env.DB_DATABASE, 'dtc_testing');
const base = process.env.BROWSER_BASE_URL;
assert.match(base, /^http:\/\/127\.0\.0\.1:\d+$/);
const output = process.env.BROWSER_OUTPUT;
const email = 'browser-student@example.test';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const errors = [];
let browser;
let child;
let activePage;
const watchdog = setTimeout(() => { child?.kill(); process.exit(1); }, 300000);
function state(activation = false) {
    return JSON.parse(execFileSync(process.env.BROWSER_PHP,
        ['tests/Support/browser-testing-state.php', email, activation ? 'activation' : 'state'], { encoding: 'utf8', windowsHide: true }));
}
async function pageFor() {
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(60000);
    page.on('pageerror', error => errors.push(error.message));
    activePage = page;
    return page;
}
async function goto(page, url) {
    activePage = page;
    const response = await page.goto(base + url, { waitUntil: 'domcontentloaded' });
    assert.ok(response.status() < 400, `${url}: HTTP ${response.status()}`);
}
async function login(page, account) {
    await goto(page, '/login');
    await page.locator('input[name="email"]:visible').fill(account);
    await page.locator('input[name="password"]:visible').fill(process.env.BROWSER_PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('form:has(input[name="password"]) button[type="submit"]').click({ noWaitAfter: true })]);
    assert.ok(!new URL(page.url()).pathname.includes('login'), 'Login did not complete');
}
async function confirm(page, trigger, label) {
    await page.locator(trigger).click();
    const dialog = page.locator('.dtc-confirm-modal:visible');
    await expect(dialog).toBeVisible();
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), dialog.getByRole('button', { name: label, exact: true }).click({ noWaitAfter: true })]);
}
async function noOverflow(page, name) {
    await expect(page.locator('.dtc-skeleton-preloader')).toBeHidden({ timeout: 30000 });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(350); // Allow the responsive sidebar transition to settle.
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: path.join(output, name + '-mobile.png'), fullPage: true });
    await page.setViewportSize({ width: 1366, height: 900 });
}

(async () => {
    // Use Playwright's managed launch so startup failures retain browser logs.
    // Edge is available on this Windows host; CI can choose downloaded Chromium.
    browser = await chromium.launch({
        channel: process.env.BROWSER_CHANNEL || (process.platform === 'win32' ? 'msedge' : 'chromium'),
        headless: true,
        args: ['--disable-gpu', '--in-process-gpu'],
        timeout: 30000,
    });
    const journey = process.env.BROWSER_JOURNEY;
    const student = await pageFor();
    await login(student, email);
    const registrar = await pageFor();
    await login(registrar, process.env.BROWSER_REGISTRAR_EMAIL);
    const cashier = await pageFor();
    await login(cashier, process.env.BROWSER_CASHIER_EMAIL);
    const subject = process.env.BROWSER_SUBJECT_ID;
    const original = process.env.BROWSER_ORIGINAL_SECTION;
    const alternative = process.env.BROWSER_ALTERNATIVE_SECTION;
    async function submit(page, selector) {
        activePage = page;
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator(selector).click({ noWaitAfter: true })]);
    }
    async function approve(id) {
        await goto(registrar, `/registrar/enrollments/${id}`);
        await confirm(registrar, `#approve-enrollment-${id} button`, 'Approve');
        assert.equal(state().enrollment_status, 'approved');
    }
    async function returnSubject(id) {
        await goto(registrar, `/registrar/enrollments/${id}`);
        await registrar.locator(`label[for="replacement-subject-${id}-${subject}"]`).click();
        await registrar.locator(`#unavailable-section-${id}-${subject}`).selectOption(original);
        await registrar.locator(`#replacement-reason-${id}`).fill('The assigned section is unavailable. Please choose another section.');
        await confirm(registrar, `[data-dtc-confirm-form="#return-subject-replacement-${id}"]`, 'Return for replacement');
        assert.equal(state().revision_status, 'open');
        assert.equal(state().payments[0].refund_status, 'not_required');
        await goto(student, `/portal/enrollment/${id}/revise`);
    }
    if (journey === 'hold') {
        await goto(student, '/portal/enrollment/create');
        await expect(student.locator('#semester')).toHaveValue('1');
        await expect(student.locator('select[name="semester"]')).toHaveCount(0);
        await student.locator('#program_id').selectOption(process.env.BROWSER_PROGRAM_ID);
        await expect(student.locator('input[name="subject_ids[]"]')).toHaveCount(1);
        await student.locator('input[name="subject_ids[]"]').uncheck();
        await submit(student, 'form:has(#year_level) button[type="submit"]');
        await expect(student.locator('.dtc-enrollment-create-body > .alert-danger')).toBeVisible();
        await expect(student.locator('input[name="subject_ids[]"]')).toHaveCount(1);
        await expect(student.locator('input[name="subject_ids[]"]')).not.toBeChecked();
        await student.locator('input[name="subject_ids[]"]').check();
        await submit(student, 'form:has(#year_level) button[type="submit"]');
        assert.equal(state().hold, true);
        assert.deepEqual(state().assignments, []);
        const id = state().enrollment_id;
        await goto(student, `/portal/enrollment/${id}/payment-info`);
        await expect(student.locator('input[name="proof_of_payment"]')).toHaveCount(0);
        await noOverflow(student, 'sections-needed');
        await goto(cashier, `/cashier/payments/${id}`);
        await expect(cashier.locator(`[data-dtc-confirm-form="#walkinForm-${id}"]`)).toHaveCount(0);
        const csrf = await cashier.locator('meta[name="csrf-token"]').getAttribute('content');
        const blocked = await cashier.request.post(`${base}/cashier/payments/${id}`, { form: { _token: csrf } });
        assert.ok(blocked.status() < 500);
        assert.equal(state().hold, true);
        assert.deepEqual(state().payments, []);
        await goto(registrar, `/registrar/enrollments/${id}`);
        const choice = registrar.locator(`#approval-section-${id}-${subject}`);
        await choice.selectOption(original);
        await submit(registrar, `#approve-enrollment-${id} button`);
        assert.equal(state().hold, true, 'Full offering must not release the hold');
        await registrar.locator(`#approval-section-${id}-${subject}`).selectOption(alternative);
        await submit(registrar, `#approve-enrollment-${id} button`);
        assert.equal(state().hold, false);
        assert.equal(state().paid, false);
        assert.equal(state().enrollment_status, 'pending');
        assert.deepEqual(state().assignments, [Number(alternative)]);
        await goto(student, `/portal/enrollment/${id}/payment-info`);
        await expect(student.locator('input[name="proof_of_payment"]')).toHaveCount(1);
        await noOverflow(student, 'sections-assigned-payment-enabled');
        console.log('PASS: full group holds enrollment, blocks cash collection, rejects full section and releases payment only after valid assignment');
    } else if (journey === 'gcash') {
        const id = state().enrollment_id;
        for (let attempt = 1; attempt <= 2; attempt++) {
            await goto(student, `/portal/enrollment/${id}/payment-info`);
            await student.locator('input[name="reference_number"]').fill('123456789'+attempt);
            await student.locator('input[name="proof_of_payment"]').setInputFiles({ name: `proof-${attempt}.png`, mimeType: 'image/png',
                buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB9sAAAAASUVORK5CYII=', 'base64') });
            await submit(student, 'form:has(input[name="proof_of_payment"]) button[type="submit"]');
            assert.equal(state().payments.at(-1).status, 'pending');
            assert.equal(state().paid, false);
            await goto(cashier, `/cashier/payments/${id}`);
            if (attempt === 1) {
                await cashier.locator(`[data-target="rejectPanel-${id}"]`).click();
                await expect(cashier.locator(`#remarks-${id}`)).toBeVisible();
                await cashier.locator('.dtc-review-reject-cancel').click();
                await expect(cashier.locator(`#remarks-${id}`)).toBeHidden();
                assert.equal(state().payments[0].status, 'pending');
                await cashier.locator(`[data-target="rejectPanel-${id}"]`).click();
                await cashier.locator(`#remarks-${id}`).fill('The submitted reference does not match the payment proof.');
                await confirm(cashier, `[data-dtc-confirm-form="#rejectForm-${id}"]`, 'Reject');
                assert.equal(state().payments[0].status, 'rejected');
                await goto(student, `/portal/enrollment/${id}/payment-info`);
                await expect(student.locator('body')).toContainText('does not match');
                await noOverflow(student, 'gcash-rejected-resubmit');
            } else {
                await goto(cashier, `/cashier/payments?review=${id}`);
                await expect(cashier.locator('#paymentModal')).toBeVisible();
                await cashier.locator(`[data-target="rejectPanel-${id}"]`).click();
                await expect(cashier.locator(`#remarks-${id}`)).toBeVisible();
                await cashier.locator('.dtc-review-reject-cancel').click();
                await expect(cashier.locator(`#remarks-${id}`)).toBeHidden();
                const paymentId = state().payments.at(-1).id;
                await confirm(cashier, `#verify-gcash-${paymentId} button`, 'Verify');
            }
        }
        assert.equal(state().paid, true);
        assert.equal(state().enrollment_status, 'pending');
        assert.deepEqual(state().payments.map(p => p.status), ['rejected', 'verified']);
        await approve(id);
        console.log('PASS: GCash proof rejection, corrected resubmission, verification and final approval');
    } else if (journey === 'replacement' || journey === 'refund') {
        const id = state().enrollment_id;
        const originalPayments = state().payments;
        await returnSubject(id);
        if (journey === 'replacement') {
            const choices = student.locator('[data-revision-choice]');
            await expect(choices.locator(`option[value="${original}"]`)).toHaveCount(0);
            await choices.selectOption(alternative);
            await student.locator('#confirm-revision').check();
            await choices.selectOption('');
            await expect(student.locator('#confirm-revision')).not.toBeChecked();
            await choices.selectOption(alternative);
            await student.locator('#confirm-revision').check();
            await noOverflow(student, 'replacement-review');
            await submit(student, '#enrollment-revision-form button[type="submit"]');
            assert.equal(state().revision_status, 'submitted');
            assert.deepEqual(state().payments, originalPayments);
            assert.deepEqual(state().assignments, [Number(alternative)]);
            await approve(id);
            assert.deepEqual(state().payments, originalPayments);
            console.log('PASS: unavailable section excluded, revision confirmation resets, replacement preserves original payment and receives final approval');
        } else {
            const form = student.locator('form[id^="request-refund-"]');
            await form.locator('textarea[name="reason"]').fill('I cannot take the alternative class and request a full refund.');
            await form.locator('button[type="submit"]').click();
            assert.equal(state().revision_status, 'open', 'Refund requires explicit student confirmation');
            await form.locator('input[name="confirm_refund"]').check();
            await submit(student, 'form[id^="request-refund-"] button[type="submit"]');
            assert.equal(state().enrollment_status, 'rejected');
            assert.equal(state().payments[0].refund_status, 'pending');
            assert.equal(state().revision_status, 'refund_requested');
            await goto(student, `/portal/enrollment/${id}/payment-info`);
            await noOverflow(student, 'refund-pending');
            await goto(cashier, `/cashier/payments/${id}`);
            const refundForm = cashier.locator('form:has(input[name="refund_amount"])');
            await refundForm.locator('input[name="refund_amount"]').fill('400');
            await refundForm.locator('input[name="refund_reference"]').fill('TEST-EXTERNAL-REFUND');
            await submit(cashier, 'form:has(input[name="refund_amount"]) button[type="submit"]');
            assert.equal(state().payments[0].refund_status, 'pending', 'Partial refund must not release the payment block');
            await cashier.locator('input[name="refund_amount"]').fill('500');
            await cashier.locator('input[name="refund_reference"]').fill('TEST-EXTERNAL-REFUND');
            await submit(cashier, 'form:has(input[name="refund_amount"]) button[type="submit"]');
            assert.equal(state().payments[0].refund_status, 'refunded');
            assert.equal(state().revision_status, 'refunded');
            assert.equal(state().payments[0].receipt_no, originalPayments[0].receipt_no);
            assert.equal(state().payments[0].paid_at, originalPayments[0].paid_at);
            assert.equal(state().payments[0].status, 'verified');
            await goto(student, `/portal/enrollment/${id}/payment-info`);
            await expect(student.locator('body')).toContainText(/refund confirmed/i);
            await noOverflow(student, 'refund-confirmed');
            console.log('PASS: explicit student refund choice, pending follow-up, wrong amount rejected, exact full confirmation and original receipt preserved');
        }
    } else {
        throw Error('Unknown enrollment journey');
    }
    assert.deepEqual(errors, [], 'Uncaught browser JavaScript errors');
})().catch(async error => {
    console.error(error.stack || error);
    if (activePage) {
        console.error('Failed URL: ' + activePage.url());
        await activePage.screenshot({ path: path.join(output, 'failure.png'), fullPage: true, timeout: 5000 }).catch(() => {});
        fs.writeFileSync(path.join(output, 'failure.html'), await Promise.race([activePage.content().catch(() => ''), delay(5000).then(() => '')]));
    }
    process.exitCode = 1;
}).finally(async () => {
    if (browser) await browser.close().catch(() => {});
    child?.kill();
    clearTimeout(watchdog);
});
