<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Repository\TextSectionRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<TextSection>
 *
 * @method        TextSection|Proxy                              create(array|callable $attributes = [])
 * @method static TextSection|Proxy                              createOne(array $attributes = [])
 * @method static TextSection|Proxy                              find(object|array|mixed $criteria)
 * @method static TextSection|Proxy                              findOrCreate(array $attributes)
 * @method static TextSection|Proxy                              first(string $sortedField = 'id')
 * @method static TextSection|Proxy                              last(string $sortedField = 'id')
 * @method static TextSection|Proxy                              random(array $attributes = [])
 * @method static TextSection|Proxy                              randomOrCreate(array $attributes = [])
 * @method static TextSectionRepository|ProxyRepositoryDecorator repository()
 * @method static TextSection[]|Proxy[]                          all()
 * @method static TextSection[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static TextSection[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static TextSection[]|Proxy[]                          findBy(array $attributes)
 * @method static TextSection[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static TextSection[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class TextSectionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return TextSection::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'statement'        => StatementFactory::new(),
            'orderInStatement' => 1,
            'textRaw'          => '<p>Default text section content</p>',
            'text'             => 'Default text section content',
        ];
    }
}
