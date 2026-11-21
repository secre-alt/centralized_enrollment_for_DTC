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
const email = 'browser-applicant@example.test';
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
    const applicant = await pageFor();
    await goto(applicant, '/apply');
    await expect(applicant.locator('#academic_status')).toBeVisible();
    await applicant.locator('#wizNext').click();
    await expect(applicant.locator('#academic_status')).toBeFocused();
    await applicant.locator('#academic_status').selectOption('new_student');
    await applicant.locator('#program_id').selectOption(process.env.BROWSER_PROGRAM_ID);
    await applicant.locator('#wizNext').click();
    await applicant.locator('#first_name').fill('Browser');
    await applicant.locator('#last_name').fill('Applicant');
    await applicant.locator('#gender').selectOption('male');
    await applicant.locator('#birthdate').fill('2005-01-01');
    await applicant.locator('#wizNext').click();
    await applicant.locator('#email').fill(email);
    await applicant.locator('#country').fill('Philippines');
    await applicant.locator('#city_text').fill('Danao');
    await applicant.locator('#current_address').fill('Test Street');
    await applicant.locator('#wizNext').click();
    await applicant.locator('#education_status').selectOption('Graduated');
    await applicant.locator('#most_recent_school').fill('Browser Test School');
    await applicant.locator('#school_year_completed').fill('2025-2026');
    await applicant.locator('#wizNext').click();
    await applicant.locator('#wizNext').click();
    await applicant.locator('#wizNext').click();
    await expect(applicant.locator('#wizSubmit')).toBeVisible();
    await noOverflow(applicant, 'admission-review');
    await confirm(applicant, '#wizSubmit', 'Submit');
    assert.equal(state().application_status, 'submitted');
    console.log('PASS: public wizard validation and application submission');

    const registrar = await pageFor();
    await login(registrar, process.env.BROWSER_REGISTRAR_EMAIL);
    const applicationId = state().application_id;
    await goto(registrar, `/registrar/applications/${applicationId}`);
    await confirm(registrar, `#start-review-${applicationId} button`, 'Start');
    assert.equal(state().application_status, 'under_review');
    await confirm(registrar, `#approve-application-${applicationId} button`, 'Approve');
    assert.equal(state().application_status, 'approved');
    console.log('PASS: Registrar starts review and approves admission');

    await goto(applicant, state(true).activation_path);
    await applicant.locator('input[name="password"]').fill(process.env.BROWSER_PASSWORD);
    await applicant.locator('input[name="password_confirmation"]').fill(process.env.BROWSER_PASSWORD);
    await Promise.all([applicant.waitForNavigation({ waitUntil: 'domcontentloaded' }), applicant.locator('form:has(input[name="password"]) button[type="submit"]').click({ noWaitAfter: true })]);
    await login(applicant, email);
    await goto(applicant, '/portal/enrollment/create');
    const subjects = applicant.locator('input[name="subject_ids[]"]');
    await expect(subjects).toHaveCount(1);
    await subjects.first().check();
    await noOverflow(applicant, 'enrollment-create');
    await Promise.all([applicant.waitForNavigation({ waitUntil: 'domcontentloaded' }), applicant.locator('form:has(#year_level) button[type="submit"]').click({ noWaitAfter: true })]);
    assert.equal(state().hold, false);
    assert.equal(state().paid, false);
    await noOverflow(applicant, 'enrollment-pending');
    const enrollmentId = state().enrollment_id;
    console.log('PASS: password setup, applicant login and published-group assignment');

    const cashier = await pageFor();
    await login(cashier, process.env.BROWSER_CASHIER_EMAIL);
    await goto(cashier, `/cashier/payments/${enrollmentId}`);
    await confirm(cashier, `[data-dtc-confirm-form="#walkinForm-${enrollmentId}"]`, 'Confirm');
    assert.equal(state().paid, true);
    assert.equal(state().enrollment_status, 'pending');
    await goto(registrar, `/registrar/enrollments/${enrollmentId}`);
    await noOverflow(registrar, 'registrar-review');
    await confirm(registrar, `#approve-enrollment-${enrollmentId} button`, 'Approve');
    assert.equal(state().enrollment_status, 'approved');
    await goto(applicant, '/portal/enrollment');
    await noOverflow(applicant, 'enrollment-approved');
    console.log('PASS: cash payment then Registrar approval and student status');
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
