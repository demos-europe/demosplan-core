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
 * Twig template selected for the assessment table export. The case value is the basename
 * of the corresponding `.tex.twig` template under
 * `@DemosPlanCore/DemosPlanAssessmentTable/DemosPlan/`. It is derived from the
 * {@see ExportTemplate} (layout) plus the `original`/`anonymous` flags.
 */
enum ExportTemplateName: string
{
    case EXPORT = 'export';
    case EXPORT_ANONYMOUS = 'export_anonymous';
    case EXPORT_CONDENSED = 'export_condensed';
    case EXPORT_CONDENSED_ANONYMOUS = 'export_condensed_anonymous';
    case EXPORT_FRAGMENTS_ANONYMOUS = 'export_fragments_anonymous';
    case EXPORT_ORIGINAL = 'export_original';
}
