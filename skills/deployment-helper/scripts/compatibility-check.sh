#!/bin/bash
# Shopware Deployment Helper skill — compatibility check
#
# Usage:  compatibility-check.sh [project-root]      (defaults to ".")
# Output: JSON {"compatible":bool,"errors":[],"warnings":[],"info":[]} on stdout
# Exit:   0 = compatible, 1 = incompatible
#
# Policy: a requirement that cannot be positively confirmed is treated as
# incompatible ("unknown counts as incompatible", per shopware-cli#1337).
# The check is read-only and never modifies the project.

PROJECT_ROOT="${1:-.}"

errors=()
warnings=()
info=()

if [ ! -d "$PROJECT_ROOT" ]; then
  printf '{"compatible":false,"errors":["Project directory not found: %s"],"warnings":[],"info":[]}\n' "$PROJECT_ROOT"
  exit 1
fi

# ----- PHP >= 8.2 (also our JSON/JSON-parsing engine below) -----
php_bin="$(command -v php 2>/dev/null || true)"
if [ -z "$php_bin" ]; then
  # Without PHP we can neither run Deployment Helper nor reliably inspect the
  # project, so stop here with a hand-written JSON payload.
  printf '{"compatible":false,"errors":["PHP not found in PATH (PHP 8.2+ required)"],"warnings":[],"info":[]}\n'
  exit 1
fi

php_ver="$("$php_bin" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"
php_major="${php_ver%%.*}"
php_minor="${php_ver#*.}"
if [ -z "$php_ver" ] || [ -z "$php_major" ] || [ -z "$php_minor" ]; then
  errors+=("Could not determine PHP version (PHP 8.2+ required)")
elif [ "$php_major" -lt 8 ] || { [ "$php_major" -eq 8 ] && [ "$php_minor" -lt 2 ]; }; then
  errors+=("PHP 8.2+ required, found $php_ver")
else
  info+=("PHP $php_ver detected")
fi

# ----- Shopware >= 6.5 (unknown = incompatible) -----
# Prefer the exact installed version from composer.lock; fall back to the
# constraint declared in composer.json. Both are parsed with PHP's JSON reader
# rather than grep, so version constraints do not produce false results.
composer_json="$PROJECT_ROOT/composer.json"
composer_lock="$PROJECT_ROOT/composer.lock"

if [ ! -f "$composer_json" ]; then
  errors+=("composer.json not found — not a Shopware project")
else
  sw_ver="$("$php_bin" -r '
    $root = $argv[1];
    $ver = "";
    $lock = $root . "/composer.lock";
    if (is_file($lock)) {
      $d = json_decode((string) file_get_contents($lock), true);
      foreach (array_merge($d["packages"] ?? [], $d["packages-dev"] ?? []) as $p) {
        if (in_array($p["name"] ?? "", ["shopware/core", "shopware/platform"], true)) {
          $ver = ltrim((string) ($p["version"] ?? ""), "v");
          break;
        }
      }
    }
    if ($ver === "") {
      $d = json_decode((string) file_get_contents($root . "/composer.json"), true);
      $req = $d["require"] ?? [];
      $ver = (string) ($req["shopware/core"] ?? $req["shopware/platform"] ?? "");
    }
    echo $ver;
  ' "$PROJECT_ROOT" 2>/dev/null)"

  # First major.minor found in the string (handles "6.6.1.0", "~6.6.0", "^6.5", "6.5.* || 6.6.*").
  sw_mm="$(printf '%s' "$sw_ver" | tr -c '0-9.' ' ' | awk '{print $1}')"
  sw_major="${sw_mm%%.*}"
  sw_rest="${sw_mm#*.}"
  sw_minor="${sw_rest%%.*}"

  sw_ok=false
  case "$sw_major" in
    ''|*[!0-9]*) : ;;
    *)
      case "$sw_minor" in
        ''|*[!0-9]*) : ;;
        *)
          if [ "$sw_major" -gt 6 ] || { [ "$sw_major" -eq 6 ] && [ "$sw_minor" -ge 5 ]; }; then
            sw_ok=true
          fi
          ;;
      esac
      ;;
  esac

  if [ "$sw_ok" = true ]; then
    info+=("Shopware $sw_ver detected (>= 6.5)")
  elif [ -n "$sw_ver" ]; then
    errors+=("Shopware 6.5+ required, found $sw_ver")
  else
    errors+=("Could not determine a shopware/core >= 6.5 version from composer.lock or composer.json (unknown counts as incompatible)")
  fi
fi

# ----- Informational (never blocking) -----
if [ -n "$(command -v composer 2>/dev/null || true)" ] || [ -f "$PROJECT_ROOT/composer.phar" ]; then
  info+=("Composer available")
else
  warnings+=("Composer not found in PATH and no composer.phar in project")
fi

if grep -qs "DATABASE_URL" "$PROJECT_ROOT/.env" "$PROJECT_ROOT/.env.local" 2>/dev/null; then
  info+=("DATABASE_URL configured")
else
  warnings+=("DATABASE_URL not found in .env / .env.local — ensure it is set at deployment time")
fi

if [ -f "$PROJECT_ROOT/.shopware-project.yml" ]; then
  info+=(".shopware-project.yml exists")
  if grep -qs "deployment:" "$PROJECT_ROOT/.shopware-project.yml"; then
    info+=("deployment configuration section found")
  else
    warnings+=(".shopware-project.yml has no 'deployment' section — Deployment Helper config needed")
  fi
else
  info+=(".shopware-project.yml not found — will be needed for Deployment Helper configuration")
fi

if [ -f "$composer_lock" ] && grep -qs '"shopware/deployment-helper"' "$composer_lock"; then
  info+=("Deployment Helper already installed")
else
  info+=("Deployment Helper not yet installed — add it via Composer")
fi

# ----- Emit result (PHP encodes the JSON so messages are always escaped) -----
compatible=false
[ "${#errors[@]}" -eq 0 ] && compatible=true

join_nl() { [ "$#" -eq 0 ] && return 0; printf '%s\n' "$@"; }

COMPAT="$compatible" \
ERRS="$(join_nl "${errors[@]}")" \
WARNS="$(join_nl "${warnings[@]}")" \
INFOS="$(join_nl "${info[@]}")" \
"$php_bin" -r '
  $split = fn($s) => $s === "" ? [] : explode("\n", rtrim($s, "\n"));
  echo json_encode([
    "compatible" => getenv("COMPAT") === "true",
    "errors"     => $split(getenv("ERRS") ?: ""),
    "warnings"   => $split(getenv("WARNS") ?: ""),
    "info"       => $split(getenv("INFOS") ?: ""),
  ], JSON_UNESCAPED_SLASHES), "\n";
'

[ "$compatible" = true ] && exit 0 || exit 1
