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
 *
 * @phpstan-method        Proxy<OrgaType> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<OrgaType> createOne(array $attributes = [])
 * @phpstan-method static Proxy<OrgaType> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<OrgaType> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<OrgaType> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<OrgaType> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<OrgaType> random(array $attributes = [])
 * @phpstan-method static Proxy<OrgaType> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<OrgaType> repository()
 * @phpstan-method static list<Proxy<OrgaType>> all()
 * @phpstan-method static list<Proxy<OrgaType>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<OrgaType>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Proxy<OrgaType>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<OrgaType>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<OrgaType>> randomSet(int $number, array $attributes = [])
 */
final class OrgaTypeFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function getDefaults(): array
    {
        return [
            'label' => self::faker()->text(45),
            'name'  => self::faker()->text(6),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return OrgaType::class;
    }
}
