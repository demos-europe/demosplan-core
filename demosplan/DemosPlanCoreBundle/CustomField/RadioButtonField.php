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

class RadioButtonField extends AbstractOptionsCustomField
{
    public function getFieldType(): string
    {
        return 'singleSelect';
    }

    public function formatValueForDisplay(mixed $value): string
    {
        if (!is_string($value) || '' === $value) {
            return '';
        }

        return $this->getCustomOptionValueById($value)?->getLabel() ?? '';
    }
}
