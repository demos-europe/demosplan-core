<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<SingleDocument>
 *
 * @method        SingleDocument|Proxy                              create(array|callable $attributes = [])
 * @method static SingleDocument|Proxy                              createOne(array $attributes = [])
 * @method static SingleDocument|Proxy                              find(object|array|mixed $criteria)
 * @method static SingleDocument|Proxy                              findOrCreate(array $attributes)
 * @method static SingleDocument|Proxy                              first(string $sortedField = 'id')
 * @method static SingleDocument|Proxy                              last(string $sortedField = 'id')
 * @method static SingleDocument|Proxy                              random(array $attributes = [])
 * @method static SingleDocument|Proxy                              randomOrCreate(array $attributes = [])
 * @method static SingleDocumentRepository|ProxyRepositoryDecorator repository()
 * @method static SingleDocument[]|Proxy[]                          all()
 * @method static SingleDocument[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static SingleDocument[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static SingleDocument[]|Proxy[]                          findBy(array $attributes)
 * @method static SingleDocument[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static SingleDocument[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class SingleDocumentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SingleDocument::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        $categories = [
            'file',
            'arbeitskreis',
            'landschaftsplan',
            'fnp',
            'untersuchung',
            'informationen',
            'e_unterlagen',
            'fnp',
            'sv-eu',
            'protokolle',
        ];

        $procedure = ProcedureFactory::new();

        return [
            'category'         => self::faker()->randomElement($categories),
            'deleted'          => false,
            'document'         => self::faker()->colorName().'.pdf:'.self::faker()->uuid().':'.self::faker()->numberBetween([255], [99999]).':application/pdf',
            'element'          => ElementsFactory::new()->create(['procedure' => $procedure]),
            'order'            => self::faker()->numberBetween([0], [999]),
            'procedure'        => $procedure,
            'statementEnabled' => self::faker()->boolean(),
            'symbol'           => '',
            'text'             => self::faker()->text(2000),
            'title'            => 'default test single document',
            'visible'          => true,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(SingleDocument $singleDocument): void {})
        ;
    }
}
