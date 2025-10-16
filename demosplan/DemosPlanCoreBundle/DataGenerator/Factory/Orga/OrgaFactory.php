<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\SlugFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Orga>
 *
 * @method        Orga|Proxy                              create(array|callable $attributes = [])
 * @method static Orga|Proxy                              createOne(array $attributes = [])
 * @method static Orga|Proxy                              find(object|array|mixed $criteria)
 * @method static Orga|Proxy                              findOrCreate(array $attributes)
 * @method static Orga|Proxy                              first(string $sortedField = 'id')
 * @method static Orga|Proxy                              last(string $sortedField = 'id')
 * @method static Orga|Proxy                              random(array $attributes = [])
 * @method static Orga|Proxy                              randomOrCreate(array $attributes = [])
 * @method static OrgaRepository|ProxyRepositoryDecorator repository()
 * @method static Orga[]|Proxy[]                          all()
 * @method static Orga[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Orga[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Orga[]|Proxy[]                          findBy(array $attributes)
 * @method static Orga[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Orga[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class OrgaFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function defaults(): array
    {
        $slug = SlugFactory::createOne()->_real();

        return [
            'createdDate'    => self::faker()->dateTime(),
            'addSlug'        => $slug,
            'currentSlug'    => $slug,
            'dataProtection' => self::faker()->text(65535),
            'deleted'        => self::faker()->boolean(),
            'imprint'        => self::faker()->text(65535),
            'modifiedDate'   => self::faker()->dateTime(),
            'showlist'       => self::faker()->boolean(),
            'showname'       => self::faker()->boolean(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Orga::class;
    }
}
