/**
 * Minimal service worker for Web Push. Displays notifications pushed by
 * the application (laravel-notification-channels/webpush payload shape).
 */
self.addEventListener("push", function (event) {
    var payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {
            title: "Notification",
            body: event.data ? event.data.text() : ""
        };
    }

    var title = payload.title || "Notification";
    var options = {
        body: payload.body || "",
        icon: payload.icon || "/favicon.ico",
        badge: payload.badge || "/favicon.ico",
        data: payload.data || {}
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", function (event) {
    event.notification.close();

    var url = (event.notification.data && event.notification.data.url) || "/";

    event.waitUntil(clients.openWindow(url));
});
