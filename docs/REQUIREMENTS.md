# ADB Finanzas — Relevamiento funcional para reescritura

Documento base para migrar el sistema legacy (`ADBFinanzas` + `adbprocessor.jar`) a una arquitectura moderna.  
Fuentes analizadas: código Java/JSP/FTL, esquema `finanzas_nodata.sql`, capturas de pantalla del admin Althair.

---

## 1. Resumen del sistema actual

### 1.1 Propósito

Aplicación web de **contabilidad personal / pequeña empresa** orientada a Argentina, con:

- Plan de cuentas jerárquico y partida doble.
- Registro simplificado de ingresos y pagos (que generan asientos automáticamente).
- Soporte **multimoneda** (ARS, USD, EUR, BTC y extensible).
- Gestión de **tarjetas de crédito** con cuotas e informe mensual de liquidación.
- Informes contables y de gestión (balance, mayor, evolución patrimonial, gastos, intereses, FCI, tarjetas).
- Facturación electrónica AFIP — **fuera de alcance definitivo** (no se migrará).
- Panel admin con control de acceso por perfiles.

### 1.2 Stack legacy (referencia)

| Capa | Tecnología |
|------|------------|
| Backend | Java (sin Spring), Servlets, framework propio `adbprocessor` |
| Persistencia | Hibernate 3 + MySQL 8, DAOs manuales, SQL embebido |
| Vistas | JSP shell + FreeMarker + jQuery + template Althair (UIKit/Material) |
| API | Endpoints `?action_name=...` que devuelven JSON o HTML parcial |
| Jobs | `java.util.Timer` (cotizaciones, backup; mining deshabilitado) |
| Deploy | Ant + Tomcat + JAR ofuscado |

### 1.3 Principios contables que debe respetar la reescritura

1. **Partida doble**: todo movimiento económico genera asientos con debe = haber (en moneda local convertida).
2. **Doble registro de importes**: `debe_origen` / `haber_origen` en moneda de la cuenta + `debe` / `haber` en moneda local (`origen × cotización`).
3. **Cuentas no imputables**: solo las hojas del plan (`imputable = true`) reciben movimientos.
4. **Jerarquía de saldos**: los saldos de cuentas padre = suma de hijas (usado en Balance).
5. **Convención de códigos** (ejemplos del sistema actual):
   - `1.x` Activo (caja/bancos `1.1.01.xx`, FCI, etc.)
   - `2.x` Pasivo (tarjetas `2.1.01.xx`)
   - `4.x` Ingresos
   - `5.x` Egresos / gastos (`5.1.90.xx` = otros conceptos TC)
   - `5.9.00.00` Ajustes y redondeos (conciliación)

---

## 2. Modelo de datos (entidades principales)

### 2.1 Núcleo contable

| Tabla | Entidad | Descripción |
|-------|---------|-------------|
| `monedas` | Moneda | Código ISO (ARS, USD…), símbolo, flag `local` |
| `cuentas` | Cuenta | Plan jerárquico: código, descripción, padre, moneda, `tipo_estado` (A/P/R/I/E), `tipo_cuenta`, `clase` (EF, CA, TC, FCI…), `imputable`, `habilitada` |
| `cotizaciones` | Cotización | Por fecha y moneda: compra/venta (se usa principalmente `venta`) |
| `asientos` | Asiento | Cabecera: fecha, descripción, confirmado, ejercicio, vínculo a movimiento/origen |
| `asiento_items` | Línea de asiento | Cuenta, moneda, debe/haber (local y origen), cotización, cuota, unidades |
| `ingresos` | Ingreso | Atajo UX: concepto (cuenta ingreso) → destino (cuenta activo), importe, cotización → genera asiento |
| `pagos` | Pago | Atajo UX: origen (cuenta activo/pasivo) ← concepto (cuenta gasto), cuotas, `id_origen`/`cuota` para cuotas derivadas |
| `tipos_movimiento` | TipoMovimiento | Plantillas para movimientos genéricos (parcialmente implementado) |
| `movimientos` | Movimiento | Transferencias complejas (ABM incompleto en legacy; baja prioridad) |

