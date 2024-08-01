<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<User>
 *
 * @method        User|Proxy                              create(array|callable $attributes = [])
 * @method static User|Proxy                              createOne(array $attributes = [])
 * @method static User|Proxy                              find(object|array|mixed $criteria)
 * @method static User|Proxy                              findOrCreate(array $attributes)
 * @method static User|Proxy                              first(string $sortedField = 'id')
 * @method static User|Proxy                              last(string $sortedField = 'id')
 * @method static User|Proxy                              random(array $attributes = [])
 * @method static User|Proxy                              randomOrCreate(array $attributes = [])
 * @method static UserRepository|ProxyRepositoryDecorator repository()
 * @method static User[]|Proxy[]                          all()
 * @method static User[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static User[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static User[]|Proxy[]                          findBy(array $attributes)
 * @method static User[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static User[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public function __construct(private readonly ParameterBagInterface $parameterBag)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        $email = self::faker()->email();
        return [
            'createdDate' => self::faker()->dateTime(),
            'deleted' => false,
            'modifiedDate' => self::faker()->dateTime(),
            'providedByIdentityProvider' => self::faker()->boolean(),
            'orga' => OrgaFactory::new(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'password' => md5($this->parameterBag->get('alternative_login_testuser_defaultpass')),
            'login' => $email,
            'email' => $email,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(User $user): void {})
        ;
    }
}
