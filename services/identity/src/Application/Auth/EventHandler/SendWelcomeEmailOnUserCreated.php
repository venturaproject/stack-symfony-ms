<?php

declare(strict_types=1);

namespace App\Application\Auth\EventHandler;

use App\Application\Shared\Mail\MailerInterface;
use App\Domain\Auth\Event\UserCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SendWelcomeEmailOnUserCreated implements EventSubscriberInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserCreated::class => 'handle'];
    }

    public function handle(UserCreated $event): void
    {
        $this->mailer->send(
            to: $event->email,
            subject: 'Welcome!',
            template: 'emails/welcome.html.twig',
            context: [
                'userEmail' => $event->email,
            ],
        );
    }
}
