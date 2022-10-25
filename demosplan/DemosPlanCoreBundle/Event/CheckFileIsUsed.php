<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

class CheckFileIsUsed extends DPlanEvent
{
    /**
     * @param bool $used
     */

    /**
     * @param string $fileId
     */
    public function __construct(string $fileId)
    {
        $this->fileId = $fileId;
    }

    /**
     * @return fileId
     */
    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function setUsed(bool $used)
    {

        $this->used = $used;

        return $this;
    }
}
