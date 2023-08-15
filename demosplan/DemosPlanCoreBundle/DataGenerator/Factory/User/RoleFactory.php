<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Role>
 *
 * @method        Role|Proxy                     create(array|callable $attributes = [])
 * @method static Role|Proxy                     createOne(array $attributes = [])
 * @method static Role|Proxy                     find(object|array|mixed $criteria)
 * @method static Role|Proxy                     findOrCreate(array $attributes)
 * @method static Role|Proxy                     first(string $sortedField = 'id')
 * @method static Role|Proxy                     last(string $sortedField = 'id')
 * @method static Role|Proxy                     random(array $attributes = [])
 * @method static Role|Proxy                     randomOrCreate(array $attributes = [])
 * @method static RoleRepository|RepositoryProxy repository()
 * @method static Role[]|Proxy[]                 all()
 * @method static Role[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Role[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Role[]|Proxy[]                 findBy(array $attributes)
 * @method static Role[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Role[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class RoleFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'code'      => Role::PLANNING_AGENCY_ADMIN,
            'name'      => 'Fachplaner-Admin',
            'groupCode' => Role::GLAUTH,
            'groupName' => 'Kommune',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Role::class;
    }
}
