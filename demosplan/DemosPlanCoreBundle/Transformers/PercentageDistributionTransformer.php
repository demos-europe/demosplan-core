<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;
use Enqueue\Util\UUID;

class PercentageDistributionTransformer extends BaseTransformer
{
    protected $type = 'PercentageDistribution';

    public function transform(PercentageDistribution $percentageDistribution): array
    {
        return [
            'id'          => UUID::generate(),
            'total'       => $percentageDistribution->getTotal(),
            'percentages' => $percentageDistribution->getPercentages(),
            'absolutes'   => $percentageDistribution->getAbsolutes(),
        ];
    }
}
