<?php

namespace Goodport\JsErrorLogger\Tests\Support;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use RuntimeException;
use Stringable;

final class FailingOnceLogger extends Logger
{
    private bool $failed = false;

    public function __construct()
    {
        parent::__construct('failing-once', [new TestHandler]);
    }

    /**
     * Simulates a channel that fails the first write and then recovers.
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        if (! $this->failed) {
            $this->failed = true;

            throw new RuntimeException('The logger failed once.');
        }

        parent::error($message, $context);
    }
}
