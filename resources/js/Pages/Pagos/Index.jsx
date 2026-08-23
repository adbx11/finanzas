import AdminLayout from '@/Layouts/AdminLayout';
import FilterableTable from '@/Components/DataTable/FilterableTable';
import RowActions from '@/Components/DataTable/RowActions';
import PeriodFilters from '@/Components/PeriodFilters';
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
        key: 'concepto',
        header: 'Concepto',
        filterKey: 'concepto',
        sortKey: 'concepto',
        render: (row) => row.cuenta_concepto?.descripcion,
    },
    {
        key: 'origen',
        header: 'Origen',
        filterKey: 'origen',
        sortKey: 'origen',
        render: (row) => row.cuenta_origen?.descripcion,
    },
    {
        key: 'moneda',
        header: 'Moneda',
        filterKey: 'moneda',
        sortKey: 'moneda',
        render: (row) => row.moneda?.simbolo,
    },
    {
        key: 'importe',
        header: 'Importe',
        filterKey: 'importe',
        sortKey: 'importe',
        align: 'right',
        className: 'font-mono',
        render: (row) => Number(row.importe).toLocaleString('es-AR', { minimumFractionDigits: 2 }),
    },
    {
        key: 'comentarios',
        header: 'Comentarios',
        filterKey: 'comentarios',
        sortKey: 'comentarios',
        render: (row) => row.comentarios,
    },
    {
        key: 'actions',
        header: '',
        filterable: false,
        sortable: false,
        align: 'right',
        render: (row) => (
            <RowActions
                editHref={route('pagos.edit', row.id)}
                copyHref={route('pagos.create', { from: row.id })}
                destroyRoute="pagos.destroy"
                destroyId={row.id}
                destroyMessage="¿Eliminar pago?"
            />
        ),
    },
];

export default function Index({ pagos, total, year, month, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
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
        'pagos.index',
        { filters: initialFilters, sort: initialSort, direction: initialDirection },
        { year: year ?? '', month: month ?? '' },
    );

    usePersistedListState('pagos.index', {
        ...queryParams,
        year: year ?? '',
        month: month ?? '',
        page: pagos.current_page,
    });

    const changePeriod = (y, m) => {
        router.get(
            route('pagos.index'),
            { ...queryParams, year: y ?? '', month: m ?? '', page: 1 },
            { preserveState: true },
        );
    };

    return (
        <AdminLayout header={`Pagos — Total: $${total}`}>
            <Head title="Pagos" />

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <PrimaryButton onClick={() => router.visit(route('pagos.create'))}>Nuevo pago</PrimaryButton>
                <PeriodFilters year={year} month={month} onChange={changePeriod} />
            </div>

            <FilterableTable
                columns={columns}
                rows={pagos.data}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
                pagination={pagos}
                onPageChange={goToPage}
            />
        </AdminLayout>
    );
}
