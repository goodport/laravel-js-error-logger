# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-08

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
