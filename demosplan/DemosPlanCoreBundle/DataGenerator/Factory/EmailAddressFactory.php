<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<EmailAddress>
 *
 * @method        EmailAddress|Proxy                     create(array|callable $attributes = [])
 * @method static EmailAddress|Proxy                     createOne(array $attributes = [])
 * @method static EmailAddress|Proxy                     find(object|array|mixed $criteria)
 * @method static EmailAddress|Proxy                     findOrCreate(array $attributes)
 * @method static EmailAddress|Proxy                     first(string $sortedField = 'id')
 * @method static EmailAddress|Proxy                     last(string $sortedField = 'id')
 * @method static EmailAddress|Proxy                     random(array $attributes = [])
 * @method static EmailAddress|Proxy                     randomOrCreate(array $attributes = [])
 * @method static EmailAddressRepository|RepositoryProxy repository()
 * @method static EmailAddress[]|Proxy[]                 all()
 * @method static EmailAddress[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static EmailAddress[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static EmailAddress[]|Proxy[]                 findBy(array $attributes)
 * @method static EmailAddress[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static EmailAddress[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class EmailAddressFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'fullAddress' => self::faker()->freeEmail(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return EmailAddress::class;
    }
}
