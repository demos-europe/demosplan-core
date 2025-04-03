<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldService;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PhaseDTO;
use Doctrine\ORM\EntityManagerInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\Querying\Pagination\PagePagination;
use Pagerfanta\Pagerfanta;
use Webmozart\Assert\Assert;

/**
 * @template-implements RepositoryInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, >
 */
class CustomFieldJsonRepository implements RepositoryInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ConditionFactoryInterface $conditionFactory,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function getEntityByIdentifier(string $id, array $conditions, array $identifierPropertyPath): object
    {
        // [$procedureId, $customFieldName] = explode('_', $id, 2);
        $identifierCondition = $this->conditionFactory->propertyHasValue($id, $identifierPropertyPath);
        $conditions[] = $identifierCondition;
        $entities = $this->entityManager->getEntities($conditions, [], null);

        $this->customFieldService->loadFromJsonOnlyList();

        return match (count($entities)) {
            0       => throw new InvalidArgumentException('No matching BLABLA entity found.'),
            1       => array_pop($entities),
            default => throw new InvalidArgumentException('Multiple matching BLABLA entities found.'),
        };
    }

    public function getEntitiesByIdentifiers(array $identifiers, array $conditions, array $sortMethods, array $identifierPropertyPath): array
    {
        throw new InvalidArgumentException();
        Assert::isEmpty($conditions);
        Assert::isEmpty($sortMethods);

        return [];
    }

    public function getEntities(array $conditions, array $sortMethods): array
    {
        throw new InvalidArgumentException();
        Assert::isEmpty($conditions);
        Assert::isEmpty($sortMethods);

        $phases = [];
        foreach ($this->globalConfig->getRawInternalPhases() as $internalPhase) {
            $phases[] = $this->createPhaseDto($internalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_INTERNAL);
        }

        foreach ($this->globalConfig->getRawExternalPhases() as $externalPhase) {
            $phases[] = $this->createPhaseDto($externalPhase, Permissions::PROCEDURE_PERMISSION_SCOPE_EXTERNAL);
        }

        return $phases;
    }

    private function createPhaseDto(array $phase, string $type)
    {
        $phaseDto = new PhaseDTO();
        $phaseDto->setId($phase[self::PROCEDURE_PHASE_KEY]);
        $phaseDto->setName($phase[self::PROCEDURE_PHASE_NAME]);
        $phaseDto->setPermissionsSet($phase[self::PROCEDURE_PHASE_PERMISSIONS_SET]);
        $phaseDto->setParticipationState($phase[self::PROCEDURE_PHASE_PARTICIPATION_STATE] ?? null);
        $phaseDto->setType($type);

        return $phaseDto->lock();
    }

    public function getEntitiesForPage(array $conditions, array $sortMethods, PagePagination $pagination): Pagerfanta
    {
        throw new InvalidArgumentException();
        Assert::isEmpty($conditions);
        Assert::isEmpty($sortMethods);
    }

    public function deleteEntityByIdentifier(string $entityIdentifier, array $conditions, array $identifierPropertyPath): void
    {
        // TODO: Implement deleteEntityByIdentifier() method.
        throw new InvalidArgumentException();
    }

    public function reindexEntities(array $entities, array $conditions, array $sortMethods): array
    {
        // TODO: Implement reindexEntities() method.

        return [];
    }

    public function isMatchingEntity(object $entity, array $conditions): bool
    {
        // TODO: Implement isMatchingEntity() method.
        throw new InvalidArgumentException();
    }

    public function assertMatchingEntity(object $entity, array $conditions): void
    {
        // TODO: Implement assertMatchingEntity() method.
        throw new InvalidArgumentException();
    }
}
