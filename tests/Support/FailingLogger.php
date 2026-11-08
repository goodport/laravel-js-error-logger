<?php

namespace Goodport\JsErrorLogger\Tests\Support;

use Psr\Log\LoggerInterface;
use RuntimeException;

final class FailingLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        $this->fail();
    }

    public function alert($message, array $context = []): void
    {
        $this->fail();
    }

    public function critical($message, array $context = []): void
    {
        $this->fail();
    }

    public function error($message, array $context = []): void
    {
        $this->fail();
    }

    public function warning($message, array $context = []): void
    {
        $this->fail();
    }

    public function notice($message, array $context = []): void
    {
        $this->fail();
    }

    public function info($message, array $context = []): void
    {
        $this->fail();
    }

    public function debug($message, array $context = []): void
    {
        $this->fail();
    }

    public function log($level, $message, array $context = []): void
    {
        $this->fail();
    }

    private function fail(): never
    {
        throw new RuntimeException('The logger failed.');
    }
}
