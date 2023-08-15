<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

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
 */
final class OrgaStatusInCustomerFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'customer' => CustomerFactory::new(),
            'orga'     => OrgaFactory::new(),
            'orgaType' => OrgaTypeFactory::new(),
            'status'   => 'accepted',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return OrgaStatusInCustomer::class;
    }
}
