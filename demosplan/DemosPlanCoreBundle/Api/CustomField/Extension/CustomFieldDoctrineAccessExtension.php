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
use demosplan\DemosPlanCoreBundle\Api\Common\DoctrineAccessConditionSubqueryTrait;
use demosplan\DemosPlanCoreBundle\Api\CustomField\CustomFieldAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts CustomField collection queries to the current customer for CUSTOMER-scoped rows.
 *
 * Access rules come from EDT conditions ({@see CustomFieldAccessChecker::getAccessConditions()}),
 * but those cannot be applied directly to API Platform's shared QueryBuilder without overriding parts
 * of the existing query - {@see DoctrineAccessConditionSubqueryTrait} bridges them via a subquery.
 *
 * Note: `$resourceClass` is the Doctrine entity class (`CustomFieldConfiguration::class`), not the
 * API resource DTO class.
 */
final class CustomFieldDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    use DoctrineAccessConditionSubqueryTrait;

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

        $this->restrictToSubqueryIds(
            $queryBuilder,
            $this->customFieldConfigurationRepository->generateAccessConditionQueryBuilder($this->accessChecker->getAccessConditions())
        );
    }
}
