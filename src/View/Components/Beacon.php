<?php

namespace Goodport\JsErrorLogger\View\Components;

use Closure;
use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\Support\RouteExclusions;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\View\Component;

final class Beacon extends Component
{
    public function __construct(
        private ReportingSettings $settings,
        private RouteExclusions $exclusions,
        private Request $request,
        private ?string $nonce = null,
    ) {}

    /**
     * Renders the inline beacon when reporting is enabled and the current route is not excluded.
     */
    public function render(): View|Closure|string
    {
        if (! $this->settings->enabled()) {
            return '';
        }

        if ($this->exclusions->matches($this->request->route()?->getName())) {
            return '';
        }

        return view('js-error-logger::components.beacon', [
            'config' => [
                'max_events_per_page' => $this->settings->maxEventsPerPage(),
                'max_message_length' => $this->settings->maxMessageLength(),
                'max_url_length' => $this->settings->maxUrlLength(),
                'capture_resource_errors' => $this->settings->captureResourceErrors(),
                'capture_preload_errors' => $this->settings->capturePreloadErrors(),
                'strip_query_strings' => $this->settings->stripQueryStrings(),
            ],
            'endpoint' => route($this->settings->routeName()),
            'nonce' => $this->resolveNonce(),
        ]);
    }

    /**
     * Returns the component nonce, then the current Vite nonce, or null.
     */
    private function resolveNonce(): ?string
    {
        if ($this->nonce !== null && $this->nonce !== '') {
            return $this->nonce;
        }

        if (! app()->bound(Vite::class) || ! class_exists(Vite::class) || ! method_exists(Vite::class, 'cspNonce')) {
            return null;
        }

        $nonce = ViteFacade::cspNonce();

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
