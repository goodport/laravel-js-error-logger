<?php

namespace Goodport\JsErrorLogger\Tests\Support;

use Illuminate\Cache\ArrayStore;
use RuntimeException;

final class FailingCacheStore extends ArrayStore
{
    /**
     * Simulates a cache backend that cannot reserve the deduplication key.
     */
    public function add($key, $value, $ttl = null)
    {
        throw new RuntimeException('The cache store failed to add the key.');
    }
}
