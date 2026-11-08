<?php

namespace Goodport\JsErrorLogger\Tests\Feature;

use Goodport\JsErrorLogger\Tests\TestCase;

final class RouteExclusionTest extends TestCase
{
    /**
     * The beacon is not rendered on an exactly excluded route name.
     */
    public function test_exact_route_name_is_excluded(): void
    {
        $this->setPackageConfig('ignore.routes', ['tests.excluded']);

        $response = $this->get(route('tests.excluded'));

        $response->assertOk();
        $this->assertSame('', $response->getContent());
    }

    /**
     * The beacon is rendered when the current route name is not excluded.
     */
    public function test_non_matching_route_name_renders_beacon(): void
    {
        $this->setPackageConfig('ignore.routes', ['tests.excluded']);

        $response = $this->get(route('tests.shown'));

        $response->assertOk();
        $response->assertSee('navigator.sendBeacon', false);
    }

    /**
     * A wildcard pattern excludes every matching route name.
     */
    public function test_wildcard_pattern_excludes_route_names(): void
    {
        $this->setPackageConfig('ignore.routes', ['tests.ex*']);

        $response = $this->get(route('tests.excluded'));

        $response->assertOk();
        $this->assertSame('', $response->getContent());
    }

    /**
     * An unnamed route has no route name and cannot be excluded.
     */
    public function test_unnamed_route_renders_beacon(): void
    {
        $this->setPackageConfig('ignore.routes', ['tests.*']);

        $response = $this->get(self::UNNAMED_BEACON_PATH);

        $response->assertOk();
        $response->assertSee('navigator.sendBeacon', false);
    }
}
