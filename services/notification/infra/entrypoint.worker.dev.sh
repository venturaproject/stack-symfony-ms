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

echo "Ensuring Messenger transports exist..."
php bin/console messenger:setup-transports

echo "Starting AMQP consumer..."
exec php bin/console messenger:consume amqp --time-limit=3600 --memory-limit=256M --no-debug
