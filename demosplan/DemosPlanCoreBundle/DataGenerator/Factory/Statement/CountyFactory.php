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
 *
 * @phpstan-method        Proxy<County> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<County> createOne(array $attributes = [])
 * @phpstan-method static Proxy<County> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<County> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<County> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<County> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<County> random(array $attributes = [])
 * @phpstan-method static Proxy<County> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<County> repository()
 * @phpstan-method static list<Proxy<County>> all()
 * @phpstan-method static list<Proxy<County>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<County>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Proxy<County>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<County>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<County>> randomSet(int $number, array $attributes = [])
 */
final class CountyFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        return [
            'name' => self::faker()->text(36),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(County $county): void {})
        ;
    }

    protected static function getClass(): string
    {
        return County::class;
    }
}
