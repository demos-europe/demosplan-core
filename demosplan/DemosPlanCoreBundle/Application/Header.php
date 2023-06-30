<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Application;

class Header
{
    final public const FILE_HASH = 'X-Demosplan-File-Hash';
    final public const FILE_ID = 'X-Demosplan-File-Id';
    final public const PROCEDURE_ID = 'X-Demosplan-Procedure-Id';
}
