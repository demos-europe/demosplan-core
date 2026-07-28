<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api;

use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Common contract for the per-resource access checkers used by {@see AbstractDoctrineResourceProvider}
 * implementations (e.g. {@see Place\AccessChecker}).
 */
interface AccessCheckerInterface
{
    public function isAvailable(): bool;

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array;
}
