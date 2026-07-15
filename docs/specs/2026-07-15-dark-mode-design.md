# Dark Mode Implementation Design

**Date:** 2026-07-15
**Status:** Approved
**Estimated Effort:** 3-4 hours

## Overview

Add a tri-state theme preference (Light/Dark/System) to the admin interface that persists in localStorage and applies instantly across all admin pages without page refresh.

## Recommended Approach: Client-Side with Server Fallback

Theme resolution and application happens entirely client-side using:
- Inline script in `<head>` to prevent first-paint flash
- localStorage for persistence
- `matchMedia` API for OS preference detection
- CSS `dark:` variants via Tailwind class-based dark mode

**Why this approach:**
- No server-side changes or database schema updates
- Instant theme changes without page refresh
- No flash of wrong theme on page load
- Simple, isolated implementation

## Architecture

### Theme Resolution Logic

```
1. Read localStorage.getItem('theme')
2. If null → default to 'system'
3. If 'system' → check matchMedia('(prefers-color-scheme: dark)')
4. If 'light' or 'dark' → use that value
5. Apply 'dark' class to <html> element if resolved to dark
```

### Files to Modify

1. **tailwind.config.js** — Enable class-based dark mode
2. **admin_layout.php** — Add inline theme script, add dark mode classes
3. **admin_settings.php** — Add theme selector section
4. **admin_login.php** — Add dark mode classes
5. **admin_dashboard.php** — Add dark mode classes
6. **admin_urls.php** — Add dark mode classes
7. **admin_analytics.php** — Add dark mode classes, update Chart.js colors

### Class Addition Pattern

Throughout all view files, add Tailwind `dark:` variants:

- `bg-white` → `bg-white dark:bg-gray-900`
- `bg-gray-50` → `bg-gray-50 dark:bg-gray-800`
- `text-gray-600` → `text-gray-600 dark:text-gray-300`
- `text-gray-700` → `text-gray-700 dark:text-gray-200`
- `text-gray-900` → `text-gray-900 dark:text-gray-100`
- `border-gray-50` → `border-gray-50 dark:border-gray-700`
- `hover:bg-gray-100` → `hover:bg-gray-100 dark:hover:bg-gray-700`

## UI Components

### Settings Page — Theme Selector

**Location:** Top of Settings page, before "Change Password" section

**Visual presentation:** Same section styling as other Settings sections (white background card, rounded-lg, shadow)

**HTML Structure:**
```html
<div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Appearance</h2>
    
    <form id="themeForm" class="max-w-md">
        <div class="space-y-3">
            <label class="flex items-center space-x-3 cursor-pointer">
                <input type="radio" name="theme" value="light" 
                       class="w-4 h-4 text-blue-500">
                <span class="text-gray-700 dark:text-gray-300">Light</span>
            </label>
            
            <label class="flex items-center space-x-3 cursor-pointer">
                <input type="radio" name="theme" value="dark" 
                       class="w-4 h-4 text-blue-500">
                <span class="text-gray-700 dark:text-gray-300">Dark</span>
            </label>
            
            <label class="flex items-center space-x-3 cursor-pointer">
                <input type="radio" name="theme" value="system" 
                       class="w-4 h-4 text-blue-500">
                <span class="text-gray-700 dark:text-gray-300">System (follow OS preference)</span>
            </label>
        </div>
    </form>
</div>
```

**Behavior:**
- Radio button reflects saved localStorage value (or 'system' if unset)
- On change → instant theme switch + save to localStorage
- No submit button needed (reacts immediately)
- No flash message needed (change is visually obvious)

## Data Flow

### Page Load Sequence

```
1. Browser requests admin page
2. Server renders HTML with current content
3. <head> contains inline script that executes BEFORE paint:
   - Read localStorage.theme
   - Resolve to actual theme (system → check matchMedia)
   - Apply 'dark' class to <html> if needed
4. Page renders with correct theme visible immediately
5. DOMContentLoaded event fires:
   - Settings page: Initialize radio button from localStorage
   - All pages: Set up matchMedia listener for OS preference changes
```

### Theme Change Flow (User Interaction)

```
User clicks radio button
→
JavaScript 'change' event listener
→
Save to localStorage.theme
→
Resolve theme (same logic as page load)
→
Apply/remove 'dark' class on <html>
→
Update Chart.js colors if on analytics page
→
Done (instant, no refresh required)
```

### OS Preference Change Flow

```
User's OS switches light/dark (while browser is open)
→
matchMedia('(prefers-color-scheme: dark)') event fires
→
Only if localStorage.theme === 'system'
→
Re-resolve theme
→
Apply/remove 'dark' class on <html>
→
Update Chart.js colors if on analytics page
→
Done (instant, no refresh required)
```

