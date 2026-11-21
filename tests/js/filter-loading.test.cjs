const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function fixture() {
    const events = {};
    const children = [];
    const form = { attributes: {}, querySelector: () => children[0], appendChild: el => children.push(el),
        setAttribute(name, value) { this.attributes[name] = value; }, matches: () => true };
    const frames = [];
    const window = { addEventListener: (name, fn) => { events[name] = fn; }, requestAnimationFrame: fn => frames.push(fn) };
    const document = { addEventListener: (name, fn) => { events[name] = fn; },
        querySelectorAll: () => children, createElement: () => ({ setAttribute() {} }) };
    vm.runInNewContext(fs.readFileSync('public/js/dtc-filters.js', 'utf8'), { window, document });
    return { window, form, children, events, frames };
}

test('AJAX loading reuses one accessible status and clears busy state on completion', () => {
    const f = fixture();
    f.window.dtcSetFilterLoading(f.form, true);
    f.window.dtcSetFilterLoading(f.form, true);
    assert.equal(f.children.length, 1);
    assert.equal(f.children[0].textContent, 'Applying filters…');
    assert.equal(f.children[0].hidden, false);
    assert.equal(f.form.attributes['aria-busy'], 'true');
    f.window.dtcSetFilterLoading(f.form, false);
    assert.equal(f.children[0].hidden, true);
    assert.equal(f.form.attributes['aria-busy'], 'false');
});

test('native submissions show loading; cancelled submissions do not; back navigation clears it', () => {
    const f = fixture();
    f.events.submit({ target: f.form, defaultPrevented: true });
    assert.equal(f.children.length, 0);
    f.events.submit({ target: f.form, defaultPrevented: false });
    assert.equal(f.children[0].hidden, false);
    f.children[0].parentElement = f.form;
    f.events.pageshow();
    assert.equal(f.children[0].hidden, true);
});

test('local filtering paints loading first and clears it even if filtering fails', () => {
    const f = fixture();
    let applied = false;
    f.window.dtcApplyLocalFilters(f.form, () => { applied = true; throw new Error('failed'); });
    assert.equal(applied, false);
    assert.equal(f.children[0].hidden, false);
    f.frames.shift()();
    assert.equal(applied, false);
    assert.throws(() => f.frames.shift()(), /failed/);
    assert.equal(applied, true);
    assert.equal(f.children[0].hidden, true);
});

test('filter reset links show loading, while modified clicks leave the current page idle', () => {
    const f = fixture();
    const link = { closest: () => f.form };
    const target = { closest: () => link };
    f.events.click({ target, button: 0, ctrlKey: true });
    assert.equal(f.children.length, 0);
    f.events.click({ target, button: 0 });
    assert.equal(f.children[0].hidden, false);
});
