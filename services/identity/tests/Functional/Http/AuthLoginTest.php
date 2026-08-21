<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Domain\Auth\Entity\Role;
use App\Domain\Auth\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Reference example of a Functional test: boots the real kernel, talks to the
 * real (test) database and runs the real HTTP request/response cycle through
 * the firewall — the closest thing to "does the infra actually work" short of
 * hitting it through the gateway. DAMADoctrineTestBundle wraps each test in a
 * transaction and rolls it back afterwards, so the fixtures below never leak
 * into another test or into your dev data.
 */
final class AuthLoginTest extends WebTestCase
{
    public function testValidCredentialsReturnAnAccessTokenAndTheUserProfile(): void
    {
        $client = static::createClient();
        $this->createUser('jane@example.com', 'correct horse battery staple');

        $client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'jane@example.com', 'password' => 'correct horse battery staple'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['accessToken']);
        self::assertSame('jane@example.com', $body['user']['email']);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = static::createClient();
        $this->createUser('jane@example.com', 'correct horse battery staple');

        $client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'jane@example.com', 'password' => 'wrong password'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function createUser(string $email, string $password): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $role = new Role();
        $role->setName('USER');
        $role->setDescription('Standard user role');
        $em->persist($role);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $user->setRoles(new ArrayCollection([$role]));
        $em->persist($user);

        $em->flush();
    }
}
