<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class MultiSelectField extends AbstractOptionsCustomField
{
    public function getFieldType(): string
    {
        return 'multiSelect';
    }

    public function formatValueForDisplay(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        return collect($value)
            ->map(fn (string $optionId) => $this->getCustomOptionValueById($optionId)?->getLabel())
            ->filter()
            ->implode(', ');
    }
}
