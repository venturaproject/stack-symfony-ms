#!/bin/sh
# Runs once, on the postgres container's first-ever startup (official image
# convention: anything in /docker-entrypoint-initdb.d/ only fires against an
# empty data directory). One Postgres container, one database per service —
# a local-dev simplification; production would split these onto separate
# instances/clusters so no service could technically reach another's schema.
set -eu

IFS=','
for db in $POSTGRES_MULTIPLE_DATABASES; do
    unset IFS
    echo "Creating database '$db'..."
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<-EOSQL
        CREATE DATABASE "$db";
EOSQL
done
