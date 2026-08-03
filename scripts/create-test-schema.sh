#!/usr/bin/env bash
#
# create-test-schema.sh
#
# Clones the CURRENT structure (no data) of the dev database (elanregi_spice)
# into the dedicated test schema (elanregi_spice_test) so that PHPUnit
# integration tests can run in isolation and never mutate the dev database.
#
# This script is idempotent: it DROPs and re-CREATEs the TARGET (test) schema
# every run, then reloads the structure from the source. It NEVER touches the
# source (dev) database.
#
# Credentials are read from two repo-root env files:
#   .env.local       — source/dev credentials (elanregi_spice)
#   .env.test.local  — target/test credentials (elanregi_spice_test)
#
# After running this, load reference data with:
#   php tests/setup-test-database.php
#
set -euo pipefail

# Resolve repo root (this script lives in <repo>/scripts/).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Dev credentials: prefer .env.local (matches this bootstrap's own convention
# elsewhere in the project), fall back to .env (the file the running app
# actually loads per users/init.php) if .env.local doesn't exist.
SOURCE_ENV="${REPO_ROOT}/.env.local"
if [[ ! -f "${SOURCE_ENV}" && -f "${REPO_ROOT}/.env" ]]; then
    SOURCE_ENV="${REPO_ROOT}/.env"
fi
TARGET_ENV="${REPO_ROOT}/.env.test.local"

# MAMP MySQL 8 binaries (see docs/development/ENVIRONMENT.md).
MYSQL_BIN="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
MYSQLDUMP_BIN="/Applications/MAMP/Library/bin/mysql80/bin/mysqldump"

# --- Validate prerequisites --------------------------------------------------

if [[ ! -f "${SOURCE_ENV}" ]]; then
    echo "ERROR: source env file not found: ${SOURCE_ENV}" >&2
    echo "       Neither .env.local nor .env exists in the repo root — complete the" >&2
    echo "       setup steps in docs/development/ENVIRONMENT.md first." >&2
    exit 1
fi

if [[ ! -f "${TARGET_ENV}" ]]; then
    echo "ERROR: test env file not found: ${TARGET_ENV}" >&2
    echo "       Run: cp .env.test.local.sample .env.test.local, then fill in DB_PASS." >&2
    echo "       See docs/development/ENVIRONMENT.md for details." >&2
    exit 1
fi

# --- Parse credentials -------------------------------------------------------
# Env files are simple KEY=value lines with no quoting. Read a single key from
# a given file. Fails loudly on a missing/empty key rather than letting `set -e`
# kill the script silently — grep exits 1 on no match, and pipefail propagates
# that through `| head | cut` even though both of those succeed, so an
# unguarded call site would die with no output and no indication which key
# or file was the problem.
read_env() {
    local file="$1" key="$2" value
    value="$(grep -E "^${key}=" "${file}" | head -n1 | cut -d= -f2-)" || true
    if [[ -z "${value}" ]]; then
        echo "ERROR: ${key} not found (or empty) in ${file}" >&2
        exit 1
    fi
    printf '%s' "${value}"
}

SOURCE_DB_NAME="$(read_env "${SOURCE_ENV}" DB_NAME)"
SOURCE_DB_USER="$(read_env "${SOURCE_ENV}" DB_USER)"
SOURCE_DB_PASS="$(read_env "${SOURCE_ENV}" DB_PASS)"

TARGET_DB_NAME="$(read_env "${TARGET_ENV}" DB_NAME)"
TARGET_DB_USER="$(read_env "${TARGET_ENV}" DB_USER)"
TARGET_DB_PASS="$(read_env "${TARGET_ENV}" DB_PASS)"

# Host/port come from .env.local — both files describe the same local MAMP MySQL.
DB_HOST="$(read_env "${SOURCE_ENV}" DB_HOST)"
DB_PORT="$(read_env "${SOURCE_ENV}" DB_PORT)"

# DB_HOST may be in "host:port" form; strip any port so -h gets a bare host.
DB_HOST="${DB_HOST%%:*}"

# --- Critical safety guard ---------------------------------------------------
# This MUST run before any DROP/CREATE DATABASE. It is the only thing preventing
# this script from ever dropping the dev database if .env.test.local were ever
# misconfigured to point at elanregi_spice. Case-folded: MAMP's MySQL runs with
# lower_case_table_names=2 on macOS's case-insensitive filesystem, so a typo'd-case
# name would otherwise slip past a naive string comparison.
SOURCE_DB_NAME_LOWER="$(tr '[:upper:]' '[:lower:]' <<< "${SOURCE_DB_NAME}")"
TARGET_DB_NAME_LOWER="$(tr '[:upper:]' '[:lower:]' <<< "${TARGET_DB_NAME}")"
if [[ "${SOURCE_DB_NAME_LOWER}" == "${TARGET_DB_NAME_LOWER}" ]]; then
    echo "ERROR: source and target schema names are identical (${SOURCE_DB_NAME}) — refusing to run, this would target the dev database." >&2
    exit 1
fi

# --- Clone structure ---------------------------------------------------------

echo "Cloning structure of '${SOURCE_DB_NAME}' into '${TARGET_DB_NAME}' (no data)..."

DUMP_FILE="$(mktemp -t elanregistry_test_schema.XXXXXX.sql)"
trap 'rm -f "${DUMP_FILE}"' EXIT

echo "  → Dumping structure from source..."
MYSQL_PWD="${SOURCE_DB_PASS}" "${MYSQLDUMP_BIN}" \
    -h "${DB_HOST}" -P "${DB_PORT}" \
    -u "${SOURCE_DB_USER}" \
    --no-data --no-tablespaces \
    "${SOURCE_DB_NAME}" > "${DUMP_FILE}"

# Strip DEFINER clauses mysqldump bakes into both triggers (`/*!50017
# DEFINER=...*/`) and views (`/*!50013 DEFINER=... SQL SECURITY DEFINER */`).
# Without this, these objects stay owned by the SOURCE user, and using them
# under the TARGET user fails (e.g. "TRIGGER command denied") the moment a
# test touches them — omitting DEFINER makes MySQL default it to whichever
# user loads this file (TARGET_DB_USER), which is what we actually want.
sed -i.bak -E \
    -e 's/\/\*!50017 DEFINER=`[^`]*`@`[^`]*`\*\///g' \
    -e 's/\/\*!50013 DEFINER=`[^`]*`@`[^`]*` SQL SECURITY DEFINER \*\///g' \
    "${DUMP_FILE}"
rm -f "${DUMP_FILE}.bak"

echo "  → Recreating target schema '${TARGET_DB_NAME}'..."
MYSQL_PWD="${TARGET_DB_PASS}" "${MYSQL_BIN}" \
    -h "${DB_HOST}" -P "${DB_PORT}" \
    -u "${TARGET_DB_USER}" \
    -e "DROP DATABASE IF EXISTS \`${TARGET_DB_NAME}\`; CREATE DATABASE \`${TARGET_DB_NAME}\`;"

echo "  → Loading structure into target..."
MYSQL_PWD="${TARGET_DB_PASS}" "${MYSQL_BIN}" \
    -h "${DB_HOST}" -P "${DB_PORT}" \
    -u "${TARGET_DB_USER}" \
    "${TARGET_DB_NAME}" < "${DUMP_FILE}"

echo ""
echo "Done. Test schema '${TARGET_DB_NAME}' now mirrors the structure of '${SOURCE_DB_NAME}'."
echo "Next step: load reference data with:"
echo "  php tests/setup-test-database.php"
