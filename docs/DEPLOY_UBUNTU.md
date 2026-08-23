# Deploy ADB Finanzas v2 — Ubuntu + MySQL + Nginx

Guía para instalar la app en un VPS/servidor con **Ubuntu 22.04/24.04**, **MySQL local** y **Nginx**.

Supuestos:

- Dominio o IP apuntando al servidor (ej. `finanzas.tudominio.com`)
- Acceso SSH como usuario con `sudo`
- Código en Git (o copia del repo)
- Base MySQL con el esquema/datos legacy (dump o sync desde prod)

Ruta de ejemplo: `/var/www/finanzas`.

---

## 1. Paquetes del sistema

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mysql-server git unzip curl \
  zip gzip mysql-client \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-ftp
```

En Ubuntu 22.04, si no tenés PHP 8.3 en los repos:

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
# luego el mismo bloque php8.3-* de arriba
```

PHP **≥ 8.1** (recomendado 8.2/8.3). Extensiones mínimas: `pdo_mysql`, `mbstring`, `bcmath`, `xml`, `curl`, `zip`. `ftp` solo si usás destinos FTP de backup.

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Node.js 20+ (solo para build del frontend)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

En producción no hace falta dejar Node corriendo: alcanza con `npm run build` en cada deploy.

---

## 2. MySQL

```bash
sudo mysql
```

```sql
CREATE DATABASE finanzas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'finanzas'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD_FUERTE';
GRANT ALL PRIVILEGES ON finanzas.* TO 'finanzas'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Cargar datos

**Opción A — dump existente**

```bash
mysql -u finanzas -p finanzas < /ruta/al/dump.sql
```

**Opción B — sync desde otra máquina** (ver [scripts/README.md](../scripts/README.md)):

```bash
# en el entorno que tenga acceso de lectura a la BD origen
./scripts/db-sync.sh
```

El esquema legacy (`cuentas`, `asientos`, `ingresos`, `pagos`, etc.) debe existir **antes** de las migraciones Laravel (esas solo agregan `users`, `permissions`, `jobs`, etc.).

---

## 3. Código de la aplicación

```bash
sudo mkdir -p /var/www
sudo chown "$USER":www-data /var/www
cd /var/www

git clone <URL_DEL_REPO> finanzas
cd finanzas
```

Si no usás Git, subí el proyecto (sin `node_modules` / `vendor`) y descomprimilo ahí.

---

## 4. Variables de entorno

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Valores típicos de producción:

```env
APP_NAME="ADB Finanzas"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://finanzas.tudominio.com
APP_TIMEZONE=America/Argentina/Buenos_Aires

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finanzas
DB_USERNAME=finanzas
DB_PASSWORD=CAMBIAR_PASSWORD_FUERTE

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=stack
LOG_LEVEL=error

# Backup
BACKUP_DISKS=local
BACKUP_DUMP_GZIP=true
BACKUP_NOTIFICATION_EMAIL=tu@email.com
# Opcional FTP: BACKUP_DISKS=local,backup_ftp + BACKUP_FTP_*
```

`APP_URL` debe coincidir con la URL pública (incluye `https://` si usás TLS).

---

## 5. Dependencias, build y migraciones

```bash
cd /var/www/finanzas

composer install --no-dev --optimize-autoloader

npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=RolesSeeder --force
php artisan finanzas:import-users --password='CAMBIAR_PASSWORD_INICIAL'
```

Tras el import, cambiá las contraseñas desde la UI (admin). Los hashes legacy no son compatibles con bcrypt.

Verificación rápida:

```bash
php artisan finanzas:db-verify
php artisan schedule:list
```

Índices opcionales de performance (si aún no están en la BD):

```bash
mysql -u finanzas -p finanzas < scripts/add-performance-indexes.sql
```

---

## 6. Permisos

```bash
cd /var/www/finanzas

sudo chown -R "$USER":www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

El usuario de deploy necesita poder escribir en `storage` y `bootstrap/cache` (o hacerlo como `www-data` / con grupo compartido).

---

## 7. PHP-FPM

Comprobar el socket (ajusta la versión):

```bash
ls /run/php/php8.3-fpm.sock
sudo systemctl enable --now php8.3-fpm
```

Opcional — subir límites en `/etc/php/8.3/fpm/php.ini` o pool:

```ini
upload_max_filesize = 20M
post_max_size = 20M
memory_limit = 256M
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 8. Nginx

```bash
sudo nano /etc/nginx/sites-available/finanzas
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name finanzas.tudominio.com;
    root /var/www/finanzas/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

Activar y probar:

```bash
sudo ln -s /etc/nginx/sites-available/finanzas /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d finanzas.tudominio.com
```

Después, confirmá `APP_URL=https://finanzas.tudominio.com` y:

```bash
php artisan config:cache
```

---

## 9. Cron (scheduler)

Cotizaciones (9–17h), backup diario 03:00 y limpieza 03:30.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/finanzas && php artisan schedule:run >> /dev/null 2>&1
```

Comprobar:

```bash
sudo -u www-data php /var/www/finanzas/artisan schedule:list
```

Con `QUEUE_CONNECTION=sync` no hace falta un worker de colas: los jobs del scheduler corren en el mismo proceso.

`mysqldump` y `gzip` deben estar en el PATH de `www-data` (paquetes `mysql-client` / `gzip` del paso 1).

---

## 10. Cachés de producción

```bash
cd /var/www/finanzas
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Tras cambiar `.env`:

```bash
php artisan config:clear
php artisan config:cache
```

---

## 11. Actualizar (deploy recurrente)

Un solo comando desde la raíz del proyecto:

```bash
cd /var/www/finanzas
chmod +x update.sh   # solo la primera vez
./update.sh
```

El script hace: `git pull` → `composer install` → `npm ci` + `npm run build` → `migrate` → cachés → reload PHP-FPM. Pone la app en mantenimiento mientras corre.

Opciones:

```bash
./update.sh --skip-frontend          # sin npm (build hecho en otro lado)
./update.sh --skip-fpm               # no recarga PHP-FPM
BRANCH=main PHP_FPM_SERVICE=php8.3-fpm ./update.sh
```

Equivalente manual:

```bash
cd /var/www/finanzas
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.3-fpm
```

---

## 12. Checklist post-install

| Check | Cómo |
|-------|------|
| Login | Abrir `APP_URL`, entrar con usuario importado |
| Scheduler | `php artisan schedule:list` |
| Cotizaciones | UI o `php artisan finanzas:fetch-cotizaciones` |
| Backup | `php artisan finanzas:backup` y pantalla admin Backup |
| Permisos | `storage/logs/laravel.log` escribible |
| HTTPS | Certbot renovando (`sudo certbot renew --dry-run`) |

---

## 13. Problemas frecuentes

**502 Bad Gateway** — socket PHP-FPM incorrecto en Nginx o servicio caído:

```bash
sudo systemctl status php8.3-fpm
ls /run/php/
```

**500 / permission denied** — ownership de `storage` y `bootstrap/cache` (paso 6).

**Página en blanco / assets 404** — faltó `npm run build` o `APP_URL` mal; los assets viven en `public/build`.

**Cron no corre** — crontab de `www-data`, path absoluto a `artisan`, timezone `APP_TIMEZONE`.

**Backup falla** — `mysqldump` no en PATH; probar `sudo -u www-data mysqldump --version`.

**Login rechazado** — usuarios inactivos o sin import: `php artisan finanzas:import-users --password=...`.

---

## Referencias

- Setup local: [README.md](../README.md)
- Sync BD: [scripts/README.md](../scripts/README.md)
- Backup / FTP: sección Backup del README
