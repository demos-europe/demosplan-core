<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Repository\StatementMetaRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<StatementMeta>
 *
 * @method        StatementMeta|Proxy                              create(array|callable $attributes = [])
 * @method static StatementMeta|Proxy                              createOne(array $attributes = [])
 * @method static StatementMeta|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementMeta|Proxy                              findOrCreate(array $attributes)
 * @method static StatementMeta|Proxy                              first(string $sortedField = 'id')
 * @method static StatementMeta|Proxy                              last(string $sortedField = 'id')
 * @method static StatementMeta|Proxy                              random(array $attributes = [])
 * @method static StatementMeta|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementMetaRepository|ProxyRepositoryDecorator repository()
 * @method static StatementMeta[]|Proxy[]                          all()
 * @method static StatementMeta[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementMeta[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementMeta[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementMeta[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementMeta[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        StatementMeta&Proxy<StatementMeta> create(array|callable $attributes = [])
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> createOne(array $attributes = [])
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> find(object|array|mixed $criteria)
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> findOrCreate(array $attributes)
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> first(string $sortedField = 'id')
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> last(string $sortedField = 'id')
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> random(array $attributes = [])
 * @phpstan-method static StatementMeta&Proxy<StatementMeta> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<StatementMeta, EntityRepository> repository()
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> all()
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> findBy(array $attributes)
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<StatementMeta&Proxy<StatementMeta>> randomSet(int $number, array $attributes = [])
 */
final class StatementMetaFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StatementMeta::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'authorFeedback'     => self::faker()->boolean(),
            'authorName'         => self::faker()->text(255),
            'caseWorkerName'     => self::faker()->text(255),
            'houseNumber'        => self::faker()->text(255),
            'orgaCity'           => self::faker()->text(255),
            'orgaDepartmentName' => self::faker()->text(255),
            'orgaEmail'          => self::faker()->text(255),
            'orgaName'           => self::faker()->text(255),
            'orgaPostalCode'     => self::faker()->text(255),
            'orgaStreet'         => self::faker()->text(255),
            'statement'          => StatementFactory::new(),
            'submitName'         => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
