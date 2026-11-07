const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

// Exercise browser validation against the exact rules exported by Laravel.
const rules = JSON.parse(execFileSync('php', ['-r', "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode(App\\Support\\AdmissionValidation::clientRules());"], { encoding: 'utf8' }));
const source = fs.readFileSync('public/js/application-wizard.js', 'utf8');
const country = { value: 'Philippines' };
const context = vm.createContext({ window: { dtcAdmissionValidation: rules }, document: { getElementById: () => country, querySelectorAll: () => [] } });
vm.runInContext(source.slice(source.indexOf('    function validationMessage(el)'), source.indexOf('    function revealInvalidField(el)')), context);
function message(name, value, extra = {}) {
    context.control = { name, value, maxLength: 255, type: 'text', required: false, ...extra };
    return vm.runInContext('validationMessage(control)', context);
}

test('birthdate rejects future dates, invalid calendar dates, and non-ISO values', () => {
    for (const value of ['2004-02-29', rules.latestBirthdate, rules.earliestBirthdate]) assert.equal(message('birthdate', value), '');
    for (const value of [rules.today, '1600-01-01', '1800-01-01']) assert.notEqual(message('birthdate', value), '', value);
    const tooYoung = new Date(rules.latestBirthdate + 'T00:00:00Z');
    tooYoung.setUTCDate(tooYoung.getUTCDate() + 1);
    const tooOld = new Date(rules.earliestBirthdate + 'T00:00:00Z');
    tooOld.setUTCDate(tooOld.getUTCDate() - 1);
    for (const value of [tooYoung, tooOld]) assert.notEqual(message('birthdate', value.toISOString().slice(0, 10)), '');
    for (const value of ['2025-02-29', '2005-02-30', '9999-12-31', '2005-01-01T12:00:00Z', '01/02/2005']) assert.notEqual(message('birthdate', value), '', value);
    assert.notEqual(message('birthdate', '', {required:true}), '');
});

test('country names use the local list and supported aliases', () => {
    for (const value of ['Philippines', 'japan', 'United States', 'United States of America', 'UK', '']) assert.equal(message('country', value), '');
    for (const value of ['Not a country', '__proto__', 'Philippiness']) assert.notEqual(message('country', value), '');
});

test('all contact fields accept formatting and reject invalid mobile lengths and text', () => {
    for (const field of rules.phoneFields) {
        for (const value of ['09171234567', '+63 917-123-4567', '(02) 8123-4567', '032-123-4567', '+1 (202) 555-0123', '']) assert.equal(message(field, value), '', value);
        for (const value of ['0912345678', '+63912345678', 'call me', '09+171234567', '+0123456789']) assert.notEqual(message(field, value), '', value);
    }
});

test('optional identifiers, school years, Unicode names, and country-specific postal codes validate', () => {
    for (const field of ['lrn', 'postal_code', 'school_year_completed', 'middle_name']) assert.equal(message(field, ''), '');
    assert.equal(message('lrn', '001234567890'), '');
    assert.notEqual(message('lrn', '12345678901x'), '');
    for (const value of ['2025-2026', rules.schoolYearMaxStart + '-' + (rules.schoolYearMaxStart + 1)]) {
        assert.equal(message('school_year_completed', value), '', value);
    }
    for (const value of ['2025', '2024–2025', '2024 - 2025', 'last year']) {
        assert.equal(message('school_year_completed', value), 'Enter a school year in YYYY-YYYY format.', value);
    }
    assert.equal(message('school_year_completed', '2024-2026'), 'The ending year must be one year after the starting year.');
    assert.equal(
        message('school_year_completed', (rules.schoolYearMaxStart + 1) + '-' + (rules.schoolYearMaxStart + 2)),
        'Enter a current or previous school year.'
    );
    assert.equal(message('school_year_completed', '', { required: true }), 'Please enter your last school year attended.');
    for (const value of ['José', 'O’Neill-Peña', '李', "D'Arcy", 'Dela\tCruz']) assert.equal(message('first_name', value), '');
    for (const value of ['12345', 'John42', '<b>John</b>']) assert.notEqual(message('last_name', value), '');
    assert.equal(message('postal_code', '0604'), '');
    assert.notEqual(message('postal_code', '06004'), '');
    for (const value of ['0000', '123', '12-3', '12 3']) assert.notEqual(message('postal_code', value), '', value);
    country.value = 'United Kingdom';
    assert.equal(message('postal_code', 'SW1A 1AA'), '');
    assert.notEqual(message('postal_code', '<invalid>'), '');
    for (const value of ['SW1A--1AA', 'SW1A  1AA', 'SW1A-', '-SW1A', 'A'.repeat(21)]) assert.notEqual(message('postal_code', value), '', value);
    for (const value of ['12345-6789', 'K1A 0B1']) assert.equal(message('postal_code', value), '');
    country.value = 'Philippines';
});

test('file limits and invalid email feedback are enforced before navigation', () => {
    assert.notEqual(message('email', 'invalid', {validity:{typeMismatch:true}}), '');
    for (const file of [{name:'scan.exe',size:12}, {name:'scan.pdf',size:0}, {name:'scan.pdf',size:rules.maxUploadBytes + 1}]) assert.notEqual(message('upload', '', {type:'file',files:[file]}), '');
    assert.equal(message('upload', '', {type:'file',files:[{name:'scan.PDF',size:rules.maxUploadBytes}]}), '');
    assert.equal(message('upload', '', {type:'file',files:[]}), '');
    if (rules.maxPostBytes) {
        context.document.querySelectorAll = () => [{ disabled:false, closest:() => null, files:[{size:rules.maxPostBytes}] }];
        assert.match(message('upload', '', {type:'file',files:[{name:'scan.pdf',size:12}]}), /Combined uploads/);
        context.document.querySelectorAll = () => [];
    }
});
