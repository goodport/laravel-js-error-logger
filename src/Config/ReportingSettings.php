<?php

namespace Goodport\JsErrorLogger\Config;

use Illuminate\Support\Arr;

final class ReportingSettings
{
    /**
     * Middleware names that break the stateless reporting contract.
     *
     * @var list<string>
     */
    private const RESTRICTED_MIDDLEWARE = ['web', 'session', 'auth'];

    /**
     * @param  list<string>  $routeMiddleware
     * @param  list<string>  $ignoredUserAgents
     * @param  list<string>  $ignoredMessages
     * @param  list<string>  $ignoredRoutes
     */
    private function __construct(
        private bool $enabled,
        private string $routePath,
        private string $routeName,
        private array $routeMiddleware,
        private int $throttlePerMinute,
        private string $logChannel,
        private string $logMessagePrefix,
        private int $maxEventsPerPage,
        private int $maxMessageLength,
        private int $maxUrlLength,
        private bool $dedupeEnabled,
        private ?string $dedupeStore,
        private int $dedupeMinutes,
        private bool $stripQueryStrings,
        private bool $includeIp,
        private bool $includeUserAgent,
        private array $ignoredUserAgents,
        private array $ignoredMessages,
        private array $ignoredRoutes,
        private bool $captureResourceErrors,
        private bool $capturePreloadErrors,
    ) {}

