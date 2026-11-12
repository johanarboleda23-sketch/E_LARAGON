const normalize = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const controls = new WeakMap();
let nextListId = 0;
const MAX_VISIBLE_OPTIONS = 20;

const style = document.createElement('style');
style.textContent = `
    .select-search-native {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }
    .select-search-control {
        position: relative;
        min-width: 0;
        flex: 1 1 auto;
    }
    .select-search-results {
        position: absolute;
        top: calc(100% + .25rem);
        left: 0;
        z-index: 50;
        display: none;
        width: max(100%, 14rem);
        max-height: 16rem;
        overflow-y: auto;
        border: 1px solid #cbd6cf;
        border-radius: .5rem;
        background: white;
        box-shadow: 0 .5rem 1rem rgb(25 37 34 / 15%);
    }
    .select-search-results[aria-hidden="false"] {
        display: block;
    }
    .select-search-option {
        display: block;
        width: 100%;
        padding: .5rem .65rem;
        border: 0;
        background: white;
        color: #192522;
        cursor: pointer;
        font: inherit;
        font-size: .75rem;
        text-align: left;
    }
    .select-search-option:hover,
    .select-search-option[aria-selected="true"] {
        background: #edf7f4;
    }
    .select-search-empty,
    .select-search-count {
        padding: .5rem .65rem;
        color: #71807a;
        font-size: .7rem;
    }
    .select-search-count {
        border-top: 1px solid #e6ede8;
    }
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
`;
document.head.appendChild(style);

function availableOptions(select) {
    return Array.from(select.options).filter((option) =>
        option.value !== '' && !option.hidden && !option.matches(':disabled') && !option.parentElement?.hidden
    );
}

function optionLabel(option, options) {
    const text = option.text.trim();
    return options.filter((candidate) => candidate.text.trim() === text).length > 1
        ? `${text} (${option.value})`
        : text;
}

function renderOptions(select, input, list) {
    const query = normalize(input.value.trim());
    const options = availableOptions(select);
    const matches = query
        ? options.filter((option) => normalize(`${option.text} ${option.value}`).includes(query))
        : [];
    const visibleOptions = matches.slice(0, MAX_VISIBLE_OPTIONS);

    list.replaceChildren();
    visibleOptions.forEach((option, index) => {
        const suggestion = document.createElement('button');
        suggestion.type = 'button';
        suggestion.className = 'select-search-option';
        suggestion.setAttribute('role', 'option');
        suggestion.setAttribute('aria-selected', 'false');
        suggestion.dataset.value = option.value;
        suggestion.textContent = optionLabel(option, options);
        suggestion.addEventListener('mousedown', (event) => event.preventDefault());
        suggestion.addEventListener('click', () => chooseOption(select, input, option));
        list.append(suggestion);
    });

    if (query && matches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'select-search-empty';
        empty.textContent = 'No hay opciones que coincidan.';
        list.append(empty);
    } else if (matches.length > MAX_VISIBLE_OPTIONS) {
        const count = document.createElement('div');
        count.className = 'select-search-count';
        count.textContent = `Mostrando ${MAX_VISIBLE_OPTIONS} de ${matches.length} resultados. Sigue escribiendo para filtrar.`;
        list.append(count);
    }

    const isOpen = query.length > 0;
    list.setAttribute('aria-hidden', String(!isOpen));
    input.setAttribute('aria-expanded', String(isOpen));
}

function syncSelection(select, input) {
    const option = select.selectedOptions[0];
    input.value = option && option.value !== ''
        ? optionLabel(option, availableOptions(select))
        : '';
    input.setCustomValidity('');
}

function chooseOption(select, input, option) {
    select.value = option.value;
    syncSelection(select, input);
    select.dispatchEvent(new Event('input', { bubbles: true }));
    select.dispatchEvent(new Event('change', { bubbles: true }));
    input.setAttribute('aria-expanded', 'false');
    input.parentElement.querySelector('.select-search-results')?.setAttribute('aria-hidden', 'true');
    input.focus();
}

