import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';

function buildPayload(extraParams, filters, sort, direction, page = 1) {
    const payload = { ...extraParams, page };

    const hasFilters = Object.values(filters).some((v) => String(v ?? '').trim() !== '');
    if (hasFilters) {
        payload.filters = filters;
    }

    if (sort) {
        payload.sort = sort;
        payload.direction = direction || 'asc';
    }

    return payload;
}

export function useDataTableQuery(
    routeName,
    { filters: initialFilters = {}, sort: initialSort = null, direction: initialDirection = 'asc' } = {},
    extraParams = {},
) {
    const [filters, setFilters] = useState(initialFilters ?? {});
    const [sort, setSort] = useState(initialSort);
    const [direction, setDirection] = useState(initialDirection ?? 'asc');

    useEffect(() => {
        setFilters(initialFilters ?? {});
    }, [JSON.stringify(initialFilters)]);

    useEffect(() => {
        setSort(initialSort ?? null);
        setDirection(initialDirection ?? 'asc');
    }, [initialSort, initialDirection]);

    const stableExtra = useMemo(() => extraParams, [JSON.stringify(extraParams)]);

    const navigate = (nextFilters, nextSort, nextDirection, page = 1) => {
        router.get(
            route(routeName),
            buildPayload(stableExtra, nextFilters, nextSort, nextDirection, page),
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const pushFilters = useDebouncedCallback((nextFilters) => {
        navigate(nextFilters, sort, direction, 1);
    }, 400);

    const setFilter = (key, value) => {
        const next = { ...filters, [key]: value };
        if (!String(value ?? '').trim()) {
            delete next[key];
        }
        setFilters(next);
        pushFilters(next);
    };

    const clearFilters = () => {
        setFilters({});
        pushFilters({});
    };

    const toggleSort = (sortKey) => {
        if (!sortKey) {
            return;
        }

        let nextSort = sortKey;
        let nextDirection = 'asc';

        if (sort === sortKey) {
            nextDirection = direction === 'asc' ? 'desc' : 'asc';
        }

        setSort(nextSort);
        setDirection(nextDirection);
        navigate(filters, nextSort, nextDirection, 1);
    };

    const goToPage = (page) => {
        navigate(filters, sort, direction, Number(page) || 1);
    };

    const hasActiveFilters = Object.values(filters).some((v) => String(v ?? '').trim() !== '');

    const queryParams = buildPayload(stableExtra, filters, sort, direction);

    return {
        filters,
        sort,
        direction,
        setFilter,
        clearFilters,
        toggleSort,
        goToPage,
        hasActiveFilters,
        queryParams,
    };
}
