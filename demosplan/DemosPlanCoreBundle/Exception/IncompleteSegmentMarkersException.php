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
 * Thrown when a DOCX template contains only one of the two required segment-block
 * markers, or when either marker appears more than once.
 */
class IncompleteSegmentMarkersException extends InvalidStatementTemplateException
{
}
