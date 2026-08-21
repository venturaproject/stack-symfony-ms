<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'messenger' => [
            'default_bus' => 'command.bus',
            'buses' => [
                'command.bus' => null,
                'query.bus' => null,
                // Dedicated bus for integration events — every message dispatched here
                // is routed straight to RabbitMQ (see `routing` below), never handled
                // in-process. Kept separate from command.bus/query.bus, which stay
                // fully synchronous.
                'event.bus' => null,
            ],
            'transports' => [
                // Backed by the `messenger_messages` table (see entrypoint's
                // `messenger:setup-transports`) — no extra infra needed. Consumed by
                // the dedicated `worker` container/service.
                'async' => '%env(MESSENGER_TRANSPORT_DSN)%',
                // Topic exchange shared by every service. Identity only ever publishes
                // here (it has no consumer/worker binding any queue to this transport).
                'amqp' => [
                    'dsn' => '%env(MESSENGER_AMQP_DSN)%',
                    'options' => [
                        'exchange' => [
                            'name' => 'domain_events',
                            'type' => 'topic',
                        ],
                    ],
                ],
            ],
            'routing' => [
                // Emails sent via MailerInterface are queued instead of blocking the request.
                'Symfony\Component\Mailer\Messenger\SendEmailMessage' => 'async',
                'IntegrationEvents\Identity\UserCreated' => 'amqp',
            ],
        ],
    ]);
};
