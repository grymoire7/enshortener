# Zero-Configuration Deployment Design

**Date:** 2026-01-16
**Status:** Approved
**Goal:** Enable true zero-configuration deployment - copy directory to shared hosting and it just works.

## Problem Statement

Current setup process requires:
1. User visits `/admin` on first run
2. System generates random password and writes to `setup.txt`
3. User must manually copy password and delete `setup.txt`

This creates friction and security concerns (forgotten `setup.txt` files).

## Design Goals

1. **Zero manual steps** - No file deletion, no password copying from files
2. **Intuitive setup** - Web-based password creation on first visit
3. **Password recovery** - Simple FTP-based reset for shared hosting users
4. **Fast redirects** - No unnecessary file system checks on short URL access
5. **Single-user focus** - Don't over-engineer for edge cases

## Architecture Overview

### Database Initialization

**Current:** `DB::init()` assumes database exists, fails if not.

**New Design:**

```php
// DB::init() - unchanged behavior
// Opens connection, throws PDOException if database missing

// DB::createDatabase($config) - new method
// Creates database.sqlite with full schema
// Returns PDO connection
```

**Schema Creation:**
- urls table (id, short_code, long_url, created_at, click_count)
- clicks table (id, url_id, clicked_at, referrer, user_agent)
- settings table (key, value)
- All indexes
- Initial settings row: `admin_password_hash` = empty string

### Setup Flow

#### First Deployment - Short URL Access

**File:** `index.php`

```php
try {
    $db = DB::init($config);
    // Normal redirect logic...
} catch (PDOException $e) {
    // Show friendly error page (no redirect)
    // "URL Shortener - Setup Required
    //  This shortener hasn't been configured.
    //  Visit /admin to complete setup."
}
```

**Why no redirect?** Keep it simple. User visiting a short URL gets a clear message without unnecessary redirects.

#### First Deployment - Admin Access

**File:** `admin.php`

```php
try {
    $db = DB::init($config);
} catch (PDOException $e) {
    // Database missing, create it
    $db = DB::createDatabase($config);
}

// Check if setup needed
$needsSetup = !is_setup_complete() || file_exists(__DIR__ . '/reset.txt');

if ($needsSetup) {
    // Show setup form (views/admin_setup.php)
    // Instead of normal routing
}
```

**Setup Form:**
- Header: "Welcome! Set Admin Password" (or "Reset Admin Password" if reset.txt exists)
- Password field (min 8 chars)
- Confirm password field
- CSRF token
- Submit → POST /admin/setup

**POST /admin/setup Handler:**
1. Validate CSRF token
2. Validate passwords match and meet requirements (8+ chars)
3. Hash password with `password_hash()`
4. Update settings: `admin_password_hash` = hashed password
5. Delete `reset.txt` if it exists
6. Auto-login user (`$_SESSION['admin_logged_in'] = true`)
7. Redirect to `/admin` dashboard with success message

### Password Reset Flow

**User Action:**
1. Connect to shared hosting via FTP
2. Create empty file `reset.txt` in root directory (next to index.php)
3. Visit `/admin`

**System Behavior:**
```php
$needsSetup = !is_setup_complete() || file_exists(__DIR__ . '/reset.txt');

if ($needsSetup) {
    $isReset = file_exists(__DIR__ . '/reset.txt');
    // Show same setup form
    // Header changes based on $isReset flag
}
```

**After Password Reset:**
- `reset.txt` deleted automatically
- User auto-logged in
- Redirected to dashboard

**Security Notes:**
- `reset.txt` can contain anything (even empty) - just checks file existence
- File deleted immediately after successful reset
- If `reset.txt` created accidentally, it persists harmlessly (password unchanged until reset completed)
- Admin can still login normally even if `reset.txt` exists

## Error Handling

### Database Creation Failure

If `DB::createDatabase()` throws exception:

```php
try {
    $db = DB::createDatabase($config);
} catch (PDOException $e) {
    // Show error page with troubleshooting:
    // "Setup Failed - Database Creation Error
    //
    //  Possible fixes:
    //  - Check directory is writable (chmod 755 or 775)
    //  - Verify disk space available
    //  - Ensure SQLite3 extension enabled
    //
    //  Error: {$e->getMessage()}"
}
```

Don't retry automatically - show clear error and let user fix environment.

### Race Conditions (Multiple First Visits)

**Not a priority** - Single-user app, edge case unlikely.

If it happens:
- SQLite handles concurrent creates gracefully
- First request wins, others get brief lock
- Could add fallback: if `createDatabase()` fails, retry `init()` (another request might have succeeded)

**Decision:** Don't implement unless needed. Keep it simple.

### Orphaned reset.txt

If user creates `reset.txt` but never completes reset:
- File persists harmlessly
- Password unchanged
- Next visit to `/admin` (while logged out) shows reset form
- User can delete via FTP if created accidentally

## Implementation Plan

### Files to Create

**views/admin_setup.php** (new)
- Reusable setup form view
- Takes `$isReset` parameter to change header text
- Password + confirm fields
- CSRF protection
- Form posts to `/admin/setup`

### Files to Modify

**lib/db.php**
- Add `DB::createDatabase($config)` method
- Method creates database file
- Executes schema SQL (CREATE TABLE statements)
- Inserts initial settings row
- Returns PDO connection

