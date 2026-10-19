#!/bin/bash
set -e

# Permissões para desenvolvimento local (volume montado pertence ao usuário do host)
chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true
mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache
[ -f database/database.sqlite ] || touch database/database.sqlite
chmod -f a+rwX database/database.sqlite 2>/dev/null || true

# Link público de storage (logotipos e imagens de prêmios)
[ -e public/storage ] || php artisan storage:link 2>/dev/null || true

exec "$@"
