#!/bin/bash
#
# SessionStart hook for Claude Code on the web.
#
# Provisions everything needed to run the PHPUnit suite and PHPCS linter of the
# CommonsBooking plugin inside a remote session:
#   1. PHP/Composer dependencies (incl. the strauss "vendor-prefixed/" build)
#   2. A local MariaDB server + the WordPress test database
#   3. The WordPress core + PHPUnit test library, fetched from GitHub mirrors
#      (wordpress.org and the WordPress SVN are blocked by the egress policy)
#
# The script is idempotent: the container filesystem is cached after the hook
# completes, so the heavy install steps are skipped on subsequent sessions;
# only the database server (a process, not filesystem state) is restarted.
#
# It runs only in the remote environment. Locally it exits immediately.

set -euo pipefail

# Only provision in Claude Code on the web.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$PROJECT_DIR"

# --- configuration ----------------------------------------------------------
WP_BRANCH="6.6"
WP_CACHE_DIR="$HOME/.cache/cb-wp"
export WP_CORE_DIR="$WP_CACHE_DIR/wordpress"
export WP_TESTS_DIR="$WP_CACHE_DIR/wordpress-tests-lib"
STRAUSS_VERSION="0.26.5"
DB_NAME="wordpress_test"
DB_USER="wp"
DB_PASS="wp"
# Composer plugins (phpcs installer) need this when running as root.
export COMPOSER_ALLOW_SUPERUSER=1
export DEBIAN_FRONTEND=noninteractive

log() { echo "[session-start] $*"; }

# --- 1. PHP / Composer dependencies -----------------------------------------
# The one package that cannot be installed unauthenticated is phpstan/phpstan
# (a dist-only phar whose GitHub zipball is rate-limited to HTTP 403 without a
# token). It — and phpbench — are static-analysis/benchmark tools not needed to
# run the tests or the PHPCS linter, so we install from a trimmed manifest via
# the COMPOSER env var, leaving the real composer.json / composer.lock
# untouched. Provide a GITHUB_TOKEN if you also want the full `composer install`.
if [ ! -f vendor-prefixed/autoload.php ] || [ ! -f vendor/autoload.php ]; then
  log "Installing PHP dependencies (trimmed dev set)…"
  composer config -g use-github-api false >/dev/null 2>&1 || true

  php -r '
    $c = json_decode(file_get_contents("composer.json"), true);
    foreach (["phpstan/phpstan","szepeviktor/phpstan-wordpress","phpstan/extension-installer","phpbench/phpbench"] as $p) {
      unset($c["require-dev"][$p]);
    }
    $c["scripts"] = new stdClass();
    file_put_contents(".composer-test.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  '

  COMPOSER=.composer-test.json composer update \
    --no-scripts --ignore-platform-reqs --prefer-source --no-interaction

  # Generate the strauss-prefixed dependencies (vendor-prefixed/), which the
  # plugin bootstrap requires. Strauss reads extra.strauss from the real
  # composer.json and scans the installed vendor/.
  [ -f bin/strauss.phar ] || curl -sSL -o bin/strauss.phar \
    "https://github.com/BrianHenryIE/strauss/releases/download/${STRAUSS_VERSION}/strauss.phar"
  php bin/strauss.phar
else
  log "PHP dependencies already present — skipping."
fi

# --- 2. MariaDB -------------------------------------------------------------
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
  log "Installing MariaDB…"
  apt-get update -qq
  apt-get install -y -qq mariadb-server mariadb-client
fi

mkdir -p /var/lib/mysql /run/mysqld
chown -R mysql:mysql /var/lib/mysql /run/mysqld 2>/dev/null || true

if [ ! -d /var/lib/mysql/mysql ]; then
  log "Initializing MariaDB data directory…"
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql \
    --auth-root-authentication-method=normal >/dev/null 2>&1
fi

# Start the server if it is not already accepting connections (processes are
# not part of the cached filesystem, so this runs on every session start).
if ! mysqladmin ping >/dev/null 2>&1; then
  log "Starting MariaDB…"
  mariadbd-safe --user=mysql >/tmp/mariadb.log 2>&1 &
  for _ in $(seq 1 30); do
    mysqladmin ping >/dev/null 2>&1 && break
    sleep 1
  done
fi

log "Ensuring test database exists…"
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME};
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# --- 3. WordPress core + PHPUnit test library (from GitHub mirrors) ----------
if [ ! -f "$WP_CORE_DIR/wp-load.php" ]; then
  log "Fetching WordPress core (${WP_BRANCH})…"
  rm -rf "$WP_CORE_DIR"
  git clone --quiet --depth 1 --branch "$WP_BRANCH" \
    https://github.com/WordPress/WordPress.git "$WP_CORE_DIR"
fi

if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
  log "Fetching WordPress test library (${WP_BRANCH})…"
  rm -rf /tmp/wp-develop "$WP_TESTS_DIR"
  git clone --quiet --depth 1 --branch "$WP_BRANCH" \
    --filter=blob:none --sparse \
    https://github.com/WordPress/wordpress-develop.git /tmp/wp-develop
  (
    cd /tmp/wp-develop
    git sparse-checkout set --no-cone \
      tests/phpunit/includes tests/phpunit/data wp-tests-config-sample.php >/dev/null 2>&1
    git checkout --quiet HEAD
  )
  mkdir -p "$WP_TESTS_DIR"
  cp -r /tmp/wp-develop/tests/phpunit/includes "$WP_TESTS_DIR/includes"
  cp -r /tmp/wp-develop/tests/phpunit/data "$WP_TESTS_DIR/data"
  cp /tmp/wp-develop/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"

  cfg="$WP_TESTS_DIR/wp-tests-config.php"
  sed -i "s#define( 'ABSPATH', dirname( __FILE__ ) . '/src/' );#define( 'ABSPATH', '${WP_CORE_DIR}/' );#" "$cfg"
  sed -i "s/youremptytestdbnamehere/${DB_NAME}/" "$cfg"
  sed -i "s/yourusernamehere/${DB_USER}/" "$cfg"
  sed -i "s/yourpasswordhere/${DB_PASS}/" "$cfg"
fi

# --- persist env for the agent session --------------------------------------
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
  {
    echo "export WP_TESTS_DIR=\"$WP_TESTS_DIR\""
    echo "export WP_CORE_DIR=\"$WP_CORE_DIR\""
    echo "export COMPOSER_ALLOW_SUPERUSER=1"
  } >> "$CLAUDE_ENV_FILE"
fi

log "Provisioning complete. Run tests with: vendor/bin/phpunit"
