<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends PersistentProxyObjectFactory<Tag>
 *
 * @method        Tag|Proxy                     create(array|callable $attributes = [])
 * @method static Tag|Proxy                     createOne(array $attributes = [])
 * @method static Tag|Proxy                     find(object|array|mixed $criteria)
 * @method static Tag|Proxy                     findOrCreate(array $attributes)
 * @method static Tag|Proxy                     first(string $sortedField = 'id')
 * @method static Tag|Proxy                     last(string $sortedField = 'id')
 * @method static Tag|Proxy                     random(array $attributes = [])
 * @method static Tag|Proxy                     randomOrCreate(array $attributes = [])
 * @method static TagRepository|RepositoryProxy repository()
 * @method static Tag[]|Proxy[]                 all()
 * @method static Tag[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Tag[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Tag[]|Proxy[]                 findBy(array $attributes)
 * @method static Tag[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Tag[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class TagFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function defaults(): array
    {
        return [
            'title' => self::faker()->word(),
            'topic' => TagTopicFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Tag::class;
    }
}
