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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\BookmarkRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Bookmark>
 *
 * @method        Bookmark|Proxy                              create(array|callable $attributes = [])
 * @method static Bookmark|Proxy                              createOne(array $attributes = [])
 * @method static Bookmark|Proxy                              find(object|array|mixed $criteria)
 * @method static Bookmark|Proxy                              findOrCreate(array $attributes)
 * @method static BookmarkRepository|ProxyRepositoryDecorator repository()
 * @method static Bookmark[]|Proxy[]                          all()
 * @method static Bookmark[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 *
 * @phpstan-method        Bookmark&Proxy<Bookmark> create(array|callable $attributes = [])
 * @phpstan-method static Bookmark&Proxy<Bookmark> createOne(array $attributes = [])
 * @phpstan-method static Bookmark&Proxy<Bookmark> find(object|array|mixed $criteria)
 * @phpstan-method static Bookmark&Proxy<Bookmark> findOrCreate(array $attributes)
 * @phpstan-method static ProxyRepositoryDecorator<Bookmark, EntityRepository> repository()
 * @phpstan-method static list<Bookmark&Proxy<Bookmark>> all()
 * @phpstan-method static list<Bookmark&Proxy<Bookmark>> createMany(int $number, array|callable $attributes = [])
 */
final class BookmarkFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Bookmark::class;
    }

    /**
     * The referenced query is created for the same procedure as the bookmark, because the two are
     * expected to agree: the API's access conditions match on the procedure, and the stored query
     * carries its own `procedureId`.
     *
     * @see forSegmentList() to control the saved view, and to place the bookmark for a given user
     */
    protected function defaults(): array|callable
    {
        return function (): array {
            $procedure = ProcedureFactory::createOne();

            return [
                'filterSet' => HashedQueryFactory::new()->forSegmentList($procedure),
                'name'      => self::faker()->words(3, true),
                'procedure' => $procedure,
                'user'      => UserFactory::new(),
            ];
        };
    }

    /**
     * A bookmark of a segment list view, owned by the given user in the given procedure.
     *
     * @param array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string} $viewSettings
     */
    public function forSegmentList(User $user, Procedure $procedure, string $name, array $viewSettings = [], array $filter = []): self
    {
        return $this->with([
            'filterSet' => HashedQueryFactory::new()->forSegmentList($procedure, $viewSettings, $filter),
            'name'      => $name,
            'procedure' => $procedure,
            'user'      => $user,
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
