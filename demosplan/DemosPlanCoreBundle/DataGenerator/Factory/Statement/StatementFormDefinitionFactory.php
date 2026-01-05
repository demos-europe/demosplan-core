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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends PersistentProxyObjectFactory<StatementFormDefinition>
 *
 * @method        StatementFormDefinition|Proxy     createOne(array $attributes = [])
 * @method static StatementFormDefinition|Proxy     createOrFirst(array $attributes = [])
 * @method static StatementFormDefinition|Proxy     first(string $sortedField = 'id')
 * @method static StatementFormDefinition|Proxy     last(string $sortedField = 'id')
 * @method static StatementFormDefinition|Proxy     random(array $attributes = [])
 * @method static StatementFormDefinition|Proxy     randomOrCreate(array $attributes = [])
 * @method static StatementFormDefinition[]|Proxy[] all()
 * @method static StatementFormDefinition[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static StatementFormDefinition[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static StatementFormDefinition[]|Proxy[] findBy(array $attributes)
 * @method static StatementFormDefinition[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementFormDefinition[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class StatementFormDefinitionFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StatementFormDefinition::class;
    }

    protected function defaults(): array|callable
    {
        return [];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (): void {
            // The constructor already creates default field definitions
            // No additional initialization needed
        });
    }
}
