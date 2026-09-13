/**
 * DTC EMS — Application Wizard
 *
 * Features:
 *  - Multi-step wizard navigation with per-step validation
 *  - localStorage persistence: form data survives page reload
 *  - Tab switching on Step 5 (Upload vs Requirements Checklist)
 *  - Interactive requirements checklist (checkboxes, not bullets)
 *  - Cascading PH address picker  (Region → Province → City → Barangay)
 *  - Cascading PH place of birth  (Region → Province → City, Step 2)
 *  - Postal code auto-filled from barangay's postalCode via PSGC API
 *  - Religion searchable datalist
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ════════════════════════════════════════════════════════════
       CONFIG
    ════════════════════════════════════════════════════════════ */

    const TOTAL       = 6;
    const STORAGE_KEY = 'dtc_application_draft';
    const CHECKS_KEY  = 'dtc_req_checks';
    const PSGC_BASE   = 'https://psgc.cloud/api';
    let   current     = 1;


    /* ════════════════════════════════════════════════════════════
       ELEMENTS — WIZARD
    ════════════════════════════════════════════════════════════ */

    const panels     = document.querySelectorAll('.wiz-panel');
    const steps      = document.querySelectorAll('.wiz-step');
    const connectors = document.querySelectorAll('.wiz-connector');
    const bar        = document.getElementById('wizardBar');
    const labelCur   = document.getElementById('wizCurrentStep');
    const btnPrev    = document.getElementById('wizPrev');
    const btnNext    = document.getElementById('wizNext');
    const btnSubmit  = document.getElementById('wizSubmit');
    const tabBtns    = document.querySelectorAll('.doc-tab-btn');
    const tabPanels  = document.querySelectorAll('.doc-tab-panel');

    /* Step 1 / 5 — applicant reactive */
    const applicantType            = document.getElementById('academic_status');
    const maritalStatus            = document.getElementById('marital_status');
    const disability               = document.getElementById('disability');
    const documentSections         = document.querySelectorAll('.document-section');
    const noDocumentNotice         = document.getElementById('noDocumentNotice');
    const marriageDocument         = document.getElementById('marriageDocument');
    const spouseField              = document.getElementById('spouseField');
    const pwdIdField               = document.getElementById('pwdIdField');
    const crossEnrolleeNotice      = document.getElementById('crossEnrolleeNotice');
    const physicalRequirementsCard = document.getElementById('physicalRequirementsCard');
    const physicalRequirementsList = document.getElementById('physicalRequirementsList');

    /* Step 3 — current address cascade */
    const selRegion   = document.getElementById('addr_region');
    const selProvince = document.getElementById('addr_province');
    const selCity     = document.getElementById('addr_city');
    const selBarangay = document.getElementById('addr_barangay');
    const hidRegion   = document.getElementById('region_text');
    const hidProvince = document.getElementById('province_text');
    const hidCity     = document.getElementById('city_text');
    const hidBarangay = document.getElementById('barangay_text');
    const inpPostal   = document.getElementById('postal_code');

    /* Step 2 — place of birth cascade */


    /* ════════════════════════════════════════════════════════════
       PHYSICAL REQUIREMENTS DATA
    ════════════════════════════════════════════════════════════ */

    const physicalRequirements = {
        new_student: [
            'Form 138 or equivalent — original/required physical copy',
            'Certificate of Good Moral Character — original/required physical copy',
            'PSA Birth Certificate — required physical copy',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],
        transferee: [
            'Certificate of Transfer Credentials',
            'Transcript of Records',
            'Certificate of Good Moral Character',
            'PSA Birth Certificate',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],
        shiftee: [], returnee: [], cross_enrollee: [],
    };


    /* ════════════════════════════════════════════════════════════
       WIZARD NAVIGATION
    ════════════════════════════════════════════════════════════ */

    function goTo(n, skipValidation) {
        if (!skipValidation && n > current && !validateStep(current)) return;
        current = Math.max(1, Math.min(TOTAL, n));

        panels.forEach(function (p, i) { p.classList.toggle('active', i + 1 === current); });
        steps.forEach(function (s, i) {
            s.classList.remove('active', 'completed');
            if (i + 1 === current) s.classList.add('active');
            if (i + 1 <  current) s.classList.add('completed');
        });
        connectors.forEach(function (c, i) { c.classList.toggle('completed', i + 1 < current); });

        bar.style.width = ((current / TOTAL) * 100) + '%';
        labelCur.textContent = current;
        btnPrev.style.visibility = current === 1 ? 'hidden' : 'visible';

        if (current === TOTAL) {
            btnNext.classList.add('d-none');
            btnSubmit.classList.remove('d-none');
        } else {
            btnNext.classList.remove('d-none');
            btnSubmit.classList.add('d-none');
        }
        if (current === 6) buildReview();
        if (window.lucide) lucide.createIcons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }


    /* ════════════════════════════════════════════════════════════
       VALIDATION
    ════════════════════════════════════════════════════════════ */

    function validateStep(n) {
        const panel = document.getElementById('step-' + n);
        if (!panel) return true;
        let ok = true;
        panel.querySelectorAll('[required]').forEach(function (el) {
            if (el.type === 'hidden') return;
            const field = el.closest('.wiz-field');
            if (!el.value.trim()) {
                el.classList.add('wiz-invalid');
                if (field) {
                    let err = field.querySelector('.wiz-err-msg');
                    if (!err) {
                        err = document.createElement('span');
                        err.className = 'wiz-err-msg wiz-err-dynamic';
                        err.innerHTML = '<i data-lucide="alert-circle"></i> This field is required.';
                        field.appendChild(err);
                        if (window.lucide) lucide.createIcons({ nodes: [err] });
                    }
                    field.classList.remove('wiz-shake');
                    void field.offsetWidth;
                    field.classList.add('wiz-shake');
                    field.addEventListener('animationend', function () {
                        field.classList.remove('wiz-shake');
                    }, { once: true });
                }
                ok = false;
            } else {
                el.classList.remove('wiz-invalid');
                if (field) { const d = field.querySelector('.wiz-err-dynamic'); if (d) d.remove(); }
            }
        });
        return ok;
    }

    document.querySelectorAll('.wiz-input').forEach(function (el) {
        function clear() {
            el.classList.remove('wiz-invalid');
            const field = el.closest('.wiz-field');
            const d = field && field.querySelector('.wiz-err-dynamic');
            if (d) d.remove();
        }
        el.addEventListener('input',  clear);
        el.addEventListener('change', clear);
    });

    btnNext.addEventListener('click', function () { goTo(current + 1); });
    btnPrev.addEventListener('click', function () { goTo(current - 1, true); });

    steps.forEach(function (s, i) {
        s.addEventListener('click', function () { if (s.classList.contains('completed')) goTo(i + 1, true); });
        s.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && s.classList.contains('completed')) { e.preventDefault(); goTo(i + 1, true); }
        });
    });

    /* ── Submit guard: single combined consent checkbox ── */
    document.getElementById('applicationForm').addEventListener('submit', function (e) {
        const chk    = document.getElementById('ack_enrollment');
        const ackErr = document.getElementById('ackError');
        const hidAck = document.getElementById('acknowledgement');

        if (!chk) return;

        if (!chk.checked) {
            e.preventDefault();
            ackErr.classList.remove('d-none');
            ackErr.scrollIntoView({ behavior: 'smooth', block: 'center' });

            /* Shake the label */
            const lbl = chk.closest('.simple-ack-label') || chk.nextElementSibling;
            if (lbl) {
                lbl.classList.add('consent-check-shake');
                lbl.addEventListener('animationend', function () {
                    lbl.classList.remove('consent-check-shake');
                }, { once: true });
            }
            chk.addEventListener('change', function () {
                if (chk.checked) { hidAck.value = '1'; ackErr.classList.add('d-none'); }
            }, { once: true });
        } else {
            hidAck.value = '1';
            try { localStorage.removeItem(STORAGE_KEY); } catch (ex) {}
        }
    });

    /* ── Submit confirmation modal ── */
    const confirmModal = document.getElementById('confirmModal');
    const confirmSubmit = document.getElementById('confirmSubmit');
    const confirmCancel = document.getElementById('confirmCancel');

    if (btnSubmit && confirmModal) {
        btnSubmit.addEventListener('click', function (e) {
            e.preventDefault();
            const chk = document.getElementById('ack_enrollment');
            const ackErr = document.getElementById('ackError');
            const hidAck = document.getElementById('acknowledgement');

            if (!chk || !chk.checked) {
                e.preventDefault();
                if (ackErr) {
                    ackErr.classList.remove('d-none');
                    ackErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                const lbl = chk.closest('.simple-ack-label') || chk.nextElementSibling;
                if (lbl) {
                    lbl.classList.add('consent-check-shake');
                    lbl.addEventListener('animationend', function () {
                        lbl.classList.remove('consent-check-shake');
                    }, { once: true });
                }
                chk.addEventListener('change', function () {
                    if (chk.checked) { hidAck.value = '1'; if (ackErr) ackErr.classList.add('d-none'); }
                }, { once: true });
                return;
            }

            confirmModal.classList.add('active');
        });

        if (confirmSubmit) {
            confirmSubmit.addEventListener('click', function () {
                const form = document.getElementById('applicationForm');
                if (form) {
                    form.submit();
                }
            });
        }

        if (confirmCancel) {
            confirmCancel.addEventListener('click', function () {
                confirmModal.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) {
                confirmModal.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && confirmModal.classList.contains('active')) {
                confirmModal.classList.remove('active');
            }
        });
    }

    /* ── Privacy policy modal ── */
    const privacyModalEl = document.getElementById('privacyModal');
    const closePrivacyModalBtn = document.getElementById('closePrivacyModal');
    const openPrivacyModalLink = document.getElementById('openPrivacyModal');

    if (privacyModalEl) {
        // Open modal when clicking Privacy Policy link
        if (openPrivacyModalLink) {
            openPrivacyModalLink.addEventListener('click', function (e) {
                e.preventDefault();
                privacyModalEl.classList.add('active');
            });
        }

        // Close modal when clicking "I Understand"
        if (closePrivacyModalBtn) {
            closePrivacyModalBtn.addEventListener('click', function () {
                privacyModalEl.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        privacyModalEl.addEventListener('click', function (e) {
            if (e.target === privacyModalEl) {
                privacyModalEl.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && privacyModalEl.classList.contains('active')) {
                privacyModalEl.classList.remove('active');
            }
        });
    }

    /* ── Terms modal ── */
    const termsModalEl = document.getElementById('termsModal');
    const closeTermsModalBtn = document.getElementById('closeTermsModal');
    const openTermsModalLink = document.getElementById('openTermsModal');

    if (termsModalEl) {
        // Open modal when clicking Terms link
        if (openTermsModalLink) {
            openTermsModalLink.addEventListener('click', function (e) {
                e.preventDefault();
                termsModalEl.classList.add('active');
            });
        }

        // Close modal when clicking "I Understand"
        if (closeTermsModalBtn) {
            closeTermsModalBtn.addEventListener('click', function () {
                termsModalEl.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        termsModalEl.addEventListener('click', function (e) {
            if (e.target === termsModalEl) {
                termsModalEl.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && termsModalEl.classList.contains('active')) {
                termsModalEl.classList.remove('active');
            }
        });
    }


    /* ════════════════════════════════════════════════════════════
       FILE PICKER LABELS
    ════════════════════════════════════════════════════════════ */

    document.querySelectorAll('.document-input-file').forEach(function (input) {
        const wrap = input.closest('.document-input-control');
        const fn   = wrap && wrap.querySelector('.document-input-filename');
        input.addEventListener('change', function () {
            if (!fn) return;
            if (input.files && input.files.length) {
                fn.textContent = input.files[0].name;
                wrap.classList.add('has-file');
            } else {
                fn.textContent = fn.dataset.placeholder || 'No file chosen';
                wrap.classList.remove('has-file');
            }
        });
    });


    /* ════════════════════════════════════════════════════════════
       DOCUMENT TABS
    ════════════════════════════════════════════════════════════ */

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const t = btn.getAttribute('data-tab');
            tabBtns.forEach(function (b)  { b.classList.toggle('active', b === btn); });
            tabPanels.forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-tab-panel') === t); });
            if (window.lucide) lucide.createIcons();
        });
    });


    /* ════════════════════════════════════════════════════════════
       REQUIREMENTS CHECKLIST
    ════════════════════════════════════════════════════════════ */

    function loadChecks() { try { return JSON.parse(localStorage.getItem(CHECKS_KEY)) || {}; } catch (e) { return {}; } }
    function saveCheck(type, i, v) {
        const all = loadChecks();
        if (!all[type]) all[type] = {};
        all[type][i] = v;
        try { localStorage.setItem(CHECKS_KEY, JSON.stringify(all)); } catch (e) {}
    }
    function buildChecklist(type) {
        const reqs  = physicalRequirements[type] || [];
        const saved = (loadChecks()[type]) || {};
        physicalRequirementsList.innerHTML = '';
        if (!reqs.length) { physicalRequirementsCard.classList.add('d-none'); return; }
        physicalRequirementsCard.classList.remove('d-none');
        reqs.forEach(function (text, i) {
            const id  = 'req_' + type + '_' + i;
            const li  = document.createElement('li');
            const chk = document.createElement('input');
            chk.type = 'checkbox'; chk.className = 'wiz-req-checkbox'; chk.id = id; chk.checked = saved[i] === true;
            const lbl = document.createElement('label');
            lbl.className = 'wiz-req-label'; lbl.htmlFor = id;
            lbl.innerHTML = '<span class="wiz-req-box"></span><span class="wiz-req-text">' + text + '</span>';
            chk.addEventListener('change', function () { saveCheck(type, i, chk.checked); });
            li.appendChild(chk); li.appendChild(lbl);
            physicalRequirementsList.appendChild(li);
        });
    }


    /* ════════════════════════════════════════════════════════════
       APPLICANT-TYPE REACTIVE SECTIONS
    ════════════════════════════════════════════════════════════ */

    function updateApplicantType() {
        const t = applicantType.value;
        documentSections.forEach(function (s) { s.classList.toggle('d-none', s.getAttribute('data-type') !== t); });
        noDocumentNotice.classList.toggle('d-none', !!t);
        crossEnrolleeNotice.classList.toggle('d-none', t !== 'cross_enrollee');
        buildChecklist(t);
    }
    function updateMaritalStatus() {
        const m = maritalStatus.value === 'married';
        spouseField.classList.toggle('d-none', !m);
        marriageDocument.classList.toggle('d-none', !m);
        if (!m) document.getElementById('spouse_name').value = '';
    }
    function updateDisability() {
        pwdIdField.classList.toggle('d-none', disability.value.trim() === '');
    }

    applicantType.addEventListener('change', function () { updateApplicantType(); saveField(applicantType); });
    maritalStatus.addEventListener('change', function () { updateMaritalStatus(); saveField(maritalStatus); suggestHonorific(); });
    disability.addEventListener('input',    function () { updateDisability();    saveField(disability); });

    /* Auto-suggest honorific based on gender + marital status */
    function suggestHonorific() {
        const honorifSel = document.getElementById('honorific');
        if (!honorifSel || honorifSel.value) return; /* don't override user choice */
        const g = document.getElementById('gender')        ? document.getElementById('gender').value        : '';
        const m = document.getElementById('marital_status')? document.getElementById('marital_status').value: '';
        if (g === 'male')   { honorifSel.value = 'Mr.'; }
        else if (g === 'female') {
            honorifSel.value = (m === 'married') ? 'Mrs.' : 'Ms.';
        }
        if (honorifSel.value) saveField(honorifSel);
    }
    document.getElementById('gender')        && document.getElementById('gender').addEventListener('change',        function () { suggestHonorific(); saveField(document.getElementById('gender')); });
    document.getElementById('marital_status')&& document.getElementById('marital_status').addEventListener('change', suggestHonorific);


    /* ════════════════════════════════════════════════════════════
       PSGC API  — shared cache + fetch helper
    ════════════════════════════════════════════════════════════ */

    const _cache = {};
    function apiFetch(url) {
        if (_cache[url]) return Promise.resolve(_cache[url]);
        return fetch(url)
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) { _cache[url] = d; return d; });
    }


    /* ════════════════════════════════════════════════════════════
       SHARED CASCADE HELPERS
    ════════════════════════════════════════════════════════════ */

    function setLoading(sel, yes) {
        sel.disabled = yes;
        const w = sel.closest('.loc-select-wrap');
        if (w) w.classList.toggle('loc-loading', yes);
    }

    function resetSelect(sel, placeholder) {
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        sel.disabled  = true;
        const w = sel.closest('.loc-select-wrap');
        if (w) w.classList.remove('loc-loading');
    }

    function populateSelect(sel, items, valKey, lblKey) {
        sel.innerHTML = '<option value="">Select…</option>';
        items
            .slice()
            .sort(function (a, b) { return a[lblKey].localeCompare(b[lblKey]); })
            .forEach(function (item) {
                const o = document.createElement('option');
                o.value = item[valKey]; o.textContent = item[lblKey];
                sel.appendChild(o);
            });
        sel.disabled = false;
        const w = sel.closest('.loc-select-wrap');
        if (w) w.classList.remove('loc-loading');
    }

    function mirrorText(el, val) { if (el) el.value = val || ''; }

    /* ── Postal code auto-fill ──────────────────────────────────
       Priority: barangay postalCode → city postalCode → silent fail
       Both PSGC endpoints return a postalCode field.
       The barangay-level is more specific; city-level fires immediately
       when city is chosen (before the user picks a barangay).
    ──────────────────────────────────────────────────────────── */
    function flashPostal(code) {
        if (!code || !inpPostal) return;
        inpPostal.value = code;
        saveField(inpPostal);
        inpPostal.classList.add('loc-autofilled');
        setTimeout(function () { inpPostal.classList.remove('loc-autofilled'); }, 1600);
    }

    function applyPostalFromCity(cityCode) {
        if (!cityCode || !inpPostal) return;
        apiFetch(PSGC_BASE + '/cities-municipalities/' + cityCode)
            .then(function (data) {
                const code = data && (data.postalCode || data.postal_code || data.zipCode);
                if (code) flashPostal(code);
            })
            .catch(function () { /* silent */ });
    }

    function applyPostalFromBarangay(barangayCode) {
        if (!barangayCode || !inpPostal) return;
        apiFetch(PSGC_BASE + '/barangays/' + barangayCode)
            .then(function (data) {
                const code = data && (data.postalCode || data.postal_code || data.zipCode);
                if (code) flashPostal(code);
            })
            .catch(function () { /* silent */ });
    }


    /* ════════════════════════════════════════════════════════════
       CASCADE A — CURRENT ADDRESS  (Step 3)
       Region → Province → City/Municipality → Barangay
       Barangay selection triggers postal-code auto-fill from API
    ════════════════════════════════════════════════════════════ */

    function addrLoadRegions() {
        if (!selRegion) return;
        setLoading(selRegion, true);
        resetSelect(selProvince, 'Select province…');
        resetSelect(selCity,     'Select city / municipality…');
        resetSelect(selBarangay, 'Select barangay…');

        apiFetch(PSGC_BASE + '/regions')
            .then(function (data) {
                populateSelect(selRegion, data, 'code', 'name');
                const draft = loadDraft();
                if (draft._addr_region) {
                    selRegion.value = draft._addr_region;
                    if (selRegion.value) addrOnRegion(true);
                }
            })
            .catch(function () {
                selRegion.innerHTML = '<option value="">Could not load regions — try refreshing</option>';
                setLoading(selRegion, false);
            });
    }

    function addrOnRegion(silent) {
        const code = selRegion.value;
        const name = selRegion.options[selRegion.selectedIndex] ? selRegion.options[selRegion.selectedIndex].text : '';
        mirrorText(hidRegion, name);
        resetSelect(selProvince, 'Loading provinces…');
        resetSelect(selCity,     'Select city / municipality…');
        resetSelect(selBarangay, 'Select barangay…');
        mirrorText(hidProvince, ''); mirrorText(hidCity, ''); mirrorText(hidBarangay, '');
        if (!silent) { const d = loadDraft(); d._addr_region = code; saveDraft(d); }
        if (!code) { resetSelect(selProvince, 'Select province…'); updateAddressSummary(); return; }

        setLoading(selProvince, true);
        apiFetch(PSGC_BASE + '/regions/' + code + '/provinces')
            .then(function (data) {
                if (!data || !data.length) {
                    return apiFetch(PSGC_BASE + '/regions/' + code + '/cities-municipalities')
                        .then(function (cities) {
                            selProvince.innerHTML = '<option value="__none__">— No province —</option>';
                            selProvince.disabled = false;
                            selProvince.closest('.loc-select-wrap').classList.remove('loc-loading');
                            mirrorText(hidProvince, '—');
                            populateSelect(selCity, cities, 'code', 'name');
                            const draft = loadDraft();
                            if (draft._addr_city) { selCity.value = draft._addr_city; if (selCity.value) addrOnCity(true); }
                        });
                }
                populateSelect(selProvince, data, 'code', 'name');
                const draft = loadDraft();
                if (draft._addr_province) { selProvince.value = draft._addr_province; if (selProvince.value) addrOnProvince(true); }
            })
            .catch(function () { resetSelect(selProvince, 'Could not load provinces'); });
    }

    function addrOnProvince(silent) {
        const code = selProvince.value;
        const name = selProvince.options[selProvince.selectedIndex] ? selProvince.options[selProvince.selectedIndex].text : '';
        mirrorText(hidProvince, name);
        resetSelect(selCity, 'Loading cities…'); resetSelect(selBarangay, 'Select barangay…');
        mirrorText(hidCity, ''); mirrorText(hidBarangay, '');
        if (!silent) { const d = loadDraft(); d._addr_province = code; saveDraft(d); }
        if (!code || code === '__none__') { resetSelect(selCity, 'Select city / municipality…'); return; }

        setLoading(selCity, true);
        apiFetch(PSGC_BASE + '/provinces/' + code + '/cities-municipalities')
            .then(function (data) {
                populateSelect(selCity, data, 'code', 'name');
                const draft = loadDraft();
                if (draft._addr_city) { selCity.value = draft._addr_city; if (selCity.value) addrOnCity(true); }
            })
            .catch(function () { resetSelect(selCity, 'Could not load cities'); });
    }

    function addrOnCity(silent) {
        const code = selCity.value;
        const name = selCity.options[selCity.selectedIndex] ? selCity.options[selCity.selectedIndex].text : '';
        mirrorText(hidCity, name);
        resetSelect(selBarangay, 'Loading barangays…'); mirrorText(hidBarangay, '');
        if (!silent) { const d = loadDraft(); d._addr_city = code; saveDraft(d); }
        updateAddressSummary();
        /* Fill postal from city immediately; barangay selection will refine it */
        if (code) applyPostalFromCity(code);
        if (!code) { resetSelect(selBarangay, 'Select barangay…'); return; }

        setLoading(selBarangay, true);
        apiFetch(PSGC_BASE + '/cities-municipalities/' + code + '/barangays')
            .then(function (data) {
                populateSelect(selBarangay, data, 'code', 'name');
                const draft = loadDraft();
                if (draft._addr_barangay) { selBarangay.value = draft._addr_barangay; if (selBarangay.value) addrOnBarangay(true); }
            })
            .catch(function () { resetSelect(selBarangay, 'Could not load barangays'); });
    }

    function addrOnBarangay(silent) {
        const code = selBarangay.value;
        const name = selBarangay.options[selBarangay.selectedIndex] ? selBarangay.options[selBarangay.selectedIndex].text : '';
        mirrorText(hidBarangay, name);
        if (!silent) { const d = loadDraft(); d._addr_barangay = code; saveDraft(d); }
        updateAddressSummary();
        /* Fetch postal code from barangay endpoint */
        if (code) applyPostalFromBarangay(code);
    }

    /* Address summary chip */
    function updateAddressSummary() {
        const el  = document.getElementById('locSummary');
        const txt = document.getElementById('locSummaryText');
        if (!el || !txt) return;
        const parts = [
            hidBarangay && hidBarangay.value ? 'Brgy. ' + hidBarangay.value : '',
            hidCity     && hidCity.value     ? hidCity.value     : '',
            hidProvince && hidProvince.value && hidProvince.value !== '—' ? hidProvince.value : '',
            hidRegion   && hidRegion.value   ? hidRegion.value   : '',
        ].filter(Boolean);
        if (parts.length >= 2) {
            txt.textContent = parts.join(', ');
            el.classList.add('visible');
            if (window.lucide) lucide.createIcons({ nodes: [el] });
        } else {
            el.classList.remove('visible');
        }
    }

    if (selRegion) {
        selRegion.addEventListener('change',   function () { addrOnRegion(false); });
        selProvince.addEventListener('change', function () { addrOnProvince(false); });
        selCity.addEventListener('change',     function () { addrOnCity(false); });
        selBarangay.addEventListener('change', function () { addrOnBarangay(false); });
        addrLoadRegions();
    }



    /* ════════════════════════════════════════════════════════════
       LOCALSTORAGE PERSISTENCE
    ════════════════════════════════════════════════════════════ */

    /* Location select names are persisted manually above; exclude from generic loop */
    const LOC_NAMES = ['_addr_region','_addr_province','_addr_city','_addr_barangay'];

    const PERSIST_SELECTOR = [
        'input[type="text"]',
        'input[type="email"]',
        'input[type="date"]',
        'input[type="tel"]',
        'select',
        'textarea',
    ].join(', ');

    function loadDraft() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch (e) { return {}; } }
    function saveDraft(d) { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(d)); } catch (e) {} }
    function saveField(el) {
        if (!el || !el.name) return;
        const d = loadDraft(); d[el.name] = el.value; saveDraft(d);
    }

    function restoreDraft() {
        const draft = loadDraft();
        if (!Object.keys(draft).length) return;
        document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
            if (LOC_NAMES.indexOf(el.name) !== -1) return;   /* handled by cascade */
            if (!el.name || el.value !== '') return;
            if (draft[el.name] !== undefined) el.value = draft[el.name];
        });
    }

    document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
        if (LOC_NAMES.indexOf(el.name) !== -1) return;
        el.addEventListener('change', function () { saveField(el); });
        if (el.tagName === 'INPUT' && el.type !== 'date') {
            el.addEventListener('input', function () { saveField(el); });
        }
    });

    restoreDraft();
    updateApplicantType();
    updateMaritalStatus();
    updateDisability();

    window._wizGoTo = goTo;



    /* ════════════════════════════════════════════════════════════
       STEP 6 — REVIEW PANEL
       Reads every named field from the form and renders a
       read-only summary so the applicant can verify before submitting.
    ════════════════════════════════════════════════════════════ */

    function buildReview() {
        const form = document.getElementById('applicationForm');
        if (!form) return;

        /* Helper: get display value for a field by name */
        function val(name) {
            const el = form.querySelector('[name="' + name + '"]:not([type="hidden"])') ||
                       form.querySelector('[name="' + name + '"]');
            if (!el) return '—';
            if (el.tagName === 'SELECT') {
                const opt = el.options[el.selectedIndex];
                return (opt && opt.text && opt.value) ? opt.text : '—';
            }
            return el.value.trim() || '—';
        }

        /* Helper: get program name from select */
        function programName() {
            const sel = document.getElementById('program_id');
            if (!sel) return '—';
            const opt = sel.options[sel.selectedIndex];
            return (opt && opt.value) ? opt.text : '—';
        }

        /* Helper: assemble full address from hidden fields + plain fields */
        function fullAddress() {
            const street   = val('current_address');
            const barangay = document.getElementById('barangay_text') ? document.getElementById('barangay_text').value.trim() : '';
            const city     = document.getElementById('city_text')     ? document.getElementById('city_text').value.trim()     : '';
            const province = document.getElementById('province_text') ? document.getElementById('province_text').value.trim() : '';
            const region   = document.getElementById('region_text')   ? document.getElementById('region_text').value.trim()   : '';
            const postal   = val('postal_code');
            const country  = val('country');

            const parts = [street, barangay ? 'Brgy. ' + barangay : '', city, province, region].filter(function(p){ return p && p !== '—'; });
            let addr = parts.join(', ');
            if (postal && postal !== '—') addr += ' ' + postal;
            if (country && country !== '—') addr += ', ' + country;
            return addr || '—';
        }

        /* Section builder */
        function section(title, icon, rows) {
            const filled = rows.filter(function(r){ return r[1] && r[1] !== '—'; });
            const html = rows.map(function(r) {
                const empty = !r[1] || r[1] === '—';
                return '<div class="rev-row' + (empty ? ' rev-row--empty' : '') + '">' +
                    '<span class="rev-label">' + r[0] + '</span>' +
                    '<span class="rev-value">' + (empty ? '<em>Not provided</em>' : escHtml(r[1])) + '</span>' +
                '</div>';
            }).join('');
            return '<div class="rev-section">' +
                '<div class="rev-section-head">' +
                    '<i data-lucide="' + icon + '"></i>' +
                    '<span>' + title + '</span>' +
                    '<span class="rev-badge">' + filled.length + ' / ' + rows.length + '</span>' +
                '</div>' +
                '<div class="rev-section-body">' + html + '</div>' +
            '</div>';
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        /* Applicant type label map */
        const typeLabels = {
            new_student:'New Student / First Year', transferee:'Transferee',
            shiftee:'Shiftee', returnee:'Returnee / Readmitted', cross_enrollee:'Cross-Enrollee'
        };
        const rawType = val('academic_status');
        const typeLabel = typeLabels[rawType] || rawType;

        /* Assemble display full name */
        function fullName() {
            const honorific = val('honorific') !== '—' ? val('honorific') : '';
            const first  = val('first_name')  !== '—' ? val('first_name')  : '';
            const middle = val('middle_name') !== '—' ? val('middle_name') : '';
            const last   = val('last_name')   !== '—' ? val('last_name')   : '';
            const suffix = val('suffix')      !== '—' ? val('suffix')      : '';
            const mi = middle ? middle.charAt(0).toUpperCase() + '.' : '';
            const name = [first, mi, last].filter(Boolean).join(' ');
            return [honorific, name, suffix].filter(Boolean).join(' ') || '—';
        }

        const html =
            section('Program & Applicant Type', 'graduation-cap', [
                ['Applicant Type',   typeLabel],
                ['Intended Program', programName()],
            ]) +
            section('Personal Information', 'user', [
                ['Full Name',      fullName()],
                ['Gender',         val('gender')],
                ['Date of Birth',  val('birthdate')],
                ['Age',            (function() {
                    const dob = val('birthdate');
                    if (!dob || dob === '—') return '—';
                    const today = new Date();
                    const birth = new Date(dob);
                    let age = today.getFullYear() - birth.getFullYear();
                    const m = today.getMonth() - birth.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                    return age > 0 ? age + ' years old' : '—';
                })()],
                ['Place of Birth', val('birth_place')],
                ['Religion',       val('religion')],
                ['Nationality',    val('nationality')],
                ['LRN',            val('lrn')],
                ['Marital Status', val('marital_status')],
                ['Spouse Name',    val('spouse_name')],
            ]) +
            section('Contact & Address', 'map-pin', [
                ['Email',    val('email')],
                ['Phone',    val('phone')],
                ['Address',  fullAddress()],
            ]) +
            section('Family Information', 'users', [
                ["Father's Name",       val('father_name')],
                ["Father's Occupation", val('father_occupation')],
                ["Mother's Name",       val('mother_name')],
                ["Mother's Occupation", val('mother_occupation')],
                ["Parent's Address",    val('parent_address')],
                ["Parent's Contact",    val('parent_contact')],
                ['Occupation',          val('occupation')],
                ['Disability',          val('disability')],
                ['PWD ID',              val('pwd_id')],
            ]);

        const container = document.getElementById('reviewSummary');
        if (container) {
            container.innerHTML = html;
            if (window.lucide) lucide.createIcons({ nodes: [container] });
        }
    }

});