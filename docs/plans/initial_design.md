## URL shortener with local hosting and analytics (https://trcy.cc)

- Must use PHP
- Must use sqlite
- Must be dead simple to deploy on shared hosting
- Must have admin interface for managing URLs
- Does NOT require user accounts, only admin access via password
- Must have simple web interface for creating and managing short URLs
- Must have analytics dashboard showing click statistics
- Must have option for custom short URL slugs
- Ships with default database file
- Must set admin password on first run of /admin interface

Draft database schema:

```sql
CREATE TABLE urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    short_code TEXT UNIQUE NOT NULL,
    long_url TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    click_count INTEGER DEFAULT 0
);

CREATE TABLE clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_id INTEGER NOT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    referrer TEXT, // referrer URL
    user_agent TEXT, // user agent string
    FOREIGN KEY (url_id) REFERENCES urls(id)
);

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL
);
```

Admin interface features:
- Login with password
- Create new short URL
- View list of existing short URLs
- Edit/delete existing short URLs
- View analytics dashboard
  - Total clicks per URL
  - Clicks over time (daily/weekly/monthly)
  - Referrer breakdown
  - User agent breakdown
- Change admin password
- Set site-wide settings (e.g. base URL)
- Simple, clean UI using basic HTML/CSS (no heavy frameworks)
- Responsive design for mobile access

Considerations:

- Security: Prevent SQL injection, XSS, CSRF
- Performance: Indexing, caching
  - Scalability: for indvidual use on shared hosting, not large scale
  - Cache frequently accessed URLs in memory? Optional
  - Index short_code in urls table
  - Index url_id in clicks table
- Usability: Simple UI/UX design
- Deployment: Easy setup script, documentation

Questions:

1. Given the restricted use case (personal use on shared hosting, sqlite,
   etc.), how much value would a PHP framework (like Laravel or Slim) add
   versus a simple custom implementation? Slim for routing and middleware?