**admin.php**
- Wrap `DB::init()` in try/catch
- On exception, call `DB::createDatabase()`
- Add setup detection: `$needsSetup = !is_setup_complete() || file_exists('reset.txt')`
- If setup needed, show setup form instead of routing
- Add `POST /admin/setup` route handler:
  - Validate CSRF
  - Validate passwords (match, 8+ chars)
  - Hash and save to database
  - Delete `reset.txt` if exists
  - Auto-login
  - Redirect to dashboard

**index.php**
- Wrap `DB::init()` in try/catch
- On exception, show friendly HTML error page
- No redirect, no database initialization

**lib/auth.php**
- `is_setup_complete()` - keep as-is (checks if password hash is not empty)
- No changes needed

### Files to Delete

**lib/setup.php**
- Remove `generate_admin_password()`
- Remove `get_setup_password()`
- Delete entire file

**Remove setup.txt references:**
- Delete from `.gitignore` if present
- Remove any documentation about setup.txt

## Testing Strategy

### Manual Testing Checklist

**Fresh Deployment:**
1. Delete `database.sqlite`
2. Visit `/test123` → verify "Setup Required" page displays
3. Visit `/admin` → verify database created automatically
4. Verify setup form shown (not login form)
5. Submit with mismatched passwords → verify error
6. Submit with short password (<8 chars) → verify error
7. Submit valid password → verify redirect to dashboard
8. Verify auto-logged in (no second login needed)
9. Create short URL
10. Visit short URL → verify redirect works
11. Check database file created and writable

**Password Reset:**
1. Create empty `reset.txt` in root
2. Visit `/admin` (while logged out) → verify reset form shown
3. Set new password → verify success
4. Verify `reset.txt` deleted automatically
5. Verify logged in with new password
6. Logout and login again → verify new password works

**Existing Installation:**
1. Normal login flow → verify unchanged
2. Dashboard, URL management → verify no regressions
3. Short URL redirects → verify performance unchanged

### PHPUnit Tests

**tests/DBTest.php - Add:**
- `testCreateDatabaseCreatesFile()` - verify file created
- `testCreateDatabaseCreatesSchema()` - verify tables exist
- `testCreateDatabaseInsertsInitialSettings()` - verify admin_password_hash row
- `testCreateDatabaseReturnsConnection()` - verify returns PDO
- `testInitThrowsWhenDatabaseMissing()` - verify exception behavior

**tests/SecurityTest.php - Update:**
- Remove `setup.txt` related tests
- Add `testResetFileTriggersSetupForm()` - verify reset.txt detection
- Add `testSetupFormValidatesPasswords()` - verify validation logic
- Add `testSetupDeletesResetFile()` - verify cleanup

**tests/RouterTest.php - Add:**
- `testAdminSetupRouteValidatesCSRF()` - verify CSRF protection
- `testAdminSetupRouteHashesPassword()` - verify password_hash() used
- `testAdminSetupRouteAutoLogsIn()` - verify session set
- `testAdminSetupRouteRedirects()` - verify redirect to dashboard

## Documentation Updates

### README.md Changes

**Remove:**
- All references to `setup.txt`
- Manual database setup instructions
- Password copying instructions

**Add:**

```markdown
## Installation

1. Copy all files to your web server
2. Ensure directory is writable: `chmod 755 /path/to/urlshortener`
3. Visit `/admin` in your browser
4. Set your admin password
5. Done!

## Password Reset

If you forget your admin password:

1. Connect to your server via FTP
2. Create an empty file named `reset.txt` in the root directory
3. Visit `/admin` in your browser
4. Set your new password
5. The `reset.txt` file will be deleted automatically
```

### AGENTS.md Updates

Update implementation status:
- Mark setup.txt removal as complete
- Add zero-config deployment task
- Update file list (remove setup.php, add admin_setup.php)

## Success Criteria

✅ User copies directory to shared hosting
✅ Visits `/admin` - database created automatically
✅ Sets password via web form
✅ No manual file operations required
✅ Password reset via simple FTP file creation
✅ Short URL redirects remain fast (no unnecessary checks)
✅ All existing functionality preserved
✅ Tests pass with new logic

## Non-Goals

- Multi-user support or complex permission systems
- Advanced password recovery (email, security questions)
- Automatic database migrations
- Concurrent setup handling (single-user app)
- Production-grade error recovery

## Security Considerations

**Positive Changes:**
- No plaintext passwords in `setup.txt` files
- Password chosen by user (not system-generated random string)
- CSRF protection on setup form
- Reset requires file system access (FTP) - reasonable auth for shared hosting

**Potential Concerns:**
- `reset.txt` allows anyone with FTP access to reset password
  - **Mitigation:** If attacker has FTP, they already have full access to codebase and database
  - **Acceptable:** Shared hosting context, FTP = admin access
- First visitor to `/admin` sets password
  - **Mitigation:** Document "visit /admin immediately after deployment"
  - **Acceptable:** Single-user personal tool, not SaaS

## Migration Path

**For existing installations:**
- No migration needed
- Existing `database.sqlite` continues working
- Old `setup.txt` files can be deleted manually (no impact)
- New logic only activates if database missing or password empty

**Backward compatibility:**
- 100% compatible with existing installations
- Only affects new deployments or reset scenarios
