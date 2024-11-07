<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Repository\StatementMetaRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<StatementMeta>
 *
 * @method        StatementMeta|Proxy                              create(array|callable $attributes = [])
 * @method static StatementMeta|Proxy                              createOne(array $attributes = [])
 * @method static StatementMeta|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementMeta|Proxy                              findOrCreate(array $attributes)
 * @method static StatementMeta|Proxy                              first(string $sortedField = 'id')
 * @method static StatementMeta|Proxy                              last(string $sortedField = 'id')
 * @method static StatementMeta|Proxy                              random(array $attributes = [])
 * @method static StatementMeta|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementMetaRepository|ProxyRepositoryDecorator repository()
 * @method static StatementMeta[]|Proxy[]                          all()
 * @method static StatementMeta[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementMeta[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementMeta[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementMeta[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementMeta[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class StatementMetaFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StatementMeta::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'authorFeedback'     => self::faker()->boolean(),
            'authorName'         => self::faker()->text(255),
            'caseWorkerName'     => self::faker()->text(255),
            'houseNumber'        => self::faker()->text(255),
            'orgaCity'           => self::faker()->text(255),
            'orgaDepartmentName' => self::faker()->text(255),
            'orgaEmail'          => self::faker()->text(255),
            'orgaName'           => self::faker()->text(255),
            'orgaPostalCode'     => self::faker()->text(255),
            'orgaStreet'         => self::faker()->text(255),
            'statement'          => StatementFactory::new(),
            'submitName'         => self::faker()->text(255),
        ];
    }
}
