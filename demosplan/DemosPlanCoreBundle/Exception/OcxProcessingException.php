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

use RuntimeException;

/**
 * Processing an OCX delivery hit a state it cannot continue from, e.g. the delivery or its
 * procedure is gone. Not reported to the sender in detail.
 */
class OcxProcessingException extends RuntimeException
{
}
