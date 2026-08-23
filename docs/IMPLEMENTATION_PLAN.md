# ADB Finanzas v2 — Plan de implementación detallado

**Stack:** Laravel 11 + Inertia.js + React 18 + Tailwind + shadcn/ui  
**Alcance etapa 1:** sin AFIP (definitivo), sin mining; crypto (`coin_transactions`) **sí** incluido en v2  
**Referencias:** `REQUIREMENTS.md`, `MIGRATION_PLAN.md`, esquema `finanzas_nodata.sql`

---

## 1. Decisiones de alcance (etapa 1)

### Incluido

| Área | Módulos |
|------|---------|
| P0 | Auth, layout admin, Dashboard, Monedas, Cuentas, Ingresos, Pagos, Balance |
| P1 | Tarjetas, Cotizaciones, Conciliación, Asientos, Mayor, Gastos, Intereses, Evolución patrimonial, Evolución por cuenta |
| P1 | Configuración (clave/valor), Tipos de movimiento, Plantillas email |
| P2 | FCI (cotizaciones + informe), Informe tarjetas, Plazos fijos |
| P2 | Usuarios y roles simplificados (admin / operador / solo_lectura) |
| Infra | Script copia BD producción → dev, jobs cotizaciones y backup |

### Excluido (definitivo — no implementar)

- Facturación AFIP y tablas `facturas*`, `cuits_emisores`, `puntos_de_venta`, catálogos AFIP (`alicuotas_iva`, `condiciones_iva`, `tipos_doc`, `tipos_comprobante*`)
- Mining (`mining_log`, `mining_payout_log`)
- Cliente SQL web
- Login social OAuth
- Movimientos genéricos (`movimientos` ABM incompleto en legacy) — baja prioridad; tabla candidata a borrar si no se retoma
- Geografía (país/provincia/ciudad/barrio) salvo necesidad futura

### Estrategia de base de datos

**Reutilizar el esquema MySQL existente** en la primera etapa. Laravel se conecta a las mismas tablas (`cuentas`, `asientos`, `ingresos`, etc.) mediante modelos Eloquent con `$table` explícito.

Motivos:

- Los datos de producción siguen siendo válidos sin transformación.
- El script de copia es un dump/restore estándar, reutilizable.
- Las migraciones Laravel nuevas solo agregan lo que falte (tabla `users` moderna, `sessions`, `jobs`, etc.).

Más adelante (etapa 2+) se pueden añadir migraciones incrementales: `utf8mb4`, renombrar columnas, índices, FKs formales.

---

## 2. ¿Qué necesitás hacer antes de que empiece la implementación?

### 2.1 Sí — prerrequisitos obligatorios

| # | Tarea | Detalle |
|---|-------|---------|
| 1 | **Entorno local PHP** | PHP ≥ 8.2 (recomendado 8.3), extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| 2 | **Composer** | Instalado y en PATH |
| 3 | **Node.js** | ≥ 20 LTS + npm (para build de React/Inertia) |
| 4 | **MySQL 8** | Servidor accesible en local o en tu red (el legacy ya usa `192.168.10.156`) |
| 5 | **Base de datos vacía para desarrollo** | Crear **`finanzas_dev`** (nombre sugerido). Vacía al inicio; el script la poblará |
| 6 | **Acceso de lectura a producción** | Host, puerto, usuario y contraseña MySQL de la BD `finanzas` de producción (solo para el dump) |
| 7 | **Repositorio Git** | Crear repo vacío (GitHub/GitLab) o confirmar que usaremos `D:\ADB\finanzas` como raíz del proyecto Laravel |

### 2.2 No — no hace falta que inicialices Laravel vos

Yo (o el agente en la siguiente sesión) ejecutaré:

```bash
composer create-project laravel/laravel .
php artisan breeze:install react   # o inertia manual si breeze no aplica
npm install && npm run build
```

Si el directorio `D:\ADB\finanzas` ya contiene `REQUIREMENTS.md` y el SQL, el scaffold irá **en el mismo repo** (documentos en `/docs` o raíz, app en estructura Laravel estándar).

### 2.3 Opcional pero recomendado

| Tarea | Por qué |
|-------|---------|
| Crear usuario MySQL dedicado `finanzas_dev` con permisos solo sobre `finanzas_dev` | Seguridad |
| Tener un dump reciente de producción (`finanzas_dump.sql`) | Probar el script de copia antes del desarrollo |
| Definir contraseña de dev para el usuario admin de prueba | El script puede resetearla automáticamente |

### 2.4 Comandos SQL iniciales (vos o yo al empezar)

