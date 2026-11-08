<?php

namespace Goodport\JsErrorLogger\Support;

final class ErrorFingerprint
{
    private const HEX_PATTERN = '/\b[0-9a-f]{8,}\b/i';

    private const NUMBER_PATTERN = '/\d+/';

    public function __construct(private LogSanitizer $sanitizer) {}

    /**
     * Builds the stable identity of an error without the page URL.
     */
    public function make(string $message, ?string $file, ?int $line): string
    {
        return implode('|', [
            $this->normalizeMessage($message),
            $this->normalizeFile($file),
            $line === null ? '' : (string) $line,
        ]);
    }

    /**
     * Replaces long hexadecimal values and numbers with placeholders.
     */
    private function normalizeMessage(string $message): string
    {
        $normalized = preg_replace(self::HEX_PATTERN, '<hash>', $message);
        $normalized = $normalized ?? $message;

        return preg_replace(self::NUMBER_PATTERN, '<n>', $normalized) ?? $normalized;
    }

    /**
     * Removes the query string and fragment from the source file.
     */
    private function normalizeFile(?string $file): string
    {
        if ($file === null || $file === '') {
            return '';
        }

        return $this->sanitizer->stripQueryAndFragment($file);
    }
}
