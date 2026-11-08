<?php

namespace Goodport\JsErrorLogger\Tests\Feature;

use Goodport\JsErrorLogger\Tests\TestCase;
use Illuminate\Log\LogManager;
use Monolog\Handler\TestHandler;

final class ReportTest extends TestCase
{
    /**
     * A valid beacon payload is written to the configured channel with context.
     */
    public function test_valid_error_is_logged(): void
    {
        $response = $this->postBeacon($this->payload());

        $response->assertNoContent();

        $records = $this->packageLogRecords();

        $this->assertCount(1, $records);
        $this->assertSame('Frontend JavaScript error: TypeError: value is undefined', $records[0]['message']);
        $this->assertSame('https://example.com/page', $records[0]['context']['url']);
        $this->assertSame('https://example.com/assets/app.js', $records[0]['context']['file']);
        $this->assertSame(12, $records[0]['context']['line']);
        $this->assertSame('203.0.113.10', $records[0]['context']['ip']);
        $this->assertSame('Mozilla/5.0', $records[0]['context']['user_agent']);
    }

    /**
     * A repeated error inside the deduplication window is written once.
     */
    public function test_repeated_error_is_written_once(): void
    {
        $this->postBeacon($this->payload())->assertNoContent();
        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(1, $this->packageLogRecords());
    }

    /**
     * The page URL is not part of the deduplication fingerprint.
     */
    public function test_page_url_does_not_affect_deduplication(): void
    {
        $this->postBeacon($this->payload(['url' => 'https://example.com/first']))->assertNoContent();
        $this->postBeacon($this->payload(['url' => 'https://example.com/second']))->assertNoContent();

        $this->assertCount(1, $this->packageLogRecords());
    }

