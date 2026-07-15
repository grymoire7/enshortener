#!/usr/bin/env bash
# Shared helpers for scripts/manual_tests/*.sh.
# Source this file; it is not meant to be executed directly.

BASE_URL="http://localhost:8080"

# Parses --base-url URL out of "$@", setting BASE_URL. Any other arguments
# are left (in order) in the REMAINING_ARGS array for the caller to process.
parse_base_url() {
  REMAINING_ARGS=()
  while [ $# -gt 0 ]; do
    case "$1" in
      --base-url)
        BASE_URL="$2"
        shift 2
        ;;
      *)
        REMAINING_ARGS+=("$1")
        shift
        ;;
    esac
  done
}

start_rodney() {
  rodney start >/dev/null
  trap 'rodney stop >/dev/null' EXIT
}

# clear_theme_storage
#
# Clears theme from localStorage on the currently-open page.
clear_theme_storage() {
  rodney js "(function(){ localStorage.removeItem('theme'); return true; })()" >/dev/null
}

# get_theme
#
# Returns current theme value from localStorage.
get_theme() {
  rodney js "localStorage.getItem('theme')" 2>/dev/null || echo "null"
}

# is_dark_mode
#
# Returns "true" if dark mode is active, "false" otherwise.
is_dark_mode() {
  local result=$(rodney js "document.documentElement.classList.contains('dark')" 2>/dev/null)
  echo "$result"
}
