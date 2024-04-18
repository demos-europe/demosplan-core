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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PhaseDTO;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\Querying\Pagination\PagePagination;
use Pagerfanta\Pagerfanta;
use Webmozart\Assert\Assert;

/**
 * This class limits the access to {@link Procedure} instances to those, that are allowed
 * to be shown in the procedure administration list for the authenticated user.
 *
 * @template-implements RepositoryInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, >
 */
class ProcedureYmlPhasesRepository implements RepositoryInterface
{
    final public const PROCEDURE_PHASE_NAME = 'name';
    final public const PROCEDURE_PHASE_KEY = 'key';
    final public const PROCEDURE_PHASE_PERMISSIONS_SET = 'permissionset';
    final public const PROCEDURE_PHASE_PARTICIPATION_STATE = 'participationstate';

    protected GlobalConfigInterface $globalConfig;

    public function __construct(
        GlobalConfigInterface $globalConfig
    ) {
        $this->globalConfig = $globalConfig;
    }

    public function getEntityByIdentifier(string $id, array $conditions, array $identifierPropertyPath): object
    {
        throw new InvalidArgumentException();
        // TODO: Implement getEntityByIdentifier() method.
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
