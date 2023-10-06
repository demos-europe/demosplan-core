<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Customer>
 *
 * @method        Customer|Proxy                     create(array|callable $attributes = [])
 * @method static Customer|Proxy                     createOne(array $attributes = [])
 * @method static Customer|Proxy                     find(object|array|mixed $criteria)
 * @method static Customer|Proxy                     findOrCreate(array $attributes)
 * @method static Customer|Proxy                     first(string $sortedField = 'id')
 * @method static Customer|Proxy                     last(string $sortedField = 'id')
 * @method static Customer|Proxy                     random(array $attributes = [])
 * @method static Customer|Proxy                     randomOrCreate(array $attributes = [])
 * @method static CustomerRepository|RepositoryProxy repository()
 * @method static Customer[]|Proxy[]                 all()
 * @method static Customer[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Customer[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Customer[]|Proxy[]                 findBy(array $attributes)
 * @method static Customer[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Customer[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 *
 */
final class CustomerFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        $name = self::faker()->country();
        return [
            'accessibilityExplanation' => self::faker()->text(2000),
            'name' => $name,
            'subdomain' => $name,
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Customer::class;
    }
}
