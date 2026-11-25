<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Types;

/**
 * Why an OCX delivery failed as a whole, as reported to the sender in `failure.code`.
 */
enum OcxDeliveryFailure: string
{
    case NO_LONGER_ACCEPTED = 'no_longer_accepted';
    case STALLED = 'stalled';
    case INTERNAL_ERROR = 'internal_error';
}
