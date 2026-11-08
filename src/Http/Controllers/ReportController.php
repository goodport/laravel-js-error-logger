<?php

namespace Goodport\JsErrorLogger\Http\Controllers;

use Goodport\JsErrorLogger\Http\Requests\ReportRequest;
use Goodport\JsErrorLogger\Services\ReportError;
use Illuminate\Http\Response;

final class ReportController
{
    public function __invoke(ReportRequest $request, ReportError $reportError): Response
    {
        $reportError->run(
            $request->validated(),
            $request->userAgent(),
            $request->ip(),
        );

        return response()->noContent();
    }
}