### 2.2 Productos financieros

| Tabla | Entidad | Descripción |
|-------|---------|-------------|
| `plazos_fijos` | PlazoFijo | Constitución y vencimiento con dos asientos automáticos |
| `fci_cotizaciones` | FCICotización | Valor de cuotaparte por fondo (cuenta clase FCI) y fecha; puede generar asiento de resultado |

### 2.3 Facturación AFIP

| Tabla | Entidad |
|-------|---------|
| `facturas`, `factura_items` | Factura, ítem |
| `cuits_emisores` | Emisor con certificado FE |
| `puntos_de_venta` | PV habilitado AFIP |
| `tipos_comprobante`, `tipos_comprobante_rel` | Tipos FC/NC/ND |
| `alicuotas_iva`, `condiciones_iva`, `tipos_doc` | Catálogos AFIP |

### 2.4 Seguridad y configuración

| Tabla | Uso |
|-------|-----|
| `sec_usuario`, `sec_perfil`, `sec_programa`, `sec_funcion` + tablas puente | RBAC granular |
| `configuracion` | Pares clave/valor agrupados (URLs cotización, SMTP, etc.) |
| `template` | Plantillas de email |

### 2.5 Tablas auxiliares / baja prioridad

| Tabla | Notas |
|-------|-------|
| `fechas`, `fechas_meses`, `fechas_anios` | Dimensiones temporales para reportes SQL |
| `coin_transactions`, `mining_log`, `mining_payout_log` | Crypto/mining — **menú deshabilitado**; evaluar eliminar en migración |
| `pais`, `provincia`, `ciudad` | Geografía — no aparece en menú principal |
| `email_store_sent`, `email_store_error` | Cola/histórico de mails |
| `image` | Avatares/imágenes usuario |

---

## 3. Módulos funcionales

Prioridad sugerida para implementación incremental:

- **P0** — Imprescindible para uso diario
- **P1** — Importante, uso frecuente
- **P2** — Secundario / administración
- **P3** — Opcional o legacy a evaluar

---

### 3.1 Autenticación y sesión (P0)

**Pantalla:** Login (`admin/login.ftl`)

| ID | Requisito |
|----|-----------|
| AUTH-01 | Login con usuario y contraseña |
| AUTH-02 | Sesión persistente; logout |
| AUTH-03 | Redirección a URL de inicio configurada por usuario |
| AUTH-04 | Bloqueo de usuario (`bloqueado`) |
| AUTH-05 | Recuperación de contraseña por email (plantillas existentes) |
| AUTH-06 | *(Legacy)* Login social OAuth — evaluar si se mantiene |
| AUTH-07 | *(Legacy)* Captcha en login — evaluar necesidad |

**Endpoints legacy:** `security.login`, `security.login.ajax`, `security.logout`

---

### 3.2 Seguridad / RBAC (P2)

**Menú:** Seguridad → Usuarios, Perfiles, Permisos (Programas), Cliente SQL, Logs

| ID | Requisito |
|----|-----------|
| SEC-01 | ABM de usuarios (nombre, email, perfiles, baja lógica) |
| SEC-02 | ABM de perfiles con asignación de programas y funciones |
| SEC-03 | Programas como unidades de permiso (`BG`, `CONFIGURACION`, `FACTURACION`, `DEV`, `USUARIOS`…) |
| SEC-04 | Verificación de programa antes de cada operación (`requireProgram`) |
| SEC-05 | Cliente SQL embebido — **recomendación: no reimplementar**; reemplazar por export/backup o acceso directo a DB |
| SEC-06 | Visor de logs de aplicación |

**Simplificación recomendada:** reemplazar el modelo Programa/Función XML+BD por roles fijos (`admin`, `contador`, `solo_lectura`) + policies/gates, manteniendo extensibilidad.

---

### 3.3 Inicio / Dashboard (P0)

**Pantalla:** Inicio (`admin/home.ftl`)

