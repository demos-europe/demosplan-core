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

enum TextLineWidth: int
{
    case VERTICAL_SPLIT_VIEW = 8;
    case VERTICAL_NOT_SPLIT_VIEW = 18;
    case HORIZONTAL_SPLIT_VIEW = 12;
    case HORIZONTAL_NOT_SPLIT_VIEW = 27;
}
