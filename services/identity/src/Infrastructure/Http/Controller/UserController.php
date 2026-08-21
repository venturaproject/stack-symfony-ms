<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\AuditLog\Query\ListAuditLogByUserQuery;
use App\Application\Auth\Command\CreateUserCommand;
use App\Application\Auth\Command\DeleteUserCommand;
use App\Application\Auth\Command\ImpersonateUserCommand;
use App\Application\Auth\Command\UpdateUserCommand;
use App\Application\Auth\DTO\AuthResponse;
use App\Application\Auth\Query\GetUserQuery;
use App\Application\Auth\Query\ListUsersQuery;
use App\Domain\Auth\Entity\User;
use App\Infrastructure\Http\Auth\AuthCookieFactory;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use SharedKernel\Application\Shared\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AuthCookieFactory $cookieFactory,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListUsersQuery(
            page: max(1, (int) $request->query->get('page', 1)),
            size: max(1, min(100, (int) $request->query->get('size', 20))),
        )));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getById(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetUserQuery($id)));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $cmd = $this->serializer->deserialize($request->getContent(), CreateUserCommand::class, 'json');
        $errors = $this->validator->validate($cmd);

        if (\count($errors) > 0) {
            return $this->json(['message' => 'Validation failed'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->commandBus->dispatch($cmd), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $cmd = $this->serializer->deserialize($request->getContent(), UpdateUserCommand::class, 'json');
        $cmd->id = $id;
        $errors = $this->validator->validate($cmd);

        if (\count($errors) > 0) {
            return $this->json(['message' => 'Validation failed'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->commandBus->dispatch($cmd));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteUserCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/impersonate', methods: ['POST'])]
    public function impersonate(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var AuthResponse $result */
        $result = $this->commandBus->dispatch(new ImpersonateUserCommand(
            targetUserId: $id,
            impersonatorEmail: $admin->getUserIdentifier(),
        ));

        $response = $this->json($result);
        $this->cookieFactory->setImpersonationCookies($response, $result->accessToken);

        return $response;
    }

    #[Route('/{id}/audit-log', methods: ['GET'])]
    public function auditLog(string $id, Request $request): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAuditLogByUserQuery(
            userId: $id,
            page: max(1, (int) $request->query->get('page', 1)),
            size: max(1, min(100, (int) $request->query->get('size', 20))),
        )));
    }
}