| ID | Requisito |
|----|-----------|
| HOME-01 | Selector de fecha "Saldos hasta…" |
| HOME-02 | Tarjetas de saldo por moneda (activo total convertido) — `stats?op=activo_x_moneda` |
| HOME-03 | Bloque BTC / wallets — `stats?op=btc` (si se mantiene crypto) |
| HOME-04 | Gráfico distribución por moneda — `distribucion_x_moneda` |
| HOME-05 | Gráfico distribución por cuenta (filtrable por moneda) — `distribucion_x_cuenta` |
| HOME-06 | Accesos rápidos a Tarjetas y Balance |
| HOME-07 | *(Comentado en legacy)* Dashboard alternativo con más widgets |

---

### 3.4 Ingresos (P0)

**Pantallas:** Listado mensual (`ingresos.ftl`), formulario alta/edición

| ID | Requisito |
|----|-----------|
| ING-01 | Listado paginado filtrable por año/mes |
| ING-02 | Columnas: fecha, concepto (cuenta ingreso), moneda, importe, comentarios |
| ING-03 | Total del período en cabecera |
| ING-04 | Alta/edición: fecha, concepto (select cuentas imputables ~`4.%`), destino (cuentas activo ~`1.1%`), moneda, importe, cotización, comentarios |
| ING-05 | Cotización automática al cambiar fecha/moneda (`moneda.abm` → `get.cotizacion`) |
| ING-06 | Al guardar: generar/reemplazar asiento contable vinculado (`Ingreso.generarAsiento`) |
| ING-07 | Acciones: editar, copiar, eliminar |
| ING-08 | Filtro por columna (concepto, comentarios) |

**Regla de asiento (ingreso):**
- Debe: cuenta destino (activo) por importe origen.
- Haber: cuenta concepto (ingreso) por importe origen.
- Misma moneda y cotización en ambos ítems.

---

### 3.5 Pagos (P0)

**Pantallas:** Listado mensual (`pagos.ftl`), formulario

| ID | Requisito |
|----|-----------|
| PAG-01 | Listado paginado por año/mes con total |
| PAG-02 | Alta/edición: fecha, concepto (gasto ~`5.%`), origen (activo/pasivo), moneda, importe, cotización, cuotas (0 = contado), comentarios |
| PAG-03 | Al guardar: asiento del pago principal |
| PAG-04 | Si `cuotas > 0`: generar N pagos hijos (`id_origen`, `cuota`) con fechas el día 15 de meses subsiguientes |
| PAG-05 | Cada cuota genera su propio asiento (lógica distinta en `Pago.generarAsiento` cuando `cuota != null`) |
| PAG-06 | Importe de última cuota ajusta redondeos |
| PAG-07 | Editar/eliminar/copiar como ingresos |

**Regla de asiento (pago contado):**
- Debe: cuenta concepto (gasto) en moneda local (importe × cotización).
- Haber: cuenta origen en moneda del pago.

---

### 3.6 Tarjetas de crédito (P0)

**Pantalla:** `tarjetas.ftl` — vista mensual por tarjeta (cuentas clase `TC`, pasivo `2.1.01.xx`)

| ID | Requisito |
|----|-----------|
| TC-01 | Selector año/mes con navegación ◀ ▶ |
| TC-02 | Resumen global: total liquidaciones del mes y saldo pendiente |
| TC-03 | Una tarjeta por panel: nombre, fecha de pago, forma de pago (cuenta banco CA), total |
| TC-04 | Listado de movimientos del mes (desde mayor de la cuenta TC): descripción, cuota X de Y, importe |
| TC-05 | Ítems con check verde = cuota ya pagada / conciliada |
| TC-06 | Guardar liquidación: actualiza fecha y cuenta origen de todos los pagos del mes |
| TC-07 | Manejo de "Otros conceptos" vía cuenta espejo `5.1.90.xx` (código derivado reemplazando `2.1.01` → `5.1.90`) |
| TC-08 | Campo diferencia ajusta importe de "otros conceptos" (`save.pago.tarjeta`) |
| TC-09 | Obtener pagos: `pago.abm` → `get.pagos.tarjeta` |

