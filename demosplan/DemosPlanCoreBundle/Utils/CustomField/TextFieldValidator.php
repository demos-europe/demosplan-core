<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

class TextFieldValidator extends CustomFieldValidator
{
    public function getFieldType(): string
    {
        return 'text';
    }

    public function getSourceToTargetMapping(): array
    {
        return [
            'CUSTOMER' => 'ORGA',
        ];
    }
}
