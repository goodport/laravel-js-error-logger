# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-09

### Changed

- **Breaking:** the package now requires PHP 8.2+ and Laravel 12.69+. PHP 8.1,
  Laravel 10, and Laravel 11 are no longer supported. Composer blocks those Laravel
  releases because of active security advisories, so they cannot be installed or
  tested with current Composer versions.

### Removed

- CI coverage for PHP 8.1, Laravel 10, and Laravel 11.

## [1.1.0] - 2026-11-09

### Added

- Route-name exclusions through `ignore.routes`: accepts route names or `*` patterns,
  and the beacon is not rendered on matching routes.

## [1.0.0] - 2026-11-08

First stable release. The public configuration keys, route name, log channel name,
and beacon payload format are now a backward-compatible contract.

### Added

- Inline ES5 browser beacon that captures uncaught errors, unhandled promise
  rejections, failed script and stylesheet loads, and Vite preload errors.
- Stateless POST report endpoint with configurable path, route name, and middleware.
- Early `204` response when reporting is disabled, before payload validation.
- Bodiless `422` validation responses for `navigator.sendBeacon` clients.
- Per-IP throttling with a configurable per-minute limit.
- Cache-backed deduplication with a configurable store and window.
- User-Agent and message ignore lists.
- Privacy controls for query strings, IP addresses, and User-Agents.
- Automatic JSON daily log channel registration.
- Optional Content-Security-Policy nonce from a component attribute or
  `Vite::cspNonce()`.
- Blade component, publishable configuration, and publishable views.

## [0.0.1] - 2026-11-08

Initial public preview, released before the package was declared stable. The code is
identical to 1.0.0; use 1.0.0 or newer.
