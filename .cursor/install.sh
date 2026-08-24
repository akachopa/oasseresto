#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

PHP_VERSION=8.5
PG_VERSION=17

if ! command -v php >/dev/null 2>&1; then
    sudo -E apt-get update -qq
    sudo -E apt-get install -y -qq software-properties-common ca-certificates curl gnupg lsb-release unzip
    sudo -E add-apt-repository -y ppa:ondrej/php

    sudo install -d /usr/share/postgresql-common/pgdg
    sudo curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
        https://www.postgresql.org/media/keys/ACCC4CF8.asc
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
        | sudo tee /etc/apt/sources.list.d/pgdg.list >/dev/null

    sudo -E apt-get update -qq
    sudo -E apt-get install -y -qq \
        "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" "php${PHP_VERSION}-pgsql" \
        "php${PHP_VERSION}-redis" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
        "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-sqlite3" \
        "postgresql-${PG_VERSION}" "postgresql-client-${PG_VERSION}" redis-server
fi

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

sudo pg_ctlcluster "${PG_VERSION}" main start || true
sudo redis-server /etc/redis/redis.conf --daemonize yes || true

sudo -u postgres psql -v ON_ERROR_STOP=1 <<'SQL'
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'oasse') THEN
    CREATE ROLE oasse LOGIN PASSWORD 'oasse' SUPERUSER;
  END IF;
END $$;
SQL
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='oasse'" | grep -q 1 \
    || sudo -u postgres createdb -O oasse oasse
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='oasse_test'" | grep -q 1 \
    || sudo -u postgres createdb -O oasse oasse_test

cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env

composer install --no-interaction --prefer-dist
npm install

php artisan key:generate --force
php artisan migrate --force --seed
npm run build
