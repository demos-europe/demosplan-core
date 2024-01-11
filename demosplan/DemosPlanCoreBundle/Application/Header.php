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

/**
 * Should be lowercase.
 *
 * @see https://httpwg.org/specs/rfc7540.html#HttpHeaders
 */
class Header
{
    final public const FILE_HASH = 'x-demosplan-file-hash';
    final public const FILE_ID = 'x-demosplan-file-id';
    final public const PROCEDURE_ID = 'x-demosplan-procedure-id';
}
