# Woodpecker Method — Chess Trainer (cPanel Deployment)

Upload all contents of this folder to your cPanel **`public_html/wood/`** directory.

App URL: **https://chesstrackers.com/wood/**

## Folder Structure

```
public_html/wood/
├── index.html          ← React app (static)
├── forgot-password.php ← Password reset page
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

Do **not** need to upload `scripts/` to production (dev tools only; blocked by `.htaccess` if present).

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

Visit **https://chesstrackers.com/wood/**. API calls go to `/wood/api/*`.

If upgrading from v1 and the API crashes with a database schema error, open **`/wood/api/repair-schema.php`** once, then delete that file.

## Troubleshooting HTTP 500

The Cloudflare / `favicon.ico` messages in the browser console are **not** the cause of a 500 on `/wood/`.

After uploading, test in this order:

1. **https://chesstrackers.com/wood/test-ok.txt** → should show plain text `woodpecker ok`
2. **https://chesstrackers.com/wood/index.html** → should load the app shell
3. **https://chesstrackers.com/wood/api/health.php** → should show `PHP OK` and `database: ok`

| Result | What to do |
|--------|------------|
| `test-ok.txt` fails | Files not in `public_html/wood/`, or rename `.htaccess` to `.htaccess.bak` and test again |
| `test-ok.txt` works, `index.html` fails | Re-upload `.htaccess` from this package (use LF line endings, not `.htaccess.txt`) |
| `health.php` fails on database | Run `/wood/api/repair-schema.php` once, or replace `data/woodpecker.db` |
| All three work but `/wood/` fails | Clear browser cache; try `/wood/` with trailing slash |

Delete `test-ok.txt` and `api/health.php` after debugging.

## Hide from search engines

This app is private training software and should not appear in Google or other search results.

Already included in the package:

- `noindex` meta tags on HTML pages
- `X-Robots-Tag` headers via `.htaccess` and API responses
- `robots.txt` inside `/wood/`

**Recommended:** add this to **`chesstrackers.com/robots.txt`** (domain root) if you have one:

```
User-agent: *
Disallow: /wood/
```

## Accounts

| Role | Username | Password |
|------|----------|----------|
| Student | alice2026 | pass1234 |
| Academy | testacademy | pass1234 |

Forgot password? Open **https://chesstrackers.com/wood/forgot-password** and enter your **username** plus **Student ID** (the number on your home screen, e.g. `#42`).

## Notes

- The database file (`data/woodpecker.db`) is created automatically on first request if missing
- `.htaccess` files protect the database and puzzle data from direct download
- **No Node.js, npm, or Composer is needed** — runtime is pure PHP + static files in `assets/`
- The browser UI is pre-built JavaScript in `assets/` (served like any static site); you do not install or run Node on the server
- Optional dev scripts live in `scripts/` and are all PHP (e.g. `php scripts/parse-pgn.php`, `php scripts/check-health.php`)

## Local development (PHP only)

```powershell
cd path\to\Wood
php -S localhost:3001 router.php
```

Open **http://localhost:3001** (not `file://` on `index.html`).

Smoke test:

```powershell
php scripts/check-health.php
```
