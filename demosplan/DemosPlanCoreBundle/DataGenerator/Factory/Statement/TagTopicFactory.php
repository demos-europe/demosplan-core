<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<TagTopic>
 *
 * @method        TagTopic|Proxy                     create(array|callable $attributes = [])
 * @method static TagTopic|Proxy                     createOne(array $attributes = [])
 * @method static TagTopic|Proxy                     find(object|array|mixed $criteria)
 * @method static TagTopic|Proxy                     findOrCreate(array $attributes)
 * @method static TagTopic|Proxy                     first(string $sortedField = 'id')
 * @method static TagTopic|Proxy                     last(string $sortedField = 'id')
 * @method static TagTopic|Proxy                     random(array $attributes = [])
 * @method static TagTopic|Proxy                     randomOrCreate(array $attributes = [])
 * @method static TagTopicRepository|RepositoryProxy repository()
 * @method static TagTopic[]|Proxy[]                 all()
 * @method static TagTopic[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static TagTopic[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static TagTopic[]|Proxy[]                 findBy(array $attributes)
 * @method static TagTopic[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static TagTopic[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class TagTopicFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'procedure' => ProcedureFactory::new(),
            'title'     => self::faker()->word(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return TagTopic::class;
    }
}
