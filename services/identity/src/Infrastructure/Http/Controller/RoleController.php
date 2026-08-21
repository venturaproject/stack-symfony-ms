<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Auth\Command\CreateRoleCommand;
use App\Application\Auth\Command\DeleteRoleCommand;
use App\Application\Auth\Command\UpdateRoleCommand;
use App\Application\Auth\Query\GetRoleQuery;
use App\Application\Auth\Query\ListRolesQuery;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use SharedKernel\Application\Shared\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/roles')]
#[IsGranted('ROLE_ADMIN')]
class RoleController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListRolesQuery()));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getById(string $id): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetRoleQuery($id)));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        return $this->json($this->commandBus->dispatch(
            new CreateRoleCommand($data['name'], $data['description'] ?? null)
        ), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        return $this->json($this->commandBus->dispatch(
            new UpdateRoleCommand($id, $data['name'], $data['description'] ?? null)
        ));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteRoleCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
