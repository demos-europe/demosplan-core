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

final class DocumentReportEntryResourceType extends ReportEntryResourceType
{
    public static function getName(): string
    {
        return 'DocumentReport';
    }

    protected function getGroups(): array
    {
        return [ReportEntry::GROUP_DOCUMENT];
    }

    protected function getCategories(): array
    {
        return [
            ReportEntry::CATEGORY_ADD,
            ReportEntry::CATEGORY_UPDATE,
            ReportEntry::CATEGORY_DELETE,
        ];
    }
}
