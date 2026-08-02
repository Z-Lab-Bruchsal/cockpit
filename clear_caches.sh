#!/bin/bash
php artisan cache:clear
php artisan config:clear
php artisan event:clear
php artisan route:clear
php artisan schedule:clear-cache
php artisan filament:optimize-clear
php artisan optimize:clear
