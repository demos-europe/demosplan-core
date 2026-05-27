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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ApiResources\DraftStatementResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceAccess\DraftStatementAccessChecker;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class DraftStatementStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly DraftStatementAccessChecker $accessChecker,
        private readonly DraftStatementRepository $draftStatementRepository,
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), DraftStatementResource::class);

        // Explicit permission check - throw exception if not granted
        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        // Handle collection (GET /api/3.0/DraftStatement)
        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection();
        }

        // Handle single item (GET /api/3.0/DraftStatement/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?DraftStatementResource
    {
        // Access conditions are re-applied so that GET /{id} 404s when the
        // draft belongs to another user / procedure / orga.
        try {
            $draftStatement = $this->draftStatementRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->mapDraftStatementToResource($draftStatement);
    }

    private function provideCollection(): array
    {
        $draftStatements = $this->draftStatementRepository->getEntities(
            $this->accessChecker->getAccessConditions(),
            [],
        );

        $resources = [];
        foreach ($draftStatements as $draftStatement) {
            $resources[] = $this->mapDraftStatementToResource($draftStatement);
        }

        return $resources;
    }

    private function mapDraftStatementToResource(DraftStatement $draftStatement): DraftStatementResource
    {
        $resource = new DraftStatementResource();
        $resource->id = $draftStatement->getId();

        // Field-level permission gate — mirrors the permission-gated
        // setReadableByCallable in DraftStatementResourceType::getProperties().
        if ($this->currentUser->hasPermission('feature_statements_custom_fields')) {
            $resource->customFields = $draftStatement->getCustomFields()?->toJson();
        }

        return $resource;
    }
}
