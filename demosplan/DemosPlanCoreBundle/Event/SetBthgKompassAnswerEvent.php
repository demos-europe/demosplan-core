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

class SetBthgKompassAnswerEvent extends DPlanEvent
{
    /**
     * @param array
     */
    private $data;

    /**
     * @param string
     */
    private $statementId;

    public function __construct(array $data, string $statementId)
    {
        $this->data = $data;
        $this->statementId = $statementId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatementId(): array
    {
        return $this->statementId;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }
}
