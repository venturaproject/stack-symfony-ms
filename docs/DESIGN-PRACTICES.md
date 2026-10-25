# Design practices: SOLID, layers and DTOs

This guide adopts the useful conventions in `ARQUITECTURA_SOLID_Y_DTOS.md` from the companion
`boilerplate-symfony-ddd` project, adapted to the services and boundaries that exist here. It distinguishes
what is implemented from what remains a recommendation; a folder name or interface alone is not proof that
the runtime behavior is correct.

## Dependency direction

```text
Infrastructure  ->  Application  ->  Domain
```

- **Domain** owns business entities, value objects, domain events, and business repository contracts.
- **Application** owns commands, queries, use cases, response projections, and ports needed by use cases.
- **Infrastructure** owns HTTP, Doctrine mappings/adapters, Messenger wiring, external services, and port
  implementations.
- `packages/integration-events` owns versioned wire contracts. A service translates between those contracts
  and its own commands/domain events; it must not share its entities with another service.
- `packages/shared-kernel` is restricted to generic shared primitives and framework plumbing. Do not move a
  bounded-context business concept there to avoid designing a service contract.

Use `make deptrac` to check the configured internal layer/package dependencies. It currently reports
uncovered external symbols (PHP, Symfony and Doctrine types) because those vendor APIs are not modeled as
architecture layers; a clean Deptrac exit means no configured layer violations, not that every external
dependency has been reviewed. Domain use of framework types is a known compromise; tighten it deliberately,
especially when adding new domain code.

## DTO conventions

DTOs transport data; they do not enforce business rules, access persistence, or serialize themselves into an
HTTP response.

### HTTP input DTOs — Infrastructure

- Put request-specific input models under `Infrastructure/Http/Input` when they make validation and
  deserialization clearer.
- They may be mutable only when the HTTP serializer needs to hydrate them. Do not pass `Request`, HTTP input
  models, or Symfony validation types into Application.
- Controllers map validated input and route identity into an explicit command/query using named arguments.
  A route ID must not be overridden by a body field.
- Validate transport shape at the edge; enforce business invariants in Domain or Application, where they also
  apply to non-HTTP callers.

### Commands and queries — Application

- Model intent, not a copy of the HTTP request. Prefer `final readonly` classes with explicit scalar/value
  object fields.
- Keep them independent of HTTP, Doctrine, serializer, and framework request objects.
- Include an idempotency/correlation key only when the use case requires it; keep wire-event IDs explicit
  when mapping integration events.

### Response DTOs — Application

- Return stable, immutable projections; never expose Doctrine entities or aggregates as API payloads.
- Map explicitly from a domain entity/read model (for example, `Response::from($entity)`) and include only
  fields intended for that consumer. Avoid serializing arbitrary entity graphs or leaking internal IDs,
  credentials, associations, or persistence metadata.
- Keep HTTP status codes, headers, cookies, and JSON response construction in the Infrastructure controller.
- Use distinct projections for distinct consumers when their data contract or authorization differs; avoid a
  “universal DTO” with many optional fields.

### Tests and changes

When adding a DTO, test its mapping/contract where it matters: safe fields, nullability, serialization shape,
and compatibility for integration events. Do not add behavior to a DTO just to make mapping convenient; place
business decisions in a use case or domain object.

## SOLID checks to apply in reviews

- **Single responsibility:** handlers coordinate use cases; entities protect invariants; adapters translate
  protocols and persistence; DTOs carry data.
- **Open/closed and Liskov:** add an adapter behind a small port and test that its behavior matches the port's
  contract, including failure semantics.
- **Interface segregation:** define ports around a consumer's actual operation set, not a generic “manager”.
- **Dependency inversion:** Application depends on ports, not Doctrine/Symfony adapters. Put query/read-model
  ports in Application when they return application projections; keep business aggregate repositories in
  Domain.

The Notification idempotency ledger is the reference technical persistence example: its record and Doctrine
adapter live in Infrastructure, and `CreateNotificationHandler` depends on an Application-owned interface.
For a domain repository, the implementation belongs in Infrastructure while its business contract belongs in
Domain. The Identity audit-log read port is in Application because it returns an application response
projection; this keeps Domain from depending on an Application DTO.
