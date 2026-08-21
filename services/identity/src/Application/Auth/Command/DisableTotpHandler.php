<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Domain\Auth\Exception\InvalidPasswordException;
use App\Domain\Auth\Exception\TotpRequiredByPolicyException;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use App\Domain\Settings\Repository\SecuritySettingsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class DisableTotpHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly SecuritySettingsRepositoryInterface $securitySettings,
    ) {
    }

    public function handle(DisableTotpCommand $cmd): void
    {
        // $cmd->userId always comes from the already-authenticated #[CurrentUser] in
        // TotpController — this find can't actually miss. Deliberately not throwing
        // Symfony\Component\Security\Core\Exception\BadCredentialsException here (or
        // below): that's an AuthenticationException, which the security firewall
        // intercepts anywhere in the request, not just during login — it masks this
        // re-entered-password check behind the JWT authenticator's generic "token not
        // found" response instead of a real "wrong password" one.
        $user = $this->users->findById($cmd->userId)
            ?? throw new InvalidPasswordException();

        if (!$this->hasher->isPasswordValid($user, $cmd->password)) {
            throw new InvalidPasswordException();
        }

        // 2FA is mandatory system-wide right now — disabling would just force the
        // "set up TOTP" challenge again on the very next login. Not a security hole,
        // but confusing UX, so block it outright while the policy is on.
        if ($this->securitySettings->get()->isRequire2fa()) {
            throw new TotpRequiredByPolicyException();
        }

        $user->disableTotp();
        $this->users->save($user);
    }
}
