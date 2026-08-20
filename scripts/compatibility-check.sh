#!/bin/bash

# Shopware Deployment Helper Skill - Compatibility Check
# Usage: ./compatibility-check.sh /path/to/project
# Output: JSON with compatibility status and warnings/errors
#
# This script validates that a Shopware project meets minimum requirements
# for Deployment Helper skill installation and usage.

set -e

# Input: project root directory
PROJECT_ROOT="${1:-.}"
if [[ ! -d "$PROJECT_ROOT" ]]; then
  echo '{"compatible": false, "errors": ["Project directory not found: '"$PROJECT_ROOT"'"]}'
  exit 1
fi

# Initialize result
COMPATIBLE=true
ERRORS=()
WARNINGS=()
INFO=()

# ===== PHP Version =====
PHP_BIN=$(which php 2>/dev/null || echo "")
if [[ -z "$PHP_BIN" ]]; then
  COMPATIBLE=false
  ERRORS+=("PHP not found in PATH")
else
  PHP_VERSION=$("$PHP_BIN" -v | head -n1 | grep -oP '\d+\.\d+' | head -n1)
  if [[ -z "$PHP_VERSION" ]]; then
    COMPATIBLE=false
    ERRORS+=("Could not determine PHP version")
  else
    MAJOR_VERSION=$(echo "$PHP_VERSION" | cut -d. -f1)
    MINOR_VERSION=$(echo "$PHP_VERSION" | cut -d. -f2)
    if [[ $MAJOR_VERSION -lt 8 ]] || [[ ($MAJOR_VERSION -eq 8 && $MINOR_VERSION -lt 2) ]]; then
      COMPATIBLE=false
      ERRORS+=("PHP 8.2+ required, found $PHP_VERSION")
    else
      INFO+=("PHP $PHP_VERSION detected")
    fi
  fi
fi

# ===== Shopware Installation Detection =====
if [[ ! -f "$PROJECT_ROOT/composer.json" ]]; then
  COMPATIBLE=false
  ERRORS+=("composer.json not found - not a Shopware project")
else
  # Check if shopware/core or shopware/platform is in composer.json
  if grep -q '"shopware/core"\|"shopware/platform"' "$PROJECT_ROOT/composer.json"; then
    if grep -q '"shopware/core"\|"shopware/platform"' "$PROJECT_ROOT/composer.json" | grep -q '6\.[5-9]\|7\.'; then
      INFO+=("Shopware 6.5+ compatible version detected in composer.json")
    else
      WARNINGS+=("Shopware version in composer.json may be older than 6.5")
    fi
  else
    WARNINGS+=("Could not verify Shopware version - check composer.json manually")
  fi
fi

# ===== Composer Availability =====
COMPOSER_BIN=$(which composer 2>/dev/null || echo "")
if [[ -z "$COMPOSER_BIN" ]]; then
  # Check if composer.phar exists in project
  if [[ ! -f "$PROJECT_ROOT/composer.phar" ]]; then
    WARNINGS+=("Composer not found in PATH and composer.phar not found in project")
  else
    INFO+=("composer.phar found in project")
  fi
else
  INFO+=("Composer available")
fi

# ===== Database Configuration =====
if [[ -f "$PROJECT_ROOT/.env" ]] || [[ -f "$PROJECT_ROOT/.env.local" ]]; then
  if grep -q "DATABASE_URL" "$PROJECT_ROOT/.env" "$PROJECT_ROOT/.env.local" 2>/dev/null; then
    INFO+=("DATABASE_URL configured")
  else
    WARNINGS+=("DATABASE_URL not found in .env files")
  fi
else
  WARNINGS+=(".env file not found - ensure DATABASE_URL is set at deployment time")
fi

# ===== .shopware-project.yml Existence =====
if [[ -f "$PROJECT_ROOT/.shopware-project.yml" ]]; then
  INFO+=(".shopware-project.yml exists")
  if grep -q "deployment:" "$PROJECT_ROOT/.shopware-project.yml"; then
    INFO+=("Deployment configuration section found")
  else
    WARNINGS+=(".shopware-project.yml exists but no 'deployment' section - Deployment Helper config needed")
  fi
else
  INFO+=(".shopware-project.yml not found - will be needed for Deployment Helper configuration")
fi

# ===== Deployment Helper Already Installed =====
if [[ -f "$PROJECT_ROOT/composer.lock" ]] && grep -q '"shopware/deployment-helper"' "$PROJECT_ROOT/composer.lock"; then
  INFO+=("Deployment Helper already installed")
else
  INFO+=("Deployment Helper not yet installed - must be added via Composer")
fi

# ===== Output Result =====
# Build JSON output
RESULT="{"
RESULT+='"compatible": '$([[ "$COMPATIBLE" == true ]] && echo "true" || echo "false")',"

if [[ ${#ERRORS[@]} -gt 0 ]]; then
  RESULT+='"errors": ['
  for i in "${!ERRORS[@]}"; do
    RESULT+="\"${ERRORS[$i]}\""
    [[ $i -lt $((${#ERRORS[@]} - 1)) ]] && RESULT+=","
  done
  RESULT+="],"
else
  RESULT+='"errors": [],'
fi

if [[ ${#WARNINGS[@]} -gt 0 ]]; then
  RESULT+='"warnings": ['
  for i in "${!WARNINGS[@]}"; do
    RESULT+="\"${WARNINGS[$i]}\""
    [[ $i -lt $((${#WARNINGS[@]} - 1)) ]] && RESULT+=","
  done
  RESULT+="],"
else
  RESULT+='"warnings": [],'
fi

if [[ ${#INFO[@]} -gt 0 ]]; then
  RESULT+='"info": ['
  for i in "${!INFO[@]}"; do
    RESULT+="\"${INFO[$i]}\""
    [[ $i -lt $((${#INFO[@]} - 1)) ]] && RESULT+=","
  done
  RESULT+="]"
else
  RESULT+='"info": []'
fi

RESULT+="}"

echo "$RESULT"

# Exit code: 0 = compatible, 1 = incompatible
[[ "$COMPATIBLE" == true ]] && exit 0 || exit 1
