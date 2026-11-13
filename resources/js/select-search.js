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
    .select-search-results {
        position: fixed;
        z-index: 9999;
        display: none;
        max-height: 12rem;
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
    .select-search-empty {
        padding: .5rem .65rem;
        color: #71807a;
        font-size: .7rem;
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
    .select-search-input:disabled {
        cursor: not-allowed;
        opacity: .6;
    }
`;
document.head.appendChild(style);

function availableOptions(select) {
    return Array.from(select.options).filter((option) =>
        (!select.required || option.value !== '') && !option.hidden && !option.matches(':disabled') && !option.parentElement?.hidden
    );
}

function optionLabel(option, options) {
    const text = option.text.trim();
    return options.filter((candidate) => candidate.text.trim() === text).length > 1
        ? `${text} (${option.value})`
        : text;
}

function positionList(input, list) {
    const rect = input.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const height = Math.min(192, Math.max(spaceBelow, rect.top) - 8);
    list.style.left = `${rect.left}px`;
    list.style.width = `${rect.width}px`;
    list.style.maxHeight = `${Math.max(height, 40)}px`;
    list.style.top = spaceBelow < 120 && rect.top > spaceBelow
        ? `${Math.max(4, rect.top - Math.min(list.scrollHeight, height) - 4)}px`
        : `${rect.bottom + 4}px`;
}

function renderOptions(select, input, list, searchValue = input.value) {
    const query = normalize(searchValue.trim());
    const options = availableOptions(select);
    const labelCounts = new Map();
    options.forEach((option) => {
        const label = option.text.trim();
        labelCounts.set(label, (labelCounts.get(label) || 0) + 1);
    });
    const matches = query
        ? options.filter((option) => normalize(`${option.text} ${option.value}`).includes(query))
        : options;

    list.replaceChildren();
    const fragment = document.createDocumentFragment();
    matches.forEach((option) => {
        const suggestion = document.createElement('button');
        suggestion.type = 'button';
        suggestion.className = 'select-search-option';
        suggestion.setAttribute('role', 'option');
        suggestion.setAttribute('aria-selected', 'false');
        suggestion.dataset.value = option.value;
        const label = option.text.trim();
        suggestion.textContent = labelCounts.get(label) > 1 ? `${label} (${option.value})` : label;
        suggestion.addEventListener('mousedown', (event) => event.preventDefault());
        suggestion.addEventListener('click', () => chooseOption(select, input, option));
        fragment.append(suggestion);
    });
    list.append(fragment);

    if (matches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'select-search-empty';
        empty.textContent = 'No hay opciones que coincidan.';
        list.append(empty);
    }

    list.setAttribute('aria-hidden', 'false');
    input.setAttribute('aria-expanded', 'true');
    positionList(input, list);
}

function syncSelection(select, input) {
    const option = select.selectedOptions[0];
    input.value = option && (option.value !== '' || !select.required)
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
    input.type = 'text';
    input.className = 'select-search-input';
    input.placeholder = 'Escribe para buscar o escoge una opción';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
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
        input.required = select.required && !input.disabled && !hidden;
    };
    syncState();
    new MutationObserver(syncState).observe(select, {
        attributes: true,
        attributeFilter: ['class', 'hidden', 'disabled', 'style', 'required'],
    });

    input.addEventListener('focus', () => {
        if (input.value) {
            input.select();
        }
        renderOptions(select, input, list, '');
    });
    input.addEventListener('click', () => {
        if (input.getAttribute('aria-expanded') === 'false') {
            renderOptions(select, input, list, '');
        }
    });
    input.addEventListener('input', () => {
        if (select.value !== '') {
            select.value = '';
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        input.setCustomValidity(input.value.trim() ? 'Elige una opción de la lista.' : '');
        renderOptions(select, input, list);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            list.setAttribute('aria-hidden', 'true');
            input.setAttribute('aria-expanded', 'false');
            return;
        }

        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && input.getAttribute('aria-expanded') === 'false') {
            renderOptions(select, input, list, '');
        }
        const suggestions = Array.from(list.querySelectorAll('.select-search-option'));
        const selectedIndex = suggestions.findIndex((suggestion) => suggestion.getAttribute('aria-selected') === 'true');

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (suggestions.length === 0) {
                return;
            }

            event.preventDefault();
            const nextIndex = selectedIndex === -1
                ? (event.key === 'ArrowDown' ? 0 : suggestions.length - 1)
                : (selectedIndex + (event.key === 'ArrowDown' ? 1 : -1) + suggestions.length) % suggestions.length;
            suggestions.forEach((suggestion, index) => {
                suggestion.setAttribute('aria-selected', String(index === nextIndex));
            });
            suggestions[nextIndex].scrollIntoView({ block: 'nearest' });
        }

        if (event.key === 'Enter' && input.getAttribute('aria-expanded') === 'true' && suggestions.length > 0) {
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
    select.addEventListener('change', () => {
        if (document.activeElement !== input || select.value !== '') {
            syncSelection(select, input);
        }
    });
    select.addEventListener('invalid', (event) => {
        event.preventDefault();
        input.setCustomValidity('Elige una opción de la lista.');
        input.reportValidity();
    });
    new MutationObserver(() => {
        if (document.activeElement !== input) {
            syncSelection(select, input);
        }
        if (document.activeElement === input && input.getAttribute('aria-expanded') === 'true') {
            renderOptions(select, input, list);
        }
    }).observe(select, { childList: true, subtree: true, attributes: true });
}

function addDatalistSearch(input) {
    if (controls.has(input) || input.closest('template')) {
        return;
    }

    const datalist = input.list;
    if (!datalist) {
        return;
    }

    const wrapper = document.createElement('div');
    const list = document.createElement('div');
    const listId = `select-search-list-${++nextListId}`;
    wrapper.className = 'select-search-control';
    list.id = listId;
    list.className = 'select-search-results';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-hidden', 'true');
    input.before(wrapper);
    wrapper.append(input, list);
    input.removeAttribute('list');
    input.classList.add('datalist-search-input');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    controls.set(input, { list });

    const close = () => {
        list.setAttribute('aria-hidden', 'true');
        input.setAttribute('aria-expanded', 'false');
    };
    const choose = (option) => {
        input.value = option.value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        input.focus();
    };
    const render = (searchValue = input.value) => {
        const query = normalize(searchValue.trim());
        const options = Array.from(datalist.options).filter((option) =>
            option.value && !option.disabled && !option.hidden
            && (!query || normalize(`${option.value} ${option.label}`).includes(query))
        );
        const fragment = document.createDocumentFragment();
        options.forEach((option) => {
            const suggestion = document.createElement('button');
            suggestion.type = 'button';
            suggestion.className = 'select-search-option';
            suggestion.setAttribute('role', 'option');
            suggestion.setAttribute('aria-selected', 'false');
            suggestion.textContent = option.label || option.value;
            suggestion.addEventListener('mousedown', (event) => event.preventDefault());
            suggestion.addEventListener('click', () => choose(option));
            fragment.append(suggestion);
        });
        list.replaceChildren(fragment);
        if (options.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'select-search-empty';
            empty.textContent = 'No hay opciones que coincidan.';
            list.append(empty);
        }
        list.setAttribute('aria-hidden', 'false');
        input.setAttribute('aria-expanded', 'true');
        positionList(input, list);
    };

    input.addEventListener('focus', () => render(''));
    input.addEventListener('click', () => {
        if (input.getAttribute('aria-expanded') === 'false') {
            render('');
        }
    });
    input.addEventListener('input', () => render());
    input.addEventListener('blur', close);
    input.addEventListener('keydown', (event) => {
        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && input.getAttribute('aria-expanded') === 'false') {
            render('');
        }
        const suggestions = Array.from(list.querySelectorAll('.select-search-option'));
        const selectedIndex = suggestions.findIndex((suggestion) => suggestion.getAttribute('aria-selected') === 'true');
        if (event.key === 'Escape') {
            close();
        } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && suggestions.length) {
            event.preventDefault();
            const nextIndex = selectedIndex === -1
                ? (event.key === 'ArrowDown' ? 0 : suggestions.length - 1)
                : (selectedIndex + (event.key === 'ArrowDown' ? 1 : -1) + suggestions.length) % suggestions.length;
            suggestions.forEach((suggestion, index) => {
                suggestion.setAttribute('aria-selected', String(index === nextIndex));
            });
            suggestions[nextIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && suggestions.length && input.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            (suggestions[selectedIndex] || suggestions[0]).click();
        }
    });
}

function addSearches(root) {
    if (root instanceof HTMLSelectElement) {
        addSearch(root);
    } else if (root instanceof HTMLInputElement && root.hasAttribute('list')) {
        addDatalistSearch(root);
    } else if (root instanceof Element || root instanceof Document) {
        root.querySelectorAll('select').forEach(addSearch);
        root.querySelectorAll('input[list]').forEach(addDatalistSearch);
    }
}

addSearches(document);
new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach(addSearches));
}).observe(document.body, { childList: true, subtree: true });

document.addEventListener('reset', (event) => {
    window.setTimeout(() => {
        event.target.querySelectorAll('select').forEach((select) => {
            const control = controls.get(select);
            if (control) {
                syncSelection(select, control.input);
            }
        });
    }, 0);
});

window.addEventListener('scroll', () => {
    const input = document.activeElement;
    if (input?.matches('.select-search-input[aria-expanded="true"], .datalist-search-input[aria-expanded="true"]')) {
        positionList(input, input.parentElement.querySelector('.select-search-results'));
    }
}, true);
window.addEventListener('resize', () => {
    const input = document.activeElement;
    if (input?.matches('.select-search-input[aria-expanded="true"], .datalist-search-input[aria-expanded="true"]')) {
        positionList(input, input.parentElement.querySelector('.select-search-results'));
    }
});
