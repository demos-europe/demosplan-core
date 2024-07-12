<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Repository\StatementFragmentRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<StatementFragment>
 *
 * @method        StatementFragment|Proxy                              create(array|callable $attributes = [])
 * @method static StatementFragment|Proxy                              createOne(array $attributes = [])
 * @method static StatementFragment|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementFragment|Proxy                              findOrCreate(array $attributes)
 * @method static StatementFragment|Proxy                              first(string $sortedField = 'id')
 * @method static StatementFragment|Proxy                              last(string $sortedField = 'id')
 * @method static StatementFragment|Proxy                              random(array $attributes = [])
 * @method static StatementFragment|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementFragmentRepository|ProxyRepositoryDecorator repository()
 * @method static StatementFragment[]|Proxy[]                          all()
 * @method static StatementFragment[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementFragment[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementFragment[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementFragment[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementFragment[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        StatementFragment&Proxy<StatementFragment> create(array|callable $attributes = [])
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> createOne(array $attributes = [])
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> find(object|array|mixed $criteria)
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> findOrCreate(array $attributes)
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> first(string $sortedField = 'id')
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> last(string $sortedField = 'id')
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> random(array $attributes = [])
 * @phpstan-method static StatementFragment&Proxy<StatementFragment> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<StatementFragment, EntityRepository> repository()
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> all()
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> findBy(array $attributes)
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<StatementFragment&Proxy<StatementFragment>> randomSet(int $number, array $attributes = [])
 */
final class StatementFragmentFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return StatementFragment::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'created'   => self::faker()->dateTime(),
            'displayId' => self::faker()->randomNumber(),
            'modified'  => self::faker()->dateTime(),
            'procedure' => ProcedureFactory::new(),
            'sortIndex' => self::faker()->randomNumber(),
            'statement' => StatementFactory::new(),
            'text'      => self::faker()->text(16777215),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(StatementFragment $statementFragment): void {})
        ;
    }
}
