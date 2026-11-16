# MeTransfer

Ein privater Dateitransfer-Dienst à la WeTransfer – nur für dich (und die User, die du per Konsole anlegst).

- Dateien per Drag & Drop hochladen – auch mehrere GB, in 10-MB-Stücken mit automatischer Wiederaufnahme
- Download-Link teilen oder direkt per E-Mail an Empfänger verschicken
- Optional: Ablaufdatum, Passwortschutz, Benachrichtigung bei Download
- Öffentliche Download-Seite mit Einzel-Download und „Alle als ZIP“ (gestreamt)
- Login mit Passwort, **Zwei-Faktor-Authentifizierung (TOTP)** und **Passkeys**
- **Keine Registrierung** – User werden nur per `php artisan` angelegt
- Abgelaufene Transfers und abgebrochene Uploads werden automatisch gelöscht

Technik: Laravel 13, Inertia 3, React 19, Tailwind 4 (Laravel React Starter Kit mit Fortify), SQLite, lokale Disk.

## Lokale Entwicklung

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan user:create
composer dev
```

Danach läuft die App unter http://localhost:8000. Mails landen mit `MAIL_MAILER=log` in `storage/logs/laravel.log`.

## Benutzerverwaltung

| Befehl | Zweck |
|---|---|
| `php artisan user:create` | Neuen Benutzer anlegen (interaktiv, oder `--name= --email= --password=`) |
| `php artisan user:list` | Alle Benutzer mit 2FA-Status, Passkeys, Transfers |
| `php artisan user:password {email}` | Passwort ändern |
| `php artisan user:reset-2fa {email} [--passkeys]` | 2FA (und optional alle Passkeys) zurücksetzen, z. B. bei verlorenem Handy |
| `php artisan user:delete {email} [--force]` | Benutzer samt allen Transfers und Dateien löschen |
| `php artisan transfers:prune` | Abgelaufene Transfers / abgebrochene Uploads löschen (läuft stündlich per Scheduler) |

2FA und Passkeys richtet jeder Benutzer selbst unter *Einstellungen → Sicherheit* ein.

## Konfiguration

In `.env` (Standardwerte in `config/transfers.php`):

| Variable | Standard | Bedeutung |
|---|---|---|
| `TRANSFERS_DISK` | `local` | Speicher-Disk (`storage/app/private`) |
| `TRANSFERS_CHUNK_SIZE` | `10485760` | Größe eines Upload-Stücks in Bytes (muss < `post_max_size` sein) |
| `TRANSFERS_MAX_FILE_SIZE` | `0` | Maximale Dateigröße in Bytes, `0` = unbegrenzt |
| `TRANSFERS_DEFAULT_EXPIRY_DAYS` | `7` | Vorausgewählte Gültigkeit |
| `TRANSFERS_DRAFT_LIFETIME_HOURS` | `24` | Nach wie vielen Stunden abgebrochene Uploads gelöscht werden |
| `TRANSFERS_NOTIFICATION_COOLDOWN` | `60` | Minuten zwischen zwei Download-Benachrichtigungen pro Transfer |

## Betrieb auf einem Server (Nginx + PHP-FPM)

### Voraussetzungen

- PHP ≥ 8.3 mit `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip`, `intl`
- Composer, Node ≥ 20 (nur zum Bauen der Assets)
- **HTTPS** – Passkeys funktionieren nur über HTTPS (oder `localhost`)

### Installation

```bash
git clone https://github.com/REIZFLUT/metransfer.git /var/www/metransfer
cd /var/www/metransfer
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan user:create
chown -R www-data:www-data storage bootstrap/cache database
```

Wichtige `.env`-Werte für Produktion:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://transfer.example.com   # exakt die öffentliche URL – wird als Passkey-Relying-Party verwendet!
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="transfer@example.com"
MAIL_FROM_NAME="MeTransfer"
```

> Achtung: Wird `APP_URL` (die Domain) später geändert, funktionieren bereits registrierte Passkeys nicht mehr.
> In Produktion gelten strengere Passwortregeln (min. 12 Zeichen, Groß-/Kleinbuchstaben, Zahlen, Sonderzeichen, nicht in Leaks).

### PHP (`php.ini` bzw. FPM-Pool)

```ini
post_max_size = 20M
upload_max_filesize = 20M
memory_limit = 256M
max_execution_time = 300
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name transfer.example.com;
    root /var/www/metransfer/public;

    ssl_certificate     /etc/letsencrypt/live/transfer.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/transfer.example.com/privkey.pem;

    index index.php;
    charset utf-8;

    # Größer als TRANSFERS_CHUNK_SIZE
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 3600;   # lange ZIP-Downloads
        fastcgi_buffering off;       # ZIP-Stream direkt ausliefern
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Queue-Worker (Mails) – systemd

`/etc/systemd/system/metransfer-queue.service`:

```ini
[Unit]
Description=MeTransfer Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/metransfer
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now metransfer-queue
```

### Scheduler (Aufräumen) – Cron

```cron
* * * * * www-data cd /var/www/metransfer && php artisan schedule:run >> /dev/null 2>&1
```

### Update

```bash
cd /var/www/metransfer
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
systemctl restart metransfer-queue
```

### Backup

Zu sichern sind `database/database.sqlite`, `storage/app/private/transfers/` und `.env` (enthält `APP_KEY` – ohne ihn sind 2FA-Geheimnisse nicht mehr lesbar).

## Tests

```bash
composer test        # Pint, PHPStan, PHPUnit
npm run check        # Lint + Format
npm run types:check  # TypeScript
```
