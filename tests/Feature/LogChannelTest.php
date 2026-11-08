<?php

namespace Goodport\JsErrorLogger\Tests\Feature;

use Goodport\JsErrorLogger\Logging\ChannelRegistrar;
use Goodport\JsErrorLogger\Tests\TestCase;
use Monolog\Formatter\JsonFormatter;

final class LogChannelTest extends TestCase
{
    protected bool $definePackageLogChannel = false;

    /**
     * The package creates its log channel when the application does not declare it.
     */
    public function test_channel_is_created_when_missing(): void
    {
        $channel = config('logging.channels.js-error-logger');

        $this->assertIsArray($channel);
        $this->assertSame('daily', $channel['driver']);
        $this->assertSame(storage_path('logs/js-errors.log'), $channel['path']);
        $this->assertSame('error', $channel['level']);
        $this->assertSame(30, $channel['days']);
        $this->assertSame(JsonFormatter::class, $channel['formatter']);
    }

    /**
     * An application-declared channel is never overwritten.
     */
    public function test_existing_channel_is_not_overwritten(): void
    {
        $this->app['config']->set('logging.channels.js-error-logger', [
            'driver' => 'single',
            'path' => storage_path('logs/custom-js-errors.log'),
        ]);

        $this->app->make(ChannelRegistrar::class)->register();

        $channel = config('logging.channels.js-error-logger');

        $this->assertIsArray($channel);
        $this->assertSame('single', $channel['driver']);
        $this->assertSame(storage_path('logs/custom-js-errors.log'), $channel['path']);
    }
}
