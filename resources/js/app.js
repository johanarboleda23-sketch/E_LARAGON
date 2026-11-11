

import Alpine from 'alpinejs';
import './select-search';

window.Alpine = Alpine;

const normalizeSearchText = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const selectSearchBuffers = new WeakMap();
window.normalizeSearchText = normalizeSearchText;

document.addEventListener('focusin', (event) => {
    const select = event.target;
    if (select instanceof HTMLSelectElement && !select.multiple) {
        const state = selectSearchBuffers.get(select) ?? {
            query: '',
            originalValue: select.value,
            originalTitle: select.getAttribute('title'),
            timeout: null,
            pendingValue: null,
        };
        selectSearchBuffers.set(select, state);
        select.title = 'Escribe un código o palabra clave para buscar entre las opciones.';
    }
});

document.addEventListener('focusout', (event) => {
    const select = event.target;
    const state = selectSearchBuffers.get(select);
    if (!(select instanceof HTMLSelectElement) || select.multiple || !state) {
        return;
    }

    window.clearTimeout(state.timeout);
    commitSelectSearch(select, state);
    select.title = state.originalTitle ?? '';
    selectSearchBuffers.delete(select);
});

document.addEventListener('keydown', (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || select.multiple || select.disabled) {
        return;
    }

    const state = selectSearchBuffers.get(select);
    if (event.key === 'Escape') {
        if (state && state.pendingValue !== null) {
            select.value = state.originalValue;
        }
        if (state) {
            window.clearTimeout(state.timeout);
            state.query = '';
            state.pendingValue = null;
            select.title = 'Escribe un código o palabra clave para buscar entre las opciones.';
        }
        return;
    }

    if (event.key === 'Backspace') {
        if (!state?.query) {
            return;
        }

        event.preventDefault();
        state.query = state.query.slice(0, -1);
        selectOptionMatching(select, state);
        scheduleSelectSearchCommit(select, state);
        return;
    }

    if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
        if (state && state.pendingValue !== null && ['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
            commitSelectSearch(select, state);
        }
        return;
    }

    event.preventDefault();

    const currentState = state ?? {
        query: '',
        originalTitle: select.getAttribute('title'),
        originalValue: select.value,
        timeout: null,
        pendingValue: null,
    };
    if (!currentState.query) {
        currentState.originalValue = select.value;
    }
    currentState.query += event.key;
    selectOptionMatching(select, currentState);
    selectSearchBuffers.set(select, currentState);
    scheduleSelectSearchCommit(select, currentState);
});

function selectOptionMatching(select, state) {
    const normalizedQuery = normalizeSearchText(state.query.trim());
    if (!normalizedQuery) {
        state.pendingValue = null;
        return;
    }

    const option = Array.from(select.options).find((candidate) => {
        if (candidate.matches(':disabled') || candidate.value === '') {
            return false;
        }

        return normalizeSearchText(`${candidate.text} ${candidate.value}`).includes(normalizedQuery);
    });

    state.pendingValue = option?.value ?? null;
    select.title = option
        ? `Coincidencia: ${option.text}. Presiona Enter o espera para seleccionar.`
        : 'No hay coincidencias. Sigue escribiendo o usa Retroceso para corregir.';
}

function scheduleSelectSearchCommit(select, state) {
    window.clearTimeout(state.timeout);
    state.timeout = window.setTimeout(() => {
        commitSelectSearch(select, state);
        state.query = '';
    }, 900);
}

function commitSelectSearch(select, state) {
    if (state.pendingValue === null) {
        return;
    }

    if (select.value !== state.pendingValue) {
        select.value = state.pendingValue;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    state.originalValue = select.value;
    state.pendingValue = null;
}

Alpine.start();