function addSearch(select) {
    if (controls.has(select) || select.multiple || select.closest('template')) {
        return;
    }

    const wrapper = document.createElement('div');
    const input = document.createElement('input');
    const list = document.createElement('div');
    const listId = `select-search-list-${++nextListId}`;
    const labelElement = select.labels?.[0]?.cloneNode(true);
    labelElement?.querySelectorAll('select, input, button').forEach((element) => element.remove());
    const label = labelElement?.textContent.trim()
        || select.getAttribute('aria-label')
        || select.closest('div')?.querySelector('label')?.textContent.trim()
        || 'opciones';

    wrapper.className = 'select-search-control';
    input.type = 'search';
    const inheritedClasses = select.className
        .split(/\s+/)
        .filter((name) => name && !/(^|:)hidden$/.test(name))
        .join(' ');
    input.className = `select-search-input ${inheritedClasses}`;
    input.placeholder = `Escribe código o palabra para buscar ${label}`;
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-label', label);
    input.id = `select-search-input-${nextListId}`;
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    Array.from(select.labels || []).forEach((label) => {
        if (label.htmlFor === select.id) {
            label.htmlFor = input.id;
        }
    });
    if (select.getAttribute('aria-describedby')) {
        input.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
    }
    input.setCustomValidity('');
    list.id = listId;
    list.className = 'select-search-results';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-hidden', 'true');

    const wasRequired = select.required;
    select.required = false;
    select.classList.add('select-search-native');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;
    select.before(wrapper);
    wrapper.append(input, list);
    controls.set(select, { input, list });
    syncSelection(select, input);

    const syncState = () => {
        const hidden = select.hidden || window.getComputedStyle(select).display === 'none';
        wrapper.hidden = hidden;
        input.disabled = select.matches(':disabled');
        input.required = wasRequired && !input.disabled && !hidden;
    };
    syncState();
    new MutationObserver(syncState).observe(select, {
        attributes: true,
        attributeFilter: ['class', 'hidden', 'disabled', 'style'],
    });
    window.addEventListener('resize', syncState);

    input.addEventListener('focus', () => {
        if (select.value) {
            input.select();
        }
        renderOptions(select, input, list);
    });
    input.addEventListener('input', () => {
        const query = normalize(input.value.trim());
        const options = availableOptions(select);
        const exact = options.filter((option) =>
            normalize(option.value) === query || normalize(optionLabel(option, options)) === query
        );

        if (exact.length === 1) {
            chooseOption(select, input, exact[0]);
        } else {
            select.value = '';
            input.setCustomValidity(input.value.trim() ? 'Elige una opción de la lista.' : '');
        }

        renderOptions(select, input, list);
    });
    input.addEventListener('change', () => {
        const query = normalize(input.value.trim());
        const options = availableOptions(select);
        const matches = options.filter((option) =>
            normalize(option.value) === query || normalize(optionLabel(option, options)) === query
        );
        if (matches.length === 1) {
            chooseOption(select, input, matches[0]);
        }
    });
    input.addEventListener('keydown', (event) => {
        const suggestions = Array.from(list.querySelectorAll('.select-search-option'));
        const selectedIndex = suggestions.findIndex((suggestion) => suggestion.getAttribute('aria-selected') === 'true');

        if (event.key === 'Escape') {
            list.setAttribute('aria-hidden', 'true');
            input.setAttribute('aria-expanded', 'false');
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (suggestions.length === 0) {
                return;
            }

            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            const nextIndex = (selectedIndex + direction + suggestions.length) % suggestions.length;
            suggestions.forEach((suggestion, index) => {
                suggestion.setAttribute('aria-selected', String(index === nextIndex));
            });
        }

        if (event.key === 'Enter' && suggestions.length > 0) {
            const activeOption = suggestions[selectedIndex] || suggestions[0];
            const option = availableOptions(select).find((candidate) => candidate.value === activeOption.dataset.value);
            if (option) {
                event.preventDefault();
                chooseOption(select, input, option);
            }
        }
    });
    input.addEventListener('blur', () => {
        if (select.value) {
            syncSelection(select, input);
        }
        list.setAttribute('aria-hidden', 'true');
        input.setAttribute('aria-expanded', 'false');
    });
    select.addEventListener('change', () => syncSelection(select, input));
    new MutationObserver(() => {
        renderOptions(select, input, list);
        if (select.value) {
            syncSelection(select, input);
        }
    }).observe(select, { childList: true, subtree: true, attributes: true });
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
