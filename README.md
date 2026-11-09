# Laravel JS Error Logger

Capture browser JavaScript errors and write them to a dedicated Laravel log channel.

The package ships a small inline beacon, a stateless POST endpoint, validation,
deduplication, throttling, and automatic log channel registration. It works without
publishing configuration, without a session, and without changes to the application
CSRF setup.

## What it captures

- Uncaught JavaScript errors.
- Unhandled promise rejections.
- Failed `<script>` and `<link>` resource loads.
- Vite chunk preload failures.

The beacon uses `navigator.sendBeacon` and is written in ES5, so it does not need a
build step and does not depend on application bundles.

## Requirements

- PHP 8.2 or newer.
- Laravel 12.69 or newer. Composer blocks older Laravel releases because of active
  security advisories.
- The `mbstring` PHP extension.
- A browser with `navigator.sendBeacon`. Older browsers silently skip reporting.

## Installation

```bash
composer require goodport/laravel-js-error-logger
```

The service provider is discovered automatically. The log channel is registered when
the package boots; no migration or manual logging configuration is required.

Place the beacon in the document `<head>` before application bundles, so early errors
are captured:

```blade
<head>
    <x-js-error-logger::beacon />
</head>
```

The component renders only when `enabled` is `true` and the current route is not
excluded (see [Route exclusions](#route-exclusions)).

When the application uses a Content-Security-Policy, pass a per-response nonce:

```blade
<x-js-error-logger::beacon :nonce="$cspNonce" />
```

If the attribute is omitted, the component uses `Vite::cspNonce()` when it is
available.

## Publishing

Publish the configuration or the views only when you need to change them:

```bash
php artisan vendor:publish --tag=js-error-logger-config
php artisan vendor:publish --tag=js-error-logger-views
```

The template can also be overridden at:

```
resources/views/vendor/js-error-logger/components/beacon.blade.php
```

## Configuration

```php
// config/js-error-logger.php
return [
    'enabled' => env('JS_ERROR_LOGGER_ENABLED', true),

    'route' => [
        'path' => env('JS_ERROR_LOGGER_PATH', 'js-error-logger'),
        'name' => 'js-error-logger.report',
        'middleware' => [],
    ],

    'throttle' => [
        'per_minute' => env('JS_ERROR_LOGGER_THROTTLE', 20),
    ],

    'log' => [
        'channel' => env('JS_ERROR_LOGGER_CHANNEL', 'js-error-logger'),
        'message_prefix' => 'Frontend JavaScript error: ',
    ],

    'limits' => [
        'max_events_per_page' => 5,
        'max_message_length' => 300,
        'max_url_length' => 2048,
    ],

    'dedupe' => [
        'enabled' => true,
        'store' => null,
        'minutes' => 30,
    ],

    'privacy' => [
        'strip_query_strings' => true,
        'include_ip' => true,
        'include_user_agent' => true,
    ],

    'ignore' => [
        'user_agents' => ['bot', 'spider', 'crawler', 'slurp'],
        'messages' => [
            'request aborted',
            'user aborted a request',
            'was aborted',
            'aborted without reason',
        ],
        'routes' => [],
    ],

    'capture' => [
        'resource_errors' => true,
        'preload_errors' => true,
    ],
];
```

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Enables the beacon, the endpoint, and error writing. |
| `route.path` | `js-error-logger` | Report endpoint path. Leading and trailing slashes are removed. |
| `route.name` | `js-error-logger.report` | Report endpoint route name. |
| `route.middleware` | `[]` | Extra route middleware. `web`, `session`, `auth`, and `auth:*` are rejected. |
| `throttle.per_minute` | `20` | Per-IP request limit for the endpoint. |
| `log.channel` | `js-error-logger` | Log channel used for reported errors. |
| `log.message_prefix` | `Frontend JavaScript error: ` | Prefix added to the logged message. |
| `limits.max_events_per_page` | `5` | Maximum beacon events sent from one page load. |
| `limits.max_message_length` | `300` | Maximum accepted and logged message length. |
| `limits.max_url_length` | `2048` | Maximum accepted and logged URL or file length. |
| `dedupe.enabled` | `true` | Enables server-side deduplication. |
| `dedupe.store` | `null` | Cache store name. `null` uses the application default store. |
| `dedupe.minutes` | `30` | Deduplication window in minutes. |
| `privacy.strip_query_strings` | `true` | Strips query strings and fragments from beacon URLs, resource sources, stack files, and logged data. Set to `false` to preserve them on both the client and the server. |
| `privacy.include_ip` | `true` | Adds the remote IP to the log context. |
| `privacy.include_user_agent` | `true` | Adds the User-Agent to the log context. |
| `ignore.user_agents` | bot list | Case-insensitive User-Agent fragments that are ignored. |
| `ignore.messages` | abort list | Case-insensitive message fragments that are ignored. |
| `ignore.routes` | `[]` | Route names or `*` patterns where the beacon is not rendered. |
| `capture.resource_errors` | `true` | Captures failed script and stylesheet loads. |
| `capture.preload_errors` | `true` | Captures `vite:preloadError` events. |

The package recursively merges these defaults with the application configuration.
A partially published config therefore keeps nested defaults that it does not
override.

Invalid configuration fails fast with
`Goodport\JsErrorLogger\Config\InvalidConfigurationException`. This includes empty
route paths and names, empty log channels, non-positive numeric limits, and
`web`, `session`, `auth`, or `auth:*` route middleware.

### Route exclusions

Add route names or `*` patterns to `ignore.routes` to skip rendering the beacon on
those pages. Matching runs at render time against the current named route, so it
uses the route name the application actually resolved. Patterns follow the
`Str::is()` wildcard syntax and are case-sensitive.

```php
'ignore' => [
    'routes' => ['checkout.*', 'account.settings'],
],
```

Unnamed routes and error pages such as `404` have no route name and cannot be
excluded this way. In SPA and Inertia applications, client-side navigations do not
re-render the Blade component, so `ignore.routes` only applies to the route of the
initial full-page load.

## How it works

1. The Blade component renders an inline ES5 beacon unless reporting is disabled or
   the current named route matches `ignore.routes`. The endpoint comes from the
   named route and the client limits come from the package configuration.
2. The beacon posts a JSON `Blob` with `{url, message, file, line}`.
3. The endpoint runs `js-error-logger.enabled`, then the named rate limiter, then
   any configured route middleware. It is not placed in the `web` middleware group,
   so no session or CSRF token is involved.
4. When `enabled` is `false`, the middleware returns `204` before request
   validation, including for invalid payloads.
5. `ReportRequest` validates the payload. Validation failures always return a
   bodiless `422`, because `sendBeacon` cannot set an `Accept` header.
6. `ReportError` sanitizes the payload, applies the ignore lists, reserves a
   deduplication key, and writes to the configured channel.
7. The endpoint returns `204`. A cache or logger failure never breaks this response.

The client also rejects `Script error.`, foreign-origin sources for window errors,
and repeated fingerprints within the same page. Client-side events are capped by
`limits.max_events_per_page`.

### Deduplication

The server builds a fingerprint from the message, file, and line. The page URL is
not included, so the same error on different pages is written once. The fingerprint
key is stored with `Cache::add()` for `dedupe.minutes`.

Deduplication reduces noise; it is not an abuse protection. The per-IP throttle is
the request limiter.

If the cache store fails, the error is still written and the cache failure is sent
to the application default log. If the configured log channel fails, the endpoint
still returns `204`, and the failure is reported once per process to the default log.

## Log channel

The channel name comes from `log.channel`, which defaults to `js-error-logger`.
When `logging.channels.<log.channel>` is not declared, the package registers a
`daily` channel under that name:

- path: `storage/logs/js-errors.log`;
- level: `error`;
- retention: 30 days;
- formatter: JSON.

An application-declared channel is never overwritten. Set `log.channel` to an
existing channel such as `stack` to use it instead.

A nonexistent channel is handled by Laravel's own log manager, which falls back to
the emergency logger. The package does not hide that framework behavior.

## Responses

| Situation | Response |
| --- | --- |
| Accepted event | `204 No Content` |
| Reporting disabled | `204 No Content`, before validation |
| Invalid payload | `422` with an empty body |
| Rate limit exceeded | `429` produced by Laravel |
| Logger or cache failure | `204 No Content` |

The `429` response is generated by the framework and can contain a body and
headers.

## Privacy and security

- The endpoint is public by design. Message length, URL and file length, page
  event count, and request rate are limited.
- All C0 and C1 control characters, `\x08`, `\t`, `\r`, `\n`, `\0`, U+2028, and
  U+2029 are replaced before logging.
- Message, URL, and file lengths are bounded; client truncation is surrogate-safe
  and server truncation is multibyte-safe.
- Query strings and fragments are removed from beacon URLs, resource sources, stack
  files, and logged data by default. Set `privacy.strip_query_strings` to `false` to
  preserve them on both the client and the server.
- IP and User-Agent can be excluded from the log context.
- Payload content is written to the log only; it is never rendered as HTML or
  executed.
- The component escapes the nonce attribute.

The browser still sends same-origin cookies because that is normal browser behavior.
The package does not start a session and does not use cookie data.

## Limitations

- Cross-origin errors without CORS are reported by the browser as `Script error.`
  and are intentionally skipped.
- Stacks are not sent. Promise rejections include only the first stack line as the
  file value.
- No user, request, or page context is sent beyond the page URL.
- Deduplication and the rate limiter both use the application cache store. The
  cache must be shared between application processes; the `array` store disables
  deduplication and cross-request throttling in production.
- `X-Forwarded-For` is trusted only as far as the application's `TrustProxies`
  configuration trusts it.
- Reporting is best effort: the endpoint never reports an error caused by the
  diagnostic request itself.

## Route cache

The route is registered through `loadRoutesFrom`, so it remains compatible with
`php artisan route:cache`. The path, name, and middleware are fixed when the route
cache is built. After changing `route.path`, `route.name`, or `route.middleware`,
run:

```bash
php artisan route:clear
php artisan route:cache
```

## Development

```bash
composer install
composer test
composer format
```

The test suite uses Orchestra Testbench. It does not need a database, migrations,
network access, or a browser.

## License

MIT. See [LICENSE](LICENSE).
