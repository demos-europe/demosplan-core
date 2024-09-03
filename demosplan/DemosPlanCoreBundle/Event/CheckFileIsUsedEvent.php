<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

class CheckFileIsUsedEvent extends DPlanEvent
{
    /**
     * @var bool
     */
    private $isUsed = false;

    public function __construct(
        /**
         * @param string
         */
        private readonly string $fileId
    ) {
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function setIsUsed(bool $isUsed)
    {
        $this->isUsed = $isUsed;
    }

    public function getIsUsed(): bool
    {
        return $this->isUsed;
    }
}
