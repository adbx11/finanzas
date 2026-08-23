export const mainNav = [
    { name: 'Inicio', href: 'dashboard', route: 'dashboard' },
    { name: 'Ingresos', href: 'ingresos.index', route: 'ingresos.*', resetListState: 'ingresos.index' },
    { name: 'Pagos', href: 'pagos.index', route: 'pagos.*', resetListState: 'pagos.index' },
    { name: 'Tarjetas', href: 'tarjetas.index', route: 'tarjetas.*' },
    { name: 'Cotizaciones', href: 'cotizaciones.index', route: 'cotizaciones.*' },
    { name: 'Conciliación', href: 'conciliacion.index', route: 'conciliacion.*' },
    { name: 'Asientos', href: 'asientos.index', route: 'asientos.*', resetListState: 'asientos.index' },
    { name: 'Crypto', href: 'crypto.index', route: 'crypto.*', resetListState: 'crypto.index' },
];

export const configNav = [
    { name: 'Cuentas', href: 'cuentas.index', route: 'cuentas.*' },
    { name: 'Monedas', href: 'monedas.index', route: 'monedas.*' },
    { name: 'Coins', href: 'crypto-coins.index', route: 'crypto-coins.*' },
    { name: 'Wallets', href: 'crypto-wallets.index', route: 'crypto-wallets.*' },
    { name: 'Configuración', href: 'configuracion.index', route: 'configuracion.*' },
    { name: 'Backup', href: 'backup.index', route: 'backup.*', adminOnly: true },
    { name: 'Usuarios', href: 'usuarios.index', route: 'usuarios.*', adminOnly: true },
    { name: 'Roles', href: 'roles.index', route: 'roles.*', adminOnly: true },
];

export const reportNav = [
    { name: 'Balance', href: 'informes.balance', route: 'informes.balance' },
    { name: 'Mayor', href: 'informes.mayor', route: 'informes.mayor' },
    { name: 'Gastos', href: 'informes.gastos', route: 'informes.gastos' },
    { name: 'Vencimientos de TC', href: 'informes.vencimientos-tc', route: 'informes.vencimientos-tc' },
    { name: 'Intereses', href: 'informes.intereses', route: 'informes.intereses' },
    { name: 'Evolución patrimonial', href: 'informes.evolucion-patrimonial', route: 'informes.evolucion-patrimonial' },
    { name: 'Evolución por cuenta', href: 'informes.evolucion-cuenta', route: 'informes.evolucion-cuenta' },
    { name: 'Crypto', href: 'informes.crypto', route: 'informes.crypto' },
];
