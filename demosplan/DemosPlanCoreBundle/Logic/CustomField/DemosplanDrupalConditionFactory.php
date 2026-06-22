<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\CustomField;

use demosplan\DemosPlanCoreBundle\Logic\CustomField\Condition\CustomFieldContainsDqlClause;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;
use Webmozart\Assert\Assert;

class DemosplanDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    final public const CUSTOM_FIELD_CONTAINS = 'CUSTOM_FIELD_CONTAINS';

    protected function getOperatorFunctionsWithValue(): array
    {
        $operators = parent::getOperatorFunctionsWithValue();

        $operators[self::CUSTOM_FIELD_CONTAINS] = function (mixed $value, ?array $path): PathsBasedInterface {
            Assert::notNull($path);
            Assert::string($value);
            [$fieldId, $optionValue] = explode('__', $value, 2);
            Assert::stringNotEmpty($fieldId);
            Assert::stringNotEmpty($optionValue);

            return new CustomFieldContainsDqlClause($fieldId, $optionValue);
        };

        return $operators;
    }
}
