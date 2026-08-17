<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceAccess;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StatementClusterAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    public function isClusterAccessAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_cluster');
    }

    public function checkClusterAccess(): void
    {
        if (!$this->isClusterAccessAllowed()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        }
    }
}
