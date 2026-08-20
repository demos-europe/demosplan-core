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
use demosplan\DemosPlanCoreBundle\Api\Tag\AccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts Doctrine-backed Tag collection reads to procedures the current user is
 * allowed to access.
 *
 * This mirrors {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\Extension\SegmentDoctrineAccessExtension}:
 * the access rule is authored in EDT condition objects
 * ({@see AccessChecker::getAccessConditions()}), which can't be applied directly to
 * the QueryBuilder API Platform's own Doctrine ORM CollectionProvider builds and
 * shares across extensions. Instead, a second, never-executed QueryBuilder for the
 * same conditions is built via {@see TagRepository::generateAccessConditionQueryBuilder()}
 * and embedded as a subquery: `<rootAlias>.id IN (<subquery DQL>)`. Only the outer
 * QueryBuilder is ever executed.
 */
final class TagDoctrineAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly TagRepository $tagRepository,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        // $resourceClass here is the Doctrine entity (Tag::class, from Provider's
        // DoctrineOptions(entityClass: ...)), not the ApiResource DTO class.
        if (Tag::class !== $resourceClass) {
            return;
        }

        $subQueryBuilder = $this->tagRepository->generateAccessConditionQueryBuilder(
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
