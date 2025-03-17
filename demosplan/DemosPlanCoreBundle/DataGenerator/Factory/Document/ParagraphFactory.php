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
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Paragraph>
 *
 * @method        Paragraph|Proxy                              create(array|callable $attributes = [])
 * @method static Paragraph|Proxy                              createOne(array $attributes = [])
 * @method static Paragraph|Proxy                              find(object|array|mixed $criteria)
 * @method static Paragraph|Proxy                              findOrCreate(array $attributes)
 * @method static Paragraph|Proxy                              first(string $sortedField = 'id')
 * @method static Paragraph|Proxy                              last(string $sortedField = 'id')
 * @method static Paragraph|Proxy                              random(array $attributes = [])
 * @method static Paragraph|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ParagraphRepository|ProxyRepositoryDecorator repository()
 * @method static Paragraph[]|Proxy[]                          all()
 * @method static Paragraph[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Paragraph[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Paragraph[]|Proxy[]                          findBy(array $attributes)
 * @method static Paragraph[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Paragraph[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ParagraphFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Paragraph::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'category'   => self::faker()->text(36),
            'createDate' => self::faker()->dateTime(),
            'deleteDate' => self::faker()->dateTime(),
            'deleted'    => self::faker()->boolean(),
            'element'    => ElementsFactory::new(),
            'lockReason' => self::faker()->text(300),
            'modifyDate' => self::faker()->dateTime(),
            'order'      => self::faker()->randomNumber(),
            'procedure'  => ProcedureFactory::new(),
            'text'       => self::faker()->text(16777215),
            'title'      => self::faker()->text(65535),
            'visible'    => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Paragraph $paragraph): void {})
        ;
    }
}
