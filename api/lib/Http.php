<?php

declare(strict_types=1);

final class Http
{
    public static function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 500): void
    {
        self::json(['error' => $message], $status);
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $apiPrefix = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/\\');
        if ($apiPrefix !== '' && str_starts_with($path, $apiPrefix)) {
            $path = substr($path, strlen($apiPrefix)) ?: '/';
        }
        return rtrim($path, '/') ?: '/';
    }

    public static function query(): array
    {
        return $_GET;
    }
}
