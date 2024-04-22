<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use EDT\ConditionFactory\PathsBasedConditionFactoryInterface;
use EDT\Querying\ConditionParsers\Drupal\PredefinedDrupalConditionFactory;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * Adds support for additional operators to the usual Drupal filter parsing. These operators are
 * needed when evaluating permission filters. This class should be used for permission parsing only;
 * if the added operators are needed for other places, e.g. via the Web-API too, it is valid to copy
 * their definitions into a separate class instead of trying to reuse this one.
 */
class PermissionDrupalConditionFactory extends PredefinedDrupalConditionFactory
{
    final public const FALSE = 'FALSE';

    final public const NOT_SIZE = 'NOT SIZE';

    public function __construct(PathsBasedConditionFactoryInterface $conditionFactory)
    {
        parent::__construct($conditionFactory);
    }

    protected function getOperatorFunctionsWithoutValue(): array
    {
        $operators = parent::getOperatorFunctionsWithoutValue();

        $operators[self::FALSE] = fn (array $path): PathsBasedInterface => $this->conditionFactory->false();

        return $operators;
    }

    protected function getOperatorFunctionsWithValue(): array
    {
        $operators = parent::getOperatorFunctionsWithValue();

        $operators[self::NOT_SIZE] = fn (
            $conditionValue, array $path
        ): PathsBasedInterface => $this->conditionFactory->propertyHasNotSize($this->assertPrimitiveNonNull($conditionValue), $this->assertPath($path));

        return $operators;
    }
}
