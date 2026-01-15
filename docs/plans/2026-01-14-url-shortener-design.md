# URL Shortener Design Document

**Project:** trcy.cc
**Date:** 2026-01-14
**Status:** Design Complete

## Overview

A self-hosted URL shortener with analytics, designed for personal use on shared hosting. Built with PHP and SQLite, featuring an admin interface for managing short URLs and viewing click statistics.

## Requirements

- Must use PHP
- Must use SQLite
- Dead simple deployment on shared hosting
- Admin interface for URL management
- No user accounts, only admin password
- Analytics dashboard with click statistics
- Custom short URL slugs
- Ships with default database file
- Admin password set on first run

## Architecture

### Project Structure

```
/
├── index.php           # Main entry point - handles redirects
├── admin.php           # Admin interface (all pages served through here)
├── router.php          # Custom mini-router
├── setup.txt           # Auto-generated password (deleted after first login)
├── database.sqlite     # SQLite database (shipped with schema)
├── config.php          # Configuration (base URL, etc.)
├── views/              # PHP templates
│   ├── admin_login.php
│   ├── admin_dashboard.php
│   ├── admin_urls_list.php
│   └── admin_analytics.php
├── public/             # Static assets
│   └── style.css       # Custom CSS overrides for Tailwind
└── lib/                # Helper functions
    ├── auth.php        # Session/password handling
    ├── csrf.php        # CSRF protection
    └── db.php          # Database connection
```

### Routing

All routing goes through two entry points:

1. **`index.php`** - Handles short URL redirects
2. **`admin.php`** - Handles all admin interface routes

`.htaccess` configuration:
```apache
RewriteEngine On
RewriteRule ^([^/]+)/?$ index.php?code=$1 [L,QSA]
RewriteRule ^admin(?:/.*)?$ admin.php [L,QSA]
```

### Custom Router

A lightweight router in `router.php` provides:
- Pattern matching with `:params`
- HTTP method routing (GET/POST)
- Simple callback-based handlers

## Database Schema

```sql
CREATE TABLE urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    short_code TEXT UNIQUE NOT NULL COLLATE NOCASE,
    long_url TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    click_count INTEGER DEFAULT 0
);
CREATE INDEX idx_short_code ON urls(short_code);

CREATE TABLE clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_id INTEGER NOT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    referrer TEXT,
    user_agent TEXT,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
);
CREATE INDEX idx_url_id ON clicks(url_id);
CREATE INDEX idx_clicked_at ON clicks(clicked_at);

CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

INSERT INTO settings (key, value) VALUES ('admin_password_hash', '');
INSERT INTO settings (key, value) VALUES ('base_url', 'https://trcy.cc');
```

**Key design decisions:**
- `COLLATE NOCASE` for case-insensitive short code lookups
- `ON DELETE CASCADE` to clean up clicks when URL is deleted
- Indexed `clicked_at` for efficient time-based analytics queries

## Admin Interface

### Pages

| Route | Page | Description |
|-------|------|-------------|
| GET /admin | Dashboard | Summary stats, recent URLs |
| GET /admin/login | Login | Password authentication |
| POST /admin/login | - | Authenticate session |
| POST /admin/logout | - | Clear session |
| GET /admin/urls | URL List | Paginated table of all URLs |
| POST /admin/urls | - | Create new URL |
| POST /admin/urls/:id | - | Update existing URL |
| DELETE /admin/urls/:id | - | Delete URL |
| GET /admin/analytics/:id | Analytics | Detailed click statistics |
| GET /admin/settings | Settings | Admin configuration |

### Dashboard Features

- Summary cards: Total URLs, Total Clicks, Clicks Today, Clicks This Week
- Recent URLs table with quick stats
- Quick link to create new URL

### URL Management

- Paginated table with columns: Short Code, Long URL, Created, Clicks, Actions
- Create/edit modal with custom slug option
- Auto-generate short codes if not provided
- Duplicate detection with suggestions

### Analytics

- Clicks over time chart (30/60/90 day toggle)
- Top referrers breakdown
- User agent categories (desktop/mobile/browser)
- Recent clicks table

## Security

### Authentication

- Admin password hashed with `password_hash()` (bcrypt)
- Session-based authentication
- Auto-generated password on first run, stored in `setup.txt`
- Warning banner until `setup.txt` is deleted

### CSRF Protection

```php
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

All forms include CSRF token and verify on POST.

### Input Sanitization

- `htmlspecialchars()` for all output (XSS prevention)
- Prepared statements for all DB queries (SQL injection prevention)
- URL validation with `filter_var($url, FILTER_VALIDATE_URL)`

## Technology Choices

| Aspect | Technology | Rationale |
|--------|-----------|-----------|
| PHP Framework | Custom mini-router | Zero dependencies, simple deployment |
| Styling | Tailwind CSS via CDN | Fast development, modern look |
| Database | SQLite | No separate server, single file |
| Charts | Chart.js via CDN | Lightweight, easy integration |
| Data Retention | No expiration | SQLite handles large datasets; simpler |

## Click Tracking

### Recording

Clicks are recorded synchronously before redirect:

```php
$click_data = [
    'url_id' => $url['id'],
    'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
];
db::insert('clicks', $click_data);
db::execute('UPDATE urls SET click_count = click_count + 1 WHERE id = ?', [$url['id']]);
header("Location: {$url['long_url']}", true, 301);
```

### Analytics Queries

```sql
-- Clicks over time (daily)
SELECT DATE(clicked_at) as date, COUNT(*) as clicks
FROM clicks WHERE url_id = ?
  AND clicked_at >= DATE('now', '-30 days')
GROUP BY DATE(clicked_at) ORDER BY date;

-- Top referrers
SELECT referrer, COUNT(*) as clicks
FROM clicks WHERE url_id = ? AND referrer IS NOT NULL
GROUP BY referrer ORDER BY clicks DESC LIMIT 10;
```

## Error Handling

| Error | Handling |
|-------|----------|
| Invalid short code | 404 page with friendly message |
| Duplicate short code | Error with suggestion (e.g., "code-2") |
| Invalid URL | Validation error before DB insert |
| SQLite locked | Retry with brief delay |
| Missing extensions | Friendly error message |
| Unwritable database | Permissions error with instructions |

## Deployment

### Requirements

- PHP 7.4+ with extensions: `pdo_sqlite`, `mbstring`
- Web server with .htaccess support (Apache)
- Writable file permissions for `database.sqlite`

### Setup Process

1. Upload all files to web server
2. Ensure `database.sqlite` is writable
3. Visit `/admin` - auto-generates password in `setup.txt`
4. Login with generated password
5. Delete `setup.txt` (prompted on screen)

### .htaccess Checks

The application validates:
- Required PHP extensions are available
- Database file is writable
- Configuration is valid

Shows helpful error messages if any check fails.

## UI/UX

- Responsive design (mobile-friendly sidebar)
- Flash messages for user feedback
- Collapsible sidebar navigation
- Modal dialogs for forms
- Clean, modern interface with Tailwind CSS

## Future Considerations

- Optional: API for programmatic URL creation
- Optional: QR code generation for URLs
- Optional: Export analytics as CSV
- Optional: Custom domain support per URL