## Implementation Details

### 1. tailwind.config.js

Add single line to enable class-based dark mode:

```javascript
export default {
  darkMode: 'class',  // Add this line
  content: ['./views/**/*.php', './index.php'],
  theme: { extend: {} },
  plugins: [],
}
```

### 2. admin_layout.php — Inline Script

Add before closing `</head>` tag:

```php
<script>
(function() {
    // Read saved preference, default to 'system'
    const saved = localStorage.getItem('theme') || 'system';

    // Check OS preference
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    // Resolve actual theme
    const isDark = saved === 'dark' || (saved === 'system' && prefersDark);

    // Apply before paint
    document.documentElement.classList.toggle('dark', isDark);

    // Listen for OS preference changes (only matters if saved === 'system')
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (localStorage.getItem('theme') === 'system') {
            document.documentElement.classList.toggle('dark', e.matches);
            // Optional: Update Chart.js instances if they exist
            if (window.updateChartColors) {
                window.updateChartColors();
            }
        }
    });
})();
</script>
```

### 3. admin_settings.php — Theme Section

Add before "Change Password" section (around line 20):

```php
$content = <<<HTML
<div>
    <h1 class="text-3xl font-bold mb-6">Settings</h1>

    <!-- Theme selector section -->
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Appearance</h2>
        
        <form id="themeForm" class="max-w-md">
            <div class="space-y-3">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="light" 
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">Light</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="dark" 
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">Dark</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="system" 
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">System (follow OS preference)</span>
                </label>
            </div>
        </form>
    </div>

    <script>
    // Initialize theme form and handle changes
    (function() {
        const form = document.getElementById('themeForm');
        if (!form) return;

        // Set initial radio button state
        const current = localStorage.getItem('theme') || 'system';
        form.querySelector(`input[value="${current}"]`).checked = true;

        // Handle theme changes
        form.addEventListener('change', (e) => {
            if (e.target.name === 'theme') {
                localStorage.setItem('theme', e.target.value);
                
                // Re-resolve and apply theme
                const saved = e.target.value;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = saved === 'dark' || (saved === 'system' && prefersDark);
                document.documentElement.classList.toggle('dark', isDark);
                
                // Update chart colors if on analytics page
                if (window.updateChartColors) {
                    window.updateChartColors();
                }
            }
        });
    })();
    </script>

    {$error_html}
    {$success_html}

    <!-- Change Password section follows... -->
```

### 4. View Files — Dark Mode Classes

Add `dark:` variants throughout:

**admin_layout.php (~15 classes):**
- `bg-white` → `bg-white dark:bg-gray-900`
- `bg-gray-50` → `bg-gray-50 dark:bg-gray-800`
- `text-gray-600` → `text-gray-600 dark:text-gray-300`
- `hover:bg-gray-100` → `hover:bg-gray-100 dark:hover:bg-gray-700`
- Apply to mastheader, sidebar, main content area

**admin_login.php (~10 classes):**
- Background, card, text colors
- Input fields, buttons
- Error messages

**admin_dashboard.php (~20 classes):**
- Stats cards (white background)
- Table headers, borders
- Navigation links

**admin_urls.php (~25 classes):**
- URL table, modal dialog
- Form inputs, buttons
- Pagination controls
- Flash messages (success/error)

**admin_analytics.php (~15 classes + Chart.js):**
- Chart container, cards
- Tables
- Dynamic Chart.js colors (see below)

**admin_settings.php (~15 classes):**
- All sections (Appearance, Change Password, Danger Zone)
- Form inputs, labels
- Buttons, messages

### 5. admin_analytics.php — Chart.js Dynamic Colors

Replace hardcoded colors with dynamic theme-aware colors:

```javascript
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Function to get theme-aware colors
function getChartColors() {
    const isDark = document.documentElement.classList.contains('dark');
    return {
        border: isDark ? 'rgb(96, 165, 250)' : 'rgb(59, 130, 246)',
        background: isDark ? 'rgba(96, 165, 250, 0.2)' : 'rgba(59, 130, 246, 0.1)'
    };
}

// Create chart
const colors = getChartColors();
const ctx = document.getElementById('clicksChart').getContext('2d');
const clicksChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: {$chart_labels_json},
        datasets: [{
            label: 'Clicks',
            data: {$chart_data_json},
            borderColor: colors.border,
            backgroundColor: colors.background,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Expose update function for theme changes
window.updateChartColors = function() {
    const colors = getChartColors();
    clicksChart.data.datasets[0].borderColor = colors.border;
    clicksChart.data.datasets[0].backgroundColor = colors.background;
    clicksChart.update();
};
</script>
```

## Testing Approach

### Scripted Manual Tests with Rodney