```sql
CREATE DATABASE finanzas_dev
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'finanzas_dev'@'%' IDENTIFIED BY '***';
GRANT ALL PRIVILEGES ON finanzas_dev.* TO 'finanzas_dev'@'%';
FLUSH PRIVILEGES;
```

Producción **no se toca** salvo `mysqldump` de lectura.

---

## 3. Script de copia de base de datos (producción → dev)

Se creará en el repo: **`scripts/db-sync.sh`** (y variante `db-sync.ps1` para Windows si lo necesitás).

### 3.1 Comportamiento

```
┌─────────────┐     mysqldump      ┌──────────────┐     mysql restore    ┌──────────────┐
│  Producción │ ─────────────────► │  archivo.sql │ ───────────────────► │ finanzas_dev │
│  (finanzas) │                    │  (temp/gz)   │                      │   (local)    │
└─────────────┘                    └──────────────┘                      └──────────────┘
                                           │
                                           ▼
                                  post-restore.sql (opcional)
                                  - reset password admin dev
                                  - truncate mining_log si querés
```

### 3.2 Variables de entorno (`.env.sync` — no commitear)

```env
# Origen (producción)
SYNC_SOURCE_HOST=192.168.10.156
SYNC_SOURCE_PORT=3306
SYNC_SOURCE_DATABASE=finanzas
SYNC_SOURCE_USER=readonly_user
SYNC_SOURCE_PASSWORD=***

# Destino (desarrollo)
SYNC_TARGET_HOST=127.0.0.1
SYNC_TARGET_PORT=3306
SYNC_TARGET_DATABASE=finanzas_dev
SYNC_TARGET_USER=finanzas_dev
SYNC_TARGET_PASSWORD=***

# Opciones
SYNC_COMPRESS=1
SYNC_SKIP_TABLES=mining_log,mining_payout_log,coin_transactions
SYNC_DEV_ADMIN_USER=admin
SYNC_DEV_ADMIN_PASSWORD=dev123456
```

### 3.3 Pasos del script

1. Validar que `mysql` y `mysqldump` están disponibles.
2. Dump con `--single-transaction --routines --triggers` (InnoDB, sin bloquear prod).
3. Excluir tablas de mining si `SYNC_SKIP_TABLES` está definido.
4. `DROP DATABASE` / `CREATE DATABASE` destino (o `mysql ... < dump` tras truncate).
5. Restaurar dump en `finanzas_dev`.
6. Ejecutar **`scripts/db-sync-post.sql`**:
   - Actualizar password del usuario admin de prueba (tabla `sec_usuario` legacy o `users` nueva).
   - Opcional: anonimizar emails en `sec_usuario`.
7. Log de duración y tamaño del dump.

### 3.4 Uso previsto

```bash
# Linux / WSL / servidor
cp .env.sync.example .env.sync   # una vez, editar credenciales
./scripts/db-sync.sh

# Repetir cada vez que quieras datos frescos de producción
./scripts/db-sync.sh --force
```

### 3.5 Integración con Laravel

- `.env` de la app apunta a `finanzas_dev` en desarrollo.
- `php artisan migrate` solo corre migraciones **nuevas** (users, sessions, permission tables); no recrea tablas legacy.
- Comando artisan complementario: `php artisan finanzas:db-verify` — cuenta registros clave y muestra última fecha de asiento.

### 3.6 Nota sobre charset

La BD legacy usa `latin1`. Tras el restore:

- La app Laravel usará `utf8mb4` en conexión; MySQL convierte en lectura/escritura.
- En una etapa posterior: migración `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4` (fuera del alcance inicial).

---

## 4. Estructura del proyecto Laravel (objetivo)

