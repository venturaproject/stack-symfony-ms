<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'messenger' => [
            'default_bus' => 'command.bus',
            'buses' => [
                'command.bus' => null,
                'query.bus' => null,
                // Declared here (even though Notification never dispatches onto it
                // itself) purely so Messenger's consume worker can resolve the bus
                // name stamped on incoming messages — they were dispatched on
                // Identity's `event.bus` at publish time and are routed back through
                // a same-named bus on this side. See OnUserCreatedIntegrationEvent.
                'event.bus' => null,
            ],
            'transports' => [
                'amqp' => [
                    'dsn' => '%env(MESSENGER_AMQP_DSN)%',
                    'options' => [
                        'exchange' => [
                            'name' => 'domain_events',
                            'type' => 'topic',
                        ],
                        'queues' => [
                            'notification_events' => [
                                'binding_keys' => ['identity.user_created'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
};
