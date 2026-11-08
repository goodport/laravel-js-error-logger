<?php

namespace Goodport\JsErrorLogger\Tests\Unit\Support;

use Goodport\JsErrorLogger\Support\RouteExclusions;
use PHPUnit\Framework\TestCase;

final class RouteExclusionsTest extends TestCase
{
    /**
     * An exact route name is excluded.
     */
    public function test_exact_route_name_is_matched(): void
    {
        $exclusions = new RouteExclusions(['checkout.index']);

        $this->assertTrue($exclusions->matches('checkout.index'));
    }

    /**
     * A wildcard pattern matches matching route names.
     */
    public function test_wildcard_pattern_is_matched(): void
    {
        $exclusions = new RouteExclusions(['checkout.*']);

        $this->assertTrue($exclusions->matches('checkout.payment'));
    }

    /**
     * A route name outside the patterns is not excluded.
     */
    public function test_non_matching_route_name_is_not_matched(): void
    {
        $exclusions = new RouteExclusions(['checkout.*']);

        $this->assertFalse($exclusions->matches('catalog.index'));
    }

    /**
     * A missing route name cannot be matched.
     */
    public function test_null_route_name_is_not_matched(): void
    {
        $exclusions = new RouteExclusions(['checkout.*', '*']);

        $this->assertFalse($exclusions->matches(null));
    }

    /**
     * Matching is case-sensitive.
     */
    public function test_matching_is_case_sensitive(): void
    {
        $exclusions = new RouteExclusions(['Checkout.Index']);

        $this->assertFalse($exclusions->matches('checkout.index'));
    }
}
