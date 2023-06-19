<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\OpenGeoDbShortTable;
use demosplan\DemosPlanCoreBundle\Repository\OpenGeoDbRepository;

class OpenGeoDbService extends CoreService
{
    public function __construct(private readonly OpenGeoDbRepository $openGeoDbRepository)
    {
    }

    /**
     * @return OpenGeoDbShortTable[]
     */
    public function getAll()
    {
        return $this->openGeoDbRepository->findAll();
    }
}
