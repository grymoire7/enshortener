# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-14

### Added

- Dark mode support with instant theme switching
- Persistent theme preference saved to localStorage

### Fixed

- Improved text contrast in dark mode for all UI elements
- More consistent short code linking across admin pages

## [1.0.0] - 2025-01-14

Initial release.

### Features

- Custom URL shortening with SQLite3 database
- Admin dashboard with analytics and Chart.js visualizations
- URL management (create, read, delete)
- Click tracking and analytics
- Session-based authentication
- CSRF protection
- Responsive design with Tailwind CSS
- Mobile-friendly admin interface
- Settings page for password and base URL configuration
- PHP 8.1+ compatible with PHPUnit test suite (36 tests)
