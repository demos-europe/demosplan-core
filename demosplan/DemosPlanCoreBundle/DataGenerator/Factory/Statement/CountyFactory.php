<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Repository\CountyRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<County>
 *
 * @method        County|Proxy                     create(array|callable $attributes = [])
 * @method static County|Proxy                     createOne(array $attributes = [])
 * @method static County|Proxy                     find(object|array|mixed $criteria)
 * @method static County|Proxy                     findOrCreate(array $attributes)
 * @method static County|Proxy                     first(string $sortedField = 'id')
 * @method static County|Proxy                     last(string $sortedField = 'id')
 * @method static County|Proxy                     random(array $attributes = [])
 * @method static County|Proxy                     randomOrCreate(array $attributes = [])
 * @method static CountyRepository|RepositoryProxy repository()
 * @method static County[]|Proxy[]                 all()
 * @method static County[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static County[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static County[]|Proxy[]                 findBy(array $attributes)
 * @method static County[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static County[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class CountyFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'name' => self::faker()->country(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return County::class;
    }
}
