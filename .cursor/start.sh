#!/usr/bin/env bash
set -uo pipefail

sudo pg_ctlcluster 17 main start || true
sudo redis-server /etc/redis/redis.conf --daemonize yes || true

cd "$(dirname "$0")/.."

php artisan serve --host=0.0.0.0 --port=8000 &
npm run dev &

wait
