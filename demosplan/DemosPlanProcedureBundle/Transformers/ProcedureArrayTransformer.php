<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Transformers;

use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;

/**
 * @deprecated use {@link ProcedureResourceType} instead
 */
class ProcedureArrayTransformer extends \demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer
{
    protected $type = 'Procedure';

    public function transform(array $procedureSearchResult): array
    {
        return $procedureSearchResult;
    }
}
