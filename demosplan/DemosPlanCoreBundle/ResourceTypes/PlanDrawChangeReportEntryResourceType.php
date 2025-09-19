<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;

final class PlanDrawChangeReportEntryResourceType extends ReportEntryResourceType
{
    public static function getName(): string
    {
        return 'PlanDrawReport';
    }

    protected function getGroups(): array
    {
        return [ReportEntry::GROUP_PLAN_DRAW];
    }

    protected function getCategories(): array
    {
        return [
            ReportEntry::CATEGORY_CHANGE,
        ];
    }
}
