@if ($nonce !== null)
<script type="application/javascript" nonce="{{ $nonce }}">
@else
<script type="application/javascript">
@endif
(function () {
    'use strict';

    if (!navigator.sendBeacon) {
        return;
    }

    var config = @json($config);
    var endpoint = @json($endpoint);
    var sentCount = 0;
    var sentFingerprints = {};

    function pageUrl() {
        var url = window.location.protocol + '//' + window.location.host + window.location.pathname;

        if (config.strip_query_strings === false) {
            url = url + window.location.search + window.location.hash;
        }

        return url;
    }

    function truncate(value, max) {
        var text = String(value);

        if (text.length <= max) {
            return text;
        }

        var shortened = text.slice(0, max);
        var last = shortened.charCodeAt(shortened.length - 1);

        if (last >= 0xD800 && last <= 0xDBFF) {
            shortened = shortened.slice(0, shortened.length - 1);
        }

        return shortened;
    }

    function stripQuery(value) {
        var text = String(value);
        var queryIndex = text.indexOf('?');
        var fragmentIndex = text.indexOf('#');
        var cutIndex = -1;

        if (queryIndex !== -1) {
            cutIndex = queryIndex;
        }

        if (fragmentIndex !== -1 && (cutIndex === -1 || fragmentIndex < cutIndex)) {
            cutIndex = fragmentIndex;
        }

        return cutIndex === -1 ? text : text.slice(0, cutIndex);
    }

    function stripSource(value) {
        var text = String(value);

        return config.strip_query_strings === false ? text : stripQuery(text);
    }

    function isForeignSource(source) {
        if (!source) {
            return false;
        }

        if (source.indexOf('//') === 0) {
            return true;
        }

        if (source.charAt(0) === '/') {
            return false;
        }

        if (source.indexOf(window.location.origin + '/') === 0) {
            return false;
        }

        return true;
    }

    function send(message, file, line, checkSource) {
        if (!message || sentCount >= config.max_events_per_page) {
            return;
        }

        if (message === 'Script error.') {
            return;
        }

        if (checkSource && isForeignSource(file)) {
            return;
        }

        var payloadMessage = truncate(message, config.max_message_length);
        var payloadFile = file ? truncate(stripSource(file), config.max_url_length) : '';
        var payloadLine = line || 0;
        var fingerprint = payloadMessage + '|' + payloadFile + '|' + payloadLine;

        if (sentFingerprints[fingerprint]) {
            return;
        }

        sentFingerprints[fingerprint] = true;

        var payload = {
            url: truncate(pageUrl(), config.max_url_length),
            message: payloadMessage,
            file: payloadFile,
            line: payloadLine
        };

        try {
            var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
        } catch (error) {
            return;
        }

        sentCount = sentCount + 1;
    }

    window.addEventListener('error', function (event) {
        var target = event.target;

        if (target && target !== window) {
            if (target.tagName !== 'SCRIPT' && target.tagName !== 'LINK') {
                return;
            }

            if (!config.capture_resource_errors) {
                return;
            }

            var source = stripSource(target.src || target.href || '');
            send('Failed to load resource: ' + source, source, 0, false);

            return;
        }

        send(event.message, event.filename, event.lineno, true);
    }, true);

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var message;

        if (reason && reason.message) {
            message = reason.message;
        } else {
            message = String(reason);
        }

        var file = '';

        if (reason && reason.stack) {
            file = String(reason.stack).split('\n')[0];
        }

        send(message, file, 0, false);
    });

    window.addEventListener('vite:preloadError', function () {
        if (!config.capture_preload_errors) {
            return;
        }

        send('Failed to load application chunk', window.location.pathname, 0, false);
    });
}());
</script>