**Dependencias:** módulo Pagos con cuotas, cuentas TC configuradas en plan de cuentas.

---

### 3.7 Cotizaciones (P1)

**Pantalla:** `cotizaciones.ftl`

| ID | Requisito |
|----|-----------|
| COT-01 | Carga manual de cotización compra/venta por fecha y moneda |
| COT-02 | Consulta cotización para una fecha (`get.cotizacion`) — usa la más reciente ≤ fecha |
| COT-03 | Cotización promedio ponderada por cuenta (`get.cotizacion.promedio`) |
| COT-04 | Fetch automático desde API externa (`CotizacionesTask`, config `cotizacion.url`) en horario 9–17h |
| COT-05 | Botón manual "actualizar cotizaciones" (`fetch.cotizaciones`) |
| COT-06 | Soporte BTC vía config / APIs (Bittrex, Coinmarketcap en legacy) |

---

### 3.8 Conciliación bancaria (P1)

**Pantalla:** `conciliacion.ftl`

| ID | Requisito |
|----|-----------|
| CONC-01 | Fecha de conciliación |
| CONC-02 | Listar cuentas imputables de activo líquido (`1.1%`): caja y bancos |
| CONC-03 | Mostrar saldo sistema (en moneda de la cuenta) |
| CONC-04 | Input saldo real por cuenta |
| CONC-05 | Calcular diferencia en pantalla |
| CONC-06 | Al guardar: si hay diferencias, crear asiento "Conciliacion" con ítems por cuenta + contrapartida en `5.9.00.00` Ajustes |

**Endpoints:** `cuenta.abm` → `get.conciliacion`, `save.conciliacion`

---

### 3.9 Asientos manuales (P1)

**Pantalla:** `asientos.ftl`

| ID | Requisito |
|----|-----------|
| AS-01 | Listado de asientos con filtros (fecha, descripción, confirmado) |
| AS-02 | Alta/edición multi-línea: cuenta, moneda, debe, haber, cotización, unidades |
| AS-03 | Validación: debe = haber, importes ≥ 0, al menos un movimiento |
| AS-04 | Flag confirmado y fecha confirmación |
| AS-05 | Campo ejercicio derivado del año de la fecha |
| AS-06 | Eliminar asiento (y cascada según reglas de origen) |

---

### 3.10 Informes (P0–P1)

Todos consumen `stats` con parámetro `op` y rango `desde`/`hasta`/`zoom` (`mensual`, `anual`, `fecha`).

#### 3.10.1 Balance (P0)

| ID | Requisito |
|----|-----------|
| REP-BAL-01 | Fecha "hasta" (y opcionalmente desde) |
| REP-BAL-02 | Atajos: HOY, mes actual, ±día/mes/año |
| REP-BAL-03 | Checkbox "Con saldo" — ocultar cuentas en cero |
| REP-BAL-04 | Checkbox "Revaluar monedas" — convertir saldos extranjeros a cotización de la fecha |
| REP-BAL-05 | Árbol jerárquico: Activo, Pasivo y PN, Ingresos, Egresos |
| REP-BAL-06 | Columnas en moneda local y USD (según capturas) |
| REP-BAL-07 | Saldos agregados por nivel (`CalculoBalance`) |

#### 3.10.2 Libro Mayor (P1)

| ID | Requisito |
|----|-----------|
| REP-MAY-01 | Selector de cuenta imputable |
| REP-MAY-02 | Rango de fechas |
| REP-MAY-03 | Saldo anterior, movimientos (fecha, asiento, descripción, debe, haber, saldo acumulado) |
| REP-MAY-04 | Soporte moneda extranjera: columnas origen y local |
| REP-MAY-05 | Campo unidades (para FCI / cuotapartes) |

#### 3.10.3 Evolución patrimonial (P1)

| ID | Requisito |
|----|-----------|
| REP-EP-01 | Series temporales de Activo, Pasivo, Ingresos, Egresos, diferencia |
| REP-EP-02 | Variación por período y variación acumulada (%) |
| REP-EP-03 | Valores en moneda local con conversión multimoneda (`ValorMultimoneda`) |

