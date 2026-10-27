<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableInstitution\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\InvitableInstitution\AccessChecker;
use demosplan\DemosPlanCoreBundle\Api\InvitableInstitution\Resource;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts GET /3.0/InvitableInstitution collection queries to the institutions the
 * current user may see, by embedding {@see AccessChecker::getAccessConditions()} as an
 * `<rootAlias>.id IN (<subquery>)` (see SegmentDoctrineAccessExtension for the rationale).
 *
 * `Orga` is shared with other API resources, so the operation's resource class is checked too.
 */
final class InvitableInstitutionAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly OrgaRepository $orgaRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Orga::class !== $resourceClass || Resource::class !== $operation?->getClass()) {
            return;
        }

        $subQueryBuilder = $this->orgaRepository->generateAccessConditionQueryBuilder(
            $this->accessChecker->getAccessConditions()
        );
        $subAlias = $subQueryBuilder->getRootAliases()[0];
        $subQueryBuilder->select("$subAlias.id");

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere($queryBuilder->expr()->in("$rootAlias.id", $subQueryBuilder->getDQL()));

        foreach ($subQueryBuilder->getParameters() as $parameter) {
            $queryBuilder->setParameter($parameter->getName(), $parameter->getValue(), $parameter->getType());
        }
    }
}
