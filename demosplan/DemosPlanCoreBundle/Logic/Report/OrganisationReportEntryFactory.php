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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;

class OrganisationReportEntryFactory extends AbstractReportEntryFactory
{
    public function createUpdateEntry(
        string $identifier,
        array $message,
        array $incoming,
        $user,
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
        $showlistChangeReason,
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

    /**
     * Documents who switched the two-factor requirement of an orga on or off.
     */
    public function createTwoFactorRequiredEntry(
        $user,
        string $identifier,
        string $orgaName,
        bool $twoFactorRequired,
    ): ReportEntry {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_ORGA_TWO_FACTOR_REQUIRED_CHANGE);
        $entry->setUser($user);
        $entry->setIdentifier($identifier);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_ORGANISATION);
        $entry->setMessage(Json::encode([
            'ident'                   => $identifier,
            'name'                    => $orgaName,
            Orga::TWO_FACTOR_REQUIRED => $twoFactorRequired,
        ], JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $entry = parent::createReportEntry();
        $entry->setGroup(ReportEntry::GROUP_ORGA);

        return $entry;
    }
}
