<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document;

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Repository\ElementsRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Elements>
 *
 * @method        Elements|Proxy                              create(array|callable $attributes = [])
 * @method static Elements|Proxy                              createOne(array $attributes = [])
 * @method static Elements|Proxy                              find(object|array|mixed $criteria)
 * @method static Elements|Proxy                              findOrCreate(array $attributes)
 * @method static Elements|Proxy                              first(string $sortedField = 'id')
 * @method static Elements|Proxy                              last(string $sortedField = 'id')
 * @method static Elements|Proxy                              random(array $attributes = [])
 * @method static Elements|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ElementsRepository|ProxyRepositoryDecorator repository()
 * @method static Elements[]|Proxy[]                          all()
 * @method static Elements[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Elements[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Elements[]|Proxy[]                          findBy(array $attributes)
 * @method static Elements[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Elements[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ElementsFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Elements::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'procedure'  => ProcedureFactory::new(),
            'category'   => self::faker()->randomElement(ElementsInterface::ELEMENT_CATEGORIES),
            'deleted'    => false,
            'enabled'    => self::faker()->boolean(),
            'file'       => self::faker()->text(256),
            'icon'       => 'fa-picture-o',
            'iconTitle'  => 'weitere Information',
            'order'      => self::faker()->numberBetween([0], [999]),
            'pId'        => '', // deprecated procedureId-field without actual relation to a procedure! (not worth to be covered by tests)
            'text'       => self::faker()->text(65535),
            'title'      => self::faker()->randomElement(ElementsInterface::ELEMENT_TITLES),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Elements $elements): void {})
        ;
    }
}
