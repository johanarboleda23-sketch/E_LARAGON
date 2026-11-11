const normalize = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const searches = new WeakMap();
let nextListId = 0;

const style = document.createElement('style');
style.textContent = `
    .select-search-input {
        box-sizing: border-box;
        width: 100%;
        min-width: 7rem;
        padding: .35rem .5rem;
        border: 1px solid #cbd6cf;
        border-radius: .375rem;
        background: white;
        color: #192522;
        font: inherit;
        font-size: .75rem;
    }
    .select-search-input[hidden] { display: none; }
`;
document.head.appendChild(style);

function availableOptions(select) {
    return Array.from(select.options).filter((option) =>
        option.value !== '' && !option.hidden && !option.matches(':disabled') && !option.parentElement?.hidden
    );
}

function suggestionText(option, options) {
    const text = option.text.trim();
    return options.filter((candidate) => candidate.text.trim() === text).length > 1
        ? `${text} (${option.value})`
        : text;
}

function refreshSuggestions(select, input, list) {
    const query = normalize(input.value.trim());
    const options = availableOptions(select);
    const matches = options.filter((option) =>
        normalize(`${option.text} ${option.value}`).includes(query)
    );
    list.replaceChildren(...matches.map((option) => {
        const suggestion = document.createElement('option');
        const text = suggestionText(option, options);
        suggestion.value = query && !normalize(text).includes(query) ? `${text} (${option.value})` : text;
        return suggestion;
    }));
}

function chooseOption(select, input, option) {
    if (select.selectedIndex !== option.index) {
        select.selectedIndex = option.index;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    input.value = '';
}

function addSearch(select) {
    if (searches.has(select) || select.multiple || select.closest('template')) {
        return;
    }

    const input = document.createElement('input');
    const list = document.createElement('datalist');
    list.id = `select-search-list-${++nextListId}`;
    input.type = 'search';
    input.className = 'select-search-input';
    input.placeholder = 'Buscar código o palabra...';
    input.setAttribute('list', list.id);
    input.setAttribute('autocomplete', 'off');
    const labelElement = select.labels?.[0]?.cloneNode(true);
    labelElement?.querySelectorAll('select, input, button').forEach((element) => element.remove());
    const label = labelElement?.textContent.trim() || select.getAttribute('aria-label') || 'opciones';
    input.setAttribute('aria-label', `Buscar ${label}`);
    select.before(input, list);
    searches.set(select, { input, list });

    const syncState = () => {
        input.hidden = select.hidden || select.style.display === 'none';
        input.disabled = select.matches(':disabled');
    };
    syncState();
    new MutationObserver(syncState).observe(select, { attributes: true, attributeFilter: ['hidden', 'disabled', 'style'] });

    input.addEventListener('focus', () => refreshSuggestions(select, input, list));
    input.addEventListener('input', () => {
        const text = input.value.trim();
        const options = availableOptions(select);
        const exact = options.filter((option) =>
            suggestionText(option, options) === text || `${suggestionText(option, options)} (${option.value})` === text
        );
        if (exact.length === 1) {
            chooseOption(select, input, exact[0]);
        }
        refreshSuggestions(select, input, list);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        const query = normalize(input.value.trim());
        const matches = query ? availableOptions(select).filter((option) =>
            normalize(`${option.text} ${option.value}`).includes(query)
        ) : [];
        if (matches.length === 1) {
            chooseOption(select, input, matches[0]);
        }
        refreshSuggestions(select, input, list);
    });
    select.addEventListener('change', () => {
        input.value = '';
    });
}

function addSearches(root) {
    if (root instanceof HTMLSelectElement) {
        addSearch(root);
    } else if (root instanceof Element || root instanceof Document) {
        root.querySelectorAll('select').forEach(addSearch);
    }
}

addSearches(document);
new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach(addSearches));
}).observe(document.body, { childList: true, subtree: true });
