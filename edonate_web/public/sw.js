/*
 * eDonate admin MFA service worker.
 *
 * Push payloads contain only a short-lived signed verification URL. The
 * service worker never receives the TOTP secret, password, or the matching
 * number itself.
 */
self.addEventListener('install', function (event) {
    // Activate the newest worker promptly so registration is usable without
    // requiring the admin to close every existing eDonate tab first.
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var payload = {};

    if (event.data) {
        try {
            payload = event.data.json() || {};
        } catch (error) {
            payload = { body: event.data.text() };
        }
    }

    var data = payload.data && typeof payload.data === 'object' ? payload.data : {};
    var title = payload.title || 'eDonate Security Alert';
    var options = {
        body: payload.body || 'Open this notification to approve your eDonate sign-in.',
        icon: payload.icon || '/images/edonate-icon.png',
        badge: payload.badge || '/images/edonate-icon.png',
        tag: payload.tag || 'edonate-admin-mfa',
        renotify: true,
        requireInteraction: true,
        data: {
            url: data.url || payload.url || '/',
            type: data.type || payload.type || 'admin_mfa_number_match'
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var notificationData = event.notification.data || {};
    var targetUrl = notificationData.url;
    if (!targetUrl) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
            for (var index = 0; index < clients.length; index += 1) {
                var client = clients[index];
                if ('focus' in client) {
                    return client.focus().then(function (focusedClient) {
                        if ('navigate' in focusedClient) {
                            return focusedClient.navigate(targetUrl);
                        }
                        return focusedClient;
                    });
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        })
    );
});
