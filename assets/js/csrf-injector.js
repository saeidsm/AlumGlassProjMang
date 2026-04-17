/**
 * AlumGlass — Global CSRF Token Injector
 *
 * Included from every header (common/ghom/pardis/mobile) to automatically
 * attach the CSRF token to every non-GET request, regardless of whether
 * the call is made via jQuery.ajax, fetch, or XMLHttpRequest.
 *
 * Required setup (already done in headers):
 *   <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
 *
 * Server-side verification (includes/security.php):
 *   verifyCsrfToken() reads $_POST['csrf_token'] and $_SERVER['HTTP_X_CSRF_TOKEN'].
 */
(function () {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // ── jQuery AJAX Interceptor ──
    // jQuery may load after this script (async/defer). Poll briefly, then
    // fall back to DOMContentLoaded. fetch + XHR hooks still cover non-jQuery traffic.
    function hookJQuery() {
        var jq = (typeof jQuery !== 'undefined') ? jQuery : (typeof $ !== 'undefined' ? $ : null);
        if (!jq || typeof jq.ajaxSetup !== 'function') return false;
        jq.ajaxSetup({
            beforeSend: function (xhr, settings) {
                var method = (settings.type || settings.method || 'GET').toUpperCase();
                if (method !== 'GET' && method !== 'HEAD') {
                    xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
                    if (typeof settings.data === 'string' &&
                        settings.data.indexOf('csrf_token=') === -1) {
                        var sep = settings.data.length > 0 ? '&' : '';
                        settings.data += sep + 'csrf_token=' + encodeURIComponent(getCsrfToken());
                    }
                }
            }
        });
        return true;
    }

    if (!hookJQuery()) {
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;
            if (hookJQuery() || attempts > 50) clearInterval(timer);
        }, 100);
        document.addEventListener('DOMContentLoaded', hookJQuery);
    }

    // ── Native fetch() Interceptor ──
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;
        window.fetch = function (url, options) {
            options = options || {};
            var method = (options.method || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD') {
                var headers = options.headers;
                if (headers instanceof Headers) {
                    if (!headers.has('X-CSRF-Token')) {
                        headers.set('X-CSRF-Token', getCsrfToken());
                    }
                } else {
                    headers = headers || {};
                    if (!headers['X-CSRF-Token'] && !headers['x-csrf-token']) {
                        headers['X-CSRF-Token'] = getCsrfToken();
                    }
                    options.headers = headers;
                }

                if (options.body instanceof FormData) {
                    if (!options.body.has('csrf_token')) {
                        options.body.append('csrf_token', getCsrfToken());
                    }
                } else if (typeof URLSearchParams !== 'undefined' &&
                           options.body instanceof URLSearchParams) {
                    if (!options.body.has('csrf_token')) {
                        options.body.append('csrf_token', getCsrfToken());
                    }
                }
            }
            return originalFetch.call(this, url, options);
        };
    }

    // ── XMLHttpRequest Interceptor (legacy code paths) ──
    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method) {
        this._csrfMethod = method;
        return originalOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
        try {
            if (this._csrfMethod && this._csrfMethod.toUpperCase() !== 'GET' &&
                this._csrfMethod.toUpperCase() !== 'HEAD') {
                this.setRequestHeader('X-CSRF-Token', getCsrfToken());
                if (typeof body === 'string' && body.indexOf('csrf_token=') === -1) {
                    var sep = body.length > 0 ? '&' : '';
                    body += sep + 'csrf_token=' + encodeURIComponent(getCsrfToken());
                }
            }
        } catch (e) {
            // Header already sent or body immutable — fall back silently.
        }
        return originalSend.call(this, body);
    };
})();
