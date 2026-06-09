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

/**
 * Extends the EDT condition factory with the CUSTOM_FIELD_CONTAINS operator.
 *
 * Filter wire format:
 *   filter[x][condition][path]=customFields.{fieldUuid}
 *   filter[x][condition][value]={optionValue}
 *   filter[x][condition][operator]=CUSTOM_FIELD_CONTAINS
 *
 * Works for both singleSelect and multiSelect custom field types.
 */
class DemosplanDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    public const CUSTOM_FIELD_CONTAINS = 'CUSTOM_FIELD_CONTAINS';

    protected function getOperatorFunctionsWithValue(): array
    {
        return array_merge(
            parent::getOperatorFunctionsWithValue(),
            [
                self::CUSTOM_FIELD_CONTAINS => function (mixed $value, ?array $path): PathsBasedInterface {
                    Assert::notNull($path);
                    Assert::count($path, 2, 'CUSTOM_FIELD_CONTAINS path must be [\'customFields\', \'{fieldId}\'], got: %s');
                    Assert::same($path[0], 'customFields', 'CUSTOM_FIELD_CONTAINS path must start with \'customFields\'');
                    Assert::string($value);

                    return new CustomFieldContainsDqlClause($path[1], $value);
                },
            ]
        );
    }
}
