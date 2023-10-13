<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<OrgaType>
 *
 * @method        OrgaType|Proxy                     create(array|callable $attributes = [])
 * @method static OrgaType|Proxy                     createOne(array $attributes = [])
 * @method static OrgaType|Proxy                     find(object|array|mixed $criteria)
 * @method static OrgaType|Proxy                     findOrCreate(array $attributes)
 * @method static OrgaType|Proxy                     first(string $sortedField = 'id')
 * @method static OrgaType|Proxy                     last(string $sortedField = 'id')
 * @method static OrgaType|Proxy                     random(array $attributes = [])
 * @method static OrgaType|Proxy                     randomOrCreate(array $attributes = [])
 * @method static OrgaTypeRepository|RepositoryProxy repository()
 * @method static OrgaType[]|Proxy[]                 all()
 * @method static OrgaType[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static OrgaType[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static OrgaType[]|Proxy[]                 findBy(array $attributes)
 * @method static OrgaType[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static OrgaType[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class OrgaTypeFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'label' => 'TöB',
            'name'  => 'OPSORG',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return OrgaType::class;
    }
}
