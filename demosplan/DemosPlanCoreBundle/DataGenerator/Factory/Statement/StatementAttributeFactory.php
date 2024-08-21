<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttributeRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<StatementAttribute>
 *
 * @method        StatementAttribute|Proxy                              create(array|callable $attributes = [])
 * @method static StatementAttribute|Proxy                              createOne(array $attributes = [])
 * @method static StatementAttribute|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementAttribute|Proxy                              findOrCreate(array $attributes)
 * @method static StatementAttribute|Proxy                              first(string $sortedField = 'id')
 * @method static StatementAttribute|Proxy                              last(string $sortedField = 'id')
 * @method static StatementAttribute|Proxy                              random(array $attributes = [])
 * @method static StatementAttribute|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementAttributeRepository|ProxyRepositoryDecorator repository()
 * @method static StatementAttribute[]|Proxy[]                          all()
 * @method static StatementAttribute[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementAttribute[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementAttribute[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementAttribute[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementAttribute[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        StatementAttribute&Proxy<StatementAttribute> create(array|callable $attributes = [])
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> createOne(array $attributes = [])
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> find(object|array|mixed $criteria)
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> findOrCreate(array $attributes)
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> first(string $sortedField = 'id')
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> last(string $sortedField = 'id')
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> random(array $attributes = [])
 * @phpstan-method static StatementAttribute&Proxy<StatementAttribute> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<StatementAttribute, EntityRepository> repository()
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> all()
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> findBy(array $attributes)
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<StatementAttribute&Proxy<StatementAttribute>> randomSet(int $number, array $attributes = [])
 */
final class StatementAttributeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StatementAttribute::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'type' => self::faker()->text(50),
        ];
    }
}
