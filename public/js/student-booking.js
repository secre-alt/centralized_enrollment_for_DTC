/* DTC EMS — student document booking wizard (4 steps). Replaces appointments-create.js on the booking page. */
(function () {
    'use strict';
    var form = document.getElementById('bkForm');
    if (!form) return;

    var fee = parseFloat(form.getAttribute('data-fee')) || 0;
    var step = 1;
    var reached = 1;
    var nextBtn = document.getElementById('bkNext');
    var backBtn = document.getElementById('bkBack');
    var note = document.getElementById('bkNote');
    var stepsEl = form.querySelector('.dr-steps');
    var panels = form.querySelectorAll('[data-step-panel]');
    var pickupDate = document.getElementById('pickup-date');
    var labels = { 1: 'Next: Pickup time', 2: 'Next: Payment', 3: 'Review booking', 4: 'Confirm booking' };

    var $ = function (sel, root) { return (root || form).querySelector(sel); };
    var checked = function (name) { return $('input[name="' + name + '"]:checked'); };
    var show = function (el, on) { if (el) el.classList.toggle('is-shown', !!on); };
    var errFor = function (key) { return $('[data-step-error="' + key + '"]'); };

    /* ---------- summary (slip + review) ---------- */
    var setText = function (sel, text, empty) {
        var el = $(sel); if (!el) return;
        el.textContent = text;
        el.classList.toggle('is-empty', !!empty);
    };

    var refresh = function () {
        var doc = checked('document_type');
        var slot = checked('appointment_slot_id');
        var pay = checked('payment_method');
        var purpose = $('[name="purpose"]');

        setText('[data-slip="doc"]', doc ? doc.getAttribute('data-label') : 'Not chosen yet', !doc);
        setText('[data-slip="when"]', slot ? slot.getAttribute('data-date') + ', ' + slot.getAttribute('data-time') : 'Not chosen yet', !slot);
        setText('[data-slip="pay"]', pay ? pay.getAttribute('data-label') : 'Cashier’s Office');

        setText('[data-review="doc"]', doc ? doc.getAttribute('data-label') : '—');
        setText('[data-review="date"]', slot ? slot.getAttribute('data-date') : '—');
        setText('[data-review="time"]', slot ? slot.getAttribute('data-time') : '—');
        setText('[data-review="pay"]', pay ? (pay.value === 'gcash' ? 'GCash (receipt will be verified)' : 'Cashier’s Office') : '—');
        setText('[data-review="purpose"]', purpose && purpose.value.trim() ? purpose.value.trim() : '—');

        var gcash = document.getElementById('gcash-details');
        var useGcash = !!(pay && pay.value === 'gcash');
        if (gcash) gcash.classList.toggle('is-open', useGcash);
    };

    var showPickupDate = function () {
        if (!pickupDate) return;
        var selectedDay = pickupDate.value;
        form.querySelectorAll('[data-slot-date-panel]').forEach(function (day) {
            var active = day.getAttribute('data-slot-date-panel') === selectedDay;
            day.hidden = !active;
            if (!active) {
                var checkedTime = day.querySelector('input[name="appointment_slot_id"]:checked');
                if (checkedTime) checkedTime.checked = false;
            }
        });
        refresh();
    };

    /* ---------- validation ---------- */
    var clearErrors = function () {
        form.querySelectorAll('[data-step-error]').forEach(function (e) { e.classList.remove('is-shown'); });
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    };

    var fail = function (key, focusEl) {
        show(errFor(key), true);
        if (focusEl) {
            focusEl.classList.add('is-invalid');
            focusEl.focus({ preventScroll: true });
            focusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            var e = errFor(key);
            if (e) e.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    };

    var validate = function (n) {
        clearErrors();
        if (n === 1 && !checked('document_type')) return fail('1');
        if (n === 2 && !checked('appointment_slot_id')) return fail('2');
        if (n === 3) {
            var purpose = $('[name="purpose"]');
            if (purpose && !purpose.value.trim()) return fail('purpose', purpose);
            var pay = checked('payment_method');
            if (!pay) return false;
            if (pay.value === 'gcash') {
                var ref = $('#gcash-ref'), proof = $('#gcash-proof');
                if (ref && !ref.value.trim()) return fail('ref', ref);
                if (proof && !(proof.files && proof.files.length)) return fail('proof');
            }
        }
        return true;
    };

    /* ---------- navigation ---------- */
    var go = function (n, opts) {
        step = n;
        reached = Math.max(reached, n);
        panels.forEach(function (p) { p.hidden = String(p.getAttribute('data-step-panel')) !== String(n); });

        form.querySelectorAll('[data-step-item]').forEach(function (li) {
            var i = parseInt(li.getAttribute('data-step-item'), 10);
            li.classList.toggle('is-done', i < n);
            li.classList.toggle('is-current', i === n);
            var btn = li.querySelector('button');
            btn.disabled = i >= n || i > reached;
            if (i === n) btn.setAttribute('aria-current', 'step'); else btn.removeAttribute('aria-current');
        });

        nextBtn.textContent = labels[n];
        backBtn.hidden = n === 1;
        note.textContent = 'Step ' + n + ' of 4';
        refresh();

        if (!opts || !opts.silent) {
            var legend = $('[data-step-panel="' + n + '"] legend');
            if (legend) { legend.setAttribute('tabindex', '-1'); legend.focus({ preventScroll: true }); }
            stepsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    var advance = function () {
        if (step < 4) {
            if (validate(step)) go(step + 1);
            return;
        }
        // Final step: re-check everything, jump to the first problem.
        for (var i = 1; i <= 3; i++) {
            if (!validate(i)) { go(i); validate(i); return; }
        }
        nextBtn.classList.add('is-busy');
        nextBtn.textContent = 'Booking…';
        form.submit();
    };

    nextBtn.addEventListener('click', advance);
    backBtn.addEventListener('click', function () { if (step > 1) go(step - 1); });
    form.querySelectorAll('[data-step-btn]').forEach(function (b) {
        b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-step-btn'), 10)); });
    });
    form.querySelectorAll('[data-goto]').forEach(function (b) {
        b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-goto'), 10)); });
    });

    // Enter in a text field moves forward instead of submitting early.
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'file') {
            e.preventDefault();
            advance();
        }
    });
    form.addEventListener('submit', function (e) { if (step < 4) { e.preventDefault(); advance(); } });

    form.addEventListener('change', function (e) {
        if (e.target.matches('input[type="radio"]')) { clearErrors(); }
        refresh();
    });
    if (pickupDate) pickupDate.addEventListener('change', showPickupDate);
    form.addEventListener('input', function (e) {
        if (e.target.matches('[name="purpose"], #gcash-ref')) {
            e.target.classList.remove('is-invalid');
            var key = e.target.name === 'purpose' ? 'purpose' : 'ref';
            show(errFor(key), false);
        }
        refresh();
    });

    /* ---------- receipt file ---------- */
    var proof = document.getElementById('gcash-proof');
    if (proof) {
        proof.addEventListener('change', function () {
            var name = $('[data-dr-file-name]'), box = proof.closest('.dr-file');
            var file = proof.files && proof.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                proof.value = '';
                name.textContent = 'That file is over 5 MB. Choose a smaller one.';
                box.classList.remove('has-file');
                return;
            }
            name.textContent = file ? file.name : 'JPG, PNG or PDF, up to 5 MB';
            box.classList.toggle('has-file', !!file);
            if (file) show(errFor('proof'), false);
        });
    }

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { nextBtn.classList.remove('is-busy'); go(step, { silent: true }); }
    });

    var next = document.getElementById('drNext');
    if (next && window.matchMedia('(min-width: 992px)').matches) next.open = true;

    var initial = parseInt(form.getAttribute('data-initial-step'), 10) || 1;
    reached = initial;
    go(initial, { silent: true });
})();
