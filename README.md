# Enshortener - URL Shortener

Enshortener is a URL shortener with analytics, designed for personal use on
shared hosting. It is designed to be simple, secure, and easy to deploy. Just
copy the files to your server and go.

## Features

- Create short URLs with custom slugs
- Track clicks with referrer and user agent analytics
- Clean admin interface
- SQLite database (no separate server needed)
- Dead simple deployment

## Requirements

- PHP 8.1 or higher
- SQLite3 extension
- Apache with .htaccess support (or equivalent)

### Development Requirements

- Node.js 18+ and npm (for building CSS)

## Installation

1. Upload all files to your web server
2. Ensure `database.sqlite` is writable (chmod 666)
3. Visit `https://{{your website}}/admin` in your browser
4. Set your admin password on the setup screen
5. Login with your password

## Usage

### Creating a URL

1. Login to the admin panel at `/admin`
2. Click "Create New URL"
3. Enter your long URL and optionally a custom short code
4. Click "Create"

### Viewing Analytics

1. Go to the URLs page (`/admin/urls`)
2. Click "Analytics" next to any URL
3. View clicks over time, top referrers, and recent clicks

## Security

- Admin password hashed with bcrypt
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS prevention with output escaping

## Local Development and Testing

You can test the application locally using PHP's built-in web server:

```bash
# Start the development server
npm run server:start
```

Then visit:
- http://localhost:8000/ - Home page
- http://localhost:8000/admin - Admin panel
- http://localhost:8000/abc - Short URL redirect (where `abc` is a short code)

**Note:** The `server.php` file is a router script for local development only.
On production servers with Apache, the `.htaccess` file handles URL rewriting.

### Resetting the Admin Password

To reset your admin password in local development:

```bash
npm run reset:password
```

To reset your admin password on hosted environments:

1. Create a file named `reset.txt` in the root directory.
2. Visit `/admin` in your browser. This will prompt you to set a new password and then it will delete the `reset.txt` file.

### Resetting the Database

To start fresh locally:

```bash
npm run reset:database
# OR: rm database.sqlite
# Then visit /admin to set up again
```

### Building CSS

The application uses Tailwind CSS for styling. To build the CSS:

```bash
# Install dependencies
npm install

# Build CSS (one-time)
npm run build:css

# Watch for changes and rebuild automatically
npm run watch:css
```

**Note:** The built `css/compiled.css` file is included in the repository, so
you don't need to build CSS for deployment. The pre-built CSS is ready to use.

### Running Tests

```bash
# Run unit tests
npm run test:unit

# Run unit tests with detailed output
npm run test:unit_details

# Check PHP syntax
npm run test:syntax
```

## License

MIT
