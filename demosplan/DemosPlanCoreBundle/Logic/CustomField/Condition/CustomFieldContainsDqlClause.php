<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\CustomField\Condition;

use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Stringable;

class CustomFieldContainsDqlClause implements ClauseFunctionInterface, Stringable
{
    public function __construct(
        private readonly string $fieldId,
        private readonly string $optionValue,
    ) {
    }

    public function getPropertyPaths(): array
    {
        return [];
    }

    public function asDql(array $valueReferences, array $propertyAliases, string $mainEntityAlias): string
    {
        return sprintf(
            'JSON_CONTAINS_CUSTOM_FIELD(%s.customFields, %s, %s) = 1',
            $mainEntityAlias,
            $valueReferences[0],
            $valueReferences[1],
        );
    }

    public function getClauseValues(): array
    {
        return [$this->fieldId, $this->optionValue];
    }

    public function apply(array $propertyValues): never
    {
        throw new NotYetImplementedException();
    }

    public function __toString(): string
    {
        return static::class;
    }
}
