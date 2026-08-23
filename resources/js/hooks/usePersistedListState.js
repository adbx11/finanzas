import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { listStateToQuery, loadListState, saveListState } from '@/utils/listState';

function urlHasQuery(url) {
    const q = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
    return [...new URLSearchParams(q).keys()].length > 0;
}

/**
 * Persiste el query del listado en sessionStorage y lo restaura
 * al volver sin query (p. ej. tras crear/editar/eliminar). El menú debe
 * llamar clearListState(routeName) para resetear.
 *
 * Observa usePage().url para re-restaurar cuando, sin remount
 * (p. ej. delete → redirect al index), la URL pierde el query.
 */
export function usePersistedListState(routeName, state, options = {}) {
    const { enabled = true } = options;
    const pageUrl = usePage().url;
    const restoringRef = useRef(false);

    useEffect(() => {
        if (!enabled || restoringRef.current) {
            return;
        }

        if (urlHasQuery(pageUrl)) {
            return;
        }

        const saved = loadListState(routeName);
        if (!saved) {
            return;
        }

        restoringRef.current = true;
        router.get(route(routeName), listStateToQuery(saved), {
            replace: true,
            preserveState: false,
            preserveScroll: true,
            onFinish: () => {
                restoringRef.current = false;
            },
        });
    }, [routeName, enabled, pageUrl]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        // Evitar pisar el estado guardado con los defaults del primer render
        // antes de restaurar (p. ej. al volver del formulario o tras eliminar).
        if (!urlHasQuery(pageUrl) && loadListState(routeName)) {
            return;
        }

        saveListState(routeName, { ...state, _ts: Date.now() });
    }, [routeName, enabled, pageUrl, JSON.stringify(state)]);
}
