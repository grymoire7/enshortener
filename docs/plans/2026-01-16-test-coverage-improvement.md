# Test Coverage Improvement Plan

**Date:** 2026-01-16
**Status:** Draft
**Problem:** Missed `setup_file_exists()` references in view files because no tests exercise view rendering

## Current Test Coverage Analysis

### What We Have (37 tests)

| File | Tests | Coverage |
|------|-------|----------|
| `RouterTest.php` | 10 | Router dispatch logic only |
| `DBTest.php` | 14 | DB class methods only |
| `SecurityTest.php` | 13 | CSRF, auth functions only |

### Critical Gaps

1. **No view rendering tests** - View functions are never called, so broken dependencies go undetected
2. **No smoke tests** - Files can have syntax errors or missing dependencies that aren't caught
3. **No integration tests** - Full request flows through `admin.php` and `index.php` aren't tested
4. **No entry point tests** - `admin.php` and `index.php` initialization logic isn't tested

## Root Cause of the Bug

The `setup_file_exists()` function was:
- Defined in `lib/auth.php`
- Called in `views/layout.php` and `views/admin_layout.php`
- Removed from `lib/auth.php` during refactor
- Never caught because no test ever called `render_layout()` or `render_admin_layout()`

## Proposed Test Structure

### 1. Smoke Tests (New: `tests/SmokeTest.php`)

**Purpose:** Catch syntax errors and missing dependencies by requiring all PHP files.

```php
class SmokeTest extends TestCase
{
    public function testAllLibFilesLoad(): void
    {
        // Require each lib file and verify no fatal errors
    }

    public function testAllViewFilesLoad(): void
    {
        // Require each view file and verify no fatal errors
    }

    public function testAdminEntryPointLoads(): void
    {
        // Require admin.php in isolated environment
    }

    public function testIndexEntryPointLoads(): void
    {
        // Require index.php in isolated environment
    }
}
```

**Tests to add:**
- `testLibDbLoads()`
- `testLibAuthLoads()`
- `testLibCsrfLoads()`
- `testRouterLoads()`
- `testAllViewFilesLoadWithoutError()` - loops through all view files

### 2. View Rendering Tests (New: `tests/ViewTest.php`)

**Purpose:** Test that view functions can be called without errors and produce expected output.

```php
class ViewTest extends TestCase
{
    public function testRenderLayoutProducesHtml(): void
    {
        ob_start();
        render_layout('Test', '<p>Content</p>');
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<p>Content</p>', $output);
    }

    public function testRenderAdminLayoutProducesHtml(): void
    {
        // Similar pattern
    }

    public function testRenderLoginPageProducesForm(): void
    {
        // Verify login form elements present
    }

    public function testRenderSetupPageProducesForm(): void
    {
        // Verify setup form elements present
    }

    public function testRenderDashboardWithEmptyDb(): void
    {
        // Test dashboard renders with no URLs
    }
}
```

**Tests to add:**
- `testRenderLayoutProducesValidHtml()`
- `testRenderLayoutWithFlashMessage()`
- `testRenderAdminLayoutProducesValidHtml()`
- `testRenderAdminLayoutHighlightsActiveNav()`
- `testRenderLoginPageShowsPasswordField()`
- `testRenderLoginPageShowsErrorMessage()`
- `testRenderSetupPageShowsPasswordFields()`
- `testRenderSetupPageShowsResetHeader()`
- `testRenderDashboardShowsStats()`
- `testRenderUrlsPageShowsCreateForm()`
- `testRenderUrlsPageShowsUrlList()`
- `testRenderAnalyticsPageShowsChart()`
- `testRenderSettingsPageShowsPasswordForm()`

### 3. Integration Tests (New: `tests/IntegrationTest.php`)

**Purpose:** Test full request flows by simulating HTTP requests.

```php
class IntegrationTest extends TestCase
{
    public function testFreshInstallShowsSetupForm(): void
    {
        // Delete database, simulate GET /admin
        // Verify setup form shown
    }

    public function testSetupCreatesPasswordAndLogs(): void
    {
        // POST to /admin/setup with valid password
        // Verify logged in and redirected
    }

    public function testLoginWithValidPassword(): void
    {
        // POST to /admin/login
        // Verify session set
    }

    public function testProtectedRouteRequiresAuth(): void
    {
        // GET /admin/urls without session
        // Verify redirect to login
    }

    public function testShortUrlRedirects(): void
    {
        // Create URL, then GET /{code}
        // Verify 301 redirect
    }

    public function testMissingDatabaseShowsSetupMessage(): void
    {
        // Delete database, GET /{code}
        // Verify setup required page
    }
}
```

**Tests to add:**
- `testFreshInstallShowsSetupOnAdmin()`
- `testFreshInstallShowsMessageOnShortUrl()`
- `testSetupWithMatchingPasswords()`
- `testSetupWithMismatchedPasswords()`
- `testSetupWithShortPassword()`
- `testResetFileTriggersSetupForm()`
- `testResetFileDeletedAfterSetup()`
- `testLoginSuccess()`
- `testLoginFailure()`
- `testLogoutClearsSession()`
- `testDashboardRequiresAuth()`
- `testUrlCreation()`
- `testUrlDeletion()`
- `testShortUrlRedirect()`
- `testShortUrlRecordsClick()`
- `testPasswordChangeRequiresCurrentPassword()`

### 4. Enhanced DB Tests (Update: `tests/DBTest.php`)

**Additional tests:**
- `testCreateDatabaseCreatesIndexes()` - Verify indexes exist
- `testCreateDatabaseIdempotent()` - Calling twice doesn't error (or errors gracefully)

### 5. Enhanced Security Tests (Update: `tests/SecurityTest.php`)

**Additional tests:**
- `testRequireAdminRedirectsWhenNotLoggedIn()`
- `testRequireCsrfRejectsInvalidToken()`
- `testRequireCsrfAcceptsValidToken()`

## Implementation Priority

### Phase 1: Smoke Tests (Highest Priority)
Would have caught the `setup_file_exists()` bug immediately.

**Estimated tests:** 6
**Files:** `tests/SmokeTest.php`

### Phase 2: View Rendering Tests (High Priority)
Ensures all views can render without errors.

**Estimated tests:** 14
**Files:** `tests/ViewTest.php`

### Phase 3: Integration Tests (Medium Priority)
Tests complete user flows.

**Estimated tests:** 16
**Files:** `tests/IntegrationTest.php`

### Phase 4: Enhanced Unit Tests (Lower Priority)
Fill remaining gaps in existing test files.

**Estimated tests:** 5
**Files:** Updates to existing test files

## Total New Tests: ~41

## Implementation Notes

### Test Database Setup
Each test class needs isolated database setup:
```php
protected function setUp(): void
{
    $this->testDbPath = sys_get_temp_dir() . '/test_' . uniqid() . '.sqlite';
    DB::createDatabase(['db_path' => $this->testDbPath]);
}
```

### Output Buffering for View Tests
Views echo output directly, so tests must use output buffering:
```php
ob_start();
render_function();
$output = ob_get_clean();
```

### Session Mocking
Tests need to handle session state:
```php
$_SESSION = [];
$_SESSION['admin_logged_in'] = true;
```

### Superglobal Setup for Integration Tests
```php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin';
$_POST = ['password' => 'testpass'];
```

## Success Criteria

1. All smoke tests pass on clean checkout
2. Removing any function used by views causes test failure
3. All view functions have at least one render test
4. All critical user flows have integration test coverage
5. Total test count: ~78 (from current 37)
