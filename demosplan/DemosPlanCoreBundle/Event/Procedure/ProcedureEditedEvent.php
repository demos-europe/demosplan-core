<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class ProcedureEditedEvent extends DPlanEvent
{
    final public const PLAN_PDF = 'planPDF';
    final public const PLAN_DRAW_PDF = 'planDrawPDF';

    /**
     * @var array Current state of the procedure
     */
    protected $originalProcedureArray;

    /**
     * @var array Data to save
     */
    protected $inData;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param string $procedureId
     */
    public function __construct(protected $procedureId, array $originalProcedureArray, array $inData, User $user)
    {
        $this->originalProcedureArray = $originalProcedureArray;
        $this->inData = $inData;
        $this->user = $user;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getOriginalProcedureArray(): array
    {
        return $this->originalProcedureArray;
    }

    public function getInData(): array
    {
        return $this->inData;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
