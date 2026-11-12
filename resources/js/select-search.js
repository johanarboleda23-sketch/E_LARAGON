const normalize = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const controls = new WeakMap();
let nextListId = 0;

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
    list.replaceChildren(...options
        .filter((option) => normalize(`${option.text} ${option.value}`).includes(query))
        .map((option) => {
            const suggestion = document.createElement('option');
            suggestion.value = optionLabel(option, options);
            return suggestion;
        }));
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
    input.focus();
}

function addSearch(select) {
    if (controls.has(select) || select.multiple || select.closest('template')) {
        return;
    }

    const wrapper = document.createElement('div');
    const input = document.createElement('input');
    const list = document.createElement('datalist');
    const listId = `select-search-list-${++nextListId}`;
    const labelElement = select.labels?.[0]?.cloneNode(true);
    labelElement?.querySelectorAll('select, input, button').forEach((element) => element.remove());
    const label = labelElement?.textContent.trim()
        || select.getAttribute('aria-label')
        || 'opciones';

    wrapper.className = 'select-search-control';
    input.type = 'search';
    const inheritedClasses = select.className
        .split(/\s+/)
        .filter((name) => name && !/(^|:)hidden$/.test(name))
        .join(' ');
    input.className = `select-search-input ${inheritedClasses}`;
    input.placeholder = `Escribe código o palabra para buscar ${label}`;
    input.setAttribute('list', listId);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-label', label);
    input.id = `select-search-input-${nextListId}`;
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
    input.addEventListener('blur', () => {
        if (select.value) {
            syncSelection(select, input);
        }
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
