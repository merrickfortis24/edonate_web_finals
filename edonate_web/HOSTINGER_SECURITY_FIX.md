# Hostinger Laravel Security Fix

This project should use Hostinger's standard Laravel split: the Laravel app lives
outside the served document root, and `public_html` contains only public files.

## Required Structure

```text
/home/USER/domains/edonate.online/
|-- edonate_web/
|   |-- app/
|   |-- bootstrap/
|   |-- config/
|   |-- database/
|   |-- resources/
|   |-- routes/
|   |-- storage/
|   |-- vendor/
|   |-- .env
|   |-- artisan
|   |-- composer.json
|   |-- package.json
|   `-- vite.config.js
`-- public_html/
    |-- index.php
    |-- .htaccess
    |-- build/
    |-- css/
    |-- js/
    |-- vendor/leaflet/
    |-- favicon.ico
    `-- robots.txt
```

Never put these in `public_html`: `.env`, `app/`, `bootstrap/`, `config/`,
`database/`, `resources/`, `routes/`, `storage/`, Composer `vendor/`,
`node_modules/`, `.git/`, `.github/`, SQL dumps, ZIP backups, private keys, or
temporary backup files.

## Move Steps

1. Create `edonate_web` beside `public_html`.
2. Move all Laravel non-public files and folders into `edonate_web`.
3. Move only the contents of Laravel's `public/` directory into `public_html`.
4. Keep `public_html/.htaccess` from Laravel's public directory.
5. Update `public_html/index.php` to load:

   ```php
   require __DIR__.'/../edonate_web/vendor/autoload.php';
   $app = require_once __DIR__.'/../edonate_web/bootstrap/app.php';
   ```

6. Ensure `edonate_web/bootstrap/app.php` sets:

   ```php
   $app->usePublicPath(dirname(__DIR__).'/../public_html');
   ```

7. Ensure Vite writes builds to `../public_html/build`.

## Commands After Moving Files

Run from `edonate_web`:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If frontend assets changed:

```bash
npm ci
npm run build
```

## Verification Checklist

These should still work:

```text
https://edonate.online/
https://edonate.online/login
https://edonate.online/css/...
https://edonate.online/js/...
https://edonate.online/build/...
https://edonate.online/vendor/leaflet/leaflet.min.css
```

These must return `403` or `404`:

```text
https://edonate.online/.env
https://edonate.online/.git/config
https://edonate.online/composer.json
https://edonate.online/composer.lock
https://edonate.online/package.json
https://edonate.online/phpunit.xml
https://edonate.online/artisan
https://edonate.online/config/app.php
https://edonate.online/storage/logs/laravel.log
https://edonate.online/vendor/composer/installed.json
https://edonate.online/edonate_db%20(2).sql
```

## Incident Response

If private files were ever reachable from `public_html`, treat secrets as
compromised:

1. Rotate the Hostinger database password.
2. Update production `.env`.
3. Rotate mail, API, OAuth, webhook, payment, SMS, cloud storage, and Firebase
   credentials if present.
4. Invalidate active sessions if account hijacking is a concern.
5. Review access logs for requests to `.env`, `.git`, Composer files, `vendor/`,
   SQL dumps, ZIP backups, and `storage/logs`.
