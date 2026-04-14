<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

class MultiSelectFieldValidator extends CustomFieldValidator
{
    public function getFieldType(): string
    {
        return 'multiSelect';
    }

    public function getSourceToTargetMapping(): array
    {
        return [
            'PROCEDURE'          => ['STATEMENT', 'SEGMENT'],
            'PROCEDURE_TEMPLATE' => ['STATEMENT', 'SEGMENT'],
        ];
    }
}
