<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ConditionFactory;

use EDT\Querying\ConditionParsers\Drupal\DrupalConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Drupal\StandardOperator;

/**
 * @template TCondition of PathsBasedInterface
 *
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

        $operators[StandardOperator::IS_NULL] = fn ($value, ?array $path): PathsBasedInterface => $this->conditionFactory->propertyIsNull($path);
        $operators[StandardOperator::IS_NOT_NULL] = fn ($value, ?array $path): PathsBasedInterface => $this->conditionFactory->propertyIsNotNull($path);

        return $operators;
    }
}
