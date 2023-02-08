<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use EDT\ConditionFactory\PathsBasedConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;

class DplanDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    public function __construct(private PathsBasedConditionFactoryInterface $conditionFactory)
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
