# Memoria del proyecto — ADB Finanzas v2

Documento de contexto para retomar el desarrollo en chats o sesiones nuevas.  
Cursor carga automáticamente la versión resumida desde `.cursor/rules/project.mdc`.

---

## Resumen

**ADB Finanzas v2** es la reescritura del sistema de finanzas personales legacy (Java) en **Laravel 10 + Inertia + React**. Paridad en operación diaria (ingresos, pagos, cuentas, informes, crypto tipificado). **AFIP y mining quedan fuera de alcance definitivo.**

| | |
|---|---|
| **Repo** | `D:\ADB\finanzas` |
| **Legacy** | `D:\ADB\workspace\ADBFinanzas` |
| **BD desarrollo** | `finanzas_dev` |
| **PHP** | 8.1.29 (limita a Laravel 10) |

---

## Decisiones técnicas

### Base de datos

- Se **reutiliza el esquema MySQL legacy** sin migraciones destructivas.
- Laravel agrega solo tablas propias: `users`, `sessions`, Spatie permissions, `jobs`, etc.
- Modelos legacy: clase base `App\Models\LegacyModel` con `$timestamps = false`.
- Esquema vacío de referencia: `docs/finanzas_nodata.sql`.

### Autenticación

- Usuarios en tabla `users` (bcrypt).
- Import inicial desde `sec_usuario`: `php artisan finanzas:import-users --password=...`
- Login acepta **username o email**.
- Roles: `admin`, `operador`, `lectura` (`RolesSeeder` + Spatie).

### Contabilidad

- `AsientoGenerator` genera asientos al crear/editar ingresos y pagos (port de `Ingreso.java` / `Pago.java`).
- `CuotaGenerator` genera cuotas de tarjeta como en `PagoABM.java` (cuenta `1.1.01.01`, día 15 del mes siguiente).
- Montos con `App\Support\Money` (bcmath).

### Frontend

- Layout admin: `resources/js/Layouts/AdminLayout.jsx`
  - Sidebar fijo en desktop (`lg+`)
  - Drawer + hamburguesa en mobile
- Tablas: `FilterableTable` + filtros server-side (`filters[columna]` en URL)
- Navegación: `resources/js/config/navigation.js`
- Idioma UI: español

---

## Módulos

### Implementados

| Módulo | Rutas | Notas |
|--------|-------|-------|
| Dashboard | `dashboard` | Conteos y última fecha de asiento |
| Monedas | `monedas.*` | CRUD inline |
| Cuentas | `cuentas.*` | CRUD inline + API `cuentas.options` |
| Ingresos | `ingresos.*` | Mes/año + filtros columna + asiento auto |
| Pagos | `pagos.*` | Mes/año + filtros + cuotas tarjeta |
| Auth | `login`, `logout` | Sin registro ni reset password |

### Pendientes (P0–P2)

- Tarjetas de crédito
- Cotizaciones (ABM + job fetch)
- Conciliación bancaria
- Asientos manuales
- Informes: Balance, Mayor, Gastos, Intereses, evolución
- Configuración clave/valor
- FCI, plazos fijos

Ver prioridades en `docs/REQUIREMENTS.md` y fases en `docs/IMPLEMENTATION_PLAN.md`.

---

## Scripts y comandos

### Setup inicial

```bash
cp .env.example .env
# Completar DB_* con credenciales de finanzas_dev
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RolesSeeder
php artisan finanzas:import-users --password=dev123456
npm install && npm run dev
php artisan serve
php artisan finanzas:db-verify
```

### Copia BD producción → dev

```powershell
# Configurar scripts/.env.sync (no commitear)
.\scripts\db-sync.ps1
```

### Artisan custom

| Comando | Descripción |
|---------|-------------|
| `finanzas:db-verify` | Verifica conexión y conteos de tablas clave |
| `finanzas:import-users` | Importa usuarios desde `sec_usuario` |

---

## Archivos importantes

```
app/Http/Controllers/          IngresoController, PagoController, CuentaController, ...
app/Http/Concerns/AppliesColumnFilters.php
app/Services/Contabilidad/     AsientoGenerator, CuotaGenerator
app/Models/                    Moneda, Cuenta, Ingreso, Pago, Asiento, ...
resources/js/Layouts/AdminLayout.jsx
resources/js/Components/DataTable/FilterableTable.jsx
resources/js/hooks/useColumnFilters.js
routes/web.php
database/seeders/RolesSeeder.php
scripts/db-sync.ps1
.cursorignore                  # excluye .env del índice
```

---

## Convenciones para contribuir

1. Cambios pequeños y enfocados; no refactorizar de más.
2. Al portar lógica, leer primero el equivalente Java en `ADBFinanzas`.
3. Nuevos listados: reutilizar `FilterableTable` + `AppliesColumnFilters`.
4. Nuevos módulos de movimientos: integrar `AsientoGenerator`.
5. No commitear `.env`, `scripts/.env.sync`, ni dumps de BD.
6. Solo crear commits cuando el usuario lo pida explícitamente.

---

## Estado conocido / gotchas

- `.env` debe usar el usuario MySQL de dev (`finanzas_dev`), no `root` sin password.
- `SYNC_RESET_PASSWORD=0` en sync si aún se prueba el sistema Java legacy.
- Pagos con cuotas: al editar el padre se regeneran hijas; cuotas hijas se editan individualmente.
- Email en `users` es required; el import genera fallback `@finanzas.local` si falta.

---

*Última actualización: julio 2026 — fase inicial con ingresos, pagos, cuentas, monedas y layout responsive.*
