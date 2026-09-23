<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(
        private string $secret,
        private Database $db
    ) {
    }

    public function createToken(string $type, int $entityId): string
    {
        $payload = $type . ':' . $entityId . ':' . (string) (int) (microtime(true) * 1000);
        $sig = hash_hmac('sha256', $payload, $this->secret);
        return $this->base64urlEncode($payload . ':' . $sig);
    }

    public function parseToken(string $token): ?array
    {
        try {
            $decoded = $this->base64urlDecode($token);
            if ($decoded === null) {
                return null;
            }
            $parts = explode(':', $decoded);
            $type = 'student';
            $entityId = null;
            $sig = null;

            if (count($parts) === 3) {
                [$entityId, , $sig] = $parts;
            } elseif (count($parts) === 4) {
                [$type, $entityId, , $sig] = $parts;
            } else {
                return null;
            }

            $payload = count($parts) === 3
                ? $parts[0] . ':' . $parts[1]
                : $parts[0] . ':' . $parts[1] . ':' . $parts[2];
            $expected = hash_hmac('sha256', $payload, $this->secret);
            if (!hash_equals($expected, (string) $sig)) {
                return null;
            }

            $id = (int) $entityId;
            if ($type === 'admin') {
                $admin = $this->db->get(
                    'SELECT id, name, username FROM super_admins WHERE id = ?',
                    [$id]
                );
                return $admin ? ['type' => 'admin', 'entity' => $admin] : null;
            }

            if ($type === 'academy') {
                $academy = $this->db->get(
                    'SELECT id, name, username FROM academies WHERE id = ? AND COALESCE(is_active, 1) = 1',
                    [$id]
                );
                return $academy ? ['type' => 'academy', 'entity' => $academy] : null;
            }

            $student = $this->db->get(
                'SELECT id, name, username FROM students WHERE id = ? AND COALESCE(is_active, 1) = 1',
                [$id]
            );
            return $student ? ['type' => 'student', 'entity' => $student] : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function bearerSession(): ?array
    {
        $header = $this->authorizationHeader();
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        return $this->parseToken(substr($header, 7));
    }

    private function authorizationHeader(): string
    {
        $candidates = [
            $_SERVER['HTTP_AUTHORIZATION'] ?? null,
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
            $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        ];

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strtolower((string) $key) === 'authorization') {
                        $candidates[] = $value;
                        break;
                    }
                }
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strtolower((string) $key) === 'authorization') {
                        $candidates[] = $value;
                        break;
                    }
                }
            }
        }

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function requireStudent(): array
    {
        $session = $this->bearerSession();
        if (!$session || $session['type'] !== 'student') {
            Http::error('Student login required', 401);
        }
        return $session;
    }

    public function requireAcademy(): array
    {
        $session = $this->bearerSession();
        if (!$session || $session['type'] !== 'academy') {
            Http::error('Academy login required', 401);
        }
        return $session;
    }

    public function requireAdmin(): array
    {
        $session = $this->bearerSession();
        if (!$session || $session['type'] !== 'admin') {
            Http::error('Super admin login required', 401);
        }
        return $session;
    }

    public function requireSession(): array
    {
        $session = $this->bearerSession();
        if (!$session) {
            Http::error('Login required', 401);
        }
        return $session;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): ?string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}

function cleanUsername(string $username): string
{
    return preg_replace('/[^a-z0-9_]/', '', strtolower(trim($username))) ?? '';
}

function sanitizeStudent(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    unset($row['password_hash'], $row['created_by_academy_id']);
    return $row;
}

function sanitizeAcademy(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    unset($row['password_hash']);
    return $row;
}

function sanitizeAdmin(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    unset($row['password_hash']);
    return $row;
}
