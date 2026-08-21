# Symfony Microservices Boilerplate

Functional Symfony 8.1 reference for building DDD/CQRS microservices locally with Docker Compose. It provides independently running services, asynchronous integration events, stateless JWT authentication, a single API entrypoint, and an isolated database per service.

It is designed for local development and learning, not as a production deployment manifest. See [Architecture](docs/ARCHITECTURE.md) and the [security review](docs/SECURITY-REVIEW.md) for design decisions and known trade-offs.

## Included services

| Service | Responsibility |
| --- | --- |
| `gateway` | nginx API gateway and single browser origin |
| `identity` | Authentication, users, roles, permissions, API clients, TOTP, and JWT issuance |
| `notification` | User notifications consumed from RabbitMQ integration events |
| `postgres` | One database per service, plus separate test databases |
| `rabbitmq` | `domain_events` topic exchange and local management UI |

`identity` publishes `IntegrationEvents\Identity\UserCreated` events. `notification` consumes them and creates the user's welcome notification. Identity owns the JWT private key; Notification verifies access tokens using the shared public key only.

## Requirements

- Docker Desktop or Docker Engine with Docker Compose v2
- Available local ports: `8090`, `15672`, and `5440`

All published ports are bound to `127.0.0.1`, so the stack is not exposed to the local network by default.

## Start the stack

```bash
docker compose -f compose.dev.yml up -d --build
docker compose -f compose.dev.yml ps
```

On first start, the services install Composer dependencies, create or share their JWT keys, run migrations, initialize transports, and seed the development administrator. Wait until `identity` and `notification` report `healthy`.

| Resource | URL |
| --- | --- |
| API gateway | http://localhost:8090 |
| Identity OpenAPI | http://localhost:8090/api/doc |
| Notification OpenAPI | http://localhost:8090/api/notifications/doc |
| RabbitMQ management | http://localhost:15672 |
| PostgreSQL | `127.0.0.1:5440` |

Development credentials:

- Administrator: `admin@admin.com` / `admin123`
- RabbitMQ: `guest` / `guest`
- PostgreSQL: `postgres` / `postgres`

These credentials are intentionally local-only. Never reuse them outside this environment.

## Verify the API

```bash
curl http://localhost:8090/api/health

curl -X POST http://localhost:8090/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@admin.com","password":"admin123"}'
```

The login response sets HTTP-only access and refresh cookies and includes an access token for API clients and Swagger UI. Protected requests can send it with `Authorization: Bearer <token>`.

## Tests and quality checks

Run checks inside their matching service container to use the same PHP version and extensions as the application:

```bash
docker compose -f compose.dev.yml exec identity php bin/phpunit
docker compose -f compose.dev.yml exec notification php bin/phpunit

docker compose -f compose.dev.yml exec identity composer phpstan
docker compose -f compose.dev.yml exec notification composer phpstan
```

Functional tests use dedicated test databases and are rolled back by `DAMADoctrineTestBundle`.

## Useful commands

```bash
# Follow application logs
docker compose -f compose.dev.yml logs -f identity notification

# Stop containers while retaining database data
docker compose -f compose.dev.yml down

# Reset all local state, including databases and generated JWT keys
docker compose -f compose.dev.yml down -v
```

## Security and production notes

- JWT keys, Composer dependencies, and runtime caches are excluded from Git.
- The gateway applies security headers and does not route the Symfony profiler or debug toolbar.
- Refresh sessions are revoked after sensitive user access changes.
- This Compose file deliberately uses development credentials, local volumes, a shared JWT public-key volume, and a single PostgreSQL container. Production requires managed secrets, TLS, reviewed migrations, independent data stores or failure domains, key distribution through JWKS, and service-to-service authentication.