```
finanzas/
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/...
│   │   ├── DashboardController.php
│   │   ├── IngresoController.php
│   │   ├── PagoController.php
│   │   ├── TarjetaController.php
│   │   ├── CuentaController.php
│   │   ├── AsientoController.php
│   │   ├── CotizacionController.php
│   │   ├── ConciliacionController.php
│   │   └── Reporte/ (Balance, Mayor, Gastos, ...)
│   ├── Models/
│   │   ├── Legacy/          # Cuenta, Asiento, Ingreso, Pago, Moneda...
│   │   └── User.php         # auth Laravel (mapeo o tabla nueva)
│   ├── Services/
│   │   ├── Contabilidad/
│   │   │   ├── AsientoGenerator.php
│   │   │   ├── CuotaGenerator.php
│   │   │   ├── ConciliacionService.php
│   │   │   └── TarjetaLiquidacionService.php
│   │   ├── Reportes/
│   │   │   ├── BalanceCalculator.php
│   │   │   ├── MayorService.php
│   │   │   └── ...
│   │   └── CotizacionFetcher.php
│   ├── Jobs/
│   │   ├── FetchCotizacionesJob.php
│   │   └── DatabaseBackupJob.php
│   └── Console/Commands/
│       ├── DbVerifyCommand.php
│       └── SyncLegacyUsersCommand.php
├── database/
│   └── migrations/          # solo tablas nuevas Laravel + spatie permission
├── resources/js/
│   ├── Components/          # shadcn/ui, DataTable, MoneyInput, DateInput
│   ├── Layouts/AdminLayout.tsx
│   └── Pages/
│       ├── Dashboard.tsx
│       ├── Ingresos/ (Index, Form)
│       ├── Pagos/
│       ├── Tarjetas/
│       ├── Cuentas/
│       ├── Reportes/
│       └── ...
├── scripts/
│   ├── db-sync.sh
│   ├── db-sync-post.sql
│   └── db-sync.ps1
├── tests/
│   └── Unit/Services/Contabilidad/   # tests críticos vs legacy
├── docs/
│   ├── REQUIREMENTS.md
│   ├── MIGRATION_PLAN.md
│   └── IMPLEMENTATION_PLAN.md
└── finanzas_nodata.sql      # referencia de esquema
```

---

## 5. Fases de implementación

### Fase 0 — Fundación (semana 1)

**Objetivo:** Proyecto corre localmente, login funciona, BD de prueba cargada.

| # | Tarea | Entregable |
|---|-------|------------|
| 0.1 | `composer create-project` + Breeze Inertia React | App en blanco con login |
| 0.2 | Tailwind + shadcn/ui + layout sidebar (menú según legacy) | `AdminLayout.tsx` |
| 0.3 | Configurar `.env` → `finanzas_dev` | Conexión OK |
| 0.4 | Crear `scripts/db-sync.sh` + `.env.sync.example` | Copia prod→dev probada |
| 0.5 | Modelos Eloquent legacy: `Moneda`, `Cuenta`, `Asiento`, `AsientoItem` | Sin migrar datos |
| 0.6 | Migración Laravel: `users` + bridge a `sec_usuario` o login directo legacy | Auth operativo |
| 0.7 | `spatie/laravel-permission` — roles `admin`, `operador`, `lectura` | Middleware `role:` |
| 0.8 | `php artisan finanzas:db-verify` | Comando diagnóstico |
| 0.9 | Helpers: `Money`, `DateAr` (formato dd/MM/yyyy, decimal con coma en UI) | `app/Support/` |

**Criterio de aceptación Fase 0:**

- [ ] `./scripts/db-sync.sh` restaura datos y podés ver conteos en tinker.
- [ ] Login con usuario de dev redirige al dashboard vacío.
- [ ] Menú lateral muestra ítems deshabilitados/grises para módulos pendientes.

**Auth — decisión técnica:**

Opción recomendada: tabla `users` Laravel + comando `finanzas:import-users` que copia desde `sec_usuario` (username, email) y asigna password bcrypt nuevo. El legacy usa **HMAC-SHA512 con salt** (`PwdUtil`), incompatible con bcrypt — no intentar reutilizar el hash.

Mientras coexistas con Tomcat en la misma BD de dev, dejá `SYNC_RESET_PASSWORD=0` en el script de copia.

---

### Fase 1 — Núcleo contable (semanas 2–4)

**Objetivo:** Registrar ingresos/pagos y ver balance correcto vs legacy.

| # | Tarea | Referencia legacy |
|---|-------|-------------------|
| 1.1 | CRUD Monedas | `MonedaABM` |
| 1.2 | CRUD Cuentas (árbol, filtros, imputable) | `CuentaABM`, `cuentas.ftl` |
| 1.3 | `AsientoGenerator::fromIngreso()` + tests | `Ingreso.java` |
| 1.4 | CRUD Ingresos (listado mes, form, cotización AJAX) | `IngresoABM`, `ingresos.ftl` |
| 1.5 | `AsientoGenerator::fromPago()` + `CuotaGenerator` + tests | `Pago.java`, `PagoABM` |
| 1.6 | CRUD Pagos (cuotas incluidas) | `pagos.ftl` |
| 1.7 | `CuentaSaldoService` (port `CuentasDAO.getSaldos`) | SQL legacy |
| 1.8 | `BalanceCalculator` + página Balance | `CalculoBalance`, `balance.ftl` |
| 1.9 | API interna cotización por fecha (`GET /api/cotizaciones/por-fecha`) | `MonedaABM.get.cotizacion` |

**Tests de regresión (obligatorios):**

