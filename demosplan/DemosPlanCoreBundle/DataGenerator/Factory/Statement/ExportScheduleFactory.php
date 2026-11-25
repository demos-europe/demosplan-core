<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Repository\ScheduledExportRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ExportSchedule>
 *
 * @method        ExportSchedule|Proxy                               create(array|callable $attributes = [])
 * @method static ExportSchedule|Proxy                               createOne(array $attributes = [])
 * @method static ExportSchedule|Proxy                               find(object|array|mixed $criteria)
 * @method static ExportSchedule|Proxy                               findOrCreate(array $attributes)
 * @method static ExportSchedule|Proxy                               first(string $sortedField = 'id')
 * @method static ExportSchedule|Proxy                               last(string $sortedField = 'id')
 * @method static ExportSchedule|Proxy                               random(array $attributes = [])
 * @method static ExportSchedule|Proxy                               randomOrCreate(array $attributes = [])
 * @method static ScheduledExportRepository|ProxyRepositoryDecorator repository()
 * @method static ExportSchedule[]|Proxy[]                           all()
 * @method static ExportSchedule[]|Proxy[]                           createMany(int $number, array|callable $attributes = [])
 * @method static ExportSchedule[]|Proxy[]                           createSequence(iterable|callable $sequence)
 * @method static ExportSchedule[]|Proxy[]                           findBy(array $attributes)
 * @method static ExportSchedule[]|Proxy[]                           randomRange(int $min, int $max, array $attributes = [])
 * @method static ExportSchedule[]|Proxy[]                           randomSet(int $number, array $attributes = [])
 */
final class ExportScheduleFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        $parameters = '{}';

        return [
            'userId'         => self::faker()->uuid(),
            'procedureId'    => self::faker()->uuid(),
            'parameters'     => $parameters,
            'parametersHash' => hash('sha256', $parameters),
            'frequency'      => ExportSchedule::FREQUENCY_DAILY,
            'nextRunAt'      => new DateTime('tomorrow'),
        ];
    }

    public static function class(): string
    {
        return ExportSchedule::class;
    }
}
