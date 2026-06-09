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

/**
 * DQL clause that generates JSON_CONTAINS_CUSTOM_FIELD(alias.customFields, fieldId, value).
 *
 * Matches segments/statements where the customFields JSON array contains an entry
 * with the given fieldId and the given value. Handles both singleSelect (scalar)
 * and multiSelect (array) field types via MySQL JSON_CONTAINS containment semantics.
 *
 * @implements ClauseFunctionInterface<bool>
 */
class CustomFieldContainsDqlClause implements ClauseFunctionInterface, Stringable
{
    public function __construct(
        private readonly string $fieldId,
        private readonly string $value
    ) {
    }

    public function getPropertyPaths(): array
    {
        return [];
    }

    public function asDql(array $valueReferences, array $propertyAliases, string $mainEntityAlias): string
    {
        [$fieldIdRef, $valueRef] = $valueReferences;

        return "JSON_CONTAINS_CUSTOM_FIELD($mainEntityAlias.customFields, $fieldIdRef, $valueRef) = 1";
    }

    public function getClauseValues(): array
    {
        return [$this->fieldId, $this->value];
    }

    public function apply(array $propertyValues): bool
    {
        throw new NotYetImplementedException();
    }

    public function __toString(): string
    {
        return static::class;
    }
}
