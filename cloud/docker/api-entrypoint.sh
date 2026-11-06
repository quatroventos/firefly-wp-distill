#!/bin/sh
set -e
cd /cloud/apps/api
php artisan migrate --force --no-interaction
exec "$@"
