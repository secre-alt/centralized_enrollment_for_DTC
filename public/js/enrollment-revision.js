(function () {
    'use strict';
    const form = document.getElementById('enrollment-revision-form');
    const review = document.getElementById('enrollment-revision-review');
    if (!form || !review) return;
    const choices = Array.from(form.querySelectorAll('[data-revision-choice]'));
    const confirmation = document.getElementById('confirm-revision');
    function render() {
        review.replaceChildren();
        choices.forEach(function (choice) {
            const row = document.createElement('p');
            const before = document.createElement('strong');
            before.textContent = choice.dataset.original;
            row.append(before, document.createElement('br'));
            row.append(document.createTextNode('Replacement: ' + (choice.value
                ? choice.options[choice.selectedIndex].textContent.replace(/\s+/g, ' ').trim()
                : 'Choose a section above.')));
            review.append(row);
        });
        const load = document.createElement('p');
        const complete = choices.length > 0 && choices.every(function (choice) { return !!choice.value; });
        const irregular = form.dataset.retainedIrregular === '1' || choices.some(function (choice) {
            return choice.value && choice.options[choice.selectedIndex].dataset.requiresIrregular === '1';
        });
        load.textContent = complete ? 'Resulting load: ' + (irregular ? 'Irregular' : 'Regular') + ' (subject to Registrar approval).'
            : 'Choose a replacement for every affected subject to review the resulting load.';
        review.append(load);
    }
    choices.forEach(function (choice) {
        choice.addEventListener('change', function () {
            if (confirmation) confirmation.checked = false;
            render();
        });
    });
    render();
}());