- Crear ingreso ARS y USD; verificar asiento debe=haber.
- Pago en 3 cuotas; verificar 3 hijos con fechas día 15.
- Balance a fecha X vs mismo informe en sistema legacy (misma BD).

**Criterio de aceptación Fase 1:**

- [ ] Un mes de ingresos/pagos coincide con legacy en balance.
- [ ] Editar/eliminar regenera o borra asientos correctamente.

---

### Fase 2 — Operación diaria (semanas 5–7)

| # | Tarea | Referencia |
|---|-------|------------|
| 2.1 | Dashboard: saldos por moneda, distribución | `Stats` ops `activo_x_moneda`, `distribucion_*`, `home.ftl` |
| 2.2 | Tarjetas: listado mensual, liquidación, otros conceptos | `PagoABM` custom, `tarjetas.ftl` |
| 2.3 | Cotizaciones: ABM manual + `FetchCotizacionesJob` | `CotizacionesTask`, `cotizaciones.ftl` |
| 2.4 | Conciliación | `CuentaABM` conciliación |
| 2.5 | Asientos manuales | `AsientoABM`, `asientos.ftl` |
| 2.6 | Informe Mayor | `Stats.mayor` |
| 2.7 | Informe Gastos | `Stats.gastos` |
| 2.8 | Informe Intereses | `Stats.intereses` |

**Criterio de aceptación Fase 2:**

- [ ] Liquidar tarjeta del mes actualiza pagos y totales como legacy.
- [ ] Conciliación con diferencia genera asiento en `5.9.00.00`.

---

### Fase 3 — Informes avanzados y config (semanas 8–9)

| # | Tarea |
|---|-------|
| 3.1 | Evolución patrimonial (`ValorMultimoneda` port) |
| 3.2 | Evolución por cuenta |
| 3.3 | Informe FCI + carga cotizaciones FCI |
| 3.4 | Informe tarjetas (serie temporal) |
| 3.5 | Plazos fijos (si confirmás uso) |
| 3.6 | Configuración clave/valor (`configuracion` table) |
| 3.7 | Tipos de movimiento ABM |
| 3.8 | Plantillas email ABM |
| 3.9 | Gestión usuarios/roles (sin programa/función legacy) |

---

### Fase 4 — Producción y corte (semana 10)

| # | Tarea |
|---|-------|
| 4.1 | `spatie/laravel-backup` o script backup en cron | ✅ `backup:run --only-db` 03:00 + `backup:clean` 03:30 |
| 4.2 | Scheduler: cotizaciones 9–17h | ✅ `hourly()->between('9:00','17:00')` + ventana en job/config |
| 4.3 | Nginx + PHP-FPM config |
| 4.4 | `deploy.sh` + webhook GitHub |
| 4.5 | Corrida paralela 2–4 semanas: legacy vs v2 misma BD |
| 4.6 | Documentación operativa (`docs/DEPLOY.md`) |

---

## 6. Componentes React reutilizables (implementar temprano)

| Componente | Uso |
|------------|-----|
| `DataTable` | Ingresos, pagos, cuentas, asientos (TanStack Table + paginación server Inertia) |
| `MoneyInput` | Importes con máscara AR |
| `DateInput` | dd/MM/yyyy |
| `CuentaSelect` | Autocomplete cuentas por código/descripción |
| `MonedaSelect` | Con símbolo |
| `PeriodPicker` | Año/mes con ◀ ▶ |
| `ReportFilters` | Desde/hasta/zoom mensual |
| `AccountTree` | Balance jerárquico |

---

## 7. Paquetes Composer / npm

### Composer

```bash
composer require inertiajs/inertia-laravel
composer require spatie/laravel-permission
composer require spatie/laravel-backup
# laravel/breeze -- dev scaffold
```

### npm

```bash
npm install @tanstack/react-table
npm install date-fns
npm install react-day-picker
# shadcn/ui init (components on demand)
```

---

## 8. Mapeo modelo Eloquent ↔ tabla legacy

| Modelo | Tabla | PK | Notas |
|--------|-------|-----|-------|
| `Moneda` | `monedas` | `id` | `local` boolean |
| `Cuenta` | `cuentas` | `id` | `id_superior` self-ref |
| `Asiento` | `asientos` | `id` | `hasMany` items |
| `AsientoItem` | `asiento_items` | `id` | |
| `Ingreso` | `ingresos` | `id` | `id_asiento` FK lógica |
| `Pago` | `pagos` | `id` | `id_origen`, `cuota` |
| `Cotizacion` | `cotizaciones` | `id` | |
| `FciCotizacion` | `fci_cotizaciones` | `id` | |
| `Configuracion` | `configuracion` | `id_configuracion` | clave/valor |
| `TipoMovimiento` | `tipos_movimiento` | `id` | |
| `PlazoFijo` | `plazos_fijos` | `id` | |

