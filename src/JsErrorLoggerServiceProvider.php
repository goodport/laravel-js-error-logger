<?php

namespace Goodport\JsErrorLogger;

use Goodport\JsErrorLogger\Config\InvalidConfigurationException;
use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\Http\Middleware\EnsureReportingEnabled;
use Goodport\JsErrorLogger\Logging\ChannelRegistrar;
use Goodport\JsErrorLogger\Services\ReportError;
use Goodport\JsErrorLogger\Support\ErrorFingerprint;
use Goodport\JsErrorLogger\Support\IgnoreList;
use Goodport\JsErrorLogger\Support\LogSanitizer;
use Goodport\JsErrorLogger\Support\RouteExclusions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class JsErrorLoggerServiceProvider extends ServiceProvider
{
    /**
     * Cached package configuration defaults.
     *
     * @var array<string, mixed>|null
     */
    private ?array $defaults = null;

    public function register(): void
    {
        $configPath = __DIR__.'/../config/js-error-logger.php';

        $this->mergeConfigFrom($configPath, 'js-error-logger');
        $this->mergePackageConfig();

        $this->app->singleton(ReportingSettings::class, function (Application $app): ReportingSettings {
            $config = $app['config']->get('js-error-logger');

            if (! is_array($config)) {
                throw new InvalidConfigurationException('The [js-error-logger] configuration must be an array.');
            }

            return ReportingSettings::fromConfig($config, $this->defaults());
        });

        $this->app->bind(RouteExclusions::class, function (Application $app): RouteExclusions {
            return new RouteExclusions($app->make(ReportingSettings::class)->ignoredRoutes());
        });

        $this->app->singleton(ReportError::class, function (Application $app): ReportError {
            $settings = $app->make(ReportingSettings::class);
            $logManager = $app->make(LogManager::class);
            $sanitizer = new LogSanitizer;

            return new ReportError(
                settings: $settings,
                ignoreList: new IgnoreList($settings->ignoredUserAgents(), $settings->ignoredMessages()),
                fingerprint: new ErrorFingerprint($sanitizer),
                sanitizer: $sanitizer,
                cacheFactory: $app->make(CacheFactory::class),
                logger: $logManager->channel($settings->logChannel()),
                fallbackLogger: $logManager,
            );
        });
    }

    public function boot(): void
    {
        $this->mergePackageConfig();

        $this->publishes([
            __DIR__.'/../config/js-error-logger.php' => config_path('js-error-logger.php'),
        ], 'js-error-logger-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/js-error-logger'),
        ], 'js-error-logger-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'js-error-logger');

        Blade::componentNamespace('Goodport\\JsErrorLogger\\View\\Components', 'js-error-logger');

        $this->app['router']->aliasMiddleware('js-error-logger.enabled', EnsureReportingEnabled::class);

        $this->registerRateLimiter();

        $this->app->make(ChannelRegistrar::class)->register();

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * Registers the named rate limiter used by the reporting route.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('js-error-logger', function (Request $request): Limit {
            $settings = $this->app->make(ReportingSettings::class);

            return Limit::perMinute($settings->throttlePerMinute())
                ->by((string) $request->ip());
        });
    }

    /**
     * Deep-merges the package defaults into the application configuration.
     */
    private function mergePackageConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $defaults = $this->defaults();
        $config = $this->app['config']->get('js-error-logger');

        $this->app['config']->set(
            'js-error-logger',
            ReportingSettings::merge($defaults, is_array($config) ? $config : []),
        );
    }

    /**
     * Returns the package configuration defaults, loading them once.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        if ($this->defaults === null) {
            $this->defaults = $this->loadDefaultConfig(__DIR__.'/../config/js-error-logger.php');
        }

        return $this->defaults;
    }

    /**
     * Loads the package configuration defaults.
     *
     * @return array<string, mixed>
     */
    private function loadDefaultConfig(string $path): array
    {
        $config = require $path;

        if (! is_array($config)) {
            throw new InvalidConfigurationException('The package configuration file must return an array.');
        }

        return $config;
    }
}
