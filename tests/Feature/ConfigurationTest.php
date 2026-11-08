<?php

namespace Goodport\JsErrorLogger\Tests\Feature;

use Goodport\JsErrorLogger\Config\InvalidConfigurationException;
use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\JsErrorLoggerServiceProvider;
use Goodport\JsErrorLogger\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConfigurationTest extends TestCase
{
    /**
     * A partially published config keeps the nested package defaults.
     */
    public function test_partial_config_keeps_nested_defaults(): void
    {
        $this->assertSame('custom-report', config('js-error-logger.route.path'));
        $this->assertSame('js-error-logger.report', config('js-error-logger.route.name'));
        $this->assertSame([], config('js-error-logger.route.middleware'));
        $this->assertSame('js-error-logger', config('js-error-logger.log.channel'));
        $this->assertSame(300, config('js-error-logger.limits.max_message_length'));
        $this->assertFalse(config('js-error-logger.privacy.include_ip'));
        $this->assertTrue(config('js-error-logger.privacy.strip_query_strings'));
        $this->assertSame([], config('js-error-logger.ignore.routes'));
        $this->assertSame('/custom-report', parse_url(route('js-error-logger.report'), PHP_URL_PATH));
    }

    /**
     * Settings resolve with nested defaults when only a nested key is overridden
     * before the provider boots.
     */
    public function test_settings_resolve_with_nested_defaults_before_boot(): void
    {
        $config = $this->app['config'];
        $config->set('js-error-logger', ['privacy' => ['include_ip' => false]]);
        $this->app->forgetInstance(ReportingSettings::class);

        (new JsErrorLoggerServiceProvider($this->app))->register();

        $this->assertSame(300, $config->get('js-error-logger.limits.max_message_length'));
        $this->assertFalse($config->get('js-error-logger.privacy.include_ip'));

        $settings = $this->app->make(ReportingSettings::class);

        $this->assertFalse($settings->includeIp());
        $this->assertTrue($settings->stripQueryStrings());
        $this->assertSame(300, $settings->maxMessageLength());
        $this->assertSame('js-error-logger', $settings->logChannel());
    }

    /**
     * Invalid configuration values are rejected with a clear exception.
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function test_invalid_configuration_is_rejected(array $overrides): void
    {
        $this->expectException(InvalidConfigurationException::class);

        ReportingSettings::fromConfig($overrides, $this->defaultConfiguration());
    }

    /**
     * Leading and trailing slashes are removed from the route path.
     */
    public function test_route_path_is_normalized(): void
    {
        $settings = ReportingSettings::fromConfig(
            ['route' => ['path' => '/custom/report/']],
            $this->defaultConfiguration(),
        );

        $this->assertSame('custom/report', $settings->routePath());
    }

    /**
     * Custom middleware is allowed when it does not break the stateless contract.
     */
    public function test_custom_middleware_is_allowed(): void
    {
        $settings = ReportingSettings::fromConfig(
            ['route' => ['middleware' => ['throttle:60,1', 'verified']]],
            $this->defaultConfiguration(),
        );

        $this->assertSame(['throttle:60,1', 'verified'], $settings->routeMiddleware());
    }

    /**
     * Route names and log channel names are trimmed.
     */
    public function test_route_name_and_log_channel_are_trimmed(): void
    {
        $settings = ReportingSettings::fromConfig([
            'route' => ['name' => '  custom.report  '],
            'log' => ['channel' => '  custom-channel  '],
        ], $this->defaultConfiguration());

        $this->assertSame('custom.report', $settings->routeName());
        $this->assertSame('custom-channel', $settings->logChannel());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): array
    {
        return [
            'empty route path' => [['route' => ['path' => '']]],
            'slash-only route path' => [['route' => ['path' => '/']]],
            'empty route name' => [['route' => ['name' => '']]],
            'whitespace route name' => [['route' => ['name' => '   ']]],
            'empty log channel' => [['log' => ['channel' => '']]],
            'whitespace log channel' => [['log' => ['channel' => '   ']]],
            'zero throttle limit' => [['throttle' => ['per_minute' => 0]]],
            'zero event limit' => [['limits' => ['max_events_per_page' => 0]]],
            'zero message limit' => [['limits' => ['max_message_length' => 0]]],
            'zero url limit' => [['limits' => ['max_url_length' => 0]]],
            'zero dedupe minutes' => [['dedupe' => ['minutes' => 0]]],
            'web middleware' => [['route' => ['middleware' => ['web']]]],
            'session middleware' => [['route' => ['middleware' => ['session']]]],
            'auth middleware' => [['route' => ['middleware' => ['auth']]]],
            'auth guard middleware' => [['route' => ['middleware' => ['auth:api']]]],
            'non-string ignored route' => [['ignore' => ['routes' => [123]]]],
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('js-error-logger.route', ['path' => 'custom-report']);
        $app['config']->set('js-error-logger.privacy', ['include_ip' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfiguration(): array
    {
        $config = require dirname(__DIR__, 2).'/config/js-error-logger.php';

        $this->assertIsArray($config);

        return $config;
    }
}