#### 3.10.4 Evolución por cuenta (P1)

| ID | Requisito |
|----|-----------|
| REP-EC-01 | Selector de cuenta |
| REP-EC-02 | Saldo acumulado, movimiento del período, debe/haber, variación % acumulada |

#### 3.10.5 Gastos (P1)

| ID | Requisito |
|----|-----------|
| REP-GAS-01 | Tabla período × categoría de gasto (cuentas 5.x) |
| REP-GAS-02 | Total por período |
| REP-GAS-03 | Excluye movimientos de cuentas clase TC del cálculo |

#### 3.10.6 Intereses (P1)

| ID | Requisito |
|----|-----------|
| REP-INT-01 | Agregación configurable por cuentas ARS y USD (default: `4.3.20.01,4.3.96.00` y `4.3.20.02,4.3.20.00,4.3.95.00`) |
| REP-INT-02 | Columnas: $, U$D, Total en $, Total en U$D |
| REP-INT-03 | Fila de totales |
| REP-INT-04 | Zoom mensual/anual |

#### 3.10.7 Informe Tarjetas (P2)

| ID | Requisito |
|----|-----------|
| REP-TC-01 | Total gastado en TC por período (gráfico/tabla) |

#### 3.10.8 Fondos comunes de inversión (P2)

| ID | Requisito |
|----|-----------|
| REP-FCI-01 | Carga de cotización diaria por fondo (`save.fcicotizacion`) |
| REP-FCI-02 | Muestra cuotapartes × cotización = valor |
| REP-FCI-03 | Al guardar cotización puede generar asiento de rendimiento (`FCICotizacionesDAO`) |
| REP-FCI-04 | Informe: valor, variación % y variación acumulada por fondo |

---

### 3.11 Facturas / NC / ND — AFIP — **FUERA DE ALCANCE**

No se implementará en v2. Tablas y pantallas legacy asociadas pueden eliminarse tras el corte (ver lista de tablas en conversación / `project.mdc`).

~~**Pantalla:** `facturas.ftl` + subpantallas AFIP~~

| ID | Requisito |
|----|-----------|
| FAC-01 | ABM facturas con ítems, IVA por alícuota |
| FAC-02 | Tipos de comprobante según condición IVA emisor/receptor |
| FAC-03 | Emisión electrónica AFIP (WSAA + WSFE): CAE, barcode |
| FAC-04 | ABM CUITs emisores con keystore/certificado |
| FAC-05 | ABM puntos de venta |
| FAC-06 | Pantalla prueba conexión AFIP |
| FAC-07 | Consulta comprobantes AFIP |
| FAC-08 | Envío de factura por email (PDF) |
| FAC-09 | Límite anual por emisor (`limite_anual`) |

**Nota:** Módulo complejo; depende de librerías AFIP en `adbprocessor`. Evaluar usar paquete PHP/Python mantenido o servicio externo.

---

### 3.12 Configuración (P1)

**Menú:** Configuración

| ID | Requisito |
|----|-----------|
| CFG-01 | Pantalla de pares clave/valor agrupados (`configuracion` + `get.configs`/`set.configs`) |
| CFG-02 | **Cuentas** — ABM plan de cuentas (ver 3.13) |
| CFG-03 | **Monedas** — ABM (ver 3.14) |
| CFG-04 | **Tipos de movimiento** — ABM plantillas |
| CFG-05 | **Plantillas de email** — ABM HTML/texto |
| CFG-06 | Parámetros de cotización, SMTP, backup, etc. |

---

### 3.13 Plan de cuentas (P0)

**Pantalla:** `cuentas.ftl`

| ID | Requisito |
|----|-----------|
| CTA-01 | Tabla jerárquica con código, descripción, superior, moneda |
| CTA-02 | Campos: tipo estado contable, tipo cuenta, clase, imputable, habilitada |
| CTA-03 | Filtros por columna |
| CTA-04 | Alta/edición/copia/eliminación |
| CTA-05 | Validación código único |
| CTA-06 | API listado para combos: `?imputable=true&fcodigo=1.1` etc. |
| CTA-07 | Cálculo de saldos por cuenta y rango (`CuentasDAO.getSaldos`) — crítico para informes |

