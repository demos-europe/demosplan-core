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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFormDefinitionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends PersistentProxyObjectFactory<ProcedureType>
 *
 * @method        ProcedureType|Proxy     createOne(array $attributes = [])
 * @method static ProcedureType|Proxy     createOrFirst(array $attributes = [])
 * @method static ProcedureType|Proxy     first(string $sortedField = 'id')
 * @method static ProcedureType|Proxy     last(string $sortedField = 'id')
 * @method static ProcedureType|Proxy     random(array $attributes = [])
 * @method static ProcedureType|Proxy     randomOrCreate(array $attributes = [])
 * @method static ProcedureType[]|Proxy[] all()
 * @method static ProcedureType[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProcedureType[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProcedureType[]|Proxy[] findBy(array $attributes)
 * @method static ProcedureType[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProcedureType[]|Proxy[] randomSet(int $number, array $attributes = [])
 */
final class ProcedureTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ProcedureType::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name'                        => self::faker()->words(2, true),
            'description'                 => self::faker()->sentence(),
            'statementFormDefinition'     => StatementFormDefinitionFactory::new(),
            'procedureBehaviorDefinition' => new ProcedureBehaviorDefinition(),
            'procedureUiDefinition'       => new ProcedureUiDefinition(),
        ];
    }

    public function withStatementFormDefinition(StatementFormDefinition $formDefinition): self
    {
        return $this->with(['statementFormDefinition' => $formDefinition]);
    }
}
