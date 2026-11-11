/**
 * Registers /sw.js, asks for notification permission and stores the
 * browser push subscription on the authenticated user via the
 * Sanctum-protected API (session cookie + CSRF token).
 *
 * Requires a <meta name="vapid-public-key"> tag in the layout head.
 */
(function () {
    "use strict";

    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
        return;
    }

    var vapidKeyMeta = document.head.querySelector('meta[name="vapid-public-key"]');
    var csrfMeta = document.head.querySelector('meta[name="csrf-token"]');

    if (!vapidKeyMeta || !vapidKeyMeta.content) {
        return;
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function postSubscription(subscription) {
        var json = subscription.toJSON();
        var encodings = PushManager.supportedContentEncodings || ["aes128gcm"];
        var contentEncoding = encodings.indexOf("aes128gcm") !== -1 ? "aes128gcm" : "aesgcm";

        return fetch("/api/v2/push-subscriptions", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfMeta ? csrfMeta.content : ""
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: json.keys,
                contentEncoding: contentEncoding
            })
        });
    }

    window.addEventListener("load", function () {
        navigator.serviceWorker
            .register("/sw.js")
            .then(function (registration) {
                if (Notification.permission === "denied") {
                    return;
                }

                return Notification.requestPermission().then(function (permission) {
                    if (permission !== "granted") {
                        return;
                    }

                    return registration.pushManager
                        .getSubscription()
                        .then(function (existing) {
                            if (existing) {
                                return existing;
                            }

                            return registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(vapidKeyMeta.content)
                            });
                        })
                        .then(function (subscription) {
                            if (subscription) {
                                return postSubscription(subscription);
                            }
                        });
                });
            })
            .catch(function (error) {
                console.error("Push subscription failed:", error);
            });
    });
})();
