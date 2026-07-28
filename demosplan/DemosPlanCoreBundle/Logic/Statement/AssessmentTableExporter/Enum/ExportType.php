<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum;

/**
 * Export types relevant to the assessment table PDF (condensed) export, provided via
 * the `exportType` request parameter.
 *
 * Note: other exporters (zip, xls, docx) accept further `exportType` values such as
 * `statementsWithAttachments`; those are intentionally not modelled here, as they never
 * reach the condensed PDF flow that uses this enum.
 */
enum ExportType: string
{
    case STATEMENTS_AND_FRAGMENTS = 'statementsAndFragments';
    case STATEMENTS_ONLY = 'statementsOnly';
    case FRAGMENTS_ONLY = 'fragmentsOnly';
}
