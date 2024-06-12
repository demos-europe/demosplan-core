<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Repository\OrgaStatusInCustomerRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<OrgaStatusInCustomer>
 *
 * @method        OrgaStatusInCustomer|Proxy                     create(array|callable $attributes = [])
 * @method static OrgaStatusInCustomer|Proxy                     createOne(array $attributes = [])
 * @method static OrgaStatusInCustomer|Proxy                     find(object|array|mixed $criteria)
 * @method static OrgaStatusInCustomer|Proxy                     findOrCreate(array $attributes)
 * @method static OrgaStatusInCustomer|Proxy                     first(string $sortedField = 'id')
 * @method static OrgaStatusInCustomer|Proxy                     last(string $sortedField = 'id')
 * @method static OrgaStatusInCustomer|Proxy                     random(array $attributes = [])
 * @method static OrgaStatusInCustomer|Proxy                     randomOrCreate(array $attributes = [])
 * @method static OrgaStatusInCustomerRepository|RepositoryProxy repository()
 * @method static OrgaStatusInCustomer[]|Proxy[]                 all()
 * @method static OrgaStatusInCustomer[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static OrgaStatusInCustomer[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static OrgaStatusInCustomer[]|Proxy[]                 findBy(array $attributes)
 * @method static OrgaStatusInCustomer[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static OrgaStatusInCustomer[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<OrgaStatusInCustomer> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<OrgaStatusInCustomer> createOne(array $attributes = [])
 * @phpstan-method static Proxy<OrgaStatusInCustomer> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<OrgaStatusInCustomer> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<OrgaStatusInCustomer> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<OrgaStatusInCustomer> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<OrgaStatusInCustomer> random(array $attributes = [])
 * @phpstan-method static Proxy<OrgaStatusInCustomer> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<OrgaStatusInCustomer> repository()
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> all()
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<OrgaStatusInCustomer>> randomSet(int $number, array $attributes = [])
 */
final class OrgaStatusInCustomerFactory extends ModelFactory
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
            'customer' => CustomerFactory::new(),
            'orga'     => OrgaFactory::new(),
            'orgaType' => OrgaTypeFactory::new(),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
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
        return OrgaStatusInCustomer::class;
    }
}
