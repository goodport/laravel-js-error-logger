<?php

namespace Goodport\JsErrorLogger\Tests\Feature;

use Goodport\JsErrorLogger\Tests\TestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Testing\TestResponse;

final class BeaconTest extends TestCase
{
    /**
     * The beacon renders the endpoint and the client limits.
     */
    public function test_beacon_renders_endpoint_and_limits(): void
    {
        $response = $this->renderBeacon();

        $response->assertOk();
        $response->assertSee(json_encode(route('js-error-logger.report'), JSON_THROW_ON_ERROR), false);
        $response->assertSee('"max_events_per_page":5', false);
        $response->assertSee('"max_message_length":300', false);
        $response->assertSee('"max_url_length":2048', false);
        $response->assertSee('"capture_resource_errors":true', false);
        $response->assertSee('"capture_preload_errors":true', false);
        $response->assertSee('"strip_query_strings":true', false);
        $response->assertSee('navigator.sendBeacon', false);
    }

    /**
     * The privacy flag is passed through to the beacon configuration.
     */
    public function test_beacon_renders_privacy_flag(): void
    {
        $this->setPackageConfig('privacy.strip_query_strings', false);

        $response = $this->renderBeacon();

        $response->assertOk();
        $response->assertSee('"strip_query_strings":false', false);
    }

    /**
     * The beacon is not rendered when reporting is disabled.
     */
    public function test_beacon_is_not_rendered_when_disabled(): void
    {
        $this->setPackageConfig('enabled', false);

        $response = $this->renderBeacon();

        $response->assertOk();
        $this->assertSame('', $response->getContent());
    }

    /**
     * The component nonce is rendered into the script tag.
     */
    public function test_component_nonce_is_rendered(): void
    {
        $response = $this->renderBeacon('tests.beacon.nonce', ['nonce' => 'example-nonce']);

        $response->assertOk();
        $response->assertSee('nonce="example-nonce"', false);
    }

    /**
     * The component nonce is escaped in the rendered markup.
     */
    public function test_component_nonce_is_escaped(): void
    {
        $nonce = '"><script>alert(1)</script>';

        $response = $this->renderBeacon('tests.beacon.nonce', ['nonce' => $nonce]);

        $response->assertOk();
        $response->assertSee('nonce="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false);
        $response->assertDontSee('"><script>alert(1)</script>', false);
    }

    /**
     * No nonce attribute is rendered when no nonce is available.
     */
    public function test_nonce_attribute_is_absent_without_a_nonce(): void
    {
        $response = $this->renderBeacon();

        $response->assertOk();
        $response->assertDontSee('nonce=', false);
    }

    /**
     * The current Vite nonce is used when the component has no nonce attribute.
     */
    public function test_vite_nonce_is_used_as_fallback(): void
    {
        if (! method_exists(Vite::class, 'useCspNonce')) {
            $this->markTestSkipped('Vite CSP nonce support is not available.');
        }

        ViteFacade::useCspNonce('vite-nonce');

        $response = $this->renderBeacon();

        $response->assertOk();
        $response->assertSee('nonce="vite-nonce"', false);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function renderBeacon(string $routeName = 'tests.beacon', array $parameters = []): TestResponse
    {
        return $this->get(route($routeName, $parameters));
    }
}
