<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Customer;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;

class CustomerDeleter extends CoreService
{
    public function __construct(
        private readonly SqlQueriesService $queriesService,
        private readonly ProcedureRepository $procedureRepository,
        private readonly ProcedureDeleter $procedureDeleter
    ) {
    }

    public function deleteCustomer(string $customerId): void
    {
    }
}
