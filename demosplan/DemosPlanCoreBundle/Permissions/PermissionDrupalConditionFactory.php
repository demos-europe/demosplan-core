<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use EDT\ConditionFactory\PathsBasedConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * Adds the `FALSE` operator to the usual Drupal filter operators. This operator is currently only needed
 * when evaluating permission filters and should not be exposed in the Web-API.
 */
class PermissionDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    public const FALSE = 'FALSE';

    protected PathsBasedConditionFactoryInterface $conditionFactory;

    public function __construct(PathsBasedConditionFactoryInterface $conditionFactory)
    {
        parent::__construct($conditionFactory);
        $this->conditionFactory = $conditionFactory;
    }

    protected function getOperatorFunctions(): array
    {
        $functions = parent::getOperatorFunctions();
        $functions[self::FALSE] = fn (array $path, $conditionValue): PathsBasedInterface => $this->conditionFactory->false();

        return $functions;
    }
}
