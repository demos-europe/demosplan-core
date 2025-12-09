<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\ClaimResource;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ClaimStateProvider implements ProviderInterface
{
    public function __construct(
       // private EntityManagerInterface $entityManager,
        //private UserRepository $userRepository
    ) {
       // $bla = '4';
    }

    public function provide(Operation $operation, array $uriVariables = [],
                            array $context = []): object|array|null
    {
        // TEMPORARILY hardcode the response to test
        $claim = new ClaimResource();
        $claim->id = $uriVariables['id'] ?? '1';
        $claim->name = 'Test User';
        $claim->orgaName = 'Test Organization';

        return $claim;
    }


    /*public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if ($resourceClass !== ClaimResource::class) {
            return null;
        }

        // Handle single item (GET /api/claim_resources/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        // Handle collection (GET /api/claim_resources)
        return $this->provideCollection($context);
    }*/

    private function provideSingle(string $id): ?ClaimResource
    {
        // Find user by ID (assuming claim ID maps to user ID for this example)
        $user = $this->userRepository->find($id);

        if (!$user) {
            return null;
        }

        return $this->mapUserToClaimResource($user);
    }

    private function provideCollection(array $context = []): array
    {
        // Get all users and map them to claims
        $users = $this->userRepository->findAll();

        $claims = [];
        foreach ($users as $user) {
            $claims[] = $this->mapUserToClaimResource($user);
        }

        return $claims;
    }

    private function mapUserToClaimResource(User $user): ClaimResource
    {
        $claim = new ClaimResource();

        $claim->id = $user->getId();
        $claim->name = $user->getName();
        $claim->orgaName = $user->getOrgaName();

        return $claim;
    }
}
