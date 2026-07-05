<?php

// Router for PHP built-in development server.
// On cPanel, Apache handles this via .htaccess rules.
// Usage: php -S localhost:3001 router.php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// API requests
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api/index.php';
    return true;
}

// Block data folder
if (str_starts_with($uri, '/data')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Serve existing static files (assets, etc.)
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

// SPA fallback - serve index.html for all other routes
readfile(__DIR__ . '/index.html');
return true;
