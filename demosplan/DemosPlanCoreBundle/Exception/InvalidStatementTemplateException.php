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

use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementTemplateValidator;
use Exception;

/**
 * Thrown by {@see StatementTemplateValidator}
 * when an uploaded DOCX template fails validation — typically an unknown
 * placeholder, a missing or duplicated segment-rendering mode marker, or a
 * malformed DOCX. The controller surfaces $exception->getMessage() in the 422 response body.
 */
class InvalidStatementTemplateException extends Exception
{
}
