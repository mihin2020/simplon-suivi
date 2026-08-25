#!/usr/bin/env sh
set -e
php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600
