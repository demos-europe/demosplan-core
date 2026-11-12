<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

/**
 * Thrown while the circuit breaker is open. The outage was already reported to the user by the
 * request that opened it.
 */
class LanguageToolUnavailableException extends LanguageToolServiceException
{
}
