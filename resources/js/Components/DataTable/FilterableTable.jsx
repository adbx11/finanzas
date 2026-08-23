import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';

function matchesFilter(value, filter) {
    if (!filter) {
        return true;
    }

    const haystack = String(value ?? '').toLowerCase();
    const needle = String(filter).toLowerCase().trim();

    return haystack.includes(needle);
}

function filterRows(rows, columns, filters) {
    return rows.filter((row) => columns.every((column) => {
        if (!column.filterKey || column.filterable === false) {
            return true;
        }

        const filterValue = filters[column.filterKey];
        if (!String(filterValue ?? '').trim()) {
            return true;
        }

        if (column.filterValue) {
            return matchesFilter(column.filterValue(row), filterValue);
        }

        return matchesFilter(row[column.filterKey], filterValue);
    }));
}

function orderColumns(columns) {
    const actions = [];
    const rest = [];
    for (const column of columns) {
        if (column.key === 'actions') {
            actions.push(column);
        } else {
            rest.push(column);
        }
    }
    return [...actions, ...rest];
}

function isActionsColumn(column) {
    return column.key === 'actions';
}

function SortIndicator({ active, direction }) {
    if (!active) {
        return <span className="ml-1 text-slate-300 dark:text-slate-600">↕</span>;
    }

    return <span className="ml-1 text-emerald-600 dark:text-emerald-400">{direction === 'asc' ? '↑' : '↓'}</span>;
}

export default function FilterableTable({
    columns,
    rows,
    filters,
    onFilterChange,
    onClearFilters,
    hasActiveFilters = false,
    sort = null,
    direction = 'asc',
    onSortChange,
    clientSide = false,
    pagination = null,
    onPageChange = null,
    emptyMessage = 'Sin registros.',
    rowKey = 'id',
}) {
    const displayRows = clientSide ? filterRows(rows, columns, filters) : rows;
    const orderedColumns = orderColumns(columns);

    return (
        <div className="space-y-3">
            {hasActiveFilters && onClearFilters && (
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={onClearFilters}
                        className="text-sm text-slate-600 hover:text-slate-900 underline dark:text-slate-400 dark:hover:text-slate-200"
                    >
                        Limpiar filtros
                    </button>
                </div>
            )}

            <div className="bg-white shadow-sm border border-slate-200 overflow-x-auto -mx-4 sm:mx-0 rounded-none sm:rounded-lg border-x-0 sm:border-x dark:bg-slate-900 dark:border-slate-700">
                <table className="min-w-[720px] w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead className="bg-slate-50 dark:bg-slate-800/80">
                        <tr>
                            {orderedColumns.map((column) => {
                                const isSortable = column.sortable !== false && column.sortKey && onSortChange;
                                const isActive = sort === column.sortKey;
                                const actions = isActionsColumn(column);

                                return (
                                    <th
                                        key={column.key}
                                        className={`px-2 sm:px-3 py-2 text-left font-medium text-slate-600 whitespace-nowrap dark:text-slate-300 ${
                                            column.align === 'right' && !actions ? 'text-right' : ''
                                        } ${
                                            actions
                                                ? 'sticky left-0 z-20 bg-slate-50 dark:bg-slate-800 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.12)]'
                                                : ''
                                        }`}
                                    >
                                        {isSortable ? (
                                            <button
                                                type="button"
                                                onClick={() => onSortChange(column.sortKey)}
                                                className={`inline-flex items-center hover:text-slate-900 dark:hover:text-slate-100 ${isActive ? 'text-emerald-700 dark:text-emerald-400' : ''} ${column.align === 'right' && !actions ? 'ml-auto' : ''}`}
                                            >
                                                {column.header}
                                                <SortIndicator active={isActive} direction={direction} />
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                        <tr className="bg-white border-t border-slate-100 dark:bg-slate-900 dark:border-slate-700">
                            {orderedColumns.map((column) => {
                                const actions = isActionsColumn(column);
                                return (
                                    <th
                                        key={`${column.key}-filter`}
                                        className={`px-2 py-1.5 ${
                                            actions
                                                ? 'sticky left-0 z-20 bg-white dark:bg-slate-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.12)]'
                                                : ''
                                        }`}
                                    >
                                        {column.filterable !== false && column.filterKey ? (
                                            <TextInput
                                                value={filters[column.filterKey] ?? ''}
                                                onChange={(e) => onFilterChange(column.filterKey, e.target.value)}
                                                placeholder={column.filterPlaceholder ?? 'Buscar...'}
                                                className="block w-full text-xs py-1"
                                            />
                                        ) : null}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {displayRows.length === 0 ? (
                            <tr>
                                <td colSpan={orderedColumns.length} className="px-3 py-8 text-center text-slate-500">
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            displayRows.map((row, rowIndex) => {
                                const striped = rowIndex % 2 === 1;
                                const rowBg = striped
                                    ? 'bg-slate-50/80 dark:bg-slate-800/40'
                                    : 'bg-white dark:bg-slate-900';
                                const stickyBg = striped
                                    ? 'bg-slate-50 dark:bg-slate-800'
                                    : 'bg-white dark:bg-slate-900';

                                return (
                                    <tr
                                        key={row[rowKey] ?? row.id}
                                        className={`${rowBg} hover:bg-emerald-50/70 dark:hover:bg-emerald-950/40 transition-colors`}
                                    >
                                        {orderedColumns.map((column) => {
                                            const actions = isActionsColumn(column);
                                            return (
                                                <td
                                                    key={`${row[rowKey] ?? row.id}-${column.key}`}
                                                    className={`px-2 sm:px-3 py-2 whitespace-nowrap ${
                                                        column.align === 'right' && !actions ? 'text-right' : ''
                                                    } ${column.className ?? ''} ${
                                                        actions
                                                            ? `sticky left-0 z-10 ${stickyBg} shadow-[2px_0_4px_-2px_rgba(0,0,0,0.12)]`
                                                            : ''
                                                    }`}
                                                >
                                                    {column.render ? column.render(row) : row[column.key]}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {pagination?.links?.length > 3 && (
                <div className="flex flex-wrap gap-2">
                    {pagination.links.map((link, index) => {
                        const pageFromUrl = (() => {
                            if (!link.url) {
                                return null;
                            }
                            try {
                                return Number(new URL(link.url, window.location.origin).searchParams.get('page') || '1');
                            } catch {
                                return null;
                            }
                        })();

                        const className = `px-3 py-1 rounded border text-sm ${
                            link.active
                                ? 'bg-emerald-600 text-white border-emerald-600'
                                : 'bg-white text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600'
                        } ${!link.url ? 'opacity-50 pointer-events-none' : ''}`;

                        if (onPageChange) {
                            return (
                                <button
                                    key={index}
                                    type="button"
                                    disabled={!link.url || pageFromUrl == null}
                                    onClick={() => onPageChange(pageFromUrl)}
                                    className={className}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            );
                        }

                        return (
                            <Link
                                key={index}
                                href={link.url || '#'}
                                preserveScroll
                                preserveState
                                className={className}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    })}
                </div>
            )}
        </div>
    );
}
