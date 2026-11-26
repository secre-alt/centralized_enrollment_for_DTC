(function () {
    'use strict';
    document.querySelectorAll('[data-release-form]').forEach(function (form) {
        var button = form.querySelector('[data-release-submit]');
        var submitting = false;
        function sync() {
            var count = form.querySelectorAll('[name="document_ids[]"]:enabled:checked').length;
            button.disabled = count === 0 || !form.elements.handover_confirmed.checked;
            button.textContent = count ? 'Release ' + count + (count === 1 ? ' document' : ' documents') : 'Release documents';
        }
        form.addEventListener('change', sync);
        form.addEventListener('submit', function (event) {
            if (submitting) {
                event.preventDefault();
                return;
            }
            sync();
            if (button.disabled || !form.checkValidity()) {
                event.preventDefault();
                return;
            }
            button.disabled = true;
            submitting = true;
            button.textContent = 'Recording release…';
        });
        document.querySelectorAll('[data-target="#' + form.closest('.modal').id + '"][data-release-document]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                form.reset();
                form.querySelectorAll('[name="document_ids[]"]:enabled').forEach(function (input) {
                    input.checked = input.value === trigger.dataset.releaseDocument;
                });
                sync();
            });
        });
        sync();
    });
})();
