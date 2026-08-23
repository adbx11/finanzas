# ADB Finanzas — Plan de migración y elección de stack

Documento complementario a `REQUIREMENTS.md`. Objetivo: elegir tecnologías que **minimicen código nuevo**, faciliten **deploy con `git pull`** en VPS económico, y eviten el footprint de Java/Tomcat.

---

## 1. Objetivos y restricciones

| Objetivo | Peso |
|----------|------|
| Menos código propio (CRUD, auth, tablas, validación) | Alto |
| Deploy simple (webhook GitHub → pull en Ubuntu) | Alto |
| Hosting económico / VPS pequeño | Alto |
| Familiaridad tuya: PHP, JS, React, Next.js | Alto |
| Evitar Java / Tomcat | Alto |
| Preservar lógica contable multimoneda existente | Alto |
| AFIP factura electrónica | Medio (módulo separable) |

### Qué NO optimizar en v1

- Multi-tenant (es instalación personal/familiar).
- Escalado horizontal.
- App móvil nativa.

---

## 2. Diagnóstico del sistema actual

### Fortalezas a conservar (conceptualmente)

- Modelo de datos MySQL maduro y probado.
- Flujos de ingreso/pago → asiento automático bien definidos.
- Informes SQL complejos ya validados en producción.

### Deuda técnica a eliminar

| Problema legacy | Impacto |
|-----------------|---------|
| Framework propio (`adbprocessor`) acoplado a Servlet/JSP | Curva de mantenimiento, pocos devs |
| Hibernate XML + DAOs duplicados | Mucho boilerplate |
| UI jQuery + FTL + AJAX manual | Difícil de testear y evolucionar |
| `java.util.Timer` para jobs | Frágil, se pierde al reiniciar sin cron |
| JAR ofuscado + deploy Ant/Tomcat | Deploy pesado (~512MB+ RAM solo JVM) |
| RBAC sobre-programado (programas/funciones en BD) | Complejidad desproporcionada para 1–3 usuarios |
| Cliente SQL en producción | Riesgo de seguridad |

---

## 3. Opciones de stack evaluadas

### Resumen comparativo

| Opción | Código a escribir | Deploy git pull | RAM típica | Curva | AFIP |
|--------|-------------------|-----------------|------------|-------|------|
| **A. Laravel + Inertia + React** | ⭐⭐⭐⭐⭐ Mínimo | ⭐⭐⭐⭐⭐ Excelente | 80–150 MB | Baja en PHP | Paquetes PHP maduros |
| **B. Laravel API + Next.js** | ⭐⭐⭐ Medio | ⭐⭐⭐ Dos apps | 150–300 MB | Media | Igual que A |
| **C. Next.js full-stack (monolito)** | ⭐⭐⭐⭐ | ⭐⭐⭐ Node + build | 150–250 MB | Media | Integrar vía API/servicio |
| **D. PHP + Filament (sin React)** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | 60–120 MB | Muy baja | Igual que A |
| **E. Mantener Java modernizado** | ⭐⭐ | ⭐⭐ | 300–512 MB+ | Alta | Ya existe |

---

## 4. Recomendación principal: **Opción A — Laravel 11 + Inertia.js + React**

### Por qué encaja con tus prioridades

