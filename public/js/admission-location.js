/* Local catalog suggestions: native keyboard-accessible inputs allow manual fallback. */
function initializeAdmissionLocations() {
    'use strict';
    const catalog = window.dtcLocations;
    if (!catalog) return;
    const fields = ['region', 'province', 'city'].map(name => document.getElementById(name + '_text'));
    const codes = ['region', 'province', 'city'].map(name => document.getElementById(name + '_code'));
    const country = document.getElementById('country');
    const countryRules = window.dtcAdmissionValidation || {};
    const postal = document.getElementById('postal_code');
    const postalHint = document.getElementById('postalCodeHint');
    let postalMode = null;
    let countryIsLocal = !country.value.trim() || country.value.trim().toLowerCase() === 'philippines';
    function normalizeCountry() {
        const value = country.value.trim().toLowerCase();
        const aliases = countryRules.countryAliases || {};
        const canonical = (Object.prototype.hasOwnProperty.call(aliases, value) ? aliases[value] : null)
            || (countryRules.countries || []).find(name => name.toLowerCase() === value);
        if (canonical && canonical !== country.value) {
            country.value = canonical;
            country.dispatchEvent(new Event('input', { bubbles: true }));
            persist(country);
        }
    }
    const hint = document.getElementById('locationHint');
    const barangay = document.getElementById('barangay_text');
    const barangayCode = document.getElementById('barangay_code');
    const barangayList = document.getElementById('barangayOptions');
    const barangayHint = document.getElementById('barangayHint');
    const barangayCache = new Map();
    let activeCity = null;
    let barangayRows = [];
    let requestVersion = 0;
    function resolveBarangay() {
        const row = match(barangayRows, barangay);
        barangayCode.value = row ? row.code : '';
    }
    async function loadBarangays(cityCode, preserveRestoredValue) {
        if (activeCity === cityCode) return;
        const previousCity = activeCity;
        activeCity = cityCode;
        const version = ++requestVersion;
        barangayRows = [];
        options(barangayList, []);
        barangayCode.value = '';
        if (!preserveRestoredValue && previousCity !== null && previousCity !== cityCode) {
            barangay.value = '';
            persist(barangay);
        }
        if (!cityCode) {
            barangayHint.dataset.state = 'ready';
            barangay.removeAttribute('aria-busy');
            barangayHint.textContent = 'Choose a city to see barangays, or enter one manually.';
            return;
        }
        barangayHint.textContent = 'Loading barangay suggestions...';
        barangayHint.dataset.state = 'loading';
        barangay.setAttribute('aria-busy', 'true');
        try {
            if (!barangayCache.has(cityCode)) {
                const response = await fetch(barangay.dataset.url.replace('CITY', encodeURIComponent(cityCode)), { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Location lookup failed');
                barangayCache.set(cityCode, await response.json());
            }
            if (version !== requestVersion) return;
            barangayRows = barangayCache.get(cityCode);
            options(barangayList, barangayRows);
            resolveBarangay();
            barangayHint.textContent = 'Search barangays in your city. Not listed? Enter it manually.';
            barangayHint.dataset.state = 'ready';
        } catch (_) {
            if (version === requestVersion) {
                activeCity = null; // Allow retry when the location is selected again.
                barangayHint.textContent = 'Suggestions are unavailable. You can enter your barangay manually.';
                barangayHint.dataset.state = 'error';
            }
        } finally {
            if (version === requestVersion) barangay.removeAttribute('aria-busy');
        }
    }
    const lists = ['region', 'province', 'city'].map(name => document.getElementById(name + 'Options'));
    function options(list, rows) {
        const signature = JSON.stringify(rows.map(row => row.name));
        if (list.dataset.signature === signature) return;
        list.dataset.signature = signature;
        list.replaceChildren(...rows.map(row => {
            const option = document.createElement('option');
            option.value = row.name;
            return option;
        }));
    }
    function match(rows, field) {
        return rows.find(row => row.name.toLocaleLowerCase() === field.value.trim().toLocaleLowerCase());
    }
    function persist(field) {
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function clearFrom(index) {
        for (let i = index; i < fields.length; i++) {
            fields[i].value = '';
            codes[i].value = '';
            persist(fields[i]);
        }
        barangay.value = '';
        barangayCode.value = '';
        persist(barangay);
    }
    function refresh(preserveRestoredValue) {
        const countryValue = country.value.trim().toLowerCase();
        const knownCountry = (countryRules.countries || []).find(name => name.toLowerCase() === countryValue);
        // Keep the current address visible while a country search is incomplete.
        if (!countryValue || knownCountry) countryIsLocal = !countryValue || knownCountry === 'Philippines';
        const local = countryIsLocal;
        [country, document.getElementById('current_address'), fields[2], document.getElementById('international_location')].forEach(field => {
            if (!field) return;
            field.required = field === country || field.name === 'current_address'
                || (field === fields[2] ? local && !codes[2].value : !local);
        });
        if (postal) {
            postal.maxLength = local ? 4 : 20;
            postal.inputMode = local ? 'numeric' : 'text';
            postal.placeholder = local ? 'e.g. 6344' : 'e.g. SW1A 1AA';
            if (postalHint) postalHint.textContent = 'Enter a valid postal code for your address (optional).';
            if (postalMode !== null && postalMode !== local && postal.value.trim()) {
                postal.dispatchEvent(new Event('change', { bubbles: true }));
            }
            postalMode = local;
        }
        const international = document.getElementById('internationalLocationField');
        if (international) international.hidden = local;
        const region = local && match(catalog.regions, fields[0]);
        const areas = region ? catalog.areas[region.code] || [] : [];
        const area = match(areas, fields[1]);
        const cities = area && area.type === 'province' ? catalog.cities[area.code] || [] : [];
        const city = match(cities, fields[2]);
        codes[0].value = region ? region.code : '';
        codes[1].value = area && area.type === 'province' ? area.code : '';
        codes[2].value = area && area.type === 'city' ? area.code : city ? city.code : '';
        fields[2].required = local && !(area && area.type === 'city');
        [country, document.getElementById('current_address'), fields[2], document.getElementById('international_location')].forEach(field => {
            if (!field) return;
            const label = document.querySelector('label[for="' + field.id + '"]');
            if (!label) return;
            label.querySelector('.req')?.remove();
            if (field.required) {
                const star = document.createElement('span'); star.className = 'req'; star.textContent = ' *'; label.appendChild(star);
            }
        });
        loadBarangays(codes[2].value, preserveRestoredValue === true);
        options(lists[0], local ? catalog.regions : []);
        options(lists[1], areas);
        options(lists[2], cities);
        fields[0].closest('.wiz-field').hidden = !local;
        fields[1].closest('.wiz-field').hidden = !local;
        fields[2].closest('.wiz-field').hidden = !local || Boolean(area && area.type === 'city');
        barangay.closest('.wiz-field').hidden = !local;
        // Hidden fields must not submit a stale Philippine address abroad.
        fields.forEach(field => { field.disabled = !local; });
        barangay.disabled = !local;
        hint.textContent = !local ? 'Enter your city and state or province below.'
            : area && area.type === 'city' ? 'Independent city selected. Enter your barangay and street below.'
            : area ? 'Choose your city or municipality, then enter your barangay.'
            : region ? 'Choose a province or independent city. You can also type a location manually.'
            : 'Choose your region to filter suggestions. Location not listed? Type it manually.';
    }
    const previousValues = fields.map(field => field.value.trim().toLowerCase());
    function restoreLocationState() {
        fields.forEach((field, index) => { previousValues[index] = field.value.trim().toLowerCase(); });
        refresh(true);
    }
    fields.forEach((field, index) => {
        function update() {
            const value = field.value.trim().toLowerCase();
            if (value !== previousValues[index]) {
                clearFrom(index + 1);
                fields.forEach((item, position) => { previousValues[position] = item.value.trim().toLowerCase(); });
            }
            refresh();
        }
        field.addEventListener('input', update);
        field.addEventListener('change', update);
    });
    country.addEventListener('input', refresh);
    country.addEventListener('blur', normalizeCountry);
    country.addEventListener('change', refresh);
    barangay.addEventListener('input', resolveBarangay);
    barangay.addEventListener('change', resolveBarangay);
    // Browsers may restore/autofill values after deferred scripts without input events.
    window.addEventListener('pageshow', restoreLocationState);
    [country, ...fields, barangay].forEach(field => field.addEventListener('focus', restoreLocationState));
    refresh(true);
}
// The wizard restores its draft in DOMContentLoaded; run after that listener.
if (document.readyState === 'complete') initializeAdmissionLocations();
else document.addEventListener('DOMContentLoaded', initializeAdmissionLocations, { once: true });
