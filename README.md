# ADB Finanzas v2

Laravel 10 + Inertia + React. Migración del sistema legacy de finanzas personales.

## Requisitos

- PHP 8.1+ con extensiones `pdo_mysql`, `mbstring`, `bcmath`
- Composer
- Node.js 20+
- MySQL 8 con base `finanzas_dev` (datos copiados desde producción)

## Configuración inicial

1. Copiar variables de entorno:

```bash
cp .env.example .env
php artisan key:generate
```

2. Editar `.env` con credenciales MySQL (mismas que `scripts/.env.sync` destino):

```env
DB_DATABASE=finanzas_dev
DB_USERNAME=finanzas_dev
DB_PASSWORD=tu_password
```

3. Migrar tablas nuevas de Laravel (users, roles, etc.) **sin tocar tablas legacy**:

```bash
php artisan migrate
php artisan db:seed --class=RolesSeeder
php artisan finanzas:import-users --password=dev123456
```

4. Instalar y compilar frontend:

```bash
npm install
npm run dev
```

5. En otra terminal:

```bash
php artisan serve
```

En Windows, para levantar ambos de una:

```bat
dev.bat
```

6. Verificar datos:

```bash
php artisan finanzas:db-verify
```

## Copiar datos de producción

Ver [scripts/README.md](scripts/README.md).

## Scheduler (cron)

En el servidor, un solo cron cada minuto:

```bash
* * * * * cd /var/www/finanzas && php artisan schedule:run >> /dev/null 2>&1
```

Tareas programadas (`app/Console/Kernel.php`):

| Tarea | Cuándo | Comando |
|-------|--------|---------|
| Cotizaciones fiat | Cada hora, 9:00–17:00 (`APP_TIMEZONE`) | `FetchCotizacionesJob` (fiat) |
| Cotizaciones crypto (BTC) | Cada hora, 24 hs | `FetchCotizacionesJob` (btc) |
| Backup BD | Diario 03:00 | `backup:run --only-db` |
| Limpieza backups | Diario 03:30 | `backup:clean` |

Ventana de cotizaciones también configurable en `configuracion`: `cotizacion.job.hora_desde` / `hora_hasta`.

En local (Windows), para probar el scheduler:

```bash
php artisan schedule:work
# o
php artisan schedule:list
php artisan schedule:run
```

Fetch manual: UI Cotizaciones o `php artisan finanzas:fetch-cotizaciones`.

## Actualizar en servidor

```bash
cd /var/www/finanzas && ./update.sh
```

Detalle: [docs/DEPLOY_UBUNTU.md](docs/DEPLOY_UBUNTU.md).

## Backup de base de datos

Usa [spatie/laravel-backup](https://github.com/spatie/laravel-backup) (solo MySQL).

Pantalla **Configuración → Backup** (solo admin): estado por destino, última corrida y botón para disparar manualmente.

```bash
# Requiere mysqldump en el PATH
php artisan finanzas:backup
php artisan backup:list
```

- Destino local: `storage/app/{APP_NAME}/`
- FTP opcionales: configurar `BACKUP_DISKS=local,backup_ftp` (+ `BACKUP_FTP_*`). Segundo FTP: `backup_ftp_2` / `BACKUP_FTP_2_*`
- PHP necesita `ext-ftp` en el servidor para los destinos FTP
- Compresión: zip del backup; `BACKUP_DUMP_GZIP=true` en Linux si hay `gzip` en PATH
- Retención: `backup:clean` diario 03:30
- Aviso por mail si falla: definir `BACKUP_NOTIFICATION_EMAIL` en `.env`
- Timezone: `APP_TIMEZONE=America/Argentina/Buenos_Aires`

Restaurar (ejemplo):

```bash
# Extraer el .sql del zip del backup y:
mysql -u USER -p finanzas_dev < dump.sql
```

## Módulos implementados (fase inicial)

- Login, Dashboard con conteos
- Monedas (CRUD)
- Cuentas (CRUD)
- Ingresos (listado por mes, alta/edición con asiento automático)
- Pagos (listado por mes, alta/edición con asiento automático y cuotas de tarjeta)
- Asientos (listado por rango de fechas, alta/edición manual con ítems)
- Informes: Balance, Mayor

## Documentación

- [docs/DEPLOY_UBUNTU.md](docs/DEPLOY_UBUNTU.md) — install en Ubuntu + MySQL + Nginx
- [docs/PROJECT_MEMORY.md](docs/PROJECT_MEMORY.md) — contexto del proyecto para nuevos chats
- [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md)
- [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md)
- [docs/MIGRATION_PLAN.md](docs/MIGRATION_PLAN.md)
