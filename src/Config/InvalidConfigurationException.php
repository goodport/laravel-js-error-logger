<?php

namespace Goodport\JsErrorLogger\Config;

use InvalidArgumentException;

final class InvalidConfigurationException extends InvalidArgumentException
{
    /**
     * Creates an exception for an invalid configuration value.
     */
    public static function forKey(string $key, mixed $value, string $reason): self
    {
        return new self(sprintf(
            'Invalid configuration value for [%s]: %s. Given: %s.',
            $key,
            $reason,
            self::describe($value),
        ));
    }

    /**
     * Returns a short human-readable representation of a configuration value.
     */
    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return "'".$value."'";
        }

        if ($value === null || is_scalar($value)) {
            return var_export($value, true);
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }
}
