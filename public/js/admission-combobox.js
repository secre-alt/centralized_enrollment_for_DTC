/* Use the same local datalists with a consistent picker on desktop and mobile. */
(function () {
    'use strict';
    document.querySelectorAll('.wiz-address-section input[list]').forEach(input => {
        const source = document.getElementById(input.getAttribute('list'));
        if (!source) return;
        input.removeAttribute('list');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-haspopup', 'listbox');
        const popup = document.createElement('div');
        popup.id = input.id + '_suggestions';
        popup.className = 'location-suggestions';
        popup.setAttribute('role', 'listbox');
        popup.setAttribute('aria-label', input.closest('.wiz-field').querySelector('label').textContent);
        popup.hidden = true;
        input.setAttribute('aria-controls', popup.id);
        input.insertAdjacentElement('afterend', popup);
        let matches = [];
        let active = -1;
        let query = '';
        function close() {
            popup.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        }
        function select(value) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        }
        function render() {
            matches = Array.from(source.options).map(option => option.value)
                .filter(value => value.toLocaleLowerCase().includes(query.toLocaleLowerCase()));
            popup.replaceChildren();
            active = -1;
            input.removeAttribute('aria-activedescendant');
            matches.forEach((value, index) => {
                const option = document.createElement('div');
                option.id = popup.id + '_' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = value;
                option.addEventListener('pointerdown', event => event.preventDefault());
                option.addEventListener('click', () => select(value));
                popup.appendChild(option);
            });
            popup.hidden = !matches.length || document.activeElement !== input;
            input.setAttribute('aria-expanded', String(!popup.hidden));
        }
        input.addEventListener('focus', () => { query = ''; render(); });
        input.addEventListener('click', () => { query = ''; render(); });
        input.addEventListener('input', () => { query = input.value.trim(); render(); });
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'Tab') { close(); return; }
            if (event.key === 'Enter' && !popup.hidden && active >= 0) {
                event.preventDefault();
                event.stopPropagation();
                select(matches[active]);
                return;
            }
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            if (popup.hidden) { query = ''; render(); }
            if (!matches.length) return;
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
            Array.from(popup.children).forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            const option = popup.children[active];
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        });
        new MutationObserver(() => {
            if (document.activeElement === input) render();
        }).observe(source, { childList: true });
    });
})();
