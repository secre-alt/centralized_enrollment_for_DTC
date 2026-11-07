/**
 * DTC EMS — Application Wizard
 *
 * Features:
 *  - Multi-step wizard navigation with per-step validation
 *  - localStorage persistence: form data survives page reload
 *  - Tab switching on Step 5 (Upload vs Requirements Checklist)
 *  - Interactive requirements checklist (checkboxes, not bullets)
 *  - Browser-assisted address entry with draft persistence
 *  - Optional free-text religion field
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ════════════════════════════════════════════════════════════
       CONFIG
    ════════════════════════════════════════════════════════════ */

    const TOTAL       = document.querySelectorAll('#applicationForm > .wiz-panel').length;
    const STORAGE_KEY = 'dtc_application_draft';
    const CHECKS_KEY  = 'dtc_req_checks';
    let   current     = 1;


    /* ════════════════════════════════════════════════════════════
       ELEMENTS — WIZARD
    ════════════════════════════════════════════════════════════ */

    const panels     = document.querySelectorAll('.wiz-panel');
    const steps      = document.querySelectorAll('.wiz-step');
    const connectors = document.querySelectorAll('.wiz-connector');
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
    const educationSections        = document.querySelectorAll('.education-section');
    const noDocumentNotice         = document.getElementById('noDocumentNotice');
    const marriageDocument         = document.getElementById('marriageDocument');
    const spouseField              = document.getElementById('spouseField');
    const pwdIdField               = document.getElementById('pwdIdField');
    const crossEnrolleeNotice      = document.getElementById('crossEnrolleeNotice');
    const physicalRequirementsCard = document.getElementById('physicalRequirementsCard');
    const physicalRequirementsList = document.getElementById('physicalRequirementsList');

    /* Step 3 — current address cascade */
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
            if (i + 1 === current) s.setAttribute('aria-current', 'step');
            else s.removeAttribute('aria-current');
            s.disabled = false;
        });
        connectors.forEach(function (c, i) { c.classList.toggle('completed', i + 1 < current); });

        const stepCount = document.getElementById('wizardStepCount');
        const sectionName = document.getElementById('wizardSectionName');
        if (stepCount) stepCount.textContent = 'Step ' + current + ' of ' + TOTAL;
        if (sectionName) sectionName.textContent = steps[current - 1].querySelector('.wiz-step-label').textContent.trim();

        btnPrev.style.visibility = current === 1 ? 'hidden' : 'visible';

        if (current === TOTAL) {
            btnNext.classList.add('d-none');
            btnSubmit.classList.remove('d-none');
        } else {
            btnNext.classList.remove('d-none');
            btnSubmit.classList.add('d-none');
        }
        if (current === TOTAL) buildReview();

        /* Rebuild each icon before Lucide renders it. Reusing a rendered SVG can
           leave the previous checkmark paths in place when returning to a step. */
        steps.forEach(function (s) {
            var dot = s.querySelector('.wiz-step-dot');
            if (!dot) return;
            var iconEl = document.createElement('i');
            var iconName = s.classList.contains('completed')
                ? 'check'
                : s.getAttribute('data-original-icon');
            if (!iconName) return;
            iconEl.setAttribute('data-lucide', iconName);
            iconEl.setAttribute('aria-hidden', 'true');
            dot.replaceChildren(iconEl);
        });

        if (window.lucide) lucide.createIcons();
        const heading = document.querySelector('#step-' + current + ' .wiz-panel-title');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    }


    /* ════════════════════════════════════════════════════════════
       VALIDATION
    ════════════════════════════════════════════════════════════ */

    function validateStep(n) {
        const panel = document.getElementById('step-' + n);
        if (!panel) return true;
        let firstInvalid = null;
        panel.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden' || el.disabled || el.closest('.d-none')) return;
            const message = validationMessage(el);
            if (message) {
                showFieldError(el, message);
                if (!firstInvalid) firstInvalid = el;
            } else clearFieldError(el);
        });
        if (firstInvalid) {
            revealInvalidField(firstInvalid);
            setTimeout(function () {
                firstInvalid.focus();
                if (firstInvalid.scrollIntoView) firstInvalid.scrollIntoView({ block: 'center', behavior: 'auto' });
            }, 0);
        }
        return !firstInvalid;
    }

    function validationMessage(el) {
        const rules = window.dtcAdmissionValidation || {};
        let value = (el.value || '').trim();
        const name = el.name || el.id;
        if ((rules.nameFields || []).includes(name)) value = value.replace(/\s+/gu, ' ');
        if (el.validity && el.validity.badInput) return 'Enter a complete, valid value.';
        if (el.required && !value) {
            return name === 'school_year_completed'
                ? 'Please enter your last school year attended.'
                : 'This field is required.';
        }
        if (el.type === 'file') {
            const file = el.files && el.files[0];
            if (!file) return '';
            if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) return 'Choose a PDF, JPG, JPEG, or PNG file.';
            if (file.size === 0) return 'Choose a non-empty document.';
            if (file.size > (rules.maxUploadBytes || 5242880)) return 'Each document must be ' + (rules.maxUploadLabel || '5 MB') + ' or smaller.';
            if (rules.maxPostBytes) {
                const files = Array.from(document.querySelectorAll('#applicationForm input[type="file"]')).filter(function (input) { return !input.disabled && !input.closest('.d-none'); });
                const total = files.reduce(function (bytes, input) { return bytes + (input.files && input.files[0] ? input.files[0].size : 0); }, 0);
                if (total + 65536 > rules.maxPostBytes) return 'Combined uploads are too large for this server. Choose smaller files.';
            }
            return '';
        }
        if (!value) return '';
        if (el.maxLength > 0 && Array.from(value).length > el.maxLength) return 'Use ' + el.maxLength + ' characters or fewer.';
        if ((rules.nameFields || []).includes(name) && !new RegExp(rules.namePattern, 'u').test(value)) {
            return 'Enter a name using letters, spaces, apostrophes, periods, or hyphens.';
        }
        if ((rules.phoneFields || []).includes(name)) {
            if (!new RegExp(rules.phoneCharacters).test(value) || !new RegExp(rules.phonePattern).test(value.replace(/[ ().-]/g, ''))) {
                return 'Use 09XXXXXXXXX, +639XXXXXXXXX, a landline with area code, or an international number starting with +.';
            }
        }
        if (name === 'email' && el.validity && el.validity.typeMismatch) return 'Enter a valid email address, such as name@example.com.';
        if (name === 'birthdate') {
            const date = new Date(value + 'T00:00:00Z');
            if (!/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(value) || !Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== value) return 'Enter a valid date of birth.';
            if (value > rules.today) return 'Enter a date of birth that is today or earlier.';
            if (value > rules.latestBirthdate) return 'Applicants must be at least ' + rules.minimumAge + ' years old.';
            if (value < rules.earliestBirthdate) return 'Enter a date of birth for an applicant aged ' + rules.maximumAge + ' or younger.';
        }
        if (name === 'country' && rules.countries && !rules.countries.some(country => country.toLowerCase() === value.toLowerCase()) && !Object.prototype.hasOwnProperty.call(rules.countryAliases || {}, value.toLowerCase())) return 'Choose a country from the suggestions.';
        if (name === 'lrn' && !/^[0-9]{12}$/.test(value)) return 'Enter exactly 12 digits for the LRN.';
        if (name === 'postal_code') {
            const country = document.getElementById('country');
            const philippines = !country || !country.value.trim() || country.value.trim().toLowerCase() === 'philippines';
            if (philippines && !new RegExp(rules.philippinePostalPattern || '^(?!0000$)[0-9]{4}$').test(value)) return 'Enter a 4-digit Philippine postal code other than 0000.';
            if (!philippines && rules.internationalPostalPattern && !new RegExp(rules.internationalPostalPattern).test(value)) return 'Use up to 20 letters or digits, with single spaces or hyphens between groups.';
        }
        if (name === 'school_year_completed' && rules.yearPattern) {
            if (!new RegExp(rules.yearPattern, 'u').test(value)) return 'Enter a school year in YYYY-YYYY format.';
            const years = value.split('-').map(Number);
            if (years[1] !== years[0] + 1) return 'The ending year must be one year after the starting year.';
            if (years[0] > rules.schoolYearMaxStart) return 'Enter a current or previous school year.';
        }
        return '';
    }

    function revealInvalidField(el) {
        if (el.closest('#personalDetailsContent')) setPersonalDetailsExpanded(true);
        const details = el.closest('details');
        if (details) details.open = true;
        if (el.type === 'file') document.querySelector('.doc-tab-btn[data-tab="uploads"]')?.click();
    }

    function showFieldError(el, message) {
        el.classList.add('wiz-invalid');
        el.setAttribute('aria-invalid', 'true');
        const field = el.closest('.wiz-field') || el.closest('.document-input');
        if (!field) return;
        let error = field.querySelector('.wiz-err-msg');
        if (!error) {
            error = document.createElement('span');
            error.className = 'wiz-err-msg wiz-err-dynamic';
            field.appendChild(error);
        }
        error.textContent = message;
        error.id = (el.id || el.name) + '-error';
        const descriptions = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (!descriptions.includes(error.id)) descriptions.push(error.id);
        el.setAttribute('aria-describedby', descriptions.join(' '));
        if (el.type === 'file') el.closest('.document-input-control')?.classList.add('is-invalid');
    }

    function clearFieldError(el) {
        el.classList.remove('wiz-invalid');
        el.removeAttribute('aria-invalid');
        const field = el.closest('.wiz-field') || el.closest('.document-input');
        const error = field && field.querySelector('.wiz-err-msg');
        if (error) {
            const descriptions = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (id) { return id && id !== error.id; });
            if (descriptions.length) el.setAttribute('aria-describedby', descriptions.join(' '));
            else el.removeAttribute('aria-describedby');
            error.remove();
        }
        if (el.type === 'file') el.closest('.document-input-control')?.classList.remove('is-invalid');
    }

    document.querySelectorAll('.wiz-input, .document-input-file').forEach(function (el) {
        if (el.type !== 'file' && el.maxLength < 0) el.maxLength = 255;
        const field = el.closest('.wiz-field');
        const error = field && field.querySelector('.wiz-err-msg');
        if (error) {
            error.id = (el.id || el.name) + '-error';
            el.setAttribute('aria-invalid', 'true');
            const descriptions = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (!descriptions.includes(error.id)) descriptions.push(error.id);
            el.setAttribute('aria-describedby', descriptions.join(' '));
        }
        function clear() {
            if (el.name === 'school_year_completed') {
                const message = validationMessage(el);
                if (message) showFieldError(el, message);
                else clearFieldError(el);
                return;
            }
            clearFieldError(el);
        }
        el.addEventListener('input',  clear);
        function check() {
            if (el.disabled || el.closest('.d-none')) return;
            const message = validationMessage(el);
            if (message) showFieldError(el, message);
            else clearFieldError(el);
        }
        el.addEventListener('change', check);
        el.addEventListener('blur', check);
    });

    btnNext.addEventListener('click', function () { goTo(current + 1); });
    btnPrev.addEventListener('click', function () { goTo(current - 1, true); });

    steps.forEach(function (s, i) {
        /* Store the original icon name so goTo() can restore it when going back */
        var origI = s.querySelector('.wiz-step-dot [data-lucide]');
        if (origI) s.setAttribute('data-original-icon', origI.getAttribute('data-lucide'));

        s.addEventListener('click', function () {
            const target = i + 1;
            if (target === current) return;
            if (target > current) {
                for (let step = 1; step < target; step++) {
                    if (!validateStep(step)) { goTo(step, true); return; }
                }
            }
            goTo(target, true);
        });
    });

    const personalDetailsToggle = document.getElementById('personalDetailsToggle');
    const personalDetailsContent = document.getElementById('personalDetailsContent');
    function setPersonalDetailsExpanded(expanded) {
        if (!personalDetailsToggle || !personalDetailsContent) return;
        personalDetailsToggle.setAttribute('aria-expanded', String(expanded));
        personalDetailsContent.hidden = !expanded;
    }
    if (personalDetailsToggle && personalDetailsContent) {
        personalDetailsToggle.addEventListener('click', function () {
            setPersonalDetailsExpanded(personalDetailsToggle.getAttribute('aria-expanded') !== 'true');
        });
    }

    /* ── Continue opens confirmation; acknowledgement is sent only on final submission ── */
    let submissionInProgress = false;
    document.getElementById('applicationForm').addEventListener('submit', function (event) {
        event.preventDefault();
        if (submissionInProgress) return;
        if (current < TOTAL) goTo(current + 1);
        else btnSubmit.click();
    });

    if (btnSubmit) {
        btnSubmit.addEventListener('click', function () {
            if (submissionInProgress) return;
            for (let step = 1; step < TOTAL; step++) {
                if (!validateStep(step)) { goTo(step, true); return; }
            }
            if (window.dtcConfirm) {
                window.dtcConfirm.open({
                    trigger: btnSubmit,
                    tone: 'neutral',
                    title: 'Submit application?',
                    message: 'Your application will be sent for review. Admission is not guaranteed.',
                    okLabel: 'Submit',
                    onConfirm: function () {
                        if (submissionInProgress) return;
                        const form = document.getElementById('applicationForm');
                        if (!form) return;
                        submissionInProgress = true;
                        document.getElementById('ack_enrollment').value = '1';
                        btnSubmit.disabled = true;
                        btnSubmit.setAttribute('aria-busy', 'true');
                        try { sessionStorage.setItem('dtc_pending_application_draft', localStorage.getItem(STORAGE_KEY) || ''); } catch (ex) {}
                        form.submit();
                    }
                });
            }
        });
    }

    /* Legal dialogs: keyboard containment and focus restoration. */
    const privacyModalEl = document.getElementById('privacyModal');
    const termsModalEl = document.getElementById('termsModal');
    function bindLegalDialog(overlay, openerId, closerId) {
        const opener = document.getElementById(openerId);
        const closer = document.getElementById(closerId);
        if (!overlay || !opener || !closer) return;
        const dialog = overlay.querySelector('[role="dialog"]');
        let returnFocus;
        let background = [];
        function close() {
            overlay.classList.remove('active');
            background.forEach(function (item) { item.node.inert = item.inert; });
            background = [];
            if (returnFocus && returnFocus.isConnected) returnFocus.focus();
        }
        opener.addEventListener('click', function (event) {
            event.preventDefault();
            returnFocus = document.activeElement;
            for (let node = overlay; node && node !== document.body; node = node.parentElement) {
                Array.from(node.parentElement.children).forEach(function (sibling) {
                    if (sibling === node || /^(SCRIPT|STYLE)$/.test(sibling.tagName)) return;
                    background.push({ node: sibling, inert: sibling.inert });
                    sibling.inert = true;
                });
            }
            overlay.classList.add('active');
            dialog.focus();
        });
        closer.addEventListener('click', close);
        overlay.addEventListener('click', function (event) { if (event.target === overlay) close(); });
        overlay.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]'))
                .filter(function (node) { return node.getClientRects().length; });
            const first = focusable[0] || dialog;
            const last = focusable[focusable.length - 1] || dialog;
            if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog)) {
                event.preventDefault(); first.focus();
            }
        });
    }
    bindLegalDialog(privacyModalEl, 'openPrivacyModal', 'closePrivacyModal');
    bindLegalDialog(termsModalEl, 'openTermsModal', 'closeTermsModal');

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

    // Reuse stored field names so existing applications and drafts remain compatible.
    const educationProfiles = {
        new_student: {
            title: 'Senior High School Background',
            description: 'Enter your most recent school and completion status.',
            school: 'Senior High School Name', year: 'School Year Graduated or Last Attended',
            program: 'Previous Degree Program', level: 'Last Year Level',
            example: 'e.g. Danao National High School'
        },
        transferee: {
            title: 'Previous College Information',
            description: 'Enter your previous college, program, and year level.',
            school: 'Previous College or University', year: 'Last School Year Attended',
            program: 'Previous Degree Program', level: 'Last Year Level',
            example: 'e.g. Cebu Normal University'
        },
        shiftee: {
            title: 'Current Academic Information',
            description: 'Enter the program you are shifting from.',
            school: 'Current College or University', year: 'Last School Year Attended',
            program: 'Current or Previous Degree Program', level: 'Current Year Level',
            example: 'Enter your current institution'
        },
        returnee: {
            title: 'Previous Enrollment Information',
            description: 'Enter your previous program and last school year attended.',
            school: 'College or University Last Attended', year: 'Last School Year Attended',
            program: 'Previous Degree Program', level: 'Last Year Level and Semester Attended',
            example: 'Enter the institution where you were last enrolled'
        },
        cross_enrollee: {
            title: 'Home Institution Information',
            description: 'Enter your home institution and current program.',
            school: 'Most Recent School Attended', year: 'Last School Year Attended',
            program: 'Current Degree Program', level: 'Current Year Level',
            example: 'Enter your most recent school'
        }
    };

    function updateEducation(type) {
        updateEducationLevels(type);
        const profile = educationProfiles[type];
        if (!profile) return;
        document.getElementById('educationHeading').textContent = profile.title;
        document.getElementById('educationDescription').textContent = profile.description;
        const labels = {
            most_recent_school: profile.school,
            school_year_completed: 'Last School Year Attended',
            previous_program: profile.program,
            last_year_level: profile.level,
            education_status: type === 'transferee' ? 'Enrollment or Completion Status' : 'Completion Status'
        };
        Object.keys(labels).forEach(function (name) {
            document.querySelector('#step-4 label[for="' + name + '"]').textContent = labels[name];
        });
        document.getElementById('most_recent_school').placeholder = profile.example;
        document.getElementById('school_year_completed').placeholder = 'e.g. 2025-2026';
        document.getElementById('previous_program').placeholder = 'e.g. BS Information Systems';
        document.getElementById('last_year_level').placeholder = type === 'returnee'
            ? 'e.g. 2nd year, 1st semester' : 'e.g. 2nd year';
    }

    function updateEducationLevels(type) {
        const select = document.getElementById('education_level');
        if (!select) return;
        const allowed = (window.dtcApplicationEducationLevels || {})[type] || [];
        const previous = select.value;
        select.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = type ? 'Select education level' : 'Choose applicant type first';
        select.appendChild(placeholder);
        allowed.forEach(function (level) {
            const option = document.createElement('option');
            option.value = level;
            option.textContent = level === 'Other' ? 'Other / equivalent qualification' : level;
            select.appendChild(option);
        });
        select.disabled = allowed.length === 0;
        select.value = allowed.includes(previous) ? previous : '';
        const hint = document.getElementById('educationLevelHint');
        if (hint) hint.textContent = previous && !allowed.includes(previous)
            ? 'Applicant type changed. Choose an education level again.'
            : type === 'new_student' ? 'Other includes equivalent qualifications.'
                : allowed.length ? 'Current or previous college level.' : 'Choose your applicant type in Admission first.';
        if (previous !== select.value) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function updateApplicantType() {
        const t = applicantType.value;
        updateEducation(t);
        const requiredEducation = ((window.dtcAdmissionValidation || {}).requiredEducation || {})[t] || [];
        document.querySelectorAll('#step-4 input, #step-4 select').forEach(function (field) {
            field.required = requiredEducation.includes(field.name);
            const label = document.querySelector('#step-4 label[for="' + field.id + '"]');
            if (label) {
                label.querySelector('.req')?.remove();
                if (field.required) {
                    const star = document.createElement('span'); star.className = 'req'; star.textContent = ' *'; label.appendChild(star);
                }
            }
        });
        documentSections.forEach(function (s) {
            const visible = s.getAttribute('data-type') === t;
            s.classList.toggle('d-none', !visible);
            s.querySelectorAll('input[type="file"]').forEach(function (field) { field.disabled = !visible; });
        });
        noDocumentNotice.classList.toggle('d-none', !!t);
        crossEnrolleeNotice.classList.toggle('d-none', t !== 'cross_enrollee');
        educationSections.forEach(function (section) {
            const types = (section.getAttribute('data-education-for') || '').split(',').map(function (value) { return value.trim(); });
            const visible = types.indexOf(t) !== -1;
            section.classList.toggle('d-none', !visible);
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !visible;
            });
        });
        buildChecklist(t);
    }
    function updateMaritalStatus() {
        const m = maritalStatus.value === 'married';
        spouseField.classList.toggle('d-none', !m);
        marriageDocument.classList.toggle('d-none', !m);
        marriageDocument.querySelectorAll('input[type="file"]').forEach(function (field) { field.disabled = !m; });
        if (!m) {
            const spouse = document.getElementById('spouse_name');
            spouse.value = '';
            saveField(spouse);
        }
    }
    function updateDisability() {
        pwdIdField.classList.toggle('d-none', disability.value.trim() === '');
        if (disability.value.trim() === '') {
            const pwd = document.getElementById('pwd_id');
            pwd.value = '';
            saveField(pwd);
        }
    }

    applicantType.addEventListener('change', function () { updateApplicantType(); saveField(applicantType); });
    maritalStatus.addEventListener('change', function () { updateMaritalStatus(); saveField(maritalStatus); });
    disability.addEventListener('input',    function () { updateDisability();    saveField(disability); });

    document.getElementById('gender') && document.getElementById('gender').addEventListener('change', function () { saveField(document.getElementById('gender')); });


    /* Legacy PSGC codes remain blank; browser-autofilled address text uses normal draft persistence. */
    const LOC_NAMES = ['region_code','province_code','city_code'];

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
            if (el.name === 'country' && el.dataset.hasOldInput === 'true') return;
            if (LOC_NAMES.indexOf(el.name) !== -1) return;   /* handled by cascade */
            // Build the allowed options before assigning a saved select value.
            if (el.name === 'education_level') updateEducationLevels(applicantType.value);
            // Preserve server-returned optional values, including an intentional blank.
            if (personalDetailsContent && personalDetailsContent.contains(el)
                && personalDetailsContent.dataset.hasOldInput === 'true') return;
            if (!el.name || (el.value !== '' && el.value !== el.dataset.draftDefault)) return;
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



    const guardianDetails = document.getElementById('guardianDetails');
    if (guardianDetails && Array.from(guardianDetails.querySelectorAll('input')).some(function (field) { return field.value.trim() !== ''; })) {
        guardianDetails.open = true;
    }

    const countryInput = document.getElementById('country');
    const internationalField = document.getElementById('internationalLocationField');
    function updateInternationalAddress() {
        if (!internationalField) return;
        const country = countryInput ? countryInput.value.trim().toLocaleLowerCase() : '';
        internationalField.hidden = country === '' || country === 'philippines';
    }
    if (countryInput) countryInput.addEventListener('input', updateInternationalAddress);
    updateInternationalAddress();
    const TITLE_CASE_FIELD_SET = new Set(window.dtcApplicationTitleCaseFields || []);
    ['honorific', 'suffix'].forEach(function (name) {
        const input = document.getElementById(name);
        if (!input) return;
        function formatAffix() {
            input.value = window.dtcNormalizeName.affix(input.value, name);
            saveField(input);
        }
        input.addEventListener('blur', formatAffix);
        input.addEventListener('change', formatAffix);
        formatAffix();
    });
    TITLE_CASE_FIELD_SET.forEach(function (name) {
        const input = document.querySelector('[name="' + name + '"]');
        if (!input) return;
        input.addEventListener('blur', function () {
            input.value = normalizeName(input.value);
            saveField(input);
        });
    });
    updateApplicantType();
    updateMaritalStatus();
    updateDisability();

    if (personalDetailsContent) {
        const hasPersonalDetails = Array.from(personalDetailsContent.querySelectorAll('input, select')).some(function (field) {
            const value = field.value.trim();
            return value !== '';
        });
        if (hasPersonalDetails) setPersonalDetailsExpanded(true);
    }

    window._wizGoTo = goTo;



    /* ════════════════════════════════════════════════════════════
       STEP 6 — REVIEW PANEL
       Reads every named field from the form and renders a
       read-only summary so the applicant can verify before submitting.
    ════════════════════════════════════════════════════════════ */

    function normalizeName(value) {
        return window.dtcNormalizeName(value);
    }

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
            const value = el.value.trim();
            if (!value) return '—';
            if (name === 'honorific' || name === 'suffix') return window.dtcNormalizeName.affix(value, name);
            return TITLE_CASE_FIELD_SET.has(name) ? normalizeName(value) : value;
        }

        function nameVal(name) {
            const value = val(name);
            return value === '—' ? value : normalizeName(value);
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

            const outside = country && country !== '—' && country.toLowerCase() !== 'philippines';
            const parts = (outside ? [street, val('international_location')] : [street, barangay ? 'Brgy. ' + barangay : '', city, province, region]).filter(function(p){ return p && p !== '—'; });
            let addr = parts.join(', ');
            if (postal && postal !== '—') addr += ' ' + postal;
            if (country && country !== '—') addr += ', ' + country;
            return addr || '—';
        }

        /* Section builder */
        function section(title, icon, rows, step, counts) {
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
                    '<span class="rev-badge" title="Details provided; optional omissions do not prevent submission">' + (counts ? counts.filled : filled.length) + ' / ' + (counts ? counts.total : rows.length) + '</span>' +
                    '<button type="button" class="rev-edit" data-edit-step="' + step + '" aria-label="Edit ' + escHtml(title) + '">Edit</button>' +
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
        const rawType = applicantType.value;
        const typeLabel = typeLabels[rawType] || rawType;

        /* Assemble display full name */
        function fullName() {
            const honorific = val('honorific') !== '—' ? val('honorific') : '';
            const first  = nameVal('first_name')  !== '—' ? nameVal('first_name')  : '';
            const middle = nameVal('middle_name') !== '—' ? nameVal('middle_name') : '';
            const last   = nameVal('last_name')   !== '—' ? nameVal('last_name')   : '';
            const suffix = val('suffix')      !== '—' ? val('suffix')      : '';
            const mi = middle ? middle.charAt(0).toUpperCase() + '.' : '';
            const name = [first, mi, last].filter(Boolean).join(' ');
            return [honorific, name, suffix].filter(Boolean).join(' ') || '—';
        }

        const profile = educationProfiles[rawType];
        const educationRows = [
            ['Education level', val('education_level')],
            ['Completion status', val('education_status')],
            [profile ? profile.school : 'Most recent school', val('most_recent_school')],
            ['Last School Year Attended', val('school_year_completed')],
            ['School location', val('school_location')],
        ];
        if (rawType === 'new_student') educationRows.push(['Senior High School strand', val('shs_strand')]);
        if (['transferee', 'shiftee', 'returnee'].indexOf(rawType) !== -1) {
            educationRows.push([profile.program, val('previous_program')]);
            educationRows.push([profile.level, val('last_year_level')]);
        }
        if (rawType === 'cross_enrollee') {
            educationRows.push(['Home institution', val('home_institution')]);
            educationRows.push(['Current course or program', val('home_program')]);
            educationRows.push(['Last year level or semester attended', val('last_year_level')]);
        }

        const documentRows = [];
        let applicableDocuments = 0;
        form.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (input.disabled || input.closest('.d-none')) return;
            applicableDocuments++;
            if (!input.files || !input.files.length) return;
            const label = input.closest('.document-input')?.querySelector('label[for="' + input.id + '"]');
            documentRows.push([label ? label.textContent.trim() : 'Supporting document', input.files[0].name]);
        });
        const selectedDocuments = documentRows.length;
        if (!documentRows.length) documentRows.push(['Supporting documents', 'No files selected']);

        const html =
            section('Admission Details', 'graduation-cap', [
                ['Applicant Type',   typeLabel],
                ['Intended Program', programName()],
            ], 1) +
            section('Personal Information', 'user', [
                ['Full Name',      fullName()],
                ['Gender',         val('gender')],
                ['Date of Birth',  val('birthdate')],
                ['Age',            (function() {
                    const dob = val('birthdate');
                    if (!dob || dob === '—') return '—';
                    const today = new Date();
                    const birthParts = dob.split('-').map(Number);
                    const birth = new Date(birthParts[0], birthParts[1] - 1, birthParts[2]);
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
                ['Spouse Name',    nameVal('spouse_name')],
                ['Your Occupation', val('occupation')],
                ['Disability / Accessibility Needs', val('disability')],
                ['PWD ID', val('pwd_id')],
            ], 2) +
            section('Contact Information', 'phone', [
                ['Email',    val('email')],
                ['Phone',    val('phone')],

            ], 3) +
            section('Current Address', 'map-pin', [['Address', fullAddress()]], 3) +
            section(profile ? profile.title : 'Educational Background', 'school', educationRows, 4) +
            section('Family Information', 'users', [
                ["Guardian's Name",     nameVal('guardian_name')],
                ["Guardian's Contact",  val('guardian_contact')],
                ["Father's Name",       nameVal('father_name')],
                ["Father's Occupation", val('father_occupation')],
                ["Mother's Name",       nameVal('mother_name')],
                ["Mother's Occupation", val('mother_occupation')],
                ["Parent's Address",    val('parent_address')],
                ["Parent's Contact",    val('parent_contact')],
            ], 5) +
            section('Supporting Documents', 'files', documentRows, 6, { filled: selectedDocuments, total: applicableDocuments });

        const container = document.getElementById('reviewSummary');
        if (container) {
            container.innerHTML = html;
            container.querySelectorAll('[data-edit-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    goTo(Number(button.getAttribute('data-edit-step')), true);
                });
            });
            if (window.lucide) lucide.createIcons({ nodes: [container] });
        }
    }

});
