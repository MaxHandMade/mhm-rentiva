#!/usr/bin/env bash
# Install WordPress Test Library inside Docker container.
# Usage: bash tests/bin/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]
#
# Root credentials (for DB creation) are read from env vars:
#   DB_ROOT_USER  (default: root)
#   DB_ROOT_PASS  (default: root)

set -e

DB_NAME="${1:-mhm_rentiva_tests}"
DB_USER="${2:-wp}"
DB_PASS="${3:-wp}"
DB_HOST="${4:-db}"
WP_VERSION="${5:-6.9.1}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/var/www/html}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASS="${DB_ROOT_PASS:-root}"

echo "==> Installing WordPress Test Library"
echo "    WP_VERSION  : $WP_VERSION"
echo "    WP_TESTS_DIR: $WP_TESTS_DIR"
echo "    DB_HOST     : $DB_HOST"
echo "    DB_NAME     : $DB_NAME"

# ── 1. Download test library ──────────────────────────────────────────────────
if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    mkdir -p "$WP_TESTS_DIR"
    echo "==> Checking out WP test includes (SVN)..."
    svn checkout --quiet \
        "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" \
        "$WP_TESTS_DIR/includes"
    echo "==> Checking out WP test data (SVN)..."
    svn checkout --quiet \
        "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/" \
        "$WP_TESTS_DIR/data"
    echo "==> Test library downloaded."
else
    echo "==> Test library already present, skipping download."
fi

# ── 2. Write wp-tests-config.php ─────────────────────────────────────────────
CONFIG_FILE="$WP_TESTS_DIR/wp-tests-config.php"
if [ ! -f "$CONFIG_FILE" ]; then
    echo "==> Writing wp-tests-config.php..."
    cat > "$CONFIG_FILE" <<PHP
<?php
define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
PHP
    echo "==> wp-tests-config.php written."
else
    echo "==> wp-tests-config.php already exists, skipping."
fi

# ── 3. Create test database via root ─────────────────────────────────────────
echo "==> Waiting for DB to be ready..."
until mysql -h"$DB_HOST" -u"$DB_ROOT_USER" -p"$DB_ROOT_PASS" --ssl=0 -e "SELECT 1" &>/dev/null; do
    echo "    DB not ready, retrying in 2s..."
    sleep 2
done

echo "==> Creating test database '$DB_NAME' and granting access to '$DB_USER'..."
mysql -h"$DB_HOST" -u"$DB_ROOT_USER" -p"$DB_ROOT_PASS" --ssl=0 <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
echo "==> Database ready."

echo ""
echo "✓ WordPress test environment is ready."
echo "  Run tests with: composer test"
echo "  or: WP_TESTS_DIR=$WP_TESTS_DIR php vendor/bin/phpunit --no-coverage"
