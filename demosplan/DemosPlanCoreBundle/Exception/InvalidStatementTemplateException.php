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

use Exception;

/**
 * Base class for all errors arising from a planner-uploaded DOCX template.
 * Subtypes carry the domain-specific data the catching boundary needs to compose
 * the user-facing message bag entry — no translation concerns live here.
 *
 * @see MalformedDocxException
 * @see UnknownPlaceholdersException
 * @see IncompleteSegmentMarkersException
 * @see MissingSegmentBlockException
 */
class InvalidStatementTemplateException extends Exception
{
}
