<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\ScheduledExport\ScheduledExportAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Repository\ScheduledExportRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts GET /3.0/ScheduledExport collection queries to the current user's own schedules in the
 * current procedure, by reusing {@see ScheduledExportAccessChecker::getAccessConditions()} as a subquery.
 */
final class ScheduledExportDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly ScheduledExportAccessChecker $accessChecker,
        private readonly ScheduledExportRepository $scheduledExportRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (ExportSchedule::class !== $resourceClass) {
            return;
        }

        $subQueryBuilder = $this->scheduledExportRepository->generateAccessConditionQueryBuilder(
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
