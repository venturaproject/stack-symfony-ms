# boilerplate-symfony-microservices

A Symfony 8.1 DDD/CQRS monolith ([`boilerplate-symfony-ddd`](../boilerplate-symfony-ddd)) decomposed into
independently deployable services, orchestrated with Docker Compose. This is a **functional reference**,
not a production-scale system: the goal is to `docker compose up` and see real inter-service async
messaging (RabbitMQ) and stateless JWT verification actually working across separate processes.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full design writeup.

## Phase 1 (current)

- **`identity`** — Auth, Users, Roles, Permissions, ApiClients, Security Policy, TOTP, JWT issuance (owns the
  only private key in the system).
- **`notification`** — in-app notifications, populated by consuming integration events off RabbitMQ.
- **`gateway`** — nginx, single origin, path-based routing to each service.
- **`rabbitmq`** — topic exchange `domain_events`.
- **`postgres`** — one container, one database per service (`identity_db`, `notification_db`).

Phase 2 (`catalog`, `customer`, `order`) is designed but not yet built.

## Running it

```bash
docker compose -f compose.dev.yml up -d
```

First boot is slow (each service runs its own `composer install`, generates its JWT keypair or waits for
Identity's public key, and auto-generates its initial migration from current entity mappings). Everything
is reachable through the gateway on `http://localhost:8090`. RabbitMQ's management UI is on
`http://localhost:15672` (guest/guest).

Seeded login: `admin@admin.com` / `admin123`.

## API docs

Each service exposes interactive Swagger UI (OpenAPI, via `nelmio/api-doc-bundle`), served through the
gateway on the same origin as the API itself:

- Identity: `http://localhost:8090/api/doc` (raw spec at `/api/doc.json`)
- Notification: `http://localhost:8090/api/notifications/doc` (raw spec at `/api/notifications/doc.json`)

To call protected endpoints from the UI: `POST /api/auth/login` with the seeded credentials above, copy
`accessToken` from the response, then click **Authorize** and paste the raw token — no `Bearer ` prefix,
Swagger UI adds that itself. The same token works against both services' docs, since Notification verifies
it statelessly against Identity's public key (see [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)).

## Testing it

Each service has its own PHPUnit suite (Unit + Functional; an empty `Integration` suite is scaffolded for
when there's something worth testing at that level), run inside the container so it uses the same PHP/deps
as the app:

```bash
docker compose -f compose.dev.yml exec identity php bin/phpunit
docker compose -f compose.dev.yml exec notification php bin/phpunit
```

Functional tests hit a real, separate test database (`identity_db_test` / `notification_db_test`) through
`DAMADoctrineTestBundle`, which wraps each test in a transaction and rolls it back afterwards — nothing
written by a test persists. A fresh `docker compose -f compose.dev.yml down -v && up -d` creates and
migrates both test databases automatically alongside the dev ones. If you're upgrading an existing stack
that predates this, create and migrate them once by hand:

```bash
docker exec boilerplate-symfony-microservices-postgres-1 psql -U postgres -c "CREATE DATABASE identity_db_test;"
docker exec boilerplate-symfony-microservices-postgres-1 psql -U postgres -c "CREATE DATABASE notification_db_test;"
docker compose -f compose.dev.yml exec -e APP_ENV=test identity php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f compose.dev.yml exec -e APP_ENV=test notification php bin/console doctrine:migrations:migrate --no-interaction
```

## Static analysis & style

Both services have PHPStan (level 7) and PHP CS Fixer (`@Symfony` + `@Symfony:risky`) wired up as composer
scripts:

```bash
docker compose -f compose.dev.yml exec identity composer phpstan
docker compose -f compose.dev.yml exec identity composer cs-check   # dry-run, shows the diff
docker compose -f compose.dev.yml exec identity composer cs-fix     # applies it
```

(same three commands work against `notification`).

## Known local-dev quirks

- **RabbitMQ's very first container start can fail** with `eacces` reading `.erlang.cookie` — a known
  Docker Desktop for Mac issue with how anonymous volumes get populated from the image on first creation.
  Harmless and non-deterministic; `docker compose -f compose.dev.yml restart rabbitmq` fixes it.
- **The web debug toolbar and profiler (`/_wdt`, `/_profiler`) are not reachable through the gateway.**
  This is deliberate, not an oversight — see the comment in `gateway/nginx.dev.conf`: nginx has no reliable
  way to distinguish "the person running this stack" from "anyone who can reach the published gateway port,"
  so routing them through would hand out stack traces, SQL with bound parameters, and request/response
  headers (including cookies) to anyone on the network. Neither service's port (9000, FastCGI) is published
  to the host, so there's no direct route in as-is; if you need the toolbar during local debugging, add a
  temporary `ports:` mapping for that service in `compose.dev.yml` (or exec into a container on the same
  Docker network) rather than opening it up through the shared gateway.
