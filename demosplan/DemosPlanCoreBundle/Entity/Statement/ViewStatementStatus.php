<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\ViewStatementStatusInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

class ViewStatementStatus extends CoreEntity implements ViewStatementStatusInterface
{
    /**
     * @ORM\Id()
     *
     * @ORM\Column(type="string", length=255)
     */
    private string $statement;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $status;

    public function getStatement(): string
    {
        return $this->statement;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
