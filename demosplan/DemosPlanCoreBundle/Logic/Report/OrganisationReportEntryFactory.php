<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;

class OrganisationReportEntryFactory extends AbstractReportEntryFactory
{
    public function createUpdateEntry(
        string $identifier,
        array $message,
        array $incoming,
        $user
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setUser($user);
        $entry->setIdentifier($identifier);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_ORGANISATION);
        $entry->setMessage(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIncoming(Json::encode($incoming, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    public function createShowlistEntry(
        $user,
        string $identifier,
        array $message,
        $showlistChangeReason
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ORGA_SHOWLIST_CHANGE);
        $entry->setUser($user);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_ORGANISATION);
        $entry->setMessage(Json::encode($showlistChangeReason, JSON_UNESCAPED_UNICODE));
        $entry->setIncoming(Json::encode($message, JSON_UNESCAPED_UNICODE));
        $entry->setIdentifier($identifier);

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $entry = parent::createReportEntry();
        $entry->setGroup(ReportEntry::GROUP_ORGA);

        return $entry;
    }
}
