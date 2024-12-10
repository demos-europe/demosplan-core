<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Customer>
 *
 * @method        Customer|Proxy                              create(array|callable $attributes = [])
 * @method static Customer|Proxy                              createOne(array $attributes = [])
 * @method static Customer|Proxy                              find(object|array|mixed $criteria)
 * @method static Customer|Proxy                              findOrCreate(array $attributes)
 * @method static Customer|Proxy                              first(string $sortedField = 'id')
 * @method static Customer|Proxy                              last(string $sortedField = 'id')
 * @method static Customer|Proxy                              random(array $attributes = [])
 * @method static Customer|Proxy                              randomOrCreate(array $attributes = [])
 * @method static CustomerRepository|ProxyRepositoryDecorator repository()
 * @method static Customer[]|Proxy[]                          all()
 * @method static Customer[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Customer[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Customer[]|Proxy[]                          findBy(array $attributes)
 * @method static Customer[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Customer[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class CustomerFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this;
    }

    public static function class(): string
    {
        return Customer::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'accessibilityExplanation'            => self::faker()->text(),
            'baseLayerLayers'                     => self::faker()->text(4096),
            'baseLayerUrl'                        => self::faker()->text(4096),
            'dataProtection'                      => self::faker()->text(65535),
            'imprint'                             => self::faker()->text(65535),
            'mapAttribution'                      => self::faker()->text(4096),
            'name'                                => self::faker()->text(50),
            'overviewDescriptionInSimpleLanguage' => self::faker()->text(),
            'signLanguageOverviewDescription'     => self::faker()->text(),
            'subdomain'                           => self::faker()->text(50),
            'termsOfUse'                          => self::faker()->text(65535),
            'xplanning'                           => self::faker()->text(65535),
        ];
    }
}
