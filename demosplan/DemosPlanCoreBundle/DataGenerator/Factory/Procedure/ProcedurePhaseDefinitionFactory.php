<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends PersistentProxyObjectFactory<ProcedurePhaseDefinition>
 *
 * @method        ProcedurePhaseDefinition|Proxy     createOne(array $attributes = [])
 * @method static ProcedurePhaseDefinition|Proxy     first(string $sortedField = 'id')
 * @method static ProcedurePhaseDefinition|Proxy     last(string $sortedField = 'id')
 * @method static ProcedurePhaseDefinition|Proxy     random(array $attributes = [])
 * @method static ProcedurePhaseDefinition|Proxy     randomOrCreate(array $attributes = [])
 * @method static ProcedurePhaseDefinition[]|Proxy[] all()
 * @method static ProcedurePhaseDefinition[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProcedurePhaseDefinition[]|Proxy[] findBy(array $attributes)
 */
final class ProcedurePhaseDefinitionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ProcedurePhaseDefinition::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name'            => self::faker()->words(3, true),
            'audience'        => 'internal',
            'permissionSet'   => 'write',
            'orderInAudience' => 1,
        ];
    }
}
