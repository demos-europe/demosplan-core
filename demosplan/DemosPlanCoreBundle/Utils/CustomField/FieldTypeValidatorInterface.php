<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

/**
 * Each field type validator implements this contract. Clean separation of concerns - each validator only knows about its own field type.
 */
interface FieldTypeValidatorInterface
{
    /**
     * Check if this validator supports the given field type.
     */
    public function supports(string $fieldType): bool;

    /**
     * Validate attributes for this specific field type.
     */
    public function validate(array $attributes): void;

    /**
     * Get the field type this validator handles.
     */
    public function getFieldType(): string;
}
