# Production-readiness checklist

This boilerplate is a local-development and architecture reference. Before using it as the starting point for
a real deployment, explicitly address the following items with the requirements of that environment.

## Security and operations

- [ ] Keep PHP on a supported, patched release. The development baseline is pinned to PHP 8.5.12, which fixes
  the critical PHP RCEs CVE-2026-109618 and CVE-2026-109619; rebuild with `--pull` after security releases.
- [ ] Replace all development credentials with managed secrets; rotate JWT keys and define a key-rotation
  procedure.
- [ ] Replace shared-volume public-key distribution with a JWKS endpoint or managed key distribution, including
  cache expiry and rotation behavior.
- [ ] Add service-to-service authentication and authorization (for example, mTLS or short-lived signed tokens)
  before adding synchronous inter-service calls.
- [ ] Restrict management/admin endpoints, network ingress, database access, and broker access; enable TLS.
- [ ] Define access-token revocation requirements, rate limits, audit retention, and PII retention/deletion.
- [ ] Add structured logs, metrics, traces, alerting, health/readiness probes, and runbooks for failed messages.

## Data and messaging

- [ ] Use an isolated database or explicitly accepted failure domain for each service; keep credentials and
  migrations independently managed.
- [ ] Commit reviewed, forward-only database migrations. Do not rely on boot-time schema diffing.
- [ ] Define integration-event ownership, compatibility rules, schema evolution, and consumer rollout order.
- [ ] Test outbox relay retries, duplicate delivery, poison messages, failed-transport replay, and broker outage
  recovery; set retention and alerting for outbox/failed queues.
- [ ] Document eventual consistency and user-visible behavior when downstream services are unavailable.

## Delivery and verification

- [ ] Run the CI quality workflow against the exact production build images and supported PHP/extensions.
- [ ] Add dependency update automation and review Composer audit results on every dependency update.
- [ ] Build immutable production images, scan images/dependencies, sign artifacts, and define rollback procedures.
- [ ] Load-test critical paths and verify backup/restore and disaster-recovery objectives.

This list is a starting point, not a certification or substitute for a threat model and operational review.
