<?php

declare(strict_types=1);

namespace App\Application\Auth\EventHandler;

use App\Application\Shared\Mail\MailerInterface;
use App\Domain\Auth\Event\UserPasswordChanged;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fires only when UpdateUserCommand actually sets a new password (see
 * User::changePassword()) — not on every profile edit. Never include credentials
 * in email; email and its async transport are not secret stores.
 */
final class SendCredentialsEmailOnPasswordChanged implements EventSubscriberInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserPasswordChanged::class => 'handle'];
    }

    public function handle(UserPasswordChanged $event): void
    {
        $this->mailer->send(
            to: $event->email,
            subject: 'Your password has been changed',
            template: 'emails/password_changed.html.twig',
            context: [
                'userEmail' => $event->email,
            ],
        );
    }
}
