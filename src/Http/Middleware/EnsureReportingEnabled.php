<?php

namespace Goodport\JsErrorLogger\Http\Middleware;

use Closure;
use Goodport\JsErrorLogger\Config\ReportingSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureReportingEnabled
{
    public function __construct(private ReportingSettings $settings) {}

    /**
     * Returns 204 when reporting is disabled before the request is validated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->enabled()) {
            return response()->noContent();
        }

        return $next($request);
    }
}
