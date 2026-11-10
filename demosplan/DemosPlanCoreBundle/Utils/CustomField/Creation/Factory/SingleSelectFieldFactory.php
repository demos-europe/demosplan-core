<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation\Factory;

use demosplan\DemosPlanCoreBundle\CustomField\AbstractOptionsCustomField;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;

class SingleSelectFieldFactory extends AbstractSelectFieldFactory
{
    public function supports(string $fieldType): bool
    {
        return 'singleSelect' === $fieldType;
    }

    protected function createField(): AbstractOptionsCustomField
    {
        return new RadioButtonField();
    }
}
