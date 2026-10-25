# Architecture

## Why this exists

[`boilerplate-symfony-ddd`](../../boilerplate-symfony-ddd) is a single Symfony DDD/CQRS monolith with clean
bounded contexts (`Domain/Auth`, `Domain/Product`, `Domain/Customer`, `Domain/Order`, `Domain/Notification`,
...) already living side by side behind `command.bus`/`query.bus`. This repo asks: do those boundaries
actually hold up as real network boundaries? It splits the monolith into independently deployable services,
orchestrated with Docker Compose, communicating over RabbitMQ (async) and REST (sync, Phase 2 only).

This is a **functional reference**, not a production system. Trade-offs made for local-dev simplicity are
called out explicitly below rather than hidden.

## Target topology

```
                         ┌─────────────────┐
   client (curl/Postman) │   API Gateway    │  nginx, path-based routing, single origin
                    ────▶│  (one port)      │  so cookies/CSRF work across all services
                         └────────┬─────────┘
              ┌───────────┬───────┼───────┬──────────────┐
              ▼           ▼       ▼       ▼               ▼
        ┌──────────┐┌──────────┐┌──────────┐┌──────────┐┌──────────────┐
        │ identity ││ catalog  ││ customer ││  order   ││ notification │
        │ (owns    ││ (Phase2) ││ (Phase2) ││ (Phase2) ││              │
        │  JWT     ││          ││          ││          ││              │
        │  keypair)││          ││          ││          ││              │
        └────┬─────┘└────┬─────┘└────┬─────┘└────┬─────┘└──────┬───────┘
             │            │            │            │              │
             ▼            ▼            ▼            ▼              ▼
         identity_db  catalog_db  customer_db   order_db    notification_db
                (one Postgres container, one database per service)

        order ──sync REST──▶ catalog   (Phase 2: stock validate/reserve)
        order ──sync REST──▶ customer  (Phase 2: customer existence check)
        identity/catalog/order ──async (RabbitMQ, topic exchange `domain_events`)──▶ notification
```

Phase 1 (built): `identity`, `notification`, `gateway`, `rabbitmq`, `postgres`.
Phase 2 (designed, not built): `catalog`, `customer`, `order`, plus Order's sync calls to Catalog/Customer.

## Bounded-context ownership

