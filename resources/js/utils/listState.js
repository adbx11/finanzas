const prefix = 'finanzas.list.';

export function listStateKey(name) {
    return `${prefix}${name}`;
}

export function saveListState(name, state) {
    try {
        sessionStorage.setItem(listStateKey(name), JSON.stringify(state));
    } catch {
        // ignore quota / private mode
    }
}

export function loadListState(name) {
    try {
        const raw = sessionStorage.getItem(listStateKey(name));
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function clearListState(name) {
    try {
        sessionStorage.removeItem(listStateKey(name));
    } catch {
        // ignore
    }
}

export function listStateToQuery(state) {
    if (!state) {
        return {};
    }

    const query = { ...state };
    delete query._ts;

    if (query.filters && !Object.values(query.filters).some((v) => String(v ?? '').trim() !== '')) {
        delete query.filters;
    }

    if (!query.sort) {
        delete query.sort;
        delete query.direction;
    }

    return query;
}

/** Href al index con filtros/periodo guardados (Cancelar en formularios). */
export function indexHrefFromListState(routeName) {
    const query = listStateToQuery(loadListState(routeName));
    return Object.keys(query).length ? route(routeName, query) : route(routeName);
}