**Clases de cuenta usadas en lógica de negocio:**

| Clase | Uso |
|-------|-----|
| EF | Efectivo / caja |
| CA | Cuenta banco |
| TC | Tarjeta de crédito (pasivo) |
| FCI | Fondo común de inversión |

---

### 3.14 Monedas (P0)

**Pantalla:** `monedas.ftl`

| ID | Requisito |
|----|-----------|
| MON-01 | ABM: código, símbolo, es local (solo una) |
| MON-02 | Moneda local siempre cotización 1 |

---

### 3.15 Tipos de movimiento (P2)

| ID | Requisito |
|----|-----------|
| TM-01 | ABM con rubros origen/destino, descripciones, flag cuotas |
| TM-02 | Integración con `movimientos` — **legacy incompleto** |

---

### 3.16 Plazos fijos (P3)

| ID | Requisito |
|----|-----------|
| PF-01 | Registro: fecha, vencimiento, cuenta banco, cuenta PF, cuenta intereses, importe, intereses |
| PF-02 | Genera asiento constitución (banco → PF) y vencimiento (banco ← PF + intereses) |
| PF-03 | Menú comentado en legacy — confirmar si se usa |

---

### 3.17 Módulos legacy deshabilitados (P3 — evaluar descarte)

| Módulo | Evidencia |
|--------|-----------|
| Coins / crypto trading | `coins.ftl`, `coin_transactions`, menú comentado |
| Mining | `mining.ftl`, `mining_log`, `MiningLogTask` comentado |
| Movimientos genéricos | `movimientos.ftl` comentado, `MovimientoABM` sin validación |
| Geografía (país/provincia/ciudad/barrio) | ABMs existen, no en menú principal |

---

## 4. Tareas en background

| Tarea | Frecuencia | Función |
|-------|------------|---------|
| `CotizacionesTask` | Cada 1h (9–17h) | Descarga cotizaciones USD/EUR/BTC de API configurada |
| `BackupTask` | Al inicio + programado | Backup BD (vía `adbprocessor`) |
| `MiningLogTask` | — | Deshabilitado |

**Requisitos migración:**
- Reemplazar `java.util.Timer` por cron del SO, Laravel Scheduler, o worker Node.
- Idempotencia en fetch de cotizaciones.

---

## 5. Interfaz de usuario (comportamiento transversal)

| ID | Requisito |
|----|-----------|
| UI-01 | Layout admin: sidebar colapsable, header con home/add/fullscreen/usuario |
| UI-02 | Navegación SPA-like: carga de templates vía AJAX (`/template?t=admin/...`) — en rewrite puede ser routing nativo |
| UI-03 | Tablas con paginación server-side, búsqueda por columna, acciones por fila |
| UI-04 | Formularios con validación cliente (jQuery validate) y servidor |
| UI-05 | Formato numérico argentino: `,` decimal, `.` miles (o sin separador miles) |
| UI-06 | Formato fecha `dd/MM/yyyy` en UI; ISO en API/BD |
| UI-07 | Selects de cuentas con búsqueda (codigo + descripción) |
| UI-08 | Material Icons / iconografía similar |
| UI-09 | Idioma: español (Argentina) |

---

## 6. API / contratos (para rewrite)

### 6.1 Patrón legacy

```
GET/POST /app?action_name={modulo}.abm&operation={op}&...
GET     /template?t=admin/{pantalla}
```

Operaciones ABM estándar: `list`, `get`, `save`, `delete`, `datatable`, `custom`.

### 6.2 API REST sugerida (nuevo sistema)

