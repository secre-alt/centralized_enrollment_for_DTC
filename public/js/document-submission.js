(function () {
    'use strict';
    document.querySelectorAll('[data-document-submission]').forEach(function (form) {
        var summary = form.querySelector('[data-document-summary]');
        var selectionError = form.querySelector('[data-document-selection-error]');
        var mobileTotal = form.querySelector('[data-mobile-request-total]');
        var money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        if (typeof window !== 'undefined' && window.matchMedia) {
            var desktop = window.matchMedia('(min-width: 992px)');
            function syncDetails() {
                form.querySelectorAll('[data-request-details]').forEach(function (details) { details.open = desktop.matches; });
            }
            syncDetails();
            desktop.addEventListener('change', syncDetails);
        }
        form.querySelectorAll('[data-document-option]').forEach(function (option) {
            var upload = option.querySelector('[data-document-upload]');
            if (upload) {
                var file = upload.querySelector('input[type="file"]');
                var remove = upload.querySelector('[data-upload-remove]');
                var refreshUpload = function () {
                    var selectedFile = file.files[0];
                    upload.querySelector('[data-upload-filename]').textContent = selectedFile ? selectedFile.name : 'No file selected';
                    upload.querySelector('[data-upload-choice]').textContent = selectedFile ? 'Replace file' : 'Choose file';
                    remove.hidden = !selectedFile;
                };
                file.addEventListener('change', refreshUpload);
                remove.addEventListener('click', function () {
                    file.value = '';
                    refreshUpload();
                    file.focus();
                });
            }
            option.querySelectorAll('[data-document-copy-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var input = option.querySelector('[data-document-copies]');
                    var current = Number(input.value);
                    input.value = Math.max(1, Math.min(10, (Number.isInteger(current) ? current : 1) + Number(button.dataset.documentCopyStep)));
                    sync();
                });
            });
        });
        var paymentContainer = form.querySelector('[data-request-payments]');
        if (paymentContainer) {
            form.querySelectorAll('[data-document-option]').forEach(function (option) {
                var section = option.querySelector('[data-document-payment-section]');
                option.paymentSection = section;
                var heading = document.createElement('h3');
                heading.className = 'dr-sec-title';
                heading.textContent = option.dataset.documentName;
                section.prepend(heading);
                paymentContainer.appendChild(section);
            });
        }
        function sync() {
            if (selectionError) selectionError.hidden = true;
            var total = 0;
            var lines = [];
            form.querySelectorAll('[data-document-option]').forEach(function (option) {
                var selected = option.querySelector('[data-document-toggle]').checked;
                var controls = option.querySelector('[data-document-controls]');
                if (controls) controls.hidden = !selected;
                option.querySelectorAll('[data-document-field]').forEach(function (field) { field.disabled = !selected; });
                option.querySelectorAll('[data-document-copy-step]').forEach(function (button) {
                    var copies = Number(option.querySelector('[data-document-copies]').value);
                    button.disabled = !selected || (Number(button.dataset.documentCopyStep) < 0 ? copies <= 1 : copies >= 10);
                });
                var paymentRoot = option.paymentSection || option;
                if (option.paymentSection) {
                    option.paymentSection.hidden = !selected;
                    paymentRoot.querySelectorAll('[data-document-field]').forEach(function (field) { field.disabled = !selected; });
                }
                var payment = paymentRoot.querySelector('[data-document-payment]:checked');
                var gcash = selected && payment && payment.value === 'gcash_qr';
                var panel = paymentRoot.querySelector('[data-document-gcash]');
                if (panel) panel.hidden = !gcash;
                var hint = paymentRoot.querySelector('[data-document-payment-hint]');
                if (hint) hint.textContent = gcash
                    ? 'Your GCash payment is subject to Cashier verification.'
                    : 'Pay at the school Cashier. Your payment status updates after verification.';
                var amount = paymentRoot.querySelector('[data-document-payment-amount]');
                if (amount) {
                    var quantity = Number(option.querySelector('[data-document-copies]').value);
                    amount.textContent = Number.isInteger(quantity) && quantity >= 1 && quantity <= 10
                        ? quantity + (quantity === 1 ? ' copy' : ' copies') + ' · ' + money.format(Number(option.dataset.documentCents) * quantity / 100)
                        : 'Enter 1–10 copies to calculate the amount';
                }
                var transferAmount = paymentRoot.querySelector('[data-document-amount]');
                if (transferAmount) {
                    var count = Number(option.querySelector('[data-document-copies]').value);
                    var validCount = Number.isInteger(count) && count >= 1 && count <= 10;
                    var centsDue = Number(option.dataset.documentCents) * count;
                    transferAmount.textContent = validCount ? money.format(centsDue / 100) : 'Check copy quantity';
                    var copyAmount = paymentRoot.querySelector('[data-document-copy-amount]');
                    copyAmount.disabled = !selected || !validCount;
                    copyAmount.setAttribute('data-copy-value', validCount ? (centsDue / 100).toFixed(2) : '');
                }
                paymentRoot.querySelectorAll('[data-document-proof]').forEach(function (field) {
                    field.disabled = !gcash;
                    field.required = !!gcash;
                });
                if (selected && summary) {
                    var copies = Number(option.querySelector('[data-document-copies]').value);
                    var cents = Number(option.dataset.documentCents);
                    var valid = Number.isInteger(copies) && copies >= 1 && copies <= 10;
                    var line = document.createElement('p');
                    line.className = 'dr-estimate-item';
                    line.textContent = option.dataset.documentName + ' — ' + (valid
                        ? copies + (copies === 1 ? ' copy' : ' copies') + ' × ' + money.format(cents / 100) + ' = ' + money.format(cents * copies / 100)
                        : 'Enter 1–10 copies') + ' · ' + (cents === 0 ? 'No payment required' : gcash ? 'GCash' : 'Cash at Cashier');
                    lines.push(line);
                    if (valid) total += cents * copies;
                    else total = NaN;
                }
            });
            if (summary) {
                var estimate = document.createElement('p');
                estimate.className = 'dr-estimate-total';
                estimate.textContent = !lines.length ? 'Select documents to see your estimate.'
                    : Number.isFinite(total) ? 'Estimated total: ' + money.format(total / 100) : 'Complete copy quantities to see your total.';
                summary.replaceChildren.apply(summary, lines.concat(estimate));
            }
            if (mobileTotal) mobileTotal.textContent = !lines.length ? 'Select your documents'
                : lines.length + (lines.length === 1 ? ' document' : ' documents') + ' · ' +
                    (Number.isFinite(total) ? 'Estimated ' + money.format(total / 100) : 'Check copy quantities');
        }
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        var steps = form.querySelectorAll('[data-request-step]');
        if (paymentContainer) {
            var currentStep = 0;
            var next = form.querySelector('[data-request-next]');
            var back = form.querySelector('[data-request-back]');
            var submit = form.querySelector('[data-document-submit]');
            function showStep(index) {
                currentStep = index;
                steps.forEach(function (panel, i) { panel.hidden = i !== index; });
                form.querySelectorAll('[data-request-step-indicator]').forEach(function (item, i) {
                    item.classList.toggle('is-current', i === index);
                    if (i === index) item.setAttribute('aria-current', 'step');
                    else item.removeAttribute('aria-current');
                });
                back.hidden = index === 0;
                next.hidden = index === 2;
                submit.hidden = index !== 2;
                next.textContent = index === 0 ? 'Next: Purpose' : 'Next: Payment';
            }
            next.addEventListener('click', function () {
                if (currentStep === 0 && !form.querySelector('[data-document-toggle]:checked')) {
                    if (selectionError) selectionError.hidden = false;
                    form.querySelector('[data-document-toggle]').focus();
                    return;
                }
                var invalid = steps[currentStep].querySelector(':invalid');
                if (invalid) { invalid.reportValidity(); return; }
                showStep(currentStep + 1);
            });
            back.addEventListener('click', function () { showStep(currentStep - 1); });
            form.addEventListener('invalid', function (event) {
                steps.forEach(function (panel, i) { if (panel.contains(event.target)) showStep(i); });
            }, true);
            form.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && currentStep < 2 && event.target.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    next.click();
                }
            });
            showStep(0);
        }
        form.addEventListener('submit', function (event) {
            if (!form.querySelector('[data-document-toggle]:checked')) {
                event.preventDefault();
                if (selectionError) selectionError.hidden = false;
                form.querySelector('[data-document-toggle]').focus();
            } else {
                var button = form.querySelector('[data-document-submit]');
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Submitting requests…';
                }
            }
        });
        sync();
    });
}());
