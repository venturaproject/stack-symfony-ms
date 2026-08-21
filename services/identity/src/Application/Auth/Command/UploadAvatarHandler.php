<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Auth\DTO\UserResponse;
use App\Domain\Auth\Exception\UserNotFoundException;
use App\Domain\Auth\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use finfo;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', method: 'handle')]
final class UploadAvatarHandler
{
    // Keyed by real file content (finfo), never by the client-supplied filename/
    // extension — a client can name a PHP payload "avatar.jpg" trivially.
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly string $mediaDir = '/app/var/media',
    ) {
    }

    public function handle(UploadAvatarCommand $cmd): UserResponse
    {
        $mimeType = (new finfo(\FILEINFO_MIME_TYPE))->file($cmd->tmpPath) ?: '';
        $ext = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if (null === $ext) {
            throw new InvalidArgumentException('Invalid file type. Allowed: jpg, jpeg, png, gif, webp');
        }

        $user = $this->users->findById($cmd->userId)
            ?? throw new UserNotFoundException();

        $dir = $this->mediaDir.'/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $cmd->userId.'.'.$ext;
        rename($cmd->tmpPath, $dir.'/'.$filename);

        $user->setAvatarUrl('/media/avatars/'.$filename);
        $user->setUpdatedAt(new DateTimeImmutable());

        return UserResponse::from($this->users->save($user));
    }
}
