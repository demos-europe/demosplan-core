<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class ClusterStatementCopyNotImplementedException extends NotYetImplementedException
{
    /** @var string */
    protected $externId;

    public function setExternId(string $externId)
    {
        $this->externId = $externId;
    }

    public function getExternId(): string
    {
        return $this->externId;
    }
}
