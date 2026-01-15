# trcy.cc - URL Shortener

A self-hosted URL shortener with analytics, designed for personal use on shared hosting.

## Features

- Create short URLs with custom slugs
- Track clicks with referrer and user agent analytics
- Clean admin interface
- SQLite database (no separate server needed)
- Dead simple deployment

## Requirements

- PHP 7.4 or higher
- SQLite3 extension
- Apache with .htaccess support (or equivalent)

## Installation

1. Upload all files to your web server
2. Ensure `database.sqlite` is writable (chmod 666)
3. Visit `/admin` in your browser
4. Your admin password will be auto-generated and saved to `setup.txt`
5. Login with the generated password
6. Delete `setup.txt` after saving your password

## Usage

### Creating a URL

1. Login to the admin panel at `/admin`
2. Click "Create New URL"
3. Enter your long URL and optionally a custom short code
4. Click "Create"

### Viewing Analytics

1. Go to the URLs page
2. Click "Analytics" next to any URL
3. View clicks over time, top referrers, and recent clicks

## File Structure

```
/
├── admin.php           # Admin interface entry point
├── index.php           # Short URL redirect handler
├── config.php          # Configuration
├── database.sqlite     # SQLite database
├── lib/                # Helper functions
├── views/              # PHP templates
└── .htaccess           # URL rewriting
```

## Security

- Admin password hashed with bcrypt
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS prevention with output escaping

## Development

### Running Tests

```bash
phpunit
```

### Test Coverage

- Router: 10 tests
- Database: 9 tests
- Security (CSRF/Auth): 17 tests

## License

MIT
