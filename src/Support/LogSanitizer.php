<?php

namespace Goodport\JsErrorLogger\Support;

final class LogSanitizer
{
    private const CONTROL_CHARACTERS_PATTERN = '/[\x00-\x1F\x7F-\x9F\x{2028}\x{2029}]/u';

    /**
     * Removes control characters and limits the value to the given length.
     */
    public function sanitize(string $value, int $maxLength): string
    {
        return $this->truncate($this->clean($value), $maxLength);
    }

    /**
     * Replaces control characters with spaces.
     */
    public function clean(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return preg_replace(self::CONTROL_CHARACTERS_PATTERN, ' ', $value) ?? $value;
    }

    /**
     * Truncates a value without splitting a multibyte character.
     */
    public function truncate(string $value, int $maxLength): string
    {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    /**
     * Removes the query string and fragment from a URL or file path.
     */
    public function stripQueryAndFragment(string $value): string
    {
        return substr($value, 0, strcspn($value, '?#'));
    }
}