**Timestamps:** tablas legacy no tienen `created_at`/`updated_at` → `$timestamps = false` en modelos.

**Decimales:** cast a `string` o usar `brick/money`; nunca `float`.

---

## 9. Rutas web (borrador)

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('ingresos', IngresoController::class);
    Route::resource('pagos', PagoController::class);
    Route::resource('cuentas', CuentaController::class);
    Route::resource('monedas', MonedaController::class);
    Route::resource('asientos', AsientoController::class);

    Route::get('/tarjetas', [TarjetaController::class, 'index']);
    Route::post('/tarjetas/liquidar', [TarjetaController::class, 'liquidar']);

    Route::get('/cotizaciones', [CotizacionController::class, 'index']);
    Route::post('/cotizaciones', [CotizacionController::class, 'store']);
    Route::post('/cotizaciones/fetch', [CotizacionController::class, 'fetch']);

    Route::get('/conciliacion', [ConciliacionController::class, 'index']);
    Route::post('/conciliacion', [ConciliacionController::class, 'store']);

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/balance', ...);
        Route::get('/mayor', ...);
        Route::get('/gastos', ...);
        Route::get('/intereses', ...);
        Route::get('/evolucion-patrimonial', ...);
        Route::get('/evolucion-cuenta', ...);
        Route::get('/fci', ...);
        Route::get('/tarjetas', ...);
    });

    Route::get('/configuracion', ...);
});
```

---

## 10. Cron / scheduler (`app/Console/Kernel.php`)

```php
$schedule->job(new FetchCotizacionesJob)
    ->hourly()
    ->between('9:00', '17:00')
    ->withoutOverlapping(55);

$schedule->command('backup:run --only-db')->dailyAt('03:00');
$schedule->command('backup:clean')->dailyAt('03:30');
```

Timezone: `APP_TIMEZONE=America/Argentina/Buenos_Aires`.

En servidor: `* * * * * cd /var/www/finanzas && php artisan schedule:run`.

---

## 11. Checklist para vos antes de la sesión de implementación

Copiá y completá:

```
[ ] PHP 8.3 + Composer instalados
[ ] Node 20+ instalado
[ ] MySQL 8 accesible
[ ] Base finanzas_dev creada
[ ] Usuario MySQL finanzas_dev con permisos
[ ] Credenciales lectura producción para mysqldump
[ ] Confirmado: repo en D:\ADB\finanzas (o ruta alternativa)
[ ] Git inicializado (git init) en el directorio del proyecto
[ ] Decisión auth: importar sec_usuario → users (recomendado)
[ ] ¿Incluir plazos fijos en etapa 1? (sí/no)
[ ] ¿Incluir FCI en etapa 1? (sí/no — por defecto sí en Fase 3)
```

---

## 12. Orden de trabajo en la próxima sesión (cuando digas "empezá")

1. Inicializar Laravel + Breeze Inertia React en el repo.
2. Crear `scripts/db-sync.sh` y probar restore (necesito credenciales en `.env.sync` local, no en git).
3. Modelos legacy + comando `db-verify`.
4. Auth + roles + layout admin.
5. Vertical slice **Ingresos** completo (el más simple para validar asientos).
6. Tests PHPUnit de `AsientoGenerator`.

---

## 13. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Queries SQL legacy incorrectas al portar | Tests comparando salida con BD real; portar SQL literal primero |
| Password hash incompatible sec_usuario | Reset password en `db-sync-post.sql` |
| Diferencias de redondeo decimal | Usar `bcmath`; tests con casos reales exportados |
| Dump grande / lento | `--compress`, excluir tablas mining, dump en horario bajo |
| Mezclar migraciones Laravel con schema legacy | Migraciones solo para tablas nuevas; documentar en README |

---

## 14. Resumen ejecutivo

| Pregunta | Respuesta |
|----------|-----------|
| ¿Inicializo Laravel yo? | **No.** Lo hacemos al empezar implementación. |
| ¿Creo base de datos? | **Sí:** `finanzas_dev` vacía + usuario MySQL. |
| ¿Copio datos yo? | Opcional; el script `db-sync.sh` lo automatiza desde producción. |
| ¿Cuándo hay algo usable? | Fin **Fase 1** (~3 semanas): ingresos, pagos, cuentas, balance. |
| ¿AFIP / mining? | **Fuera de alcance definitivo.** No portar. Tablas candidatas a DROP tras corte. |

---

*Documento vivo — actualizar al cerrar cada fase.*
