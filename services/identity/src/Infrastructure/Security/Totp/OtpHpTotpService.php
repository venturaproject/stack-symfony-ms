<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Totp;

use App\Application\Shared\Totp\TotpServiceInterface;
use OTPHP\TOTP;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OtpHpTotpService implements TotpServiceInterface
{
    public function __construct(
        #[Autowire(env: 'TOTP_ISSUER')]
        private readonly string $issuer,
    ) {
    }

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function getProvisioningUri(string $secret, string $accountLabel): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountLabel);
        $totp->setIssuer($this->issuer);

        return $totp->getProvisioningUri();
    }

    public function verify(string $secret, string $code): bool
    {
        // Leeway is in seconds and must stay under the 30s period — this accepts the
        // previous/current/next 30s window, absorbing normal clock drift between the
        // server and the user's authenticator app.
        return TOTP::createFromSecret($secret)->verify($code, null, 29);
    }

    public function generateBackupCodes(int $count = 8): array
    {
        return array_map(
            static fn () => strtoupper(bin2hex(random_bytes(5))),
            range(1, $count),
        );
    }
}
