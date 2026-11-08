<?php

use Goodport\JsErrorLogger\Config\ReportingSettings;
use Goodport\JsErrorLogger\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

$settings = app(ReportingSettings::class);

Route::post($settings->routePath(), ReportController::class)
    ->name($settings->routeName())
    ->middleware(array_merge(
        ['js-error-logger.enabled', 'throttle:js-error-logger'],
        $settings->routeMiddleware(),
    ));