Following the pattern from `../stormoji/scripts/manual_tests/`, create:

```
scripts/manual_tests/
├── dark_mode.sh        # Main test script
├── lib.sh              # Shared helpers (start_rodney, BASE_URL, etc.)
└── run_all.sh          # Run all manual test suites
```

### Test Scenarios

1. **First visit defaults**
   - Clear localStorage
   - Navigate to `/admin`
   - Verify theme defaults to 'system' (respects OS preference)
   - Check radio button reflects 'system'

2. **Light mode selection**
   - On Settings page, click "Light" radio button
   - Verify page instantly switches to light theme
   - Check `localStorage.getItem('theme') === 'light'`
   - Verify "Light" radio button is checked
   - Navigate to different page, verify theme persists

3. **Dark mode selection**
   - On Settings page, click "Dark" radio button
   - Verify page instantly switches to dark theme
   - Check `localStorage.getItem('theme') === 'dark'`
   - Verify "Dark" radio button is checked
   - Hard reload, verify dark theme persists

4. **System mode selection**
   - Click "System" radio button
   - Verify theme matches OS preference
   - Check `localStorage.getItem('theme') === 'system'`
   - Toggle OS appearance, verify admin theme switches

5. **Persistence across pages**
   - Set theme to dark
   - Navigate Dashboard → URLs → Analytics → Settings
   - Verify dark theme persists throughout
   - Check computed styles verify dark colors

6. **All pages render correctly**
   - Test each page (Dashboard, URLs, Analytics, Settings, Login)
   - Verify computed background colors in both themes
   - Verify text contrast is readable
   - Take screenshots as evidence

7. **Chart.js color switching**
   - Navigate to Analytics page
   - Toggle theme, verify chart colors update
   - Check chart border/background colors match theme

8. **OS preference following**
   - Set theme to 'system'
   - Toggle OS dark mode on/off
   - Verify admin interface switches accordingly

### Rodney Script Structure

```bash
#!/usr/bin/env bash
# Scripted regression test for dark mode

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

BASE_URL="http://localhost:8080"  # PHP built-in server

start_rodney

# Scenario 1: First visit defaults to system
echo "Scenario 1: First visit defaults to system..."
# ... rodney commands to verify

# Scenario 2: Light mode selection
echo "Scenario 2: Light mode selection..."
# ... rodney commands to verify

# Continue for all scenarios...

echo "All dark mode tests passed!"
```

## Technical Considerations

### First Paint Flash Prevention

Critical requirement: Inline script in `<head>` runs synchronously before any content renders. Without this, users see a flash of light theme on every page load.

### localStorage Quota

Minimal footprint (~10 bytes for 'theme' key), no quota concerns.

### Browser Support

- `localStorage`: IE8+
- `matchMedia`: IE10+
- `classList.toggle`: IE8+
- All modern browsers fully supported, no polyfills needed

### Accessibility

- Radio buttons have proper labels
- Color contrast meets WCAG AA in both themes (verify with rodney test)
- `prefers-color-scheme` media query respects OS accessibility preferences

### Private/Incognito Browsing

localStorage works in private browsing but clears when session ends. This is expected behavior — theme preference doesn't persist across private sessions, which is appropriate for privacy.

### Multiple Browser Tabs

Each tab reads from the same localStorage. Changing theme in one tab does NOT automatically update other tabs (this is acceptable for v1). Future enhancement could add `storage` event listener for cross-tab sync.

## Future Enhancements (Out of Scope)

1. **Quick toggle in mastheader** — Sun/moon icon for rapid switching without visiting Settings
2. **Cross-tab synchronization** — Listen for `storage` event to update theme when changed in another tab
3. **Per-page themes** — Different themes for different pages (probably overkill for this project)
4. **Theme transition animation** — Smooth fade between themes using CSS transitions
5. **High contrast mode** — Fourth option for accessibility (beyond standard dark mode)

## Summary

**Core Feature:** Tri-state theme preference (Light/Dark/System) stored in localStorage

**Key Implementation Points:**
- Settings page gets "Appearance" section with radio buttons at the top
- Instant theme changes via JavaScript (no page refresh)
- No first-paint flash thanks to inline script in `<head>`
- OS preference detection via `matchMedia` with automatic switching
- ~100 class changes across 6 view files (add `dark:` variants)
- Chart.js dynamic colors for analytics charts
- Rodney-based scripted tests for regression testing

**Estimated Effort:** 3-4 hours of focused work

**Success Criteria:**
- All admin pages render correctly in both light and dark themes
- Theme changes instantly when user selects radio button
- Theme preference persists across page navigation and browser sessions
- "System" mode correctly follows OS appearance preference
- Chart colors update appropriately with theme changes
- All scripted rodney tests pass
