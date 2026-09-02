# eDonate hybrid admin MFA setup

The admin login keeps Google Authenticator (TOTP) as a fallback and adds a
short-lived number-matching approval flow. The approval device is a browser
that has been registered from **Admin Portal → Settings → Two-Factor
Authentication**.

## Install the Web Push dependency

The application uses `minishlink/web-push` and Laravel's configured cache
store. Install dependencies from the project root with:

```bash
composer install
```

The package is used by `App\Services\AdminMfaService`; it creates a VAPID
authenticated `WebPush` client and sends a signed, expiring approval URL to
the saved browser subscription. The push payload never contains a password,
TOTP secret, or the matching number.

## Generate VAPID keys

Run this once from the project root. Keep the private key secret and keep the
same pair when deploying updates:

```bash
php -r "require 'vendor/autoload.php'; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());"
```

Copy the printed `publicKey` and `privateKey` values to the application
environment:

```dotenv
VAPID_SUBJECT=mailto:security@edonate.online
VAPID_PUBLIC_KEY=your-generated-public-key
VAPID_PRIVATE_KEY=your-generated-private-key
```

For Hostinger, place these in the deployed `.env` file, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php scripts/sync-public-assets.php --web-root="$HOME/domains/edonate.online/public_html"
```

The migration creates `admin_devices`. Its `user_id` column stores the
existing eDonate `admins.admin_id` value, because this project uses a custom
`admins` table rather than Laravel's default `users` table.

## Register a browser

1. Sign in with the existing Google Authenticator flow.
2. Open **Settings → Two-Factor Authentication**.
3. Click **Register This Browser** and allow notifications.
4. Keep this browser or phone available for future admin sign-ins.

The service worker is `public/sw.js` and is copied to the Hostinger served
web root by the public-asset sync script. Push notifications require HTTPS in
production (localhost is also allowed by browsers during local development).

## Login behavior

After the password is accepted, the login modal displays a random two-digit
number. The registered browser receives **eDonate Security Alert** and opens a
signed mobile approval page with three choices. The selected number is
validated server-side and the desktop modal completes the existing admin
session. The desktop uses the approval event through Laravel Echo/Reverb when
configured and always keeps a cache-backed polling fallback for Hostinger
deployments without a persistent WebSocket process.

If push delivery is unavailable, choose **Use Google Authenticator code
instead**. The original TOTP and recovery-code workflow remains unchanged.

## Optional Reverb/Echo configuration

The backend event is `App\Events\LoginApprovedEvent` on the private
`admin-mfa.{challenge}` channel. If the project is connected to Reverb or a
Pusher-compatible broadcaster, configure the matching Laravel broadcasting
environment variables and expose the Echo client as `window.Echo` on the
login page. The modal listens for `.LoginApproved`; cache polling remains the
source of truth and is intentionally retained as the production fallback.
