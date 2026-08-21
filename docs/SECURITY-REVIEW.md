# Security & DDD review (2026-08-21)

An audit pass over the Phase 1 stack (`identity`, `notification`, `gateway`, `rabbitmq`, `postgres`),
covering the JWT/CSRF design, the newly-added API-doc/profiler routes, Docker secret handling, and a
second DDD-boundary sweep after concurrent edits from another session. Findings below; fixed items are
marked as such, accepted risks link to their write-up in [`ARCHITECTURE.md`](ARCHITECTURE.md).

## Fixed

- **Passwords in clear text were carried by domain events and queued emails.** `UserCreated` and
  `UserPasswordChanged` included the password, making it recoverable from the Doctrine Messenger table,
  email delivery infrastructure, backups, and exception traces. Events and mail templates now contain only
  the email address and send a notification, never a credential. A real production onboarding or reset flow
  should use a single-use, expiring token stored only as a hash.
- **Changing a user's access did not invalidate its refresh sessions.** Deactivating a user, changing their
  password, email, roles, direct permissions, or deleting them now revokes every refresh token before it can
  mint another access JWT. `RefreshTokenHandler` also rejects inactive users as defense in depth. Existing
  access JWTs remain valid only until their configured 15-minute expiration; immediate cross-service access
  token revocation needs a token version or deny-list design.
- **Development ports were reachable from the host network.** PostgreSQL, RabbitMQ management, and the
  gateway are now published only on `127.0.0.1`; Docker services continue to communicate on the internal
  Compose network. Change the bind address deliberately if testing from a separate host is required.

- **Gateway was missing security headers entirely.** `gateway/nginx.dev.conf` never got the monolith's
  `security-headers.dev.conf` (X-Frame-Options, CSP, X-Content-Type-Options, etc.) — dropped when the file
  was first written, never re-added. Restored: `gateway/security-headers.dev.conf` copied in, `Dockerfile.dev`
  bakes it into the image, `nginx.dev.conf` includes it at the server level so every route gets it. Verified
  live (`curl -sI .../api/health` now shows the headers).
- **`/_wdt` and `/_profiler` were exposed on the published gateway port with no restriction.** In
  `APP_ENV=dev` these leak full stack traces, SQL with bound parameter values, and request/response headers
  (including cookies) to anyone who can reach `:8090`. IP-allowlisting (the usual `allow 127.0.0.1; deny all`
  fix) doesn't hold up reliably through Docker's port-publish NAT, and this boilerplate is verified via curl,
  never a browser, so the toolbar has no real consumer here. Fixed by removing the routes from the gateway
  entirely rather than routing them insecurely — `nginx.dev.conf` now documents why. Verified: `/_profiler/`
  through the gateway now 404s.
- **Dead CSRF exemption for `/api/v1`.** `CsrfProtectionListener.php`'s exempt-path pattern still matched
  `/api/v1/...` (copied from the monolith), but that ApiPlatform surface was deliberately never ported to
  this boilerplate (see the scope decision in `ARCHITECTURE.md`) — no route exists under that path in either
  service, so the exemption was unreachable, not a live hole. Removed for clarity; the CSRF exemption is now
  exactly `/api/auth/(login|refresh)` and `/api/health`, matching what's actually public.

## Accepted risk, already documented

These aren't bugs — they're the local-dev trade-offs called out in `ARCHITECTURE.md`'s "Documented
local-dev simplifications" section, re-verified here rather than duplicated:

- RabbitMQ/Postgres default credentials (`guest`/`guest`, `postgres`/`postgres`), RabbitMQ's management UI
  published on the host.
- JWT public key distributed via a shared Docker volume instead of a JWKS endpoint.
- No service-to-service auth on the Phase 2 sync REST calls (Order → Catalog/Customer) — Docker network
  boundary only.
- Auto-generated migrations on first boot (`doctrine:migrations:diff`) instead of hand-authored, committed
  ones.

## Verified sound, no action needed

- **JWT trust chain**: `StatelessJwtAuthenticator` calls `JWTTokenManagerInterface::parse()`, which
  cryptographically verifies the RS256 signature before any claim (including the duck-typed `id` claim from
  `JwtClaimsListener`) is trusted. A token signed with any key other than Identity's own private key is
  rejected at the signature check, before `iss` or `id` are ever read — no spoofing path.
- **Docker image hygiene**: neither service's `Dockerfile.dev` ever `COPY`s app source, `.env`, or
  `config/jwt/*` into the image — everything lands via bind mounts (`./services/*:/app`) or the
  `jwt_public_key` named volume at container start. No secret is baked into an image layer.
- **DDD boundaries, second pass**: re-swept both services for leftover cross-context references
  (Product/Customer/Order/Dashboard in Identity; Auth/Product/Customer/Order in Notification) after the
  other session's edits — clean. `NelmioApiDocBundle` (added since Phase 1 was first built) works by
  route/DTO reflection, not Domain annotations — no `OA\*`/ApiResource attributes leaked into either
  service's `Domain/` layer. The `event.bus`/`BusNameStamp` RabbitMQ routing convention in both
  `messenger.php` files is untouched and consistent with the original design. Controllers stay thin
  (CQRS dispatch only, no business logic).
