# Scripts de base de datos

## Copiar producción → desarrollo

1. Copiá la configuración:
   ```bash
   cp scripts/.env.sync.example scripts/.env.sync
   ```
2. Editá `scripts/.env.sync` con credenciales reales.
3. Creá la base destino vacía (solo la primera vez):
   ```sql
   CREATE DATABASE finanzas_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Ejecutá el sync:

   **Linux / WSL / Git Bash:**
   ```bash
   chmod +x scripts/db-sync.sh
   ./scripts/db-sync.sh
   ```

   **Windows PowerShell:**
   ```powershell
   .\scripts\db-sync.ps1
   ```

5. Repetí cuando quieras datos frescos:
   ```bash
   ./scripts/db-sync.sh --force
   ```

## Requisitos

- Cliente MySQL (`mysql`, `mysqldump`) en PATH
- Usuario de **solo lectura** en producción (recomendado)
- PHP en PATH (opcional, para resetear password del usuario admin de dev)

## Notas

- `scripts/.env.sync` no debe commitearse (agregar a `.gitignore`).
- Por defecto excluye tablas de mining/crypto del dump.
- Tras el restore, el usuario `SYNC_DEV_ADMIN_USER` queda con password `SYNC_DEV_ADMIN_PASSWORD`.
- El hash se guarda en formato bcrypt; cuando Laravel use tabla `users`, el comando `finanzas:import-users` migrará usuarios.
