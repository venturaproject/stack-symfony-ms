<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\ApiClient\Command\CreateApiClientCommand;
use App\Application\ApiClient\Command\RevokeApiClientCommand;
use App\Application\ApiClient\Query\ListApiClientsQuery;
use SharedKernel\Application\Shared\Bus\CommandBusInterface;
use SharedKernel\Application\Shared\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/api-clients')]
#[IsGranted('ROLE_ADMIN')]
class ApiClientController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListApiClientsQuery()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $decoded = json_decode($request->getContent(), true);
        $data = \is_array($decoded) ? $decoded : [];

        $name = \is_string($data['name'] ?? null) ? trim($data['name']) : '';

        $scopes = [];
        foreach (\is_array($data['scopes'] ?? null) ? $data['scopes'] : [] as $scope) {
            if (\is_string($scope)) {
                $scopes[] = $scope;
            }
        }

        if ('' === $name || [] === $scopes) {
            return $this->json(['message' => 'name and scopes are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->commandBus->dispatch(new CreateApiClientCommand(name: $name, scopes: $scopes));

        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function revoke(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new RevokeApiClientCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
