<?php

declare(strict_types=1);

require_once __DIR__ . '/scrypt/Hmac.php';
require_once __DIR__ . '/scrypt/Pbkdf2.php';
require_once __DIR__ . '/scrypt/Scrypt.php';

use Vinsaj9\Crypto\Scrypt\Scrypt;

/**
 * Derive a key using scrypt with the same defaults as Node.js crypto.scryptSync:
 * N=16384, r=8, p=1
 */
function scryptDerive(string $password, string $salt, int $length = 64): string
{
    if (function_exists('scrypt')) {
        return scrypt($password, $salt, $length, ['N' => 16384, 'r' => 8, 'p' => 1]);
    }

    return Scrypt::calc($password, $salt, 16384, 8, 1, $length);
}

function hashPassword(string $password): string
{
    $salt = bin2hex(random_bytes(16));
    $hash = bin2hex(scryptDerive($password, $salt, 64));
    return $salt . ':' . $hash;
}

function verifyPassword(string $password, ?string $stored): bool
{
    if (!$stored) {
        return false;
    }
    $parts = explode(':', $stored, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$salt, $hash] = $parts;
    $test = bin2hex(scryptDerive($password, $salt, 64));
    return hash_equals($hash, $test);
}
