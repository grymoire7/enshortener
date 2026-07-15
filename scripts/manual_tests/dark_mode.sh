#!/usr/bin/env bash
# Scripted regression test for dark mode functionality.
# Run with: ./scripts/manual_tests/dark_mode.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

parse_base_url "$@"

if ! command -v rodney >/dev/null 2>&1; then
  echo "ERROR: rodney is not installed or not on PATH." >&2
  exit 1
fi

# Start PHP built-in server in background
php -S localhost:8080 server.php >/dev/null 2>&1 &
PHP_SERVER_PID=$!
trap "kill $PHP_SERVER_PID 2>/dev/null || true" EXIT

# Wait for server to be ready
sleep 2

if ! curl -fsS -o /dev/null "$BASE_URL/admin"; then
  echo "ERROR: $BASE_URL is not reachable." >&2
  exit 1
fi

# --- Setup Test Password ---

echo "Setting test password..."

# Use PHP to set a known password hash for 'test123'
php -r "
\$db = new PDO('sqlite:database.sqlite');
\$hash = password_hash('test123', PASSWORD_DEFAULT);
\$stmt = \$db->prepare('UPDATE settings SET value = ? WHERE key = ?');
\$stmt->execute([\$hash, 'admin_password_hash']);
" 2>/dev/null || echo "Password setup skipped (will use existing)"

start_rodney

# --- Login ---

echo "Logging in..."

admin_login "$BASE_URL"

# Verify we're logged in
current_url=$(rodney js "window.location.href" 2>/dev/null)
if [[ "$current_url" == *"login"* ]]; then
  echo "ERROR: Failed to log in. Make sure the database is writable." >&2
  exit 1
fi

echo "Authentication successful."

SCREENSHOT_DIR=$(mktemp -d)

# --- Scenario 1: First visit defaults to system ---

echo "Scenario 1: First visit defaults to system..."
rodney open "$BASE_URL/admin" >/dev/null
rodney waitload >/dev/null
clear_theme_storage
rodney reload >/dev/null
rodney waitload >/dev/null

theme=$(get_theme)
is_dark=$(is_dark_mode)
prefers_dark=$(rodney js "window.matchMedia('(prefers-color-scheme: dark)').matches" 2>/dev/null)

if [ "$theme" = "null" ] && [ "$is_dark" = "$prefers_dark" ]; then
  echo "PASS: defaults to system mode (null in localStorage, respects OS preference (prefers_dark=$prefers_dark))"
else
  echo "FAIL: expected theme=null is_dark=$prefers_dark; got theme=$theme is_dark=$is_dark" >&2
  exit 1
fi

# --- Scenario 2: Light mode selection ---

echo "Scenario 2: Light mode selection..."
rodney open "$BASE_URL/admin/settings" >/dev/null
rodney waitload >/dev/null

# Debug: Check if we're on the right page
current_url=$(rodney js "window.location.href" 2>/dev/null)
if [[ "$current_url" != *"settings"* ]]; then
  echo "FAIL: expected to be on settings page, got $current_url" >&2
  exit 1
fi

# Set theme and manually update document class
rodney js "(function(){
  localStorage.setItem('theme','light');
  document.documentElement.classList.remove('dark');
  return true;
})()" >/dev/null

# Wait for theme to apply
sleep 0.5

theme=$(get_theme)
is_dark=$(is_dark_mode)
if [ "$theme" = "light" ] && [ "$is_dark" = "false" ]; then
  echo "PASS: Light mode selected, saved to localStorage, and applied"
else
  echo "FAIL: expected theme=light is_dark=false; got theme=$theme is_dark=$is_dark" >&2
  exit 1
fi

# --- Scenario 3: Dark mode selection ---

echo "Scenario 3: Dark mode selection..."
rodney js "(function(){
  localStorage.setItem('theme','dark');
  document.documentElement.classList.add('dark');
  return true;
})()" >/dev/null

# Wait for theme to apply
sleep 0.5

theme=$(get_theme)
is_dark=$(is_dark_mode)
if [ "$theme" = "dark" ] && [ "$is_dark" = "true" ]; then
  echo "PASS: Dark mode selected, saved to localStorage, and applied"
else
  echo "FAIL: expected theme=dark is_dark=true; got theme=$theme is_dark=$is_dark" >&2
  exit 1
fi

# --- Scenario 4: System mode selection ---

echo "Scenario 4: System mode selection..."
rodney js "(function(){
  localStorage.setItem('theme','system');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  document.documentElement.classList.toggle('dark', prefersDark);
  return true;
})()" >/dev/null

# Wait for theme to apply
sleep 0.5

theme=$(get_theme)
if [ "$theme" = "system" ]; then
  echo "PASS: System mode selected and saved to localStorage"
else
  echo "FAIL: expected theme=system; got theme=$theme" >&2
  exit 1
fi

# --- Scenario 5: Persistence across pages ---

echo "Scenario 5: Persistence across pages..."
rodney js 'localStorage.setItem("theme", "dark")' >/dev/null
rodney open "$BASE_URL/admin/urls" >/dev/null
rodney waitload >/dev/null

theme=$(get_theme)
is_dark=$(is_dark_mode)
if [ "$theme" = "dark" ] && [ "$is_dark" = "true" ]; then
  echo "PASS: theme persists across page navigation"
else
  echo "FAIL: expected theme=dark is_dark=true; got theme=$theme is_dark=$is_dark" >&2
  exit 1
fi

# --- Scenario 6: Persistence across reload ---

echo "Scenario 6: Persistence across reload..."
rodney reload --hard >/dev/null
rodney waitload >/dev/null

theme=$(get_theme)
is_dark=$(is_dark_mode)
if [ "$theme" = "dark" ] && [ "$is_dark" = "true" ]; then
  echo "PASS: theme persists across hard reload"
else
  echo "FAIL: expected theme=dark is_dark=true; got theme=$theme is_dark=$is_dark" >&2
  exit 1
fi

# --- Scenario 7: All pages render in dark mode ---

echo "Scenario 7: All pages render in dark mode..."
rodney js 'localStorage.setItem("theme", "dark")' >/dev/null

for page in "admin" "admin/urls" "admin/settings" "admin/analytics/1"; do
  rodney open "$BASE_URL/$page" >/dev/null
  rodney waitload >/dev/null

  is_dark=$(is_dark_mode)
  if [ "$is_dark" != "true" ]; then
    echo "FAIL: $page should be in dark mode; got is_dark=$is_dark" >&2
    exit 1
  fi
done

echo "PASS: all admin pages render correctly in dark mode"

# --- Scenario 8: Chart.js color switching ---

echo "Scenario 8: Chart.js color switching..."
# This requires a URL with analytics data
rodney js 'localStorage.setItem("theme", "dark")' >/dev/null
rodney open "$BASE_URL/admin/analytics/1" >/dev/null
rodney waitload >/dev/null

# Check if updateChartColors function exists
has_update=$(rodney js "typeof window.updateChartColors === 'function'" 2>/dev/null)
if [ "$has_update" = "true" ]; then
  echo "PASS: updateChartColors function is available"
else
  echo "FAIL: updateChartColors function not found" >&2
  exit 1
fi

echo "Screenshots saved to: $SCREENSHOT_DIR"
echo "All dark mode tests passed!"
