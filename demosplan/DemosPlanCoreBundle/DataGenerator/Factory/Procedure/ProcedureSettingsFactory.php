<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureSettingsRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ProcedureSettings>
 *
 * @method        ProcedureSettings|Proxy                              create(array|callable $attributes = [])
 * @method static ProcedureSettings|Proxy                              createOne(array $attributes = [])
 * @method static ProcedureSettings|Proxy                              find(object|array|mixed $criteria)
 * @method static ProcedureSettings|Proxy                              findOrCreate(array $attributes)
 * @method static ProcedureSettings|Proxy                              first(string $sortedField = 'id')
 * @method static ProcedureSettings|Proxy                              last(string $sortedField = 'id')
 * @method static ProcedureSettings|Proxy                              random(array $attributes = [])
 * @method static ProcedureSettings|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ProcedureSettingsRepository|ProxyRepositoryDecorator repository()
 * @method static ProcedureSettings[]|Proxy[]                          all()
 * @method static ProcedureSettings[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static ProcedureSettings[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static ProcedureSettings[]|Proxy[]                          findBy(array $attributes)
 * @method static ProcedureSettings[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static ProcedureSettings[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ProcedureSettingsFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ProcedureSettings::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'procedure'  => ProcedureFactory::new(),
            'mapExtent'  => self::faker()->randomFloat().','.self::faker()->randomFloat().','.self::faker()->randomFloat().','.self::faker()->randomFloat(),
            'coordinate' => self::faker()->randomFloat().','.self::faker()->randomFloat(),
        ];
    }

    public function withProcedure(ProcedureFactory $procedure): self
    {
        return $this->with(['procedure' => $procedure]);
    }
}