    /**
     * A disabled package returns 204 and does not validate the payload.
     */
    public function test_disabled_package_accepts_valid_payload_without_logging(): void
    {
        $this->setPackageConfig('enabled', false);

        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * A disabled package returns 204 even for an invalid payload.
     */
    public function test_disabled_package_accepts_invalid_payload(): void
    {
        $this->setPackageConfig('enabled', false);

        $response = $this->postBeacon(['url' => 'https://example.com/page']);

        $response->assertStatus(204);
        $this->assertSame('', $response->getContent());
        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * Errors reported by a bot user agent are ignored.
     */
    public function test_robot_user_agent_is_ignored(): void
    {
        $this->postBeacon($this->payload(), [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ])->assertNoContent();

        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * Aborted requests are ignored.
     */
    public function test_aborted_request_message_is_ignored(): void
    {
        $this->postBeacon($this->payload([
            'message' => 'net::ERR_ABORTED (request aborted)',
        ]))->assertNoContent();

        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * An invalid payload gets a bodiless 422 response without a redirect.
     */
    public function test_invalid_payload_gets_a_bodiless_422(): void
    {
        $response = $this->postBeacon([
            'url' => 'https://example.com/page',
        ]);

        $response->assertStatus(422);
        $this->assertSame('', $response->getContent());
        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * A message at the configured limit is accepted and logged.
     */
    public function test_message_at_the_length_limit_is_logged(): void
    {
        $message = str_repeat('a', 300);

        $this->postBeacon($this->payload(['message' => $message]))->assertNoContent();

        $records = $this->packageLogRecords();

        $this->assertCount(1, $records);
        $this->assertSame('Frontend JavaScript error: '.$message, $records[0]['message']);
    }

    /**
     * A message one character above the configured limit gets a bodiless 422.
     */
    public function test_message_over_the_length_limit_gets_a_bodiless_422(): void
    {
        $response = $this->postBeacon($this->payload([
            'message' => str_repeat('a', 301),
        ]));

        $response->assertStatus(422);
        $this->assertSame('', $response->getContent());
        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * Deduplication can be disabled so the same error is logged every time.
     */
    public function test_dedupe_disabled_logs_the_same_error_twice(): void
    {
        $this->setPackageConfig('dedupe.enabled', false);

        $this->postBeacon($this->payload())->assertNoContent();
        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(2, $this->packageLogRecords());
    }

    /**
     * Query strings and fragments are kept when stripping is disabled.
     */
    public function test_query_strings_are_preserved_when_stripping_is_disabled(): void
    {
        $this->setPackageConfig('privacy.strip_query_strings', false);

        $this->postBeacon($this->payload([
            'url' => 'https://example.com/page/42?token=secret#section',
            'file' => 'https://example.com/assets/app.js?v=1#top',
        ]))->assertNoContent();

        $context = $this->packageLogRecords()[0]['context'];

        $this->assertSame('https://example.com/page/42?token=secret#section', $context['url']);
        $this->assertSame('https://example.com/assets/app.js?v=1#top', $context['file']);
    }

    /**
     * A custom log channel receives the reported error.
     */
    public function test_custom_log_channel_is_used(): void
    {
        $this->app['config']->set('logging.channels.custom', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);
        $this->setPackageConfig('log.channel', 'custom');

        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(1, $this->logRecordsFor('custom'));
        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * A null line is accepted.
     */
    public function test_null_line_is_accepted(): void
    {
        $this->postBeacon($this->payload(['line' => null]))->assertNoContent();

        $records = $this->packageLogRecords();

        $this->assertCount(1, $records);
        $this->assertNull($records[0]['context']['line']);
    }

    /**
     * A negative line gets a bodiless 422.
     */
    public function test_negative_line_gets_a_bodiless_422(): void
    {
        $response = $this->postBeacon($this->payload(['line' => -1]));

        $response->assertStatus(422);
        $this->assertSame('', $response->getContent());
        $this->assertCount(0, $this->packageLogRecords());
    }

    /**
     * Requests above the per-minute limit receive 429.
     */
    public function test_requests_above_the_limit_receive_429(): void
    {
        $this->setPackageConfig('throttle.per_minute', 2);

        $this->postBeacon($this->payload(['message' => 'First error']))->assertNoContent();
        $this->postBeacon($this->payload(['message' => 'Second error']))->assertNoContent();
        $this->postBeacon($this->payload(['message' => 'Third error']))->assertStatus(429);
    }

    /**
     * A cache failure does not lose the error and is reported to the fallback log.
     */
    public function test_cache_failure_does_not_lose_the_error(): void
    {
        $this->setPackageConfig('dedupe.store', 'failing');

        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(1, $this->packageLogRecords());

        $fallbackRecords = $this->defaultLogRecords();

        $this->assertCount(1, $fallbackRecords);
        $this->assertSame('JavaScript error deduplication failed.', $fallbackRecords[0]['message']);
    }

    /**
     * A logger failure still returns 204 and is reported to the fallback log once per process.
     */
    public function test_logger_failure_returns_204_and_is_reported_once(): void
    {
        $this->app['config']->set('logging.channels.js-error-logger', ['driver' => 'failing']);
        $this->app->make(LogManager::class)->forgetChannel('js-error-logger');

        $this->postBeacon($this->payload(['message' => 'First failure']))->assertNoContent();
        $this->postBeacon($this->payload(['message' => 'Second failure']))->assertNoContent();

        $fallbackRecords = $this->defaultLogRecords();

        $this->assertCount(1, $fallbackRecords);
        $this->assertSame('JavaScript error logging failed.', $fallbackRecords[0]['message']);
    }

    /**
     * A logger failure releases the deduplication key so a later attempt is logged.
     */
    public function test_dedupe_key_is_released_after_a_logger_failure(): void
    {
        $this->app['config']->set('logging.channels.js-error-logger', ['driver' => 'failing_once']);
        $this->app->make(LogManager::class)->forgetChannel('js-error-logger');

        $this->postBeacon($this->payload())->assertNoContent();
        $this->postBeacon($this->payload())->assertNoContent();

        $this->assertCount(1, $this->packageLogRecords());
    }

    /**
     * The page URL query string and fragment are not written to the log.
     */
    public function test_url_query_string_and_fragment_are_stripped(): void
    {
        $this->postBeacon($this->payload([
            'url' => 'https://example.com/page/42?token=secret#section',
            'file' => 'https://example.com/assets/app.js?v=1#top',
        ]))->assertNoContent();

        $context = $this->packageLogRecords()[0]['context'];

        $this->assertSame('https://example.com/page/42', $context['url']);
        $this->assertSame('https://example.com/assets/app.js', $context['file']);
    }

    /**
     * IP and user agent are omitted from the context when privacy settings disable them.
     */
    public function test_privacy_settings_remove_ip_and_user_agent(): void
    {
        $this->setPackageConfig('privacy.include_ip', false);
        $this->setPackageConfig('privacy.include_user_agent', false);

        $this->postBeacon($this->payload())->assertNoContent();

        $context = $this->packageLogRecords()[0]['context'];

        $this->assertArrayNotHasKey('ip', $context);
        $this->assertArrayNotHasKey('user_agent', $context);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'url' => 'https://example.com/page',
            'message' => 'TypeError: value is undefined',
            'file' => 'https://example.com/assets/app.js',
            'line' => 12,
        ], $overrides);
    }
}
