const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

test('regular enrollment preselects every subject loaded for the selected term', async () => {
    const viewPath = path.join(__dirname, '..', '..', 'resources', 'views', 'student', 'enrollment', 'create.blade.php');
    const view = fs.readFileSync(viewPath, 'utf8');
    const script = view.match(/@section\('js'\)\s*<script>\s*([\s\S]*?)\s*<\/script>/);

    assert.ok(script, 'enrollment form script should be present');

    const elements = {
        'type-irregular': { checked: false, addEventListener() {} },
        'type-regular': { addEventListener() {} },
        program_id: { value: '12', addEventListener() {} },
        year_level: { value: '2', addEventListener() {} },
        semester: { value: '1', addEventListener() {} },
        'subjects-container': { innerHTML: '' },
        'subjects-hint': { textContent: '' },
    };
    const context = {
        document: {
            getElementById: id => elements[id],
            addEventListener() {},
        },
        window: {
            dtcFetch: () => Promise.resolve({
                json: () => Promise.resolve([
                    { id: 21, subject_code: 'IS201', subject_name: 'Subject One' },
                    { id: 22, subject_code: 'IS202', subject_name: 'Subject Two' },
                ]),
            }),
        },
    };

    vm.runInNewContext(script[1], context);
    context.loadSubjects();
    await new Promise(resolve => setImmediate(resolve));

    const checkboxes = elements['subjects-container'].innerHTML.match(/<input type="checkbox" name="subject_ids\[\]"[^>]*>/g);
    assert.equal(checkboxes.length, 2);
    assert.ok(checkboxes.every(checkbox => /\bchecked\b/.test(checkbox)));
    assert.doesNotMatch(elements['subjects-container'].innerHTML, /Select All/);
});

test('irregular enrollment leaves subjects individually selectable without a select-all button', async () => {
    const viewPath = path.join(__dirname, '..', '..', 'resources', 'views', 'student', 'enrollment', 'create.blade.php');
    const view = fs.readFileSync(viewPath, 'utf8');
    const script = view.match(/@section\('js'\)\s*<script>\s*([\s\S]*?)\s*<\/script>/);

    assert.ok(script, 'enrollment form script should be present');

    const elements = {
        'type-irregular': { checked: true, addEventListener() {} },
        'type-regular': { addEventListener() {} },
        program_id: { value: '12', addEventListener() {} },
        year_level: { value: '2', addEventListener() {} },
        semester: { value: '1', addEventListener() {} },
        'subjects-container': { innerHTML: '' },
        'subjects-hint': { textContent: '' },
    };
    const context = {
        document: {
            getElementById: id => elements[id],
            addEventListener() {},
        },
        window: {
            dtcFetch: () => Promise.resolve({
                json: () => Promise.resolve([
                    {
                        year_level: 1,
                        semester: 1,
                        subjects: [
                            { id: 21, subject_code: 'IS101', subject_name: 'Subject One', units: 3 },
                            { id: 22, subject_code: 'IS102', subject_name: 'Subject Two', units: 3 },
                        ],
                    },
                ]),
            }),
        },
    };

    vm.runInNewContext(script[1], context);
    context.loadSubjects();
    await new Promise(resolve => setImmediate(resolve));

    const html = elements['subjects-container'].innerHTML;
    const checkboxes = html.match(/<input type="checkbox" name="subject_ids\[\]"[^>]*>/g);
    assert.equal(checkboxes.length, 2);
    assert.ok(checkboxes.every(checkbox => !/\bchecked\b/.test(checkbox)));
    assert.doesNotMatch(html, /Select All/);
});

for (const irregular of [false, true]) {
    for (const selected of [[], ['22']]) {
        test(`${irregular ? 'irregular' : 'regular'} enrollment restores ${JSON.stringify(selected)} after validation`, async () => {
            const view = fs.readFileSync(path.join(__dirname, '../../resources/views/student/enrollment/create.blade.php'), 'utf8');
            const script = view.match(/@section\('js'\)\s*<script>\s*([\s\S]*?)\s*<\/script>/)[1];
            const elements = {
                'type-irregular': { checked: irregular, addEventListener() {} },
                'type-regular': { addEventListener() {} },
                program_id: { value: '12', addEventListener() {} },
                year_level: { value: '2', addEventListener() {} },
                semester: { value: '2', addEventListener() {} },
                'subjects-container': { innerHTML: '', dataset: { selectedSubjects: JSON.stringify(selected) } },
                'subjects-hint': { textContent: '' },
            };
            const subjects = [21, 22].map(id => ({ id, subject_code: `IS${id}`, subject_name: 'Subject', units: 3 }));
            const context = { document: { getElementById: id => elements[id], addEventListener() {} },
                window: { dtcFetch: async () => ({ json: async () => irregular ? [{ year_level: 2, semester: 2, subjects }] : subjects }) } };
            vm.runInNewContext(script, context);
            context.loadSubjects();
            await new Promise(resolve => setImmediate(resolve));
            const inputs = elements['subjects-container'].innerHTML.match(/<input type="checkbox" name="subject_ids\[\]"[^>]*>/g);
            assert.equal(inputs.length, 2);
            for (const input of inputs) {
                const id = input.match(/value="(\d+)"/)[1];
                assert.equal(/\bchecked\b/.test(input), selected.includes(id));
            }
            context.loadSubjects({ type: 'change' });
            await new Promise(resolve => setImmediate(resolve));
            const changedInputs = elements['subjects-container'].innerHTML.match(/<input type="checkbox" name="subject_ids\[\]"[^>]*>/g);
            assert.ok(changedInputs.every(input => /\bchecked\b/.test(input) === !irregular));
        });
    }
}
