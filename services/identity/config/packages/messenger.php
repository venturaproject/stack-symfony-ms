<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'messenger' => [
            'default_bus' => 'command.bus',
            // After all retries on a transport are exhausted, park the message here
            // instead of losing it — inspect/replay with `messenger:failed:show`
            // and `messenger:failed:retry`.
            'failure_transport' => 'failed',
            'buses' => [
                'command.bus' => null,
                'query.bus' => null,
                // Dedicated bus for integration events — every message dispatched here
                // is routed to the local outbox (`async`, see `routing` below), never
                // handled in-process. Kept separate from command.bus/query.bus, which
                // stay fully synchronous.
                'event.bus' => null,
            ],
            'transports' => [
                // Backed by the `messenger_messages` table (see entrypoint's
                // `messenger:setup-transports`) — no extra infra needed. Consumed by
                // the dedicated `worker` container/service. Doubles as the transactional
                // outbox for integration events: writing here shares the DB connection
                // with the entity manager, so it commits atomically with whatever domain
                // write raised the event (see IntegrationEventPublisher).
                'async' => '%env(MESSENGER_TRANSPORT_DSN)%',
                // Topic exchange shared by every service. Identity never consumes from
                // here — RelayUserCreatedToBroker sends to it directly (bypassing normal
                // message routing) once the outbox above has durably recorded the event.
                'amqp' => [
                    'dsn' => '%env(MESSENGER_AMQP_DSN)%',
                    'options' => [
                        'exchange' => [
                            'name' => 'domain_events',
                            'type' => 'topic',
                        ],
                    ],
                ],
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'routing' => [
                // Emails sent via MailerInterface are queued instead of blocking the request.
                'Symfony\Component\Mailer\Messenger\SendEmailMessage' => 'async',
                // Outbox, not a direct broker publish — see RelayUserCreatedToBroker.
                'IntegrationEvents\Identity\UserCreated' => 'async',
            ],
        ],
    ]);
};
