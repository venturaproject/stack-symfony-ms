#!/bin/sh
set -e

echo "Waiting for PostgreSQL..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USER:-postgres}" -d "${DB_NAME:-notification_db}" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL ready."

if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

echo "Waiting for Identity's public key..."
until [ -f "/shared-jwt/public.pem" ]; do
    sleep 1
done
mkdir -p config/jwt
cp /shared-jwt/public.pem config/jwt/public.pem
echo "Public key installed."

if [ -z "$(ls -A migrations/*.php 2>/dev/null)" ]; then
    echo "No migrations found, generating the initial one from current entity mappings..."
    php bin/console doctrine:migrations:diff --no-interaction
fi

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Setting up Messenger transports (declares the domain_events exchange and this service's queue)..."
php bin/console messenger:setup-transports

exec php-fpm
