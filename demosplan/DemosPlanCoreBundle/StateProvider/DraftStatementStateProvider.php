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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class DraftStatementStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DraftStatementRepository $draftStatementRepository,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), DraftStatementResource::class);

        // Explicit permission check - throw exception if not granted
        if (!$this->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        // Handle collection (GET /api/3.0/draft_statements)
        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection();
        }

        // Handle single item (GET /api/3.0/draft_statements/{id})
        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_statements_draft');
    }

    private function provideSingle(string $id): ?DraftStatementResource
    {
        // Access conditions are re-applied so that GET /{id} 404s when the
        // draft belongs to another user / procedure / orga, matching the
        // behaviour of DraftStatementResourceType.
        try {
            $draftStatement = $this->draftStatementRepository->getEntityByIdentifier(
                $id,
                $this->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->mapDraftStatementToResource($draftStatement);
    }

    private function provideCollection(): array
    {
        $draftStatements = $this->draftStatementRepository->getEntities($this->getAccessConditions(), []);

        $resources = [];
        foreach ($draftStatements as $draftStatement) {
            $resources[] = $this->mapDraftStatementToResource($draftStatement);
        }

        return $resources;
    }

    /**
     * Mirrors DraftStatementResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    private function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $user = $this->currentUser->getUser();
        if (!$user instanceof User) {
            return [$this->conditionFactory->false()];
        }

        return [
            // Current procedure only
            $this->conditionFactory->propertyHasValue($procedure->getId(), ['procedure', 'id']),

            // Not deleted
            $this->conditionFactory->propertyHasValue(false, ['deleted']),

            // Same organization
            $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['organisation', 'id']),

            // Own drafts only (works for all user types)
            $this->conditionFactory->propertyHasValue($user->getId(), ['user', 'id']),
        ];
    }

    private function mapDraftStatementToResource(DraftStatement $draftStatement): DraftStatementResource
    {
        $resource = new DraftStatementResource();
        $resource->id = $draftStatement->getId();

        // Mirrors the permission-gated setReadableByCallable in getProperties()
        if ($this->currentUser->hasPermission('feature_statements_custom_fields')) {
            $resource->customFields = $draftStatement->getCustomFields()?->toJson();
        }

        return $resource;
    }
}
