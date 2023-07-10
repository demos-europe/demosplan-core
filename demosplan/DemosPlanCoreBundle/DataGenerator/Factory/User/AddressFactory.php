<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Repository\AddressRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Address>
 *
 * @method        Address|Proxy                     create(array|callable $attributes = [])
 * @method static Address|Proxy                     createOne(array $attributes = [])
 * @method static Address|Proxy                     find(object|array|mixed $criteria)
 * @method static Address|Proxy                     findOrCreate(array $attributes)
 * @method static Address|Proxy                     first(string $sortedField = 'id')
 * @method static Address|Proxy                     last(string $sortedField = 'id')
 * @method static Address|Proxy                     random(array $attributes = [])
 * @method static Address|Proxy                     randomOrCreate(array $attributes = [])
 * @method static AddressRepository|RepositoryProxy repository()
 * @method static Address[]|Proxy[]                 all()
 * @method static Address[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Address[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Address[]|Proxy[]                 findBy(array $attributes)
 * @method static Address[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Address[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Address> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Address> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Address> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Address> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Address> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Address> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Address> random(array $attributes = [])
 * @phpstan-method static Proxy<Address> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Address> repository()
 * @phpstan-method static list<Proxy<Address>> all()
 * @phpstan-method static list<Proxy<Address>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Address>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Proxy<Address>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Address>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Address>> randomSet(int $number, array $attributes = [])
 */
final class AddressFactory extends ModelFactory
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
            'createdDate' => self::faker()->dateTime(),
            'deleted' => self::faker()->boolean(),
            'houseNumber' => self::faker()->text(),
            'modifiedDate' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Address $address): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Address::class;
    }
}
