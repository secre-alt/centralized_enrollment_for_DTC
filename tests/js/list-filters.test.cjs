const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');

test('filter counts ignore the default status and track dates and actor changes', () => {
    const fields = [{ value: 'all', getAttribute: () => 'all' }, { value: '', getAttribute: () => null }, { value: '2026-01-12', getAttribute: () => null }];
    const badge = {};
    const clear = {};
    const listeners = {};
    const form = {
        querySelectorAll: selector => selector === '[data-dtc-filter]' ? fields : [clear],
        querySelector: selector => selector === '[data-dtc-filter-count]' ? badge : selector === '[data-dtc-filter-search]' ? { value: '' } : { classList: { toggle() {} } },
        addEventListener: (event, callback) => { listeners[event] = callback; },
    };
    const document = { addEventListener: (event, callback) => callback(), querySelectorAll: () => [form] };
    vm.runInNewContext(fs.readFileSync(path.join(root, 'public/js/dtc-filters.js'), 'utf8'), { document });
    assert.equal(badge.textContent, 1);
    fields[0].value = 'verified';
    fields[1].value = '42';
    listeners.change();
    assert.equal(badge.textContent, 3);
    fields.forEach(field => { field.value = ''; });
    listeners.input();
    assert.equal(badge.hidden, true);
    assert.equal(clear.hidden, true);
});

test('application AJAX URLs retain both date filters when changing pages', () => {
    const template = fs.readFileSync(path.join(root, 'resources/views/registrar/applications/index.blade.php'), 'utf8');
    const build = template.match(/function buildUrl\(extra\) \{[\s\S]*?\n    \}/)[0];
    const sandbox = {
        URLSearchParams, baseUrl: '/registrar/applications',
        searchInput: { value: ' Applicant ' }, typeSelect: { value: 'new_student' }, statusSelect: { value: 'submitted' }, programSelect: { value: '7' },
        document: { querySelector: selector => ({ value: selector.includes('date_from') ? '2026-01-01' : selector.includes('date_to') ? '2026-01-31' : '20' }) },
    };
    vm.runInNewContext(build + '\nresult = buildUrl({ page: 2 });', sandbox);
    const params = new URL(sandbox.result, 'http://localhost').searchParams;
    assert.equal(params.get('date_from'), '2026-01-01');
    assert.equal(params.get('date_to'), '2026-01-31');
    assert.equal(params.get('page'), '2');
    assert.equal(params.get('program_id'), '7');
    assert.equal(params.get('search'), 'Applicant');
});
