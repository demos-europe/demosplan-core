<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Factory;

use EDT\Querying\ConditionParsers\Drupal\DrupalConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Conditions\IsNotNull;
use EDT\Querying\Conditions\IsNull;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template TCondition of PathsBasedInterface
 * @template-implements DrupalConditionFactoryInterface<TCondition>
 *
 * @phpstan-import-type DrupalValue from DrupalConditionFactoryInterface
 */
class DemosPlanDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    /**
     * @return array<non-empty-string, callable(DrupalValue, non-empty-list<non-empty-string>|null): TCondition>
     */
    protected function getOperatorFunctionsWithValue(): array
    {
        $operators = parent::getOperatorFunctionsWithValue();

        $operators[IsNull::OPERATOR] = fn ($value, ?array $path) => $this->conditionFactory->propertyIsNull($this->assertPath($path));
        $operators[IsNotNull::OPERATOR] = fn ($value, ?array $path) => $this->conditionFactory->propertyIsNotNull($this->assertPath($path));

        return $operators;
    }
}
