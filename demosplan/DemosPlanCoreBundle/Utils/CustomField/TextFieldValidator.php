<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;

class TextFieldValidator extends CustomFieldValidator
{
    public function getFieldType(): string
    {
        return 'text';
    }

    public function getSourceToTargetMapping(): array
    {
        return [
            CustomFieldSupportedEntity::customer->value => [CustomFieldSupportedEntity::orga->value],
        ];
    }
}
