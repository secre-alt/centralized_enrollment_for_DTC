/* Dependency-free Edge/Chrome CDP check. Start Laravel separately, then run:
 * node tests/browser/admission-layout.cjs
 * Never submits an application. Outputs measurements and screenshots to storage.
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const output = path.resolve('storage/framework/testing/admission-layout');
fs.mkdirSync(output, { recursive: true });
const executable = process.env.BROWSER_PATH || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-sandbox', '--disable-extensions',
    '--remote-debugging-port=9225', '--remote-allow-origins=*',
    '--user-data-dir=' + path.join(output, 'browser-profile'), 'about:blank'], { windowsHide: true, stdio: ['ignore','ignore','pipe'] });

let socket;
const results = [];
const watchdog = setTimeout(() => { console.error('Browser check timed out'); browser.kill(); process.exit(1); }, 180000);
(async () => {
    let targets;
    for (let i = 0; i < 60; i++) {
        try { targets = await (await fetch('http://127.0.0.1:9225/json')).json(); break; } catch { await delay(250); }
    }
    if (!targets) throw Error('Browser CDP unavailable');
    socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let next = 0;
    const pending = new Map();
    socket.addEventListener('close', () => {
        pending.forEach(job => job.reject(Error('Browser connection closed')));
        pending.clear();
    });
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (!message.id) return;
        const job = pending.get(message.id);
        pending.delete(message.id);
        message.error ? job.reject(Error(JSON.stringify(message.error))) : job.resolve(message.result);
    });
    function cdp(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++next;
            pending.set(id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params }));
        });
    }
    async function evaluate(expression) {
        const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    }
    await cdp('Page.enable');
    await cdp('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    const resetDraftScript = await cdp('Page.addScriptToEvaluateOnNewDocument', { source: "try { localStorage.removeItem('dtc_application_draft'); } catch {}" });
    console.log('Browser connected; loading admission');
    await cdp('Page.navigate', { url: process.env.ADMISSION_URL || 'http://127.0.0.1:8765/apply' });
    let ready = false;
    for (let i = 0; i < 120; i++) {
        if (await evaluate('typeof window._wizGoTo === "function"')) { ready = true; break; }
        await delay(250);
    }
    if (!ready) throw Error('Admission scripts did not initialize');
    console.log('Admission initialized');
    await evaluate(`document.documentElement.style.scrollBehavior = 'auto';
        document.querySelectorAll('*').forEach(el => el.style.animation = 'none');
        localStorage.removeItem('dtc_application_draft');`);
    for (const [width, height] of (process.env.QUICK ? [[1366,660],[412,892]] : [[1366,660],[1920,960],[1366,768],[1920,1080],[768,1024],[390,844],[320,640],[1366,500],[683,330]])) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
        for (const type of (process.env.QUICK ? ['transferee','cross_enrollee'] : ['new_student','transferee','shiftee','returnee','cross_enrollee'])) {
            await evaluate(`document.getElementById('academic_status').value = '${type}';
                document.getElementById('academic_status').dispatchEvent(new Event('change'));`);
            for (let step = 1; step <= 7; step++) {
                await evaluate(`window._wizGoTo(${step}, true); window.scrollTo(0,0);`);
                await delay(30);
                const measurement = await evaluate(`(() => {
                    const nav = document.querySelector('.admission-wizard .wiz-nav').getBoundingClientRect();
                    const footerTop = document.querySelector('.site-footer').getBoundingClientRect().top;
                    const fields = [...document.querySelectorAll('.wiz-panel.active input:not([type="hidden"]), .wiz-panel.active select')]
                        .filter(el => el.checkVisibility() && !el.disabled);
                    window.scrollTo(0, document.documentElement.scrollHeight);
                    const actionsReachable = document.querySelector('.admission-wizard .wiz-nav').getBoundingClientRect().bottom <= document.querySelector('.site-footer').getBoundingClientRect().top + 1;
                    window.scrollTo(0, 0);
                    return { actionsReachable, width: innerWidth, height: innerHeight,
                        horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
                        navBottom: Math.ceil(nav.bottom),
                        clearOfFooter: nav.bottom <= footerTop + 1,
                        fieldCount: fields.length,
                        fieldsFit: fields.every(el => el.getBoundingClientRect().bottom <= innerHeight),
                        minControlHeight: fields.length ? Math.min(...fields.map(el => el.getBoundingClientRect().height)) : null,
                        fits: nav.bottom <= innerHeight };
                })()`);
                results.push({ type, step, ...measurement });
                if (type === 'transferee' && [1366,1920,390].includes(width) && [660,960,844].includes(height)) {
                    await delay(250);
                    const screenshot = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                    fs.writeFileSync(path.join(output, `${width}x${height}-step-${step}.png`), Buffer.from(screenshot.data, 'base64'));
                }
            }
        }
    }
    await cdp('Emulation.setDeviceMetricsOverride', { width: 1366, height: 660, deviceScaleFactor: 1, mobile: false });
    const functional = await evaluate(`(async () => {
        const check = (condition, message) => { if (!condition) throw Error(message); };
        window._wizGoTo(1, true);
        const type = document.getElementById('academic_status');
        type.value = ''; type.dispatchEvent(new Event('change'));
        document.getElementById('wizNext').click();
        await new Promise(resolve => setTimeout(resolve, 20));
        check(document.activeElement.id === 'academic_status', 'Missing field receives focus');
        check(type.getAttribute('aria-invalid') === 'true', 'Missing field exposes error');
        for (const [route, required] of Object.entries(window.dtcAdmissionValidation.requiredEducation)) {
            type.value = route; type.dispatchEvent(new Event('change'));
            const actual = [...document.querySelectorAll('#step-4 input, #step-4 select')].filter(field => field.required && !field.disabled).map(field => field.name).sort();
            check(JSON.stringify(actual) === JSON.stringify([...required].sort()), 'Education requirements follow applicant type '+route);
        }
        type.value = 'transferee'; type.dispatchEvent(new Event('change'));
        const program = document.getElementById('program_id');
        check(program.options.length > 1, 'At least one program needed for functional checks');
        program.selectedIndex = 1; program.dispatchEvent(new Event('change'));
        document.querySelector('.wiz-step[data-step="7"]').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-2', 'Progress segments cannot skip missing personal details');
        for (const [id, value] of Object.entries({first_name:'Juan', last_name:'Cruz', gender:'male', birthdate:'2005-01-01', email:'layout@example.test', current_address:'Purok One', city_text:'Danao', international_location:'Tokyo'})) {
            const input = document.getElementById(id); input.value = value;
            input.dispatchEvent(new Event('input')); input.dispatchEvent(new Event('change'));
        }
        window._wizGoTo(2, true);
        const birthdate = document.getElementById('birthdate');
        birthdate.value = '9999-12-31'; birthdate.dispatchEvent(new Event('change'));
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-2' && birthdate.getAttribute('aria-invalid') === 'true', 'Future birthdate blocks Next');
        birthdate.value = '2005-01-01'; birthdate.dispatchEvent(new Event('change'));
        const lrn = document.getElementById('lrn'); lrn.value = '123';
        document.getElementById('wizNext').click();
        check(!document.getElementById('personalDetailsContent').hidden && lrn.getAttribute('aria-invalid') === 'true', 'Invalid optional LRN opens its disclosure');
        lrn.value = ''; lrn.dispatchEvent(new Event('change'));
        document.getElementById('personalDetailsToggle').click();
        window._wizGoTo(3, true);
        const addressInput = document.getElementById('current_address'); addressInput.value = '';
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-3' && addressInput.getAttribute('aria-invalid') === 'true', 'Missing residential address blocks Next');
        addressInput.value = 'Purok One'; addressInput.dispatchEvent(new Event('change'));
        const email = document.getElementById('email'); email.value = 'invalid'; email.dispatchEvent(new Event('change'));
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-3' && email.getAttribute('aria-invalid') === 'true', 'Invalid email blocks Next');
        email.value = 'layout@example.test'; email.dispatchEvent(new Event('change'));
        const phone = document.getElementById('phone'); phone.value = '0912345678'; phone.dispatchEvent(new Event('change'));
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-3' && phone.getAttribute('aria-invalid') === 'true', 'Invalid optional phone blocks Next');
        phone.value = '+63 917-123-4567'; phone.dispatchEvent(new Event('change'));
        window._wizGoTo(2, true); document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-3', 'Next advances');
        check(document.getElementById('current_address').closest('.wiz-panel').id === 'step-3', 'Address fields belong to Contact');
        check(document.getElementById('wizardStepCount').textContent === 'Step 3 of 7', 'Progress count follows navigation');
        check(document.getElementById('wizardSectionName').textContent === 'Contact', 'Progress shows the current section');
        check(document.querySelector('.admission-card').contains(document.querySelector('.wiz-nav')), 'Navigation belongs to the shared container');
        document.getElementById('wizPrev').click();
        check(document.getElementById('first_name').value === 'Juan', 'Back preserves entries');
        check(JSON.parse(localStorage.getItem('dtc_application_draft')).first_name === 'Juan', 'Draft saved');
        window._wizGoTo(7, true);
        check(document.querySelector('#reviewSummary .rev-section:last-child .rev-badge').textContent === '0 / 4', 'Empty upload summary counts actual files');
        check(document.querySelectorAll('#reviewSummary .rev-section')[4].textContent.includes('Previous College Information'), 'Review uses the applicable education profile');
        check(document.querySelectorAll('#reviewSummary .rev-section')[4].querySelector('.rev-badge').textContent === '0 / 7', 'Review includes all applicable education fields');
        for (const [id,value] of Object.entries({most_recent_school:'Example College',previous_program:'BS Information Systems',last_year_level:'Second Year'})) { document.getElementById(id).value=value; document.getElementById(id).dispatchEvent(new Event('change')); }
        const schoolYear = document.getElementById('school_year_completed');
        schoolYear.value = '2024-2026'; schoolYear.dispatchEvent(new Event('change', { bubbles: true }));
        schoolYear.value = '2024'; schoolYear.dispatchEvent(new Event('input', { bubbles: true }));
        check(schoolYear.getAttribute('aria-invalid') === 'true', 'Invalid school-year feedback remains while the value is still malformed');
        schoolYear.value = '2026-2027'; schoolYear.dispatchEvent(new Event('input', { bubbles: true }));
        check(schoolYear.getAttribute('aria-invalid') !== 'true', 'School-year feedback clears when the value becomes valid');
        window._wizGoTo(2, true); window._wizGoTo(4, true);
        check(schoolYear.value === '2026-2027', 'Last school year attended is preserved when navigating between steps');
        window._wizGoTo(2, true);
        document.getElementById('personalDetailsToggle').click();
        check(!document.getElementById('personalDetailsContent').hidden, 'Personal disclosure opens');
        const married = document.getElementById('marital_status'); married.value = 'married'; married.dispatchEvent(new Event('change'));
        check(!document.getElementById('spouseField').classList.contains('d-none'), 'Spouse field opens');
        const spouse = document.getElementById('spouse_name'); spouse.value = 'Test Spouse'; spouse.dispatchEvent(new Event('input'));
        married.value = 'single'; married.dispatchEvent(new Event('change'));
        check(spouse.value === '' && JSON.parse(localStorage.getItem('dtc_application_draft')).spouse_name === '', 'Inapplicable spouse details are cleared from form and draft');
        married.value = 'married'; married.dispatchEvent(new Event('change'));
        check(!document.getElementById('marriageDocument').classList.contains('d-none'), 'Marriage upload opens');
        const disability = document.getElementById('disability'); disability.value = 'Hearing disability'; disability.dispatchEvent(new Event('input'));
        check(!document.getElementById('pwdIdField').classList.contains('d-none'), 'PWD field opens');
        check(document.getElementById('occupation').closest('.wiz-panel').id === 'step-2' && disability.closest('.wiz-panel').id === 'step-2', 'Applicant occupation and accessibility details belong to Personal');
        const pwd = document.getElementById('pwd_id'); pwd.value = 'PWD-123'; pwd.dispatchEvent(new Event('input'));
        disability.value = ''; disability.dispatchEvent(new Event('input'));
        check(pwd.value === '' && JSON.parse(localStorage.getItem('dtc_application_draft')).pwd_id === '', 'Inapplicable PWD details are cleared from form and draft');
        disability.value = 'Hearing disability'; disability.dispatchEvent(new Event('input'));
        const regionInput = document.getElementById('region_text');
        const provinceInput = document.getElementById('province_text');
        const cityInput = document.getElementById('city_text');
        const choose = (field, value) => { field.value = value; field.dispatchEvent(new Event('input', {bubbles:true})); };
        window._wizGoTo(3, true);
        regionInput.focus(); regionInput.click();
        check(regionInput.getAttribute('aria-expanded') === 'true', 'Region picker opens on click');
        [...document.querySelectorAll('#region_text_suggestions [role="option"]')].find(option => option.textContent === 'Region VII (Central Visayas)').click();
        check(document.getElementById('region_code').value === '0700000000', 'Clicking a region resolves its code');
        check(regionInput.getAttribute('aria-expanded') === 'false', 'Selection closes picker');
        choose(regionInput, 'Region VII (Central Visayas)');
        check([...document.getElementById('provinceOptions').options].some(option => option.value === 'Bohol'), 'Province suggestions follow region');
        provinceInput.value = 'Bohol'; provinceInput.dispatchEvent(new Event('change', {bubbles:true}));
        check([...document.getElementById('cityOptions').options].some(option => option.value === 'Danao'), 'Change-only selection updates city suggestions');
        choose(cityInput, 'Danao');
        check(document.getElementById('city_code').value === '0701218000', 'Catalog city resolves its verified code');
        regionInput.dispatchEvent(new Event('input', {bubbles:true}));
        check(cityInput.value === 'Danao', 'Duplicate parent events preserve selected descendants');
        for (let wait = 0; wait < 80 && !document.getElementById('barangayOptions').options.length; wait++) await new Promise(resolve => setTimeout(resolve, 50));
        const barangayInput = document.getElementById('barangay_text');
        check([...document.getElementById('barangayOptions').options].some(option => option.value === 'Remedios'), 'Barangay suggestions are filtered by city');
        choose(barangayInput, 'Remedios');
        check(document.getElementById('barangay_code').value !== '', 'Barangay selection resolves a catalog code');
        choose(provinceInput, 'City of Cebu');
        check(barangayInput.value === '' && document.getElementById('barangay_code').value === '', 'Changing a city clears barangay');
        check(document.getElementById('province_code').value === '' && document.getElementById('city_code').value === '0730600000', 'Independent cities use a city code without a province');
        check(cityInput.closest('.wiz-field').hidden, 'Redundant city field is hidden for independent cities');
        choose(regionInput, 'Region VI (Western Visayas)');
        check(provinceInput.value === '' && cityInput.value === '' && document.getElementById('city_code').value === '', 'Changing a region clears downstream selections');
        choose(provinceInput, 'Manual province');
        check(document.getElementById('province_code').value === '', 'Manual text does not acquire a catalog code');
        const country = document.getElementById('country');
        check(document.getElementById('countryOptions').options.length > 200, 'Country suggestions are available locally');
        window._wizGoTo(3, true);
        country.value = 'Invalid country'; country.dispatchEvent(new Event('input'));
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-3' && country.getAttribute('aria-invalid') === 'true', 'Unknown country blocks Next');
        country.value = 'japan'; country.dispatchEvent(new Event('input')); country.dispatchEvent(new Event('blur'));
        check(country.value === 'Japan', 'Country names normalize to the catalog spelling');
        country.focus();
        country.value = 'United King'; country.dispatchEvent(new Event('input', {bubbles:true}));
        country.dispatchEvent(new KeyboardEvent('keydown', {key:'ArrowDown', bubbles:true}));
        country.dispatchEvent(new KeyboardEvent('keydown', {key:'Enter', bubbles:true}));
        check(country.value === 'United Kingdom' && document.querySelector('.wiz-panel.active').id === 'step-3', 'Keyboard selection chooses a country without advancing the wizard');
        const postal = document.getElementById('postal_code');
        check(postal.maxLength === 20 && postal.inputMode === 'text', 'International postal field allows text');
        postal.value = 'SW1A 1AA'; postal.dispatchEvent(new Event('change'));
        country.value = 'Philippines'; country.dispatchEvent(new Event('input'));
        check(postal.maxLength === 4 && postal.inputMode === 'numeric' && postal.getAttribute('aria-invalid') === 'true', 'Country changes revalidate existing postal codes');
        postal.value = '0604'; postal.dispatchEvent(new Event('change'));
        check(postal.value === '0604' && postal.getAttribute('aria-invalid') !== 'true', 'Leading zeroes are preserved');
        postal.value = ''; postal.dispatchEvent(new Event('change'));
        country.value = 'United Kingdom'; country.dispatchEvent(new Event('input'));
        check(regionInput.disabled && document.getElementById('region_code').value === '', 'International address excludes stale Philippine fields');
        check(!document.getElementById('internationalLocationField').hidden, 'International address opens');
        window._wizGoTo(6, true);
        const upload = document.querySelector('.document-section:not(.d-none) input[type="file"]');
        const transfer = new DataTransfer();
        transfer.items.add(new File([new Uint8Array(5242881)], 'oversize.pdf', {type:'application/pdf'}));
        upload.files = transfer.files; upload.dispatchEvent(new Event('change'));
        document.getElementById('wizNext').click();
        check(document.querySelector('.wiz-panel.active').id === 'step-6' && upload.getAttribute('aria-invalid') === 'true', 'Oversize file blocks Next');
        transfer.items.clear();
        transfer.items.add(new File(['layout QA fixture'], 'layout-check.pdf', {type:'application/pdf'}));
        upload.files = transfer.files; upload.dispatchEvent(new Event('change'));
        check(upload.closest('.document-input-control').textContent.includes('layout-check.pdf'), 'File selection updates label');
        window._wizGoTo(7, true);
        check(document.querySelectorAll('#reviewSummary .rev-section').length === 7, 'Seven review sections');
        check(document.getElementById('reviewSummary').textContent.includes('layout-check.pdf'), 'Review includes chosen file');
        check(document.querySelector('#reviewSummary .rev-section:last-child .rev-badge').textContent === '1 / 5', 'Upload badge includes applicable marriage document');
        const review = document.querySelector('#reviewSummary .rev-section');
        check(!document.querySelector('#reviewSummary details'), 'Review sections are expanded');
        check(review.textContent.includes('Transferee'), 'Review includes applicant data');
        document.getElementById('openPrivacyModal').focus(); document.getElementById('openPrivacyModal').click();
        await new Promise(resolve => setTimeout(resolve, 250));
        check(document.activeElement.getAttribute('role') === 'dialog', 'Dialog receives focus');
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key:'Tab', bubbles:true}));
        check(document.activeElement.id === 'closePrivacyModal', 'Dialog Tab stays inside');
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true}));
        check(document.activeElement.id === 'openPrivacyModal', 'Dialog restores focus');
        document.getElementById('openTermsModal').click();
        check(document.activeElement.getAttribute('aria-labelledby') === 'termsModalTitle', 'Terms dialog receives focus');
        document.getElementById('closeTermsModal').click();
        document.getElementById('wizSubmit').click();
        await new Promise(resolve => setTimeout(resolve, 50));
        check(document.getElementById('dtcConfirmModal').classList.contains('show'), 'Continue opens confirmation');
        document.getElementById('dtcConfirmCancel').click();
        check(document.getElementById('ack_enrollment').value === '', 'Cancel does not acknowledge or submit');
        localStorage.removeItem('dtc_application_draft');
        return { passed: true, checks: ['required feedback','Next/Back','draft persistence','personal/spouse/PWD/international/marriage visibility','populated review','legal-dialog focus and Escape','submission confirmation cancel'] };
    })()`);
    fs.writeFileSync(path.join(output, 'functional.json'), JSON.stringify(functional, null, 2));
    const expanded = [];
    await cdp('Emulation.setDeviceMetricsOverride', { width:412, height:892, deviceScaleFactor:1, mobile:true });
    const pickerFits = await evaluate(`window._wizGoTo(3, true); const pickerCountry = document.getElementById('country'); pickerCountry.focus(); pickerCountry.click(); const pickerRect = document.getElementById('country_suggestions').getBoundingClientRect(); pickerRect.width > 0 && pickerRect.left >= 0 && pickerRect.right <= innerWidth;`);
    assert.ok(pickerFits, 'Country picker stays within the mobile viewport');
    for (const [width, height] of [[1920,960],[1536,728],[1366,660],[390,844]]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
        await evaluate('window._wizGoTo(2, true); window.scrollTo(0,0);');
        await delay(50);
        expanded.push(await evaluate(`(() => {
            const nav = document.querySelector('.wiz-nav');
            const bottom = Math.ceil(nav.getBoundingClientRect().bottom);
            nav.scrollIntoView({block:'end', behavior:'instant'});
            return {width:innerWidth, height:innerHeight, navBottom:bottom, fits:bottom <= innerHeight,
                reachable:nav.getBoundingClientRect().bottom <= innerHeight + 1,
                overflow:document.documentElement.scrollWidth > innerWidth};
        })()`));
        await evaluate('window.scrollTo(0,0);');
        if (width >= 1536) {
            const shot = await cdp('Page.captureScreenshot', { format:'png', captureBeyondViewport:false });
            fs.writeFileSync(path.join(output, `${width}x${height}-personal-expanded.png`), Buffer.from(shot.data, 'base64'));
        }
    }
    fs.writeFileSync(path.join(output, 'expanded.json'), JSON.stringify(expanded, null, 2));
    assert.ok(expanded.every(row => row.reachable && !row.overflow), 'Expanded personal fields and actions remain reachable');
    await cdp('Emulation.setDeviceMetricsOverride', { width:1366, height:660, deviceScaleFactor:1, mobile:false });
    await delay(100);
    const dark = await evaluate(`(() => {
        document.getElementById('applicationForm').reset();
        document.getElementById('academic_status').value = 'transferee';
        for (const [id, event] of [['academic_status','change'],['marital_status','change'],['disability','input'],['country','input']]) {
            document.getElementById(id).dispatchEvent(new Event(event));
        }
        if (document.getElementById('personalDetailsToggle').getAttribute('aria-expanded') === 'true') document.getElementById('personalDetailsToggle').click();
        document.getElementById('guardianDetails').open = false;
        document.body.classList.add('dtc-dark');
        document.documentElement.classList.add('dtc-dark');
        const checks = [];
        for (let step = 1; step <= 7; step++) {
            window._wizGoTo(step, true);
            checks.push({step, width:innerWidth, height:innerHeight, navBottom:Math.ceil(document.querySelector('.wiz-nav').getBoundingClientRect().bottom), fits: document.querySelector('.wiz-nav').getBoundingClientRect().bottom <= innerHeight,
                overflow: document.documentElement.scrollWidth > innerWidth,
                schoolYearBackgroundRepeat: step === 4 ? getComputedStyle(document.getElementById('school_year_completed')).backgroundRepeat : null});
        }
        return checks;
    })()`);
    fs.writeFileSync(path.join(output, 'dark.json'), JSON.stringify(dark, null, 2));
    assert.ok(dark.every(row => ([3, 7].includes(row.step) || row.fits) && !row.overflow), 'Dark-mode desktop steps fit');
    assert.equal(dark.find(row => row.step === 4).schoolYearBackgroundRepeat, 'no-repeat', 'Dark-mode school-year dropdown icon does not tile');
    const darkScreenshot = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    fs.writeFileSync(path.join(output, 'dark-review.png'), Buffer.from(darkScreenshot.data, 'base64'));
    fs.writeFileSync(path.join(output, process.env.QUICK ? 'quick-measurements.json' : 'measurements.json'), JSON.stringify(results, null, 2));
    await cdp('Page.removeScriptToEvaluateOnNewDocument', { identifier: resetDraftScript.identifier });
    await evaluate(`localStorage.setItem('dtc_application_draft', JSON.stringify({country:'Philippines', region:'Region VII (Central Visayas)', province:'Bohol', city:'Danao', barangay:'Remedios'}));`);
    await cdp('Page.reload', {ignoreCache:false});
    await delay(1000);
    for (let retry = 0; retry < 100; retry++) {
        if (await evaluate('document.readyState === "complete" && !!document.getElementById("region_text_suggestions")')) break;
        await delay(100);
    }
    const restored = await evaluate(`(async () => {
        window._wizGoTo(3, true);
        const region = document.getElementById('region_text'); region.focus(); region.click();
        if (region.getAttribute('aria-expanded') !== 'true') return false;
        if (document.getElementById('city_code').value !== '0701218000') return false;
        const province = document.getElementById('province_text'); province.focus(); province.click();
        if (province.getAttribute('aria-expanded') !== 'true') return false;
        for (let retry = 0; retry < 80 && !document.getElementById('barangayOptions').options.length; retry++) await new Promise(resolve => setTimeout(resolve, 50));
        const barangay = document.getElementById('barangay_text'); barangay.focus(); barangay.click();
        return barangay.value === 'Remedios' && barangay.getAttribute('aria-expanded') === 'true' && document.getElementById('barangay_code').value !== '';
    })()`);
    assert.ok(restored, 'Saved location dropdowns and barangays work after page refresh');
    console.log(JSON.stringify({ cases: results.length, desktopFailures: results.filter(r => ![3, 7].includes(r.step) && r.width >= 1024 && r.height >= 660 && !r.fits), horizontalFailures: results.filter(r => r.horizontalOverflow) }, null, 2));
    assert.equal(results.filter(r => ![3, 7].includes(r.step) && r.width >= 1024 && r.height >= 660 && !r.fits).length, 0, 'Desktop entry steps must fit');
    assert.equal(results.filter(r => ![3, 7].includes(r.step) && r.width >= 1024 && r.height >= 660 && !r.actionsReachable).length, 0, 'Desktop entry actions can scroll clear of the sticky footer');
    assert.equal(results.filter(r => r.horizontalOverflow).length, 0, 'No horizontal overflow');
    await cdp('Browser.close');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
    if (socket) socket.close();
    browser.kill();
    clearTimeout(watchdog);
});