Each service owns its bounded context's `Domain/` + `Application/` layers wholesale, copied and trimmed from
the monolith (deleted contexts it doesn't own, deleted their handlers/mappings/config). Nothing is shared
between services except:

- **`packages/shared-kernel`** — shared technical adapters and a deliberately small set of shared domain
  primitives. `SharedKernel/Infrastructure` contains CQRS bus wrappers (`MessengerCommandBus`/
  `MessengerQueryBus`), HTTP/security listeners and stateless-JWT helpers; `SharedKernel/Application` contains
  bus ports; `SharedKernel/Domain` contains generic domain-event plumbing, shared exceptions, and the `Money`/
  `Currency` value objects. It contains no service-specific entities or repositories. Keep this package small:
  any business concept that belongs to one bounded context must stay in that service, not migrate here.
- **`packages/integration-events`** — the wire contracts published to/consumed from RabbitMQ (e.g.
  `IntegrationEvents\Identity\UserCreated`). Deliberately tiny, scalars-only DTOs, versioned by adding new
  classes rather than editing existing ones. This is the one intentional shared dependency between publisher
  and consumer — the alternative (fully schema-less JSON + per-service local mapping) is more realistic for
  a genuinely polyglot system, but this reference is Symfony-everywhere by design (see the root plan), so a
  small shared contracts package is the pragmatic choice.

## Sync vs. async, and why

- **Async (RabbitMQ)** is used for what was already a decoupled side effect in the monolith — e.g. Identity
  creating a user triggers a "new user registered" notification. The monolith did this in-process via
  `EventDispatcherInterface`; here it crosses a real process boundary. Identity's
  `PublishUserCreatedIntegrationEvent` (an `EventSubscriberInterface`, listening to the *domain* event
  `App\Domain\Auth\Event\UserCreated`) translates it into the *integration* event
  `IntegrationEvents\Identity\UserCreated` and dispatches it on a dedicated `event.bus`. Notification's
  `OnUserCreatedIntegrationEvent` consumes it and dispatches a local `CreateNotificationCommand` — same shape
  as the monolith's original in-process handler, just running in a different container.
- **Transactional outbox, not a direct broker publish**: `event.bus` routes every integration event to each
  publishing service's own `async` (Doctrine) transport, not straight to `amqp`. That transport shares the
  entity manager's DB connection, so the outbox row commits atomically with whatever domain write raised the
  event — either both happen or neither does. A dedicated per-event relay handler (Identity's
  `RelayUserCreatedToBroker`), consumed from the outbox by the `identity-worker`, is what actually forwards
  the event to the real `domain_events` exchange with its `AmqpStamp` routing key. If RabbitMQ is briefly
  down, only the relay step retries (per `async`'s retry strategy) — a broker outage can never lose the event
  or block the request that raised it, the way publishing straight to `amqp` at dispatch time would.
- **Dead-letter transport**: every service configures `failure_transport: failed` (`doctrine://default?queue_name=failed`).
  A message that exhausts its retries — on the outbox relay, or on Notification's `amqp` consumption — lands
  there instead of being silently dropped. Inspect/replay with `messenger:failed:show` / `messenger:failed:retry`.
- **Idempotent consumption**: RabbitMQ is at-least-once, so `IntegrationEvents\Identity\UserCreated` carries an
  `eventId` generated once at the source. `CreateNotificationHandler` checks it against Notification's
  `processed_integration_events` ledger (a dedup table, not a domain concept of the Notification bounded
  context) before creating anything, and records it in the same flush as the `Notification` insert — a
  redelivered event is a no-op, not a duplicate notification.
- **Why a separate `event.bus`** instead of routing through `command.bus`: Messenger's consume worker
  resolves the bus a message came from via the `BusNameStamp` recorded when it was first dispatched. Every
  service that might touch an integration event (publish or consume) declares an identically-named
  `event.bus`, purely so that stamp resolves on both sides — Notification never dispatches onto its own
  `event.bus` directly, it only needs the name to exist for the consume worker to find `event.bus`-registered
  handlers. `RelayUserCreatedToBroker` sends straight to the `amqp` transport (bypassing bus middleware, which
  is what would normally add this stamp), so it stamps `BusNameStamp('event.bus')` on the outgoing envelope
  itself — without it, Notification's consumer falls back to its default bus and finds no handler at all.
- **Sync (REST)**, Phase 2 only: reserved for the one place the monolith has real cross-aggregate business
  logic, not just a side effect — `PlaceOrderHandler`/`AddOrderLineHandler` synchronously reserving stock and
  validating the customer. Becomes an HTTP call to Catalog/Customer over the internal Docker network (not
  exposed via the gateway). No service-to-service auth for v1 — the internal network is trusted; a real
  deployment would add mTLS or a service mesh here.

## Stateless auth, and how the `id` claim solves a real gap

Identity is the only service with a `User` table and the only one holding the JWT private key (RS256).
Every other service verifies with `public_key` only (`lexik_jwt_authentication.yaml` has no `secret_key`),
authenticated via a custom `AbstractAuthenticator` (`StatelessJwtAuthenticator`) that never queries a
database — no `UserProviderInterface`, no `providers.entity` block in `security.yaml`.

This alone isn't enough: Notification needs to know *which* user is asking (to scope
`GET /api/notifications` to their own + broadcast notifications), but it has no `User` table to look an id
up in. The fix: `JwtClaimsListener` (shared-kernel) duck-types `getId()` off whatever object issued the
token at `JWT_CREATED` time and embeds it as an `id` claim, in addition to the `email`/`roles` Lexik already
includes by default. `JwtUser::getId()` on the verifying side just reads that claim back. This can only be
forged by whoever controls Identity's private key — the same trust boundary as the rest of the token — so
it doesn't introduce a new spoofing surface, but it's an explicit design decision worth flagging: **every
service that needs "the current user's id" gets it from the token, never from a local join.**

## Documented local-dev simplifications

These are deliberate shortcuts appropriate for a reference/demo, called out so they're not mistaken for
production guidance:

- **One Postgres container, one database per service** (`infra/postgres/init-multiple-dbs.sh`), instead of
  one instance per service. Keeps `docker compose up` light. Still enforces the boundary structurally — no
  service's Doctrine connection can see another's schema — just not the failure-domain isolation a separate
  instance/cluster per service would give you.
- **JWT public key distributed via a shared Docker volume** (`jwt_public_key`), written by Identity's
  entrypoint and read by every other service's entrypoint before boot. Stands in for what a JWKS endpoint
  (`/.well-known/jwks.json`) would do in production, where services fetch and cache the key over HTTP instead
  of relying on a shared filesystem.
- **RabbitMQ and Postgres use default/weak credentials** (`guest`/`guest`, `postgres`/`postgres`) and the
  RabbitMQ management UI is published on the host. Fine on an isolated Docker network for local dev; would
  need real credentials, no published management port, and TLS in any shared or production environment.
- **No service-to-service authentication** for the Phase 2 sync REST calls (Order → Catalog/Customer) — the
  Docker network boundary is the only protection. A production system would add mTLS, signed service
  tokens, or a service mesh.
- **Migrations are auto-generated on first boot** (`doctrine:migrations:diff` runs automatically if a
  service's `migrations/` directory is empty) rather than hand-authored and committed up front, since each
  service's entity set was trimmed down from the monolith. Convenient for standing this up quickly; a real
  project would commit reviewed migrations instead of trusting an auto-diff.

## Architecture checks and current compromises

Run `make deptrac` to verify dependencies between each service's `Domain`, `Application`, and
`Infrastructure` layers, and between those layers and the shared-kernel / integration-event packages. The
Domain may use only the shared-kernel Domain primitives; Application depends on Domain, application ports,
and integration contracts; Infrastructure is the framework-facing adapter layer. These rules check source
packages included in the Deptrac configuration; they do not prove that every framework type is absent from
Domain (for example, Identity's User model currently implements Symfony security interfaces and uses Doctrine
collections). Treat that as a known DDD compromise to reduce deliberately over time, not as a clean-architecture
ideal.

Notification's `ProcessedIntegrationEvent` is an Infrastructure persistence record, not a Notification
domain concept. Its repository is exposed to the application through an application port, keeping Doctrine
out of the handler. This is the pattern to follow for technical persistence concerns that support a use case
without representing business state.

`make quality` runs tests, PHPStan, formatting, Deptrac, and Composer security audits. The same command runs
in GitHub Actions on pushes to `main` and pull requests; CI starts the local Compose stack first so functional
and integration tests can use the service's PostgreSQL test database. RabbitMQ is available in the stack for
message-flow checks; tests that require a live broker should be explicit about that dependency.
