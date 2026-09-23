<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Password.php';
require_once __DIR__ . '/lib/Chess.php';
require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/ChessService.php';
require_once __DIR__ . '/lib/OpeningService.php';
require_once __DIR__ . '/lib/EndgameService.php';
require_once __DIR__ . '/lib/Api.php';

$config = require __DIR__ . '/config.php';

if (!is_dir($config['data_dir'])) {
    mkdir($config['data_dir'], 0755, true);
}

$db = new Database($config['db_path']);
$db->ensureSuperAdmin(
    (string) ($config['super_admin_username'] ?? 'superadmin'),
    (string) ($config['super_admin_password'] ?? '')
);
$auth = new Auth($config['session_secret'], $db);
$chess = new ChessService();
$openings = new OpeningService();
$endgames = new EndgameService();
$api = new Api($db, $auth, $chess, $config, $openings, $endgames);
