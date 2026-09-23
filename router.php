<?php

// Router for PHP built-in development server.
// On cPanel, Apache handles this via .htaccess rules.
// Usage: php -S localhost:3001 router.php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fromWoodPrefix = false;

// Local dev: accept production-style /wood/... URLs
if ($uri === '/wood' || str_starts_with($uri, '/wood/')) {
    $fromWoodPrefix = true;
    $stripped = substr($uri, 5);
    $uri = ($stripped === '' || $stripped === false) ? '/' : $stripped;
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = $uri . ($query ? ('?' . $query) : '');
    if (isset($_SERVER['SCRIPT_NAME']) && str_starts_with((string) $_SERVER['SCRIPT_NAME'], '/wood')) {
        $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_NAME'], 5) ?: '/';
    }
}

// API requests
if (str_starts_with($uri, '/api')) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

// Block data folder
if (str_starts_with($uri, '/data')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Password reset page (PHP — no Node required)
if ($uri === '/forgot-password' || $uri === '/reset-password') {
    require __DIR__ . '/forgot-password.php';
    return true;
}

// Shared login page (Student / Academy / Admin)
if ($uri === '/login' || $uri === '/academy/login') {
    require __DIR__ . '/login.php';
    return true;
}
if ($uri === '/register') {
    $prefix = $fromWoodPrefix ? '/wood' : '';
    header('Location: ' . $prefix . '/login.php?role=student', true, 302);
    return true;
}

// Super admin dashboard
if ($uri === '/admin') {
    require __DIR__ . '/admin.php';
    return true;
}

// Student / Academy module homes and Opening / Endgame modules
if ($uri === '/home') {
    require __DIR__ . '/student-home.php';
    return true;
}
if ($uri === '/academy/home') {
    require __DIR__ . '/academy-home.php';
    return true;
}
if ($uri === '/academy/openings') {
    require __DIR__ . '/academy-openings.php';
    return true;
}
if ($uri === '/academy/endgames') {
    require __DIR__ . '/academy-endgames.php';
    return true;
}
if ($uri === '/academy') {
    require __DIR__ . '/academy.php';
    return true;
}
if ($uri === '/opening') {
    require __DIR__ . '/opening.php';
    return true;
}
if ($uri === '/opening/board') {
    require __DIR__ . '/opening-board.php';
    return true;
}
if ($uri === '/endgame') {
    require __DIR__ . '/endgame.php';
    return true;
}
if ($uri === '/endgame/board') {
    require __DIR__ . '/endgame-board.php';
    return true;
}

// Serve existing static files (assets, etc.)
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    // Never dump PHP source. Let the built-in server execute real .php files
    // only when the URL maps to a real path (no /wood prefix rewrite).
    if ($ext === 'php') {
        return false;
    }
    // return false makes PHP look up the ORIGINAL URL on disk. After stripping
    // /wood, that would be /wood/assets/... which does not exist, so the SPA
    // JS/CSS 404 and Open Woodpecker shows a blank page.
    if ($fromWoodPrefix) {
        $root = realpath(__DIR__);
        $real = realpath($file);
        if ($root && $real && str_starts_with($real, $root)) {
            $mimes = [
                'js' => 'text/javascript',
                'mjs' => 'text/javascript',
                'css' => 'text/css',
                'json' => 'application/json',
                'map' => 'application/json',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'html' => 'text/html',
            ];
            if (isset($mimes[$ext])) {
                header('Content-Type: ' . $mimes[$ext]);
            }
            readfile($real);
            return true;
        }
    }
    return false;
}

// SPA fallback - serve index.html for all other routes
readfile(__DIR__ . '/index.html');
return true;
