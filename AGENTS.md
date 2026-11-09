# AGENTS.md

## About the Package

`goodport/laravel-js-error-logger` captures browser JavaScript errors and writes them
to a dedicated Laravel log channel. It provides an inline ES5 beacon, a stateless
POST endpoint, request validation, deduplication, throttling, privacy filtering, and
automatic log channel registration.

## Stack

- PHP 8.2+
- Laravel 12.69+
- Testing: Orchestra Testbench, PHPUnit
- Formatting: Laravel Pint

## Structure

- `src/JsErrorLoggerServiceProvider.php` — configuration merge, container bindings,
  view namespace, component namespace, middleware alias, rate limiter, log channel,
  and route registration.
- `config/js-error-logger.php` — publishable package configuration.
- `routes/web.php` — stateless POST report route built from `ReportingSettings`.
- `src/Config/ReportingSettings.php` — recursive config merge, route path
  normalization, ignored-route parsing, typed accessors, and configuration
  validation.
- `src/Config/InvalidConfigurationException.php` — invalid or missing configuration
  value with the key and the given value.
- `src/Http/Middleware/EnsureReportingEnabled.php` — early `204` when disabled,
  before request validation.
- `src/Http/Requests/ReportRequest.php` — payload validation and bodiless `422`
  responses.
- `src/Http/Controllers/ReportController.php` — passes validated data, IP, and
  User-Agent to the service and returns `204`.
- `src/Services/ReportError.php` — sanitization, ignore lists, deduplication,
  logging, cache failure isolation, and logger failure isolation.
- `src/Support/ErrorFingerprint.php` — message and file normalization for
  deduplication.
- `src/Support/IgnoreList.php` — case-insensitive User-Agent and message fragment
  matching.
- `src/Support/LogSanitizer.php` — control character removal, multibyte-safe
  truncation, and query/fragment stripping.
- `src/Support/RouteExclusions.php` — case-sensitive route-name matching with `*`
  wildcard patterns for beacon rendering.
- `src/Logging/ChannelRegistrar.php` — creates the JSON daily log channel when the
  application has not declared it.
- `src/View/Components/Beacon.php` — enabled check, route exclusion check, request
  route resolution, and nonce resolution.
- `resources/views/components/beacon.blade.php` — inline ES5 client beacon.
- `tests/TestCase.php` — Testbench setup, test routes, beacon request helper, log
  record helpers, and package config overrides.
- `tests/Support/FailingCacheStore.php` — cache store that throws on `add`.
- `tests/Support/FailingLogger.php` — PSR-3 logger that throws on every level.
- `tests/Support/FailingOnceLogger.php` — Monolog logger that throws on the first
  write and then records later writes.
- `tests/Unit/Support/` — `ErrorFingerprint`, `IgnoreList`, `LogSanitizer`, and
  `RouteExclusions` tests.
- `tests/Feature/` — HTTP report, beacon, route exclusion, configuration, and log
  channel tests.

## Data Flow

1. `<x-js-error-logger::beacon />` renders an inline ES5 IIFE when reporting is
   enabled and the current named route does not match `ignore.routes`. The route
   name comes from the current request at render time. The endpoint comes from
   `route(route.name)`; limits come from the configuration through `@json`.
2. The browser posts a JSON `Blob` with `{url, message, file, line}` using
   `navigator.sendBeacon`.
3. The route middleware order is `js-error-logger.enabled`, `throttle:js-error-logger`,
   then `route.middleware`. The route is not in the `web` group.
4. Disabled reporting short-circuits with `204` in middleware, so invalid payloads
   also receive `204`.
5. `ReportRequest` validates the payload. Failures throw `HttpResponseException`
   with a bodiless `422`, because `sendBeacon` cannot send `Accept: application/json`.
6. `ReportError` sanitizes the payload, skips ignored events, reserves
   `js-error-logger:<sha256>` with the cache repository's `add()` method for
   `dedupe.minutes`, and writes to the configured channel.
7. The controller returns `204`.

## Key Decisions

- The route is registered even when disabled, so toggling reporting does not depend
  on a route cache rebuild.
- The package deep-merges defaults because Laravel's `mergeConfigFrom` is shallow.
  The deep merge runs once in boot, after application configuration overrides are
  in place. When the configuration is cached, the already merged configuration is
  used.
- Route path, name, and log channel are validated at settings resolution. Empty
  values and `web`, `session`, `auth`, or `auth:*` middleware fail fast.
- `ReportError` receives the channel logger and the default logger through its
  constructor. It does not use the `Log` or `Cache` facades.
- A cache failure reports to the fallback logger and continues. A logger failure
  reports to the fallback logger once per process, releases the reserved
  deduplication key, and still returns `204`.
- The page URL is not part of the deduplication fingerprint.
- Query strings and fragments are stripped before both logging and fingerprinting.
- `privacy.strip_query_strings` controls both beacon-side stripping and server-side
  sanitization; setting it to `false` preserves them end to end.
- The provider deep-merges defaults in `register()` and again in `boot()`, caching
  the loaded defaults. `ReportingSettings::fromConfig()` also receives the defaults,
  so early or late resolution stays complete.
- The client beacon is ES5 only: `var`, `function`, no arrow functions, no `const`,
  no `let`.
- Client-side truncation never splits a UTF-16 surrogate pair; server-side
  truncation is multibyte-safe.
- The nonce comes from the component attribute first, then from `Vite::cspNonce()`,
  then it is omitted.
- Route-name exclusions are evaluated at render time from the current request, so
  the frozen report payload never carries a route name. Unnamed routes and 404s
  cannot be excluded, and SPA client-side navigations keep the beacon state from
  the initial full-page load.

## Commands

```bash
composer install
composer test
vendor/bin/phpunit
vendor/bin/pint
vendor/bin/pint --test
```

## Tests

- Use Orchestra Testbench.
- Feature tests are HTTP tests. They send a JSON body without an `Accept:
  application/json` header to match `sendBeacon`.
- Package routes are built with `route('js-error-logger.report')`; paths are never
  hardcoded.
- Test routes are named `tests.beacon`, `tests.beacon.nonce`, `tests.excluded`, and
  `tests.shown`; one beacon route is deliberately unnamed.
- The package log channel and the default log channel use Monolog `TestHandler`.
- `FailingCacheStore`, `FailingLogger`, and `FailingOnceLogger` cover failure
  isolation.
- Unit tests cover the pure support classes and do not boot the framework.
- Run only the tests related to the change while developing.

## Rules

- Keep the package stateless: no session, CSRF, authentication, or database access.
- Never hardcode application-specific routes, models, domains, or credentials.
- Keep `route.path`, `route.name`, `log.channel`, and the payload format backward
  compatible; they are public v1 contracts.
- Keep the beacon ES5-compatible and dependency-free.
- Do not add dependencies beyond the declared Composer requirements.
- Update this file when the architecture changes.
