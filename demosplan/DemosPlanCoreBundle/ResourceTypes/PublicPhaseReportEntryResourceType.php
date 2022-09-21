<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;

final class PublicPhaseReportEntryResourceType extends ReportEntryResourceType
{
    public static function getName(): string
    {
        return 'PublicPhaseReport';
    }

    protected function getGroups(): array
    {
        return [ReportEntry::GROUP_PROCEDURE];
    }

    protected function getCategories(): array
    {
        return [ReportEntry::CATEGORY_CHANGE_PHASES];
    }
}
