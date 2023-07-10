<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<User>
 *
 * @method        User|Proxy                     create(array|callable $attributes = [])
 * @method static User|Proxy                     createOne(array $attributes = [])
 * @method static User|Proxy                     find(object|array|mixed $criteria)
 * @method static User|Proxy                     findOrCreate(array $attributes)
 * @method static User|Proxy                     first(string $sortedField = 'id')
 * @method static User|Proxy                     last(string $sortedField = 'id')
 * @method static User|Proxy                     random(array $attributes = [])
 * @method static User|Proxy                     randomOrCreate(array $attributes = [])
 * @method static UserRepository|RepositoryProxy repository()
 * @method static User[]|Proxy[]                 all()
 * @method static User[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static User[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static User[]|Proxy[]                 findBy(array $attributes)
 * @method static User[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static User[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class UserFactory extends ModelFactory
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

    protected function getDefaults(): array
    {
        return [
            'deleted' => false,
            'flags' => [],
            'providedByIdentityProvider' => false,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(User $user): void {})
        ;
    }

    protected static function getClass(): string
    {
        return User::class;
    }

    public function asDeleted(): self
    {
        return $this->addState(['deleted' => true]);
    }

    public function asValidCitizenUser(): self
    {
        return $this->addState([
            'department' => DepartmentFactory::new(),
            'orga' => OrgaFactory::new(),
            'email' => self::faker()->safeEmail(),
            'login' => 'testUser_'.self::count(),
            'password' => self::faker()->password(),
            'firstname' => self::faker()->firstName(),
            'lastname' => self::faker()->lastName(),
            'newsletter'=> true,
            'noPiwik' => true,
            'profileCompleted' => true,
            'NnewUser' => false,
            'invited' => false,
            'acessConfirmed' => true,
            'forumNotification' => true,
            'currentCustomer' => CustomerFactory::find(['name' => 'Brandenburg']),
//            'dplanRoles' => ,
        ]);

    }

}