1. **PHP + `git pull`** — Ya lo usás; Laravel es el framework PHP con más ecosistema para admin/CRUD.
2. **Un solo repositorio, un solo proceso** — No necesitás Node en producción si compilás assets en CI o local (`npm run build` antes del pull).
3. **Inertia + React** — Reutilizás tu conocimiento de React sin montar SPA separada ni API REST completa para cada pantalla.
4. **Eloquent + migraciones** — Reemplaza Hibernate/DAOs; el esquema MySQL actual se adapta con migraciones incrementales.
5. **Laravel Scheduler + cron** — Un renglón en crontab reemplaza `CotizacionesTask` y `BackupTask`.
6. **Paquetes listos:**
   - [Filament](https://filamentphp.com/) o [Laravel Breeze + Inertia](https://laravel.com/docs/starter-kits) para auth/UI base.
   - [spatie/laravel-permission](https://github.com/spatie/laravel-permission) — roles simples en lugar de sec_programa/función.
   - [maatwebsite/excel](https://github.com/maatwebsite/Laravel-Excel) — export informes.
   - AFIP: `afipsdk/afip.php`, `juanmaivan/afip` u otros wrappers activos.

### Arquitectura propuesta

```
┌─────────────────────────────────────────────────────────┐
│  Nginx + PHP-FPM 8.3                                    │
│  ┌───────────────────────────────────────────────────┐  │
│  │  Laravel 11                                       │  │
│  │  ├── Routes (web + Inertia pages)                 │  │
│  │  ├── Controllers delgados                         │  │
│  │  ├── Services/ (contabilidad, informes, AFIP)     │  │
│  │  ├── Models Eloquent + Query scopes               │  │
│  │  ├── Jobs (cotizaciones, backup)                  │  │
│  │  └── Resources/React (Inertia pages, shadcn/ui)   │  │
│  └───────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────┘
                           │
                    MySQL 8 (existente)
```

### UI sugerida

- **shadcn/ui + Tailwind** — Tablas, forms, dialogs modernos; menos CSS custom que Althair.
- **TanStack Table** — Reemplaza DataTables jQuery con filtros/paginación server-side.
- **react-day-picker** o similar — Fechas dd/MM/yyyy.
- Layout sidebar replicando menú actual (familiar para el usuario).

### Capa de dominio contable (PHP)

Extraer servicios puros testeables — port directo desde Java:

| Servicio PHP | Origen Java |
|--------------|-------------|
| `AsientoGenerator` | `Ingreso.generarAsiento`, `Pago.generarAsiento` |
| `CuotaGenerator` | `PagoABM.afterSave` |
| `BalanceCalculator` | `CalculoBalance` |
| `ConciliacionService` | `CuentaABM.save.conciliacion` |
| `TarjetaLiquidacionService` | `PagoABM.save.pago.tarjeta` |
| `ReporteService` | `Stats.java` + queries de `CuentasDAO` |

Usar `brick/money` o `decimal` con `bcmath` — **nunca float** para importes.

---

## 5. Alternativa sólida: **Opción D — Laravel + Filament (sin React)**

Elegir si preferís **máxima velocidad de desarrollo** y te alcanza con UI tipo admin panel estándar.

| Pros | Contras |
|------|---------|
| CRUD casi gratis (Filament Resources) | Informes gráficos custom requieren más trabajo |
| Cero build frontend en prod | Menos control fino de UX que React |
| Ideal para Configuración, Monedas, Usuarios | Pantalla Tarjetas (multi-card) menos natural |

**Híbrido recomendado:** Filament para ABMs simples + **páginas Inertia/React** solo para Dashboard, Tarjetas, Balance e Informes complejos.

---

## 6. Alternativa: **Opción C — Next.js 15 full-stack**

Tiene sentido si querés **invertir en JavaScript a largo plazo** y aceptás Node en el servidor.

### Stack

- Next.js App Router + Server Actions o Route Handlers
- Prisma ORM → MySQL
- Auth.js (credenciales)
- shadcn/ui + TanStack Table
- `node-cron` o cron del sistema llamando a endpoint protegido

### Deploy en VPS económico

```bash
# webhook → git pull → npm ci → npm run build → pm2 restart finanzas
```

| Pros | Contras |
|------|---------|
| Un solo lenguaje (TS) en front y back | `npm run build` en cada deploy (más lento que PHP) |
| Excelente DX, tipos end-to-end | RAM Node ~150–250 MB mínimo |
| Vercel gratis para preview | MySQL en Vercel no es trivial; VPS sigue siendo mejor para prod |

**Veredicto:** Buena opción si el proyecto es tu laboratorio Next.js; para **mínimo footprint y deploy más simple**, Laravel gana.

---

## 7. Opción descartada por tus criterios: Java moderno

Spring Boot 3 + React sería "lo correcto" en enterprise, pero:

- JVM 300–512 MB+ en idle.
- Deploy = JAR + restart servicio (no un simple `git pull` de PHP).
- AFIP ya resuelto en Java, pero port a PHP tiene librerías suficientes.

Solo reconsiderar si AFIP resultara imposible de portar (poco probable).

---

## 8. Hosting económico / gratuito

### Producción recomendada: **VPS Ubuntu** (ya lo tenés)

| Componente | Config |
|------------|--------|
| Web | Nginx |
| App | PHP 8.3-FPM + Laravel **o** Node 20 + PM2 |
| DB | MySQL 8 local o managed barato |
| SSL | Certbot |
| Deploy | Webhook → script `deploy.sh` |

Ejemplo `deploy.sh` (Laravel):

```bash
#!/bin/bash
cd /var/www/finanzas
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm ci && npm run build   # o build en CI y commitear public/build
php artisan queue:restart  # si usás queues
```

### Opciones gratuitas/baratas (limitaciones)

| Plataforma | Viabilidad |
|------------|------------|
| **Oracle Cloud Free / Hetzner CX11** | ⭐ Mejor relación costo; VPS completo |
| **Railway / Render free tier** | Node/Laravel con sleep; MySQL aparte |
| **Shared hosting PHP** | Posible con Laravel si permiten cron y Composer |
| **Vercel + PlanetScale** | Solo para Next.js; cold starts, sin cron nativo |
| **Fly.io** | Posible con máquina pequeña; más setup |

Para app **privada de finanzas** con jobs programados y MySQL con histórico grande: **VPS de 1–2 GB RAM** es la opción más predecible (~€4–5/mes).

---

## 9. Estrategia de migración por fases

### Fase 0 — Preparación (1–2 semanas)

- [ ] Inicializar repo nuevo (Laravel + Inertia + React o Filament híbrido).
- [ ] Migraciones Laravel desde `finanzas_nodata.sql`; seed mínimo (monedas, plan cuentas vacío o import).
- [ ] Script import datos producción → entorno dev.
- [ ] Tests de caracterización para `AsientoGenerator` y `BalanceCalculator` comparando salidas con sistema legacy.

### Fase 1 — MVP operativo (P0)

- [ ] Auth + roles básicos (`admin`, `usuario`).
- [ ] Monedas, Cuentas (plan completo).
- [ ] Ingresos, Pagos (con cuotas).
- [ ] Dashboard (saldos por moneda).
- [ ] Informe Balance.

**Criterio de corte:** podés registrar el mes corriente y ver balance igual al sistema viejo.

### Fase 2 — Gestión diaria (P1)

- [ ] Tarjetas (liquidación mensual).
- [ ] Cotizaciones (manual + fetch automático).
- [ ] Conciliación.
- [ ] Asientos manuales.
- [ ] Informes: Mayor, Gastos, Intereses, Evoluciones.

### Fase 3 — Administración (P2)

- [ ] Configuración general.
- [ ] Tipos de movimiento.
- [ ] Plantillas email.
- [ ] Usuarios/perfiles simplificados.
- [ ] FCI (cotizaciones + informe).

### Fase 4 — Facturación AFIP — **CANCELADA**

No se migrará AFIP. Las tablas de facturación son candidatas a `DROP` tras el corte a v2.

- [ ] CUIT emisores, puntos de venta.
- [ ] Emisión FE + consulta.
- [ ] Migrar certificados.

### Fase 5 — Decommission legacy

- [ ] Período de corrida paralela (1 mes).
- [ ] Apagar Tomcat.

---

## 10. Decisiones de diseño para reducir código

### 10.1 Simplificar RBAC

**Legacy:** Programas → Funciones → Perfil → Usuario (6 tablas, UI compleja).

**Nuevo:**

```php
// roles: admin | operador | solo_lectura
// Gates: verInformes, editarMovimientos, configurar, facturar
```

Mapeo:

| Programa legacy | Rol nuevo |
|-----------------|-----------|
| BG | operador |
| CONFIGURACION | admin |
| FACTURACION | admin + permiso facturar |
| DEV | admin |

### 10.2 Unificar capa de reportes

En lugar de un `Stats.java` monolítico con `op=`, usar:

```php
ReporteRegistry::get('balance')->generar($filtros);
```

Cada informe = clase + query SQL/Eloquent + DTO respuesta. Facilita tests y cache.

### 10.3 Mantener "atajos" Ingreso/Pago

No forzar al usuario a cargar asientos manuales. Los formularios simples siguen generando asientos en backend (como ahora).

### 10.4 API REST solo donde aporte

Inertia no necesita API pública completa. Exponer API JSON solo si planeás app móvil futura.

### 10.5 Reutilizar SQL probado

Las queries de `CuentasDAO` y `Stats` son el activo más valioso. Portarlas a:

- Eloquent `DB::select()` con parámetros bound, o
- Views MySQL + queries simples.

No reescribir lógica de agregación desde cero sin tests de regresión.

---

## 11. Stack concreto recomendado (lista de paquetes)

### Backend

| Paquete | Uso |
|---------|-----|
| Laravel 11 | Framework |
| Inertia Laravel + React 18 | UI |
| Laravel Breeze (Inertia kit) | Auth scaffolding |
| spatie/laravel-permission | Roles |
| spatie/laravel-backup | Reemplazo BackupTask |
| laravel/telescope (dev) | Debug |

### Frontend

| Paquete | Uso |
|---------|-----|
| Tailwind CSS 4 | Estilos |
| shadcn/ui | Componentes |
| TanStack Table v8 | Grillas |
| TanStack Query (opcional) | Cache cliente |
| Recharts o Chart.js | Gráficos dashboard |
| react-input-mask / currency input | Formato ARS |

### Calidad

| Herramienta | Uso |
|-------------|-----|
| PHPUnit + Pest | Tests servicios contables |
| PHPStan nivel 6+ | Tipos |
| Laravel Pint | Estilo código |

---

## 12. Estimación de esfuerzo relativo

| Fase | Esfuerzo (1 dev familiarizado) |
|------|-------------------------------|
| Fase 0 | 1–2 semanas |
| Fase 1 MVP | 3–5 semanas |
| Fase 2 | 3–4 semanas |
| Fase 3 | 2–3 semanas |
| Fase 4 AFIP | 2–4 semanas |
| **Total sin AFIP** | **~2.5–4 meses** part-time |
| **Con AFIP** | **+1 mes** |

Filament puro podría reducir Fase 1–2 un 20–30% en ABMs, a costa de UX en Tarjetas/Informes.

---

## 13. Matriz de decisión — ¿qué elegir?

```
¿Querés deploy más simple posible (solo git pull + composer)?
  └─ SÍ → Laravel (+ Filament y/o Inertia)

¿Prioridad máxima en aprender/consolidar Next.js?
  └─ SÍ → Next.js + Prisma en VPS con PM2

¿Querés cero React y máximo CRUD automático?
  └─ SÍ → Laravel + Filament (admin) + Livewire para informes simples

¿Necesitás AFIP en v1?
  └─ SÍ → Laravel PHP con wrapper AFIP (Fase 4), o microservicio Java solo para FE
```

### Recomendación final

> **Laravel 11 + Inertia + React + shadcn/ui**, con **Filament solo para backoffice secundario** si hace falta, desplegado en **tu VPS Ubuntu actual** con webhook GitHub.

Es el mejor balance entre:

- reutilizar tu flujo PHP de deploy,
- aprovechar React donde la UX lo merece (dashboard, tarjetas, informes),
- minimizar código boilerplate,
- y mantener RAM bajo control (~100–150 MB vs 400+ de Tomcat).

---

## 14. Próximos pasos concretos

1. Confirmar alcance v1 (¿AFIP? ¿FCI? ¿Plazos fijos? ¿crypto?).
2. Elegir entre **Inertia+React** vs **Filament puro** (o híbrido).
3. Crear repo `finanzas-v2` con Laravel, primera migración de tablas núcleo.
4. Portar `Ingreso.generarAsiento` / `Pago.generarAsiento` a PHP con tests PHPUnit comparando 10 casos reales exportados del sistema actual.
5. Pantalla Ingresos end-to-end como vertical slice.
6. Iterar según fases del §9.

---

## 15. Referencias del código legacy

| Componente | Ubicación |
|------------|-----------|
| Lógica ingresos/pagos | `ADBFinanzas/src/com/adb/finanzas/domain/`, `functions/*ABM.java` |
| Informes | `ADBFinanzas/src/com/adb/finanzas/functions/Stats.java` |
| Balance | `ADBFinanzas/src/com/adb/finanzas/balance/` |
| Queries saldos | `ADBFinanzas/src/com/adb/finanzas/dao/CuentasDAO.java` |
| AFIP | `adbprocessor/src/com/adb/facturacion/` |
| Framework routing | `adbprocessor` + `conf/functions.xml` |
| Esquema BD | `finanzas/finanzas_nodata.sql` |

---

*Documento de planificación — no implica compromiso de implementación hasta validar alcance v1.*
