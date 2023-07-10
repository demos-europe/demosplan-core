<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Department>
 *
 * @method        Department|Proxy                     create(array|callable $attributes = [])
 * @method static Department|Proxy                     createOne(array $attributes = [])
 * @method static Department|Proxy                     find(object|array|mixed $criteria)
 * @method static Department|Proxy                     findOrCreate(array $attributes)
 * @method static Department|Proxy                     first(string $sortedField = 'id')
 * @method static Department|Proxy                     last(string $sortedField = 'id')
 * @method static Department|Proxy                     random(array $attributes = [])
 * @method static Department|Proxy                     randomOrCreate(array $attributes = [])
 * @method static DepartmentRepository|RepositoryProxy repository()
 * @method static Department[]|Proxy[]                 all()
 * @method static Department[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Department[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Department[]|Proxy[]                 findBy(array $attributes)
 * @method static Department[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Department[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class DepartmentFactory extends ModelFactory
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
            'name' => self::faker()->text(255),
            'deleted' => false,
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Department::class;
    }

    /**
     * @return selfUser::ANONYMOUS_USER_DEPARTMENT_ID
     */
    public function asAnonymousUserDepartment(): self
    {
    }
}
