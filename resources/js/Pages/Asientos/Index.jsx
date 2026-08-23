import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import FilterableTable from '@/Components/DataTable/FilterableTable';
import RowActions from '@/Components/DataTable/RowActions';
import PrimaryButton from '@/Components/PrimaryButton';
import { useDataTableQuery } from '@/hooks/useDataTableQuery';
import { usePersistedListState } from '@/hooks/usePersistedListState';
import { formatDateAR } from '@/utils/dateFormat';
import { Head, router } from '@inertiajs/react';

const columns = [
    {
        key: 'fecha',
        header: 'Fecha',
        filterKey: 'fecha',
        sortKey: 'fecha',
        render: (row) => formatDateAR(row.fecha),
    },
    {
        key: 'descripcion',
        header: 'Descripción',
        filterKey: 'descripcion',
        sortKey: 'descripcion',
        render: (row) => row.descripcion,
    },
    {
        key: 'importe',
        header: 'Importe',
        filterKey: 'importe',
        sortKey: 'importe',
        align: 'right',
        className: 'font-mono',
        render: (row) => Number(row.total_debe || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }),
    },
    {
        key: 'id',
        header: 'ID',
        filterKey: 'id',
        sortKey: 'id',
        render: (row) => row.id,
    },
    {
        key: 'actions',
        header: '',
        filterable: false,
        sortable: false,
        align: 'right',
        render: (row) => (
            <RowActions
                editHref={route('asientos.edit', row.id)}
                copyHref={route('asientos.create', { from: row.id })}
                destroyRoute="asientos.destroy"
                destroyId={row.id}
                destroyMessage="¿Eliminar asiento?"
            />
        ),
    },
];

export default function Index({ asientos, desde, hasta, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const rangeParams = { desde: desde ?? '', hasta: hasta ?? '' };
    const {
        filters,
        sort,
        direction,
        setFilter,
        clearFilters,
        toggleSort,
        goToPage,
        hasActiveFilters,
        queryParams,
    } = useDataTableQuery(
        'asientos.index',
        { filters: initialFilters, sort: initialSort, direction: initialDirection },
        rangeParams,
    );

    usePersistedListState('asientos.index', {
        ...queryParams,
        ...rangeParams,
        page: asientos.current_page,
    });

    const changeRange = (next) => {
        router.get(
            route('asientos.index'),
            { ...queryParams, ...rangeParams, ...next, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const goCreate = () => {
        router.visit(route('asientos.create'));
    };

    return (
        <AdminLayout header="Asientos">
            <Head title="Asientos" />

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <PrimaryButton onClick={goCreate}>Nuevo asiento</PrimaryButton>
                <div className="flex flex-wrap items-center gap-2">
                    <label className="text-sm text-slate-600">Desde</label>
                    <DateFilterInput
                        value={desde || ''}
                        allowEmpty
                        onCommit={(value) => changeRange({ desde: value ?? '' })}
                    />
                    <label className="text-sm text-slate-600">Hasta</label>
                    <DateFilterInput
                        value={hasta || ''}
                        allowEmpty
                        onCommit={(value) => changeRange({ hasta: value ?? '' })}
                    />
                </div>
            </div>

            <FilterableTable
                columns={columns}
                rows={asientos.data}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
                pagination={asientos}
                onPageChange={goToPage}
            />
        </AdminLayout>
    );
}
