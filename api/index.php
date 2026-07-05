<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

Http::cors();

if (Http::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $api->dispatch(Http::method(), Http::path());
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    Http::error('Internal server error', 500);
}
