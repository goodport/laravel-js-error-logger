<?php

namespace Goodport\JsErrorLogger\Support;

use Illuminate\Support\Str;

final class RouteExclusions
{
    /**
     * @param  list<string>  $patterns
     */
    public function __construct(
        private array $patterns,
    ) {}

    /**
     * Determines whether the route name matches any exclusion pattern.
     *
     * Patterns use the `Str::is()` wildcard syntax and are case-sensitive.
     * A missing route name, for example on an unnamed route or a 404 action,
     * never matches.
     */
    public function matches(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
