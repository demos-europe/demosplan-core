<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
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
 */
final class RoleFactory extends PersistentProxyObjectFactory
{


    public static function class(): string
    {
        return Role::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
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
        return $this
            // ->afterInstantiate(function(Role $role): void {})
        ;
    }
}
