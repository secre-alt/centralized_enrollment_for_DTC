// ── Global Search ─────────────────────────────────────────────────────────

const SUGGESTIONS  = ['Dashboard', 'Enrollment', 'Appointments', 'Payments', 'Settings', 'Users', 'Notifications'];
const STORAGE_KEY  = 'dtc_recent_searches';
let   searchTimer  = null;

// ── Storage helpers ───────────────────────────────────────────────────────
function getRecent() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
    catch { return []; }
}

function addRecent(q) {
    if (!q || !q.trim()) return;
    let r = getRecent().filter(x => x.toLowerCase() !== q.toLowerCase());
    r.unshift(q.trim());
    localStorage.setItem(STORAGE_KEY, JSON.stringify(r.slice(0, 5)));
}

function clearRecent() {
    localStorage.removeItem(STORAGE_KEY);
    renderRecent();
}

// ── Render helpers ────────────────────────────────────────────────────────
function renderSuggestions() {
    const el = document.getElementById('suggestions-list');
    if (!el) return;
    el.innerHTML = SUGGESTIONS.map(s => `
        <div class="search-suggestion-item"
             onclick="fillSearch('${s}')">
            <i class="fas fa-search"></i>
            <span>${s}</span>
        </div>`).join('');
}

function renderRecent() {
    const list = document.getElementById('recent-list');
    const none = document.getElementById('no-recent');
    if (!list) return;

    const recent = getRecent();
    list.innerHTML = '';

    if (recent.length === 0) {
        if (none) none.style.display = 'block';
        return;
    }

    if (none) none.style.display = 'none';
    recent.forEach(r => {
        const chip = document.createElement('button');
        chip.className   = 'search-recent-chip';
        chip.textContent = r;
        chip.onclick     = () => fillSearch(r);
        list.appendChild(chip);
    });
}

function fillSearch(value) {
    const input = document.getElementById('global-search');
    if (input) {
        input.value = value;
        runSearch(value);
    }
}

// ── State managers ────────────────────────────────────────────────────────
function openSearch() {
    const dd = document.getElementById('search-dropdown');
    if (dd) dd.classList.add('open');
    renderSuggestions();
    renderRecent();

    const input = document.getElementById('global-search');
    if (input && !input.value.trim()) {
        showDefault();
    }
}

function closeSearch() {
    const dd = document.getElementById('search-dropdown');
    if (dd) dd.classList.remove('open');
}

function showDefault() {
    document.getElementById('search-default').style.display  = 'block';
    document.getElementById('search-loading').style.display  = 'none';
    document.getElementById('search-results').style.display  = 'none';
    document.getElementById('search-empty').style.display    = 'none';
}

function showLoading() {
    document.getElementById('search-default').style.display  = 'none';
    document.getElementById('search-loading').style.display  = 'block';
    document.getElementById('search-results').style.display  = 'none';
    document.getElementById('search-empty').style.display    = 'none';
}

function showResults(results) {
    document.getElementById('search-default').style.display  = 'none';
    document.getElementById('search-loading').style.display  = 'none';
    document.getElementById('search-results').style.display  = 'block';
    document.getElementById('search-empty').style.display    = 'none';

    const list = document.getElementById('results-list');
    list.innerHTML = results.map(r => {
        const iconHtml = r.avatar
            ? `<div class="search-result-avatar">${r.avatar}</div>`
            : `<div class="search-result-icon ${r.type}"><i class="fas ${r.icon}"></i></div>`;

        return `
            <a href="${r.url}"
               class="search-result-item"
               onclick="addRecent(document.getElementById('global-search').value); closeSearch();">
                ${iconHtml}
                <div class="search-result-body">
                    <div class="search-result-title">${r.title}</div>
                    <div class="search-result-desc">${r.desc}</div>
                </div>
                <span class="search-result-badge ${r.type}">${r.type}</span>
            </a>`;
    }).join('');
}

function showEmpty(query) {
    document.getElementById('search-default').style.display  = 'none';
    document.getElementById('search-loading').style.display  = 'none';
    document.getElementById('search-results').style.display  = 'none';
    document.getElementById('search-empty').style.display    = 'block';

    const sub = document.getElementById('empty-query');
    if (sub) sub.textContent = 'No results for "' + query + '"';
}

// ── Search runner ─────────────────────────────────────────────────────────
function runSearch(query) {
    if (!query || query.trim().length < 2) {
        showDefault();
        return;
    }

    showLoading();
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        fetch('/search?q=' + encodeURIComponent(query.trim()), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            data.results.length > 0
                ? showResults(data.results)
                : showEmpty(query);
        })
        .catch(() => showEmpty(query));
    }, 300);
}

function clearSearch() {
    const input = document.getElementById('global-search');
    const clear = document.getElementById('search-clear');
    const kbd   = document.getElementById('search-kbd');

    if (input) input.value = '';
    if (clear) clear.classList.remove('visible');
    if (kbd)   kbd.style.display = 'flex';

    showDefault();
}

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('global-search');
    const clear = document.getElementById('search-clear');
    const kbd   = document.getElementById('search-kbd');

    if (!input) return;

    // Input events
    input.addEventListener('focus', () => openSearch());

    input.addEventListener('input', function () {
        const q = this.value.trim();

        if (q.length > 0) {
            if (clear) clear.classList.add('visible');
            if (kbd)   kbd.style.display = 'none';
            runSearch(q);
        } else {
            if (clear) clear.classList.remove('visible');
            if (kbd)   kbd.style.display = 'flex';
            showDefault();
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSearch();
            this.blur();
        }
        if (e.key === 'Enter' && this.value.trim()) {
            addRecent(this.value.trim());
        }
    });

    // Ctrl+K
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            input.focus();
            openSearch();
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.navbar-search-item')) {
            closeSearch();
        }
    });
});