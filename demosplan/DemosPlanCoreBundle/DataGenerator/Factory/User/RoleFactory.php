<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Role>
 *
 * @method        Role|Proxy                              create(array|callable $attributes = [])
 * @method static Role|Proxy                              createOne(array $attributes = [])
 * @method static Role|Proxy                              find(object|array|mixed $criteria)
 * @method static Role|Proxy                              findOrCreate(array $attributes)
 * @method static Role|Proxy                              first(string $sortedField = 'id')
 * @method static Role|Proxy                              last(string $sortedField = 'id')
 * @method static Role|Proxy                              random(array $attributes = [])
 * @method static Role|Proxy                              randomOrCreate(array $attributes = [])
 * @method static RoleRepository|ProxyRepositoryDecorator repository()
 * @method static Role[]|Proxy[]                          all()
 * @method static Role[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Role[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Role[]|Proxy[]                          findBy(array $attributes)
 * @method static Role[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Role[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Role&Proxy<Role> create(array|callable $attributes = [])
 * @phpstan-method static Role&Proxy<Role> createOne(array $attributes = [])
 * @phpstan-method static Role&Proxy<Role> find(object|array|mixed $criteria)
 * @phpstan-method static Role&Proxy<Role> findOrCreate(array $attributes)
 * @phpstan-method static Role&Proxy<Role> first(string $sortedField = 'id')
 * @phpstan-method static Role&Proxy<Role> last(string $sortedField = 'id')
 * @phpstan-method static Role&Proxy<Role> random(array $attributes = [])
 * @phpstan-method static Role&Proxy<Role> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Role, EntityRepository> repository()
 * @phpstan-method static list<Role&Proxy<Role>> all()
 * @phpstan-method static list<Role&Proxy<Role>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Role&Proxy<Role>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Role&Proxy<Role>> findBy(array $attributes)
 * @phpstan-method static list<Role&Proxy<Role>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Role&Proxy<Role>> randomSet(int $number, array $attributes = [])
 */
final class RoleFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Role::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->text(6),
            'code' => self::faker()->text(6),
            'groupCode' => self::faker()->text(6),
            'groupName' => self::faker()->text(60),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
