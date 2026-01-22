<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum;

enum CustomFieldPropertyName: string
{
    case columnName = 'custom_fields';
    case twigRequestName = 'customFields';
}
