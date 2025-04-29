<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<CustomFieldConfiguration>
 *
 * @method        CustomFieldConfiguration|Proxy                              create(array|callable $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              createOne(array $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              find(object|array|mixed $criteria)
 * @method static CustomFieldConfiguration|Proxy                              findOrCreate(array $attributes)
 * @method static CustomFieldConfiguration|Proxy                              first(string $sortedField = 'id')
 * @method static CustomFieldConfiguration|Proxy                              last(string $sortedField = 'id')
 * @method static CustomFieldConfiguration|Proxy                              random(array $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              randomOrCreate(array $attributes = [])
 * @method static CustomFieldConfigurationRepository|ProxyRepositoryDecorator repository()
 * @method static CustomFieldConfiguration[]|Proxy[]                          all()
 * @method static CustomFieldConfiguration[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static CustomFieldConfiguration[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static CustomFieldConfiguration[]|Proxy[]                          findBy(array $attributes)
 * @method static CustomFieldConfiguration[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static CustomFieldConfiguration[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        CustomFieldConfiguration&Proxy<CustomFieldConfiguration> create(array|callable $attributes = [])
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> createOne(array $attributes = [])
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> find(object|array|mixed $criteria)
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> findOrCreate(array $attributes)
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> first(string $sortedField = 'id')
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> last(string $sortedField = 'id')
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> random(array $attributes = [])
 * @phpstan-method static CustomFieldConfiguration&Proxy<CustomFieldConfiguration> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<CustomFieldConfiguration, EntityRepository> repository()
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> all()
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> findBy(array $attributes)
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<CustomFieldConfiguration&Proxy<CustomFieldConfiguration>> randomSet(int $number, array $attributes = [])
 */
final class CustomFieldConfigurationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CustomFieldConfiguration::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     */
    protected function defaults(): array|callable
    {
        return [
            'createDate'        => self::faker()->dateTime(),
            'modifyDate'        => self::faker()->dateTime(),
            'sourceEntityClass' => self::faker()->text(),
            'sourceEntityId'    => self::faker()->text(36),
            'targetEntityClass' => self::faker()->text(),
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
