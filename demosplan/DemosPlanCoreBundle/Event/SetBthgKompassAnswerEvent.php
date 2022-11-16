<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class SetBthgKompassAnswerEvent extends DPlanEvent
{
    /**
     * @param array
     */
    private $data;

    /**
     * @param Statement
     */
    private $statement;

    public function __construct(array $data, Statement $statement)
    {
        $this->data = $data;
        $this->statement = $statement;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }
}
