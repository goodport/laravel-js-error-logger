<?php

namespace Goodport\JsErrorLogger\Tests;

use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\JsErrorLoggerServiceProvider;
use Goodport\JsErrorLogger\Services\ReportError;
use Goodport\JsErrorLogger\Tests\Support\FailingCacheStore;
use Goodport\JsErrorLogger\Tests\Support\FailingLogger;
use Goodport\JsErrorLogger\Tests\Support\FailingOnceLogger;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Path of the route without a name used by the beacon exclusion tests.
     */
    protected const UNNAMED_BEACON_PATH = '/beacon/unnamed';

    protected bool $definePackageLogChannel = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('cache')->extend('failing', function (): Repository {
            return new Repository(new FailingCacheStore);
        });

        $this->app->make('log')->extend('failing', function (): FailingLogger {
            return new FailingLogger;
        });

        $this->app->make('log')->extend('failing_once', function (): FailingOnceLogger {
            return new FailingOnceLogger;
        });
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [JsErrorLoggerServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.failing', ['driver' => 'failing']);

        $app['config']->set('logging.default', 'test');
        $app['config']->set('logging.channels.test', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);

        if ($this->definePackageLogChannel) {
            $app['config']->set('logging.channels.js-error-logger', [
                'driver' => 'monolog',
                'handler' => TestHandler::class,
            ]);
        }
    }

    protected function defineRoutes($router): void
    {
        $router->get('/beacon', static function (): string {
            return Blade::render('<x-js-error-logger::beacon />');
        })->name('tests.beacon');

        $router->get('/beacon-with-nonce', static function (Request $request): string {
            return Blade::render('<x-js-error-logger::beacon :nonce="$nonce" />', [
                'nonce' => $request->query('nonce'),
            ]);
        })->name('tests.beacon.nonce');

        $router->get('/excluded', static function (): string {
            return Blade::render('<x-js-error-logger::beacon />');
        })->name('tests.excluded');

        $router->get('/shown', static function (): string {
            return Blade::render('<x-js-error-logger::beacon />');
        })->name('tests.shown');

        $router->get(self::UNNAMED_BEACON_PATH, static function (): string {
            return Blade::render('<x-js-error-logger::beacon />');
        });
    }

    /**
     * Sends a payload the way navigator.sendBeacon does: JSON body without an Accept header.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $server
     */
    protected function postBeacon(array $payload, array $server = []): TestResponse
    {
        $server = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ], $server);

        return $this->call(
            'POST',
            route('js-error-logger.report'),
            [],
            [],
            [],
            $server,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Overrides one package setting and drops the cached settings instance.
     */
    protected function setPackageConfig(string $key, mixed $value): void
    {
        $this->app['config']->set('js-error-logger.'.$key, $value);
        $this->app->forgetInstance(ReportingSettings::class);
        $this->app->forgetInstance(ReportError::class);
    }

    /**
     * Returns the records written to the package log channel.
     *
     * @return list<array{message: string, context: array<string, mixed>}>
     */
    protected function packageLogRecords(): array
    {
        return $this->logRecordsFor('js-error-logger');
    }

    /**
     * Returns the records written to the default application log channel.
     *
     * @return list<array{message: string, context: array<string, mixed>}>
     */
    protected function defaultLogRecords(): array
    {
        return $this->logRecordsFor('test');
    }

    /**
     * Returns the records written to the given log channel.
     *
     * @return list<array{message: string, context: array<string, mixed>}>
     */
    protected function logRecordsFor(string $channel): array
    {
        $logger = $this->app->make(LogManager::class)->channel($channel);
        $handler = $logger->getHandlers()[0] ?? null;

        $this->assertInstanceOf(TestHandler::class, $handler);

        $records = [];

        foreach ($handler->getRecords() as $record) {
            if ($record instanceof LogRecord) {
                $records[] = [
                    'message' => $record->message,
                    'context' => $record->context,
                ];

                continue;
            }

            $records[] = [
                'message' => (string) $record['message'],
                'context' => (array) ($record['context'] ?? []),
            ];
        }

        return $records;
    }
}
