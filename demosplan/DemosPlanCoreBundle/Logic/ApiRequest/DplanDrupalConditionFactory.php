<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\ConditionFactory\PathsBasedConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * This class is only needed temporary to fix a bug in a third-party dependency. After the `demos-europe/edt-...`
 * dependencies has been updated to `^0.17`, this class can be removed and its usages replaced with
 * `PredefinedDrupalConditionFactory`.
 */
class DplanDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    public function __construct(private readonly PathsBasedConditionFactoryInterface $conditionFactory)
    {
        parent::__construct($conditionFactory);
    }

    public function getOperatorFunctions(): array
    {
        $operators = parent::getOperatorFunctions();
        $operators['IS NULL'] = fn ($conditionValue, array $path): PathsBasedInterface => $this->conditionFactory->propertyIsNull($path);
        $operators['IS NOT NULL'] = fn ($conditionValue, array $path): PathsBasedInterface => $this->conditionFactory->propertyIsNotNull($path);

        return $operators;
    }
}