    /**
     * Builds validated settings by merging the package defaults with the application configuration.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $defaults
     */
    public static function fromConfig(array $config, array $defaults = []): self
    {
        $merged = self::merge($defaults, $config);

        $routePath = self::stringValue($merged, 'route.path');
        $routePath = trim($routePath, '/');

        if ($routePath === '') {
            throw InvalidConfigurationException::forKey(
                'route.path',
                Arr::get($merged, 'route.path'),
                'must not be empty after removing leading and trailing slashes',
            );
        }

        return new self(
            enabled: self::booleanValue($merged, 'enabled'),
            routePath: $routePath,
            routeName: self::trimmedStringValue($merged, 'route.name'),
            routeMiddleware: self::middlewareValue($merged),
            throttlePerMinute: self::positiveIntegerValue($merged, 'throttle.per_minute'),
            logChannel: self::trimmedStringValue($merged, 'log.channel'),
            logMessagePrefix: self::stringValue($merged, 'log.message_prefix', allowEmpty: true),
            maxEventsPerPage: self::positiveIntegerValue($merged, 'limits.max_events_per_page'),
            maxMessageLength: self::positiveIntegerValue($merged, 'limits.max_message_length'),
            maxUrlLength: self::positiveIntegerValue($merged, 'limits.max_url_length'),
            dedupeEnabled: self::booleanValue($merged, 'dedupe.enabled'),
            dedupeStore: self::nullableStringValue($merged, 'dedupe.store'),
            dedupeMinutes: self::positiveIntegerValue($merged, 'dedupe.minutes'),
            stripQueryStrings: self::booleanValue($merged, 'privacy.strip_query_strings'),
            includeIp: self::booleanValue($merged, 'privacy.include_ip'),
            includeUserAgent: self::booleanValue($merged, 'privacy.include_user_agent'),
            ignoredUserAgents: self::stringListValue($merged, 'ignore.user_agents'),
            ignoredMessages: self::stringListValue($merged, 'ignore.messages'),
            ignoredRoutes: self::stringListValue($merged, 'ignore.routes'),
            captureResourceErrors: self::booleanValue($merged, 'capture.resource_errors'),
            capturePreloadErrors: self::booleanValue($merged, 'capture.preload_errors'),
        );
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function routePath(): string
    {
        return $this->routePath;
    }

    public function routeName(): string
    {
        return $this->routeName;
    }

    /**
     * @return list<string>
     */
    public function routeMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    public function throttlePerMinute(): int
    {
        return $this->throttlePerMinute;
    }

    public function logChannel(): string
    {
        return $this->logChannel;
    }

    public function logMessagePrefix(): string
    {
        return $this->logMessagePrefix;
    }

    public function maxEventsPerPage(): int
    {
        return $this->maxEventsPerPage;
    }

    public function maxMessageLength(): int
    {
        return $this->maxMessageLength;
    }

    public function maxUrlLength(): int
    {
        return $this->maxUrlLength;
    }

    public function dedupeEnabled(): bool
    {
        return $this->dedupeEnabled;
    }

    public function dedupeStore(): ?string
    {
        return $this->dedupeStore;
    }

    public function dedupeMinutes(): int
    {
        return $this->dedupeMinutes;
    }

    public function stripQueryStrings(): bool
    {
        return $this->stripQueryStrings;
    }

    public function includeIp(): bool
    {
        return $this->includeIp;
    }

    public function includeUserAgent(): bool
    {
        return $this->includeUserAgent;
    }

    /**
     * @return list<string>
     */
    public function ignoredUserAgents(): array
    {
        return $this->ignoredUserAgents;
    }

    /**
     * @return list<string>
     */
    public function ignoredMessages(): array
    {
        return $this->ignoredMessages;
    }

    /**
     * @return list<string>
     */
    public function ignoredRoutes(): array
    {
        return $this->ignoredRoutes;
    }

    public function captureResourceErrors(): bool
    {
        return $this->captureResourceErrors;
    }

    public function capturePreloadErrors(): bool
    {
        return $this->capturePreloadErrors;
    }

    /**
     * Recursively merges configuration arrays while replacing list values.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $overrides): array
    {
        return self::deepMerge($defaults, $overrides);
    }

    /**
     * Recursively merges configuration arrays while replacing list values.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function deepMerge(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $default = $defaults[$key] ?? null;

            if (
                is_array($value)
                && is_array($default)
                && ! self::isList($default)
                && ! self::isList($value)
            ) {
                $defaults[$key] = self::deepMerge($default, $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    /**
     * Determines whether an array is a non-empty list.
     */
    private static function isList(array $value): bool
    {
        return $value !== [] && array_is_list($value);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function stringValue(array $config, string $key, bool $allowEmpty = false): string
    {
        $value = Arr::get($config, $key);

        if (! is_string($value)) {
            throw InvalidConfigurationException::forKey($key, $value, 'must be a string');
        }

        if (! $allowEmpty && trim($value) === '') {
            throw InvalidConfigurationException::forKey($key, $value, 'must not be empty');
        }

        return $value;
    }

    /**
     * Returns a non-empty string value without surrounding whitespace.
     *
     * @param  array<string, mixed>  $config
     */
    private static function trimmedStringValue(array $config, string $key): string
    {
        return trim(self::stringValue($config, $key));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function nullableStringValue(array $config, string $key): ?string
    {
        $value = Arr::get($config, $key);

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw InvalidConfigurationException::forKey($key, $value, 'must be a string or null');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function booleanValue(array $config, string $key): bool
    {
        $value = Arr::get($config, $key);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw InvalidConfigurationException::forKey($key, $value, 'must be a boolean');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function positiveIntegerValue(array $config, string $key): int
    {
        $value = Arr::get($config, $key);

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value <= 0) {
            throw InvalidConfigurationException::forKey($key, $value, 'must be a positive integer');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function middlewareValue(array $config): array
    {
        $value = Arr::get($config, 'route.middleware');

        if (! is_array($value)) {
            throw InvalidConfigurationException::forKey('route.middleware', $value, 'must be an array');
        }

        $middleware = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidConfigurationException::forKey(
                    'route.middleware',
                    $value,
                    'must contain only strings',
                );
            }

            if (self::isRestrictedMiddleware($item)) {
                throw InvalidConfigurationException::forKey(
                    'route.middleware',
                    $item,
                    'must not contain web, session or auth middleware',
                );
            }

            $middleware[] = $item;
        }

        return $middleware;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function stringListValue(array $config, string $key): array
    {
        $value = Arr::get($config, $key);

        if (! is_array($value)) {
            throw InvalidConfigurationException::forKey($key, $value, 'must be an array');
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidConfigurationException::forKey(
                    $key,
                    $value,
                    'must contain only strings',
                );
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Determines whether middleware breaks the stateless reporting contract.
     */
    private static function isRestrictedMiddleware(string $middleware): bool
    {
        return in_array($middleware, self::RESTRICTED_MIDDLEWARE, true)
            || str_starts_with($middleware, 'auth:');
    }
}
