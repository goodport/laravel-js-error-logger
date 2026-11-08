<?php

return [
    'enabled' => env('JS_ERROR_LOGGER_ENABLED', true),

    'route' => [
        'path' => env('JS_ERROR_LOGGER_PATH', 'js-error-logger'),
        'name' => 'js-error-logger.report',
        'middleware' => [],
    ],

    'throttle' => [
        'per_minute' => env('JS_ERROR_LOGGER_THROTTLE', 20),
    ],

    'log' => [
        'channel' => env('JS_ERROR_LOGGER_CHANNEL', 'js-error-logger'),
        'message_prefix' => 'Frontend JavaScript error: ',
    ],

    'limits' => [
        'max_events_per_page' => 5,
        'max_message_length' => 300,
        'max_url_length' => 2048,
    ],

    'dedupe' => [
        'enabled' => true,
        'store' => null,
        'minutes' => 30,
    ],

    'privacy' => [
        'strip_query_strings' => true,
        'include_ip' => true,
        'include_user_agent' => true,
    ],

    'ignore' => [
        'user_agents' => ['bot', 'spider', 'crawler', 'slurp'],
        'messages' => [
            'request aborted',
            'user aborted a request',
            'was aborted',
            'aborted without reason',
        ],
        // Route names or `*` patterns; the beacon is not rendered on matching routes.
        'routes' => [],
    ],

    'capture' => [
        'resource_errors' => true,
        'preload_errors' => true,
    ],
];
