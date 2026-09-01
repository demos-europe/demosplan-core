<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Tag\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use demosplan\DemosPlanCoreBundle\Api\Common\DoctrineAccessConditionSubqueryTrait;
use demosplan\DemosPlanCoreBundle\Api\Tag\AccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts GET /3.0/Tag collection queries to procedures the current user is allowed
 * to access, by reusing {@see AccessChecker::getAccessConditions()} as a subquery via
 * {@see DoctrineAccessConditionSubqueryTrait}.
 */
final class TagDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    use DoctrineAccessConditionSubqueryTrait;

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly TagRepository $tagRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Tag::class !== $resourceClass) {
            return;
        }

        $this->restrictToSubqueryIds(
            $queryBuilder,
            $this->tagRepository->generateAccessConditionQueryBuilder($this->accessChecker->getAccessConditions())
        );
    }
}
