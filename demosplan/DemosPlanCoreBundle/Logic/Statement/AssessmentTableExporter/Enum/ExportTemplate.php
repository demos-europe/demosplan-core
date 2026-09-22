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
 * Layout/format variants the assessment table can be exported in. Provided via the
 * `template` request parameter and used to select the matching Twig template.
 */
enum ExportTemplate: string
{
    case CONDENSED = 'condensed';
    case LANDSCAPE = 'landscape';
    case LANDSCAPE_WITH_FRAGMENTS = 'landscapeWithFrags';
    case PORTRAIT = 'portrait';
    case PORTRAIT_WITH_FRAGMENTS = 'portraitWithFrags';
    case PORTRAIT_WITH_PRIORITIZATION = 'portraitWithPrioritization';
}
