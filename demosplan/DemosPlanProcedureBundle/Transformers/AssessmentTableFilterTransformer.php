<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Transformers;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanProcedureBundle\ValueObject\AssessmentTableFilter;

class AssessmentTableFilterTransformer extends BaseTransformer
{
    protected $type = 'AssessmentTableFilter';

    public function transform(AssessmentTableFilter $filterItemObject)
    {
        $filterItem = [
            'name'     => $filterItemObject->getName(),
            'label'    => $filterItemObject->getLabel(),
            'type'     => $filterItemObject->getType(),
            'options'  => $filterItemObject->getAvailableOptions(),
            'selected' => $filterItemObject->getSelectedOptions(),
        ];

        $filterItem['options'] = array_values($filterItem['options']);

        return array_merge($filterItem, [
            'id' => hash('sha256', $filterItem['name'].$filterItem['type']),
        ]);
    }
}
