<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\CustomField\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\CustomField\CustomFieldAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts CustomField collection queries to the current customer for CUSTOMER-scoped rows.
 *
 * Access rules come from EDT conditions ({@see CustomFieldAccessChecker::getAccessConditions()}),
 * but those cannot be applied directly to API Platform's shared QueryBuilder without
 * overriding parts of the existing query. So we build a second QueryBuilder via
 * {@see CustomFieldConfigurationRepository::generateAccessConditionQueryBuilder()}, select
 * CustomFieldConfiguration IDs, and apply it as a subquery: `<rootAlias>.id IN (<subquery DQL>)`.
 *
 * Subquery parameters are merged into the outer query. This is safe as long as other
 * filters here continue using named parameters; positional parameter usage could
 * require renumbering to avoid collisions.
 *
 * Note: `$resourceClass` is the Doctrine entity class (`CustomFieldConfiguration::class`), not the
 * API resource DTO class.
 */
final class CustomFieldDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly CustomFieldAccessChecker $accessChecker,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (CustomFieldConfiguration::class !== $resourceClass) {
            return;
        }

        $subQueryBuilder = $this->customFieldConfigurationRepository->generateAccessConditionQueryBuilder(
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
