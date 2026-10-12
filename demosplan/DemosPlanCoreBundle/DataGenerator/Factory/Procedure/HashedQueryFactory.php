<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\HashedQueryRepository;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<HashedQuery>
 *
 * @method        HashedQuery|Proxy                              create(array|callable $attributes = [])
 * @method static HashedQuery|Proxy                              createOne(array $attributes = [])
 * @method static HashedQuery|Proxy                              find(object|array|mixed $criteria)
 * @method static HashedQuery|Proxy                              findOrCreate(array $attributes)
 * @method static HashedQueryRepository|ProxyRepositoryDecorator repository()
 * @method static HashedQuery[]|Proxy[]                          all()
 * @method static HashedQuery[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 *
 * @phpstan-method        HashedQuery&Proxy<HashedQuery> create(array|callable $attributes = [])
 * @phpstan-method static HashedQuery&Proxy<HashedQuery> createOne(array $attributes = [])
 * @phpstan-method static HashedQuery&Proxy<HashedQuery> find(object|array|mixed $criteria)
 * @phpstan-method static HashedQuery&Proxy<HashedQuery> findOrCreate(array $attributes)
 * @phpstan-method static ProxyRepositoryDecorator<HashedQuery, EntityRepository> repository()
 * @phpstan-method static list<HashedQuery&Proxy<HashedQuery>> all()
 * @phpstan-method static list<HashedQuery&Proxy<HashedQuery>> createMany(int $number, array|callable $attributes = [])
 */
final class HashedQueryFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return HashedQuery::class;
    }

    /**
     * Builds a coherent row rather than random field values: `hash` is unique in the database and is
     * meant to be the digest of the stored query, so the two are derived together. The default query
     * carries a random search phrase purely so that repeated calls produce distinct digests - two
     * otherwise identical queries would hash the same and collide on the unique index.
     *
     * @see forSegmentList() to tie the query to a specific procedure and view
     */
    protected function defaults(): array|callable
    {
        return function (): array {
            $procedure = ProcedureFactory::createOne();

            $storedQuery = new SegmentListQuery();
            $storedQuery->setProcedureId($procedure->getId());
            $storedQuery->setSearchPhrase(self::faker()->uuid());

            return [
                'hash'        => $storedQuery->getHash(),
                'procedure'   => $procedure,
                'storedQuery' => $storedQuery,
            ];
        };
    }

    /**
     * A segment list query for the given procedure, so the row is consistent on all three axes the
     * application relies on: the referenced procedure, the `procedureId` inside the stored query, and
     * the digest in the `hash` column.
     *
     * @param array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string} $viewSettings
     */
    public function forSegmentList(Procedure $procedure, array $viewSettings = [], array $filter = [], ?string $searchPhrase = null): self
    {
        $storedQuery = new SegmentListQuery();
        $storedQuery->setProcedureId($procedure->getId());
        $storedQuery->setFilter($filter);
        $storedQuery->setSearchPhrase($searchPhrase);
        $storedQuery->setViewSettings($viewSettings);

        return $this->with([
            'hash'        => $storedQuery->getHash(),
            'procedure'   => $procedure,
            'storedQuery' => $storedQuery,
        ]);
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
