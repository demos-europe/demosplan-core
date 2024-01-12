<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Segment>
 *
 * @method        Segment|Proxy                     create(array|callable $attributes = [])
 * @method static Segment|Proxy                     createOne(array $attributes = [])
 * @method static Segment|Proxy                     find(object|array|mixed $criteria)
 * @method static Segment|Proxy                     findOrCreate(array $attributes)
 * @method static Segment|Proxy                     first(string $sortedField = 'id')
 * @method static Segment|Proxy                     last(string $sortedField = 'id')
 * @method static Segment|Proxy                     random(array $attributes = [])
 * @method static Segment|Proxy                     randomOrCreate(array $attributes = [])
 * @method static SegmentRepository|RepositoryProxy repository()
 * @method static Segment[]|Proxy[]                 all()
 * @method static Segment[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Segment[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Segment[]|Proxy[]                 findBy(array $attributes)
 * @method static Segment[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Segment[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class SegmentFactory extends StatementFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return parent::getDefaults();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Segment $segment): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Segment::class;
    }
}
