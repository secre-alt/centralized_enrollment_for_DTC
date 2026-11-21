/* DTC shared filter toolbar: keeps the "Filters" count badge and "Clear all" link in sync
   while the user changes fields (needed on pages that filter live via AJAX, no reload). */
(function () {
    'use strict';
    if (window.dtcSetFilterLoading) return;

    window.dtcSetFilterLoading = function (form, loading) {
        if (!form) return;
        var status = form.querySelector('[data-filter-loading]');
        if (!status && loading) {
            status = document.createElement('div');
            status.className = 'dtc-filter-loading';
            status.setAttribute('data-filter-loading', '');
            status.setAttribute('role', 'status');
            status.textContent = 'Applying filters…';
            form.appendChild(status);
        }
        form.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (status) status.hidden = !loading;
    };

    window.dtcApplyLocalFilters = function (container, apply) {
        window.dtcSetFilterLoading(container, true);
        // Let the browser paint the status before filtering existing rows.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                try { apply(); }
                finally { window.dtcSetFilterLoading(container, false); }
            });
        });
    };

    document.addEventListener('submit', function (event) {
        if (!event.defaultPrevented && event.target.matches('.dtc-fl, .ph-filters')) {
            window.dtcSetFilterLoading(event.target, true);
        }
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a.dtc-fl-clear, a.ph-clear, a.dtc-pay-search-clear, a.dq-clear, a.notice-tab, a.dq-chip, a.dtc-filter-pill, a.rg-appointment-tab');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
        var form = link.closest('form') || document.querySelector('form.dtc-fl, form.ph-filters');
        window.dtcSetFilterLoading(form, true);
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-filter-loading]').forEach(function (status) {
            window.dtcSetFilterLoading(status.parentElement, false);
        });
    });

    function init(form) {
        var fields = form.querySelectorAll('[data-dtc-filter]');
        var search = form.querySelector('[data-dtc-filter-search]');
        var toggle = form.querySelector('[data-dtc-filter-toggle]');
        var badge  = form.querySelector('[data-dtc-filter-count]');
        var clear  = form.querySelectorAll('[data-dtc-clear]');
        if (!fields.length) return;

        function sync() {
            var count = 0;
            fields.forEach(function (el) {
                if (el.value && el.value.trim() !== '' && el.value !== el.getAttribute('data-dtc-filter-default')) count++;
            });

            if (badge) {
                badge.textContent = count;
                badge.hidden = count === 0;
            }
            if (toggle) toggle.classList.toggle('has-filters', count > 0);

            var anyActive = count > 0 || !!(search && search.value.trim() !== '');
            clear.forEach(function (el) { el.hidden = !anyActive; });
        }

        form.addEventListener('change', sync);
        form.addEventListener('input', sync);
        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dtc-filters]').forEach(init);
    });
})();
