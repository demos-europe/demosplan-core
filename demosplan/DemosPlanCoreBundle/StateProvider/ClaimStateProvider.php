<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\ClaimResource;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;

class ClaimStateProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (ClaimResource::class !== $resourceClass) {
            return null;
        }

        // Handle single item (GET /api/claim_resources/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        // Handle collection (GET /api/claim_resources)
        return $this->provideCollection($context);
    }

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
