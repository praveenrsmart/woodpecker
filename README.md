# Woodpecker Method — Chess Trainer (cPanel Deployment)

Upload all contents of this folder to your cPanel **`public_html/wood/`** directory.

App URL: **https://chesstrackers.com/wood/**

## Folder Structure

```
public_html/wood/
├── index.html          ← React app (static)
├── assets/             ← JS/CSS bundles
├── .htaccess           ← Routing: SPA fallback + API routing
├── api/                ← PHP backend
│   ├── index.php       ← API entry point
│   ├── config.php      ← Configuration
│   ├── bootstrap.php   ← Loads all PHP classes
│   ├── .htaccess       ← Routes all API paths to index.php
│   └── lib/            ← PHP classes (Auth, Chess, Database, etc.)
└── data/               ← SQLite database + puzzles (protected)
    ├── woodpecker.db   ← Database (auto-created if missing)
    ├── puzzles.json    ← 1,128 chess puzzles
    └── .htaccess       ← Blocks direct browser access
```

## Setup Instructions

### 1. Upload files

Upload **everything** in this folder to `public_html/wood/` via cPanel File Manager or FTP.

### 2. PHP requirements

In cPanel → **Select PHP Version** (or MultiPHP INI Editor):

- PHP **8.0** or newer
- Extensions: `pdo`, `pdo_sqlite`, `json` (usually enabled by default)

### 3. Folder permissions

Set the `data/` folder to **755** or **775** so PHP can write to the SQLite database.

### 4. Set a production secret (recommended)

Copy `api/config.local.example.php` to `api/config.local.php` and change the secret:

```php
<?php
return [
    'session_secret' => 'put-a-long-random-string-here',
];
```

### 5. Test

Visit **https://chesstrackers.com/wood/**. The app loads at `/wood/`. API calls go to `/wood/api/*`.

## Accounts

| Role | Username | Password |
|------|----------|----------|
| Student | alice2026 | pass1234 |
| Academy | testacademy | pass1234 |

## Notes

- The database file (`data/woodpecker.db`) is created automatically on first request if missing
- `.htaccess` files protect the database and puzzle data from direct download
- No Node.js, npm, or Composer is needed on the server — this is pure PHP
