<?php

namespace Goodport\JsErrorLogger\Logging;

use Goodport\JsErrorLogger\Config\ReportingSettings;
use Illuminate\Contracts\Config\Repository;
use Monolog\Formatter\JsonFormatter;

final class ChannelRegistrar
{
    public function __construct(
        private Repository $config,
        private ReportingSettings $settings,
    ) {}

    /**
     * Registers the package log channel unless the application already declared it.
     */
    public function register(): void
    {
        $channel = 'logging.channels.'.$this->settings->logChannel();

        if ($this->config->has($channel)) {
            return;
        }

        $this->config->set($channel, [
            'driver' => 'daily',
            'path' => storage_path('logs/js-errors.log'),
            'level' => 'error',
            'days' => 30,
            'formatter' => JsonFormatter::class,
        ]);
    }
}
