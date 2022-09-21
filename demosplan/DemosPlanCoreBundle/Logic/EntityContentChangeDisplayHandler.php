<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\ValueObject\HistoryDay;

class EntityContentChangeDisplayHandler extends CoreHandler
{
    /**
     * @var EntityContentChangeDisplayService
     */
    protected $entityContentChangeDisplayService;

    public function __construct(EntityContentChangeDisplayService $entityContentChangeDisplayService, MessageBag $messageBag)
    {
        $this->entityContentChangeDisplayService = $entityContentChangeDisplayService;
        parent::__construct($messageBag);
    }

    public function getEntityContentChangeDisplayService(): EntityContentChangeDisplayService
    {
        return $this->entityContentChangeDisplayService;
    }

    /**
     * @return array<int, HistoryDay>
     *
     * @see EntityContentChangeDisplayService::getHistoryByEntityId
     */
    public function getHistoryByEntityId(string $entityId, string $class): array
    {
        return $this->getEntityContentChangeDisplayService()->getHistoryByEntityId($entityId, $class);
    }
}
