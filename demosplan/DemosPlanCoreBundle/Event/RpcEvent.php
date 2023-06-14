<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use Exception;

class RpcEvent extends DPlanEvent
{
    /** @var array{
     *      anonymizeStatementMeta:bool,
     *      anonymizeStatementText:string,
     *      deleteStatementAttachments:bool,
     *      deleteStatementTextHistory:bool
     * }
     */
    protected $actions;

    /** @var Exception|null */
    protected $exception;

    public function __construct(array $actions)
    {
        $this->actions = $actions;
    }

    /**
     * @return array{
     *                anonymizeStatementMeta:bool,
     *                anonymizeStatementText:string,
     *                deleteStatementAttachments:bool,
     *                deleteStatementTextHistory:bool
     *                }
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getException(): ?Exception
    {
        return $this->exception;
    }

    public function setException(Exception $exception): void
    {
        $this->exception = $exception;
    }

    public function hasException(): bool
    {
        return $this->exception instanceof Exception;
    }
}
