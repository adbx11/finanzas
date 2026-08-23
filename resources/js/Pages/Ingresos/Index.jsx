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
        key: 'destino',
        header: 'Destino',
        filterKey: 'destino',
        sortKey: 'destino',
        render: (row) => row.cuenta_destino?.descripcion,
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
                editHref={route('ingresos.edit', row.id)}
                copyHref={route('ingresos.create', { from: row.id })}
                destroyRoute="ingresos.destroy"
                destroyId={row.id}
                destroyMessage="¿Eliminar ingreso?"
            />
        ),
    },
];

export default function Index({ ingresos, total, year, month, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
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
        'ingresos.index',
        { filters: initialFilters, sort: initialSort, direction: initialDirection },
        { year: year ?? '', month: month ?? '' },
    );

    usePersistedListState('ingresos.index', {
        ...queryParams,
        year: year ?? '',
        month: month ?? '',
        page: ingresos.current_page,
    });

    const changePeriod = (y, m) => {
        router.get(
            route('ingresos.index'),
            { ...queryParams, year: y ?? '', month: m ?? '', page: 1 },
            { preserveState: true },
        );
    };

    return (
        <AdminLayout header={`Ingresos — Total: $${total}`}>
            <Head title="Ingresos" />

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <PrimaryButton onClick={() => router.visit(route('ingresos.create'))}>Nuevo ingreso</PrimaryButton>
                <PeriodFilters year={year} month={month} onChange={changePeriod} />
            </div>

            <FilterableTable
                columns={columns}
                rows={ingresos.data}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
                pagination={ingresos}
                onPageChange={goToPage}
            />
        </AdminLayout>
    );
}
