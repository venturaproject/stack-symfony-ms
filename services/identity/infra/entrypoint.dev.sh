#!/bin/sh
set -e

echo "Waiting for PostgreSQL..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USER:-postgres}" -d "${DB_NAME:-identity_db}" 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL ready."

if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -f "config/jwt/private.pem" ]; then
    echo "Generating JWT keypair (Identity is the only service holding the private key)..."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

# Every other service verifies tokens with this public key alone (see
# StatelessJwtAuthenticator) — copied out to a shared volume other services
# mount read-only. A stand-in for what a JWKS endpoint would do in production.
mkdir -p /shared-jwt
cp config/jwt/public.pem /shared-jwt/public.pem

if [ -z "$(ls -A migrations/*.php 2>/dev/null)" ]; then
    echo "No migrations found, generating the initial one from current entity mappings..."
    php bin/console doctrine:migrations:diff --no-interaction
fi

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Setting up Messenger transports (declares the domain_events exchange)..."
php bin/console messenger:setup-transports

echo "Seeding initial data..."
php bin/console app:seed

exec php-fpm
