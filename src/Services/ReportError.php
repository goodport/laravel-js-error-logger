<?php

namespace Goodport\JsErrorLogger\Services;

use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\Support\ErrorFingerprint;
use Goodport\JsErrorLogger\Support\IgnoreList;
use Goodport\JsErrorLogger\Support\LogSanitizer;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Psr\Log\LoggerInterface;
use Throwable;

final class ReportError
{
    private const MAX_USER_AGENT_LENGTH = 512;

    private bool $loggerFailureReported = false;

    public function __construct(
        private ReportingSettings $settings,
        private IgnoreList $ignoreList,
        private ErrorFingerprint $fingerprint,
        private LogSanitizer $sanitizer,
        private CacheFactory $cacheFactory,
        private LoggerInterface $logger,
        private LoggerInterface $fallbackLogger,
    ) {}

    /**
     * Processes one reported JavaScript error.
     *
     * @param  array<string, mixed>  $error
     */
    public function run(array $error, ?string $userAgent, ?string $ip): void
    {
        [$message, $url, $file, $line, $userAgent] = $this->prepare($error, $userAgent);

        if ($this->ignoreList->shouldIgnore($userAgent, $message)) {
            return;
        }

        $dedupeKey = $this->claimDeduplicationKey($message, $file, $line);

        if ($dedupeKey === false) {
            return;
        }

        if (! $this->writeToLog($message, $url, $file, $line, $userAgent, $ip)) {
            $this->releaseDeduplicationKey($dedupeKey);
        }
    }

    /**
     * Sanitizes the payload and applies the configured limits.
     *
     * @param  array<string, mixed>  $error
     * @return array{0: string, 1: string, 2: ?string, 3: ?int, 4: ?string}
     */
    private function prepare(array $error, ?string $userAgent): array
    {
        $message = $this->sanitizer->sanitize(
            (string) ($error['message'] ?? ''),
            $this->settings->maxMessageLength(),
        );

        $url = (string) ($error['url'] ?? '');
        $file = isset($error['file']) ? (string) $error['file'] : null;
        $line = isset($error['line']) ? (int) $error['line'] : null;

        if ($this->settings->stripQueryStrings()) {
            $url = $this->sanitizer->stripQueryAndFragment($url);
            $file = $file === null ? null : $this->sanitizer->stripQueryAndFragment($file);
        }

        $url = $this->sanitizer->sanitize($url, $this->settings->maxUrlLength());
        $file = $file === null ? null : $this->sanitizer->sanitize($file, $this->settings->maxUrlLength());
        $userAgent = $userAgent === null
            ? null
            : $this->sanitizer->sanitize($userAgent, self::MAX_USER_AGENT_LENGTH);

        return [$message, $url, $file, $line, $userAgent];
    }

    /**
     * Reserves the deduplication key for the error.
     *
     * Returns the cache key when it was reserved, false when the error was already
     * seen in the deduplication window, and null when no key is used.
     */
    private function claimDeduplicationKey(string $message, ?string $file, ?int $line): string|false|null
    {
        if (! $this->settings->dedupeEnabled()) {
            return null;
        }

        $key = 'js-error-logger:'.hash('sha256', $this->fingerprint->make($message, $file, $line));

        try {
            return $this->cacheFactory
                ->store($this->settings->dedupeStore())
                ->add($key, true, $this->settings->dedupeMinutes() * 60)
                ? $key
                : false;
        } catch (Throwable $exception) {
            $this->reportSafely('JavaScript error deduplication failed.', $exception);

            return null;
        }
    }

    /**
     * Releases a reserved key so a later attempt can log the error.
     */
    private function releaseDeduplicationKey(string|false|null $key): void
    {
        if ($key === false || $key === null) {
            return;
        }

        try {
            $this->cacheFactory->store($this->settings->dedupeStore())->forget($key);
        } catch (Throwable $exception) {
            $this->reportSafely('JavaScript error deduplication cleanup failed.', $exception);
        }
    }

    /**
     * Writes the sanitized error to the configured channel.
     */
    private function writeToLog(
        string $message,
        string $url,
        ?string $file,
        ?int $line,
        ?string $userAgent,
        ?string $ip,
    ): bool {
        $context = [
            'url' => $url,
            'file' => $file,
            'line' => $line,
        ];

        if ($this->settings->includeIp() && $ip !== null) {
            $context['ip'] = $ip;
        }

        if ($this->settings->includeUserAgent() && $userAgent !== null) {
            $context['user_agent'] = $userAgent;
        }

        try {
            $this->logger->error($this->settings->logMessagePrefix().$message, $context);

            return true;
        } catch (Throwable $exception) {
            if (! $this->loggerFailureReported) {
                $this->loggerFailureReported = true;
                $this->reportSafely('JavaScript error logging failed.', $exception);
            }

            return false;
        }
    }

    /**
     * Reports a diagnostic failure without breaking the beacon response.
     */
    private function reportSafely(string $message, Throwable $exception): void
    {
        try {
            $this->fallbackLogger->error($message, ['exception' => $exception]);
        } catch (Throwable) {
            // The beacon endpoint must never fail because diagnostics failed.
        }
    }
}
