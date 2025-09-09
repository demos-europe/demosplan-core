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

final class StatementReportEntryResourceType extends ReportEntryResourceType
{
    public static function getName(): string
    {
        return 'StatementReport';
    }

    protected function getGroups(): array
    {
        return [ReportEntry::GROUP_STATEMENT];
    }

    protected function getCategories(): array
    {
        return [
            ReportEntry::CATEGORY_ADD,
            ReportEntry::CATEGORY_COPY,
            ReportEntry::CATEGORY_DELETE,
            ReportEntry::CATEGORY_MOVE,
            ReportEntry::CATEGORY_ANONYMIZE_META,
            ReportEntry::CATEGORY_ANONYMIZE_TEXT,
            ReportEntry::CATEGORY_DELETE_TEXT_FIELD_HISTORY,
            ReportEntry::CATEGORY_DELETE_ATTACHMENTS,
            ReportEntry::CATEGORY_STATEMENT_SYNC_INSOURCE,
            ReportEntry::CATEGORY_STATEMENT_SYNC_INTARGET,
        ];
    }
}