| Recurso | Métodos |
|---------|---------|
| `/api/auth/*` | login, logout, me |
| `/api/cuentas` | CRUD + `GET /saldos?fecha=` |
| `/api/ingresos`, `/api/pagos` | CRUD + filtros `?year=&month=` |
| `/api/asientos` | CRUD |
| `/api/cotizaciones` | CRUD + `POST /fetch` |
| `/api/conciliacion` | GET preview, POST ajuste |
| `/api/tarjetas` | GET liquidación mes, POST liquidar |
| `/api/reportes/{tipo}` | balance, mayor, evolucion-*, gastos, intereses, fci, tarjetas |
| `/api/configuracion` | GET/PATCH |
| `/api/facturas` | CRUD + `POST /{id}/emitir-afip` |

Respuesta JSON unificada:

```json
{ "ok": true, "data": {}, "meta": { "page": 1, "total": 100 } }
{ "ok": false, "errors": [{ "field": "importe", "message": "..." }] }
```

---

## 7. Reglas de negocio críticas (checklist para tests)

| # | Regla |
|---|-------|
| RN-01 | Ingreso/Pago editado regenera asiento (borra anterior) |
| RN-02 | Pago en N cuotas crea N registros hijos con `cuota` 1..N y fechas mensuales (día 15) |
| RN-03 | Asiento manual: suma debe = suma haber (tolerancia 0.01 si se desea) |
| RN-04 | Cotización extranjera: fallback a última cotización ≤ fecha |
| RN-05 | Balance con "revaluar": saldo extranjero × cotización a fecha `hasta` |
| RN-06 | Conciliación: contrapartida siempre en cuenta `5.9.00.00` |
| RN-07 | Tarjeta: cuenta otros = código `5.1.90` homólogo a `2.1.01` de la TC |
| RN-08 | Cuentas deshabilitadas no aparecen en selects (salvo flag `todas`) |
| RN-09 | Solo cuentas imputables en formularios de movimiento |
| RN-10 | FCI: cotización 0 en fin de semana hereda última válida (informe) |

---

## 8. Migración de datos

| Aspecto | Detalle |
|---------|---------|
| BD | MySQL 8 existente; charset migrar de `latin1` a `utf8mb4` |
| Decimales | `decimal(40,20)` — en rewrite `DECIMAL(18,4)` suele bastar; validar rangos |
| IDs | Mantener enteros autoincrementales para migración transparente |
| Histórico | ~36k asientos, ~74k líneas, ~14k pagos, ~2k ingresos (según AUTO_INCREMENT del dump) |
| Seguridad | Re-hashear contraseñas si se cambia algoritmo (`PwdUtil` legacy) |
| AFIP | Certificados `.pfx` en filesystem — migrar a storage seguro |

---

## 9. Fuera de alcance sugerido (v1)

- Cliente SQL web integrado
- Login social OAuth
- Módulos crypto/mining
- Movimientos genéricos incompletos
- Geografía (país/provincia/ciudad) salvo necesidad AFIP
- Editor visual de permisos estilo legacy (simplificar a roles)

---

## 10. Mapa menú → prioridad

| Menú legacy | Prioridad |
|-------------|-----------|
| Inicio | P0 |
| Ingresos | P0 |
| Pagos | P0 |
| Tarjetas | P0 |
| Cotizaciones | P1 |
| Conciliación | P1 |
| Facturas/NC/ND | P2 |
| Asientos | P1 |
| Informes → Balance | P0 |
| Informes → Mayor | P1 |
| Informes → Evolución patrimonial | P1 |
| Informes → Evolución por cuenta | P1 |
| Informes → Gastos | P1 |
| Informes → Intereses | P1 |
| Informes → Tarjetas | P2 |
| Informes → FCI | P2 |
| Configuración → * | P0–P1 |
| Seguridad → * | P2 |

---

## 11. Glosario

| Término | Significado |
|---------|-------------|
| Imputable | Cuenta hoja que admite movimientos |
| Origen / local | Importe en moneda de la cuenta vs convertido a ARS |
| TC | Tarjeta de crédito (cuenta pasivo) |
| FCI | Fondo común de inversión |
| CAE | Código de autorización electrónica AFIP |
| Zoom | Granularidad temporal del informe (día, mes, año) |

---

*Documento generado a partir del análisis del repositorio `D:\ADB\workspace\ADBFinanzas` y esquema `finanzas_nodata.sql`.*
