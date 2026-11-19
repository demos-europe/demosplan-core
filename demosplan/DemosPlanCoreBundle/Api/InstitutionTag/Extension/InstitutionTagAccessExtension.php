<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionTag\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\AccessChecker;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\Resource;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts GET /3.0/InstitutionTag collection queries to the tags of the current customer, by
 * embedding {@see AccessChecker::getTagAccessConditions()} as an `<rootAlias>.id IN (<subquery>)`
 * (see TagDoctrineAccessExtension for the rationale).
 */
final class InstitutionTagAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly InstitutionTagRepository $tagRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (InstitutionTag::class !== $resourceClass || Resource::class !== $operation?->getClass()) {
            return;
        }

        $subQueryBuilder = $this->tagRepository->generateAccessConditionQueryBuilder(
            $this->accessChecker->getTagAccessConditions()
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
