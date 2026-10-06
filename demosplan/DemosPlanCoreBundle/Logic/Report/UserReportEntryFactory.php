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

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;

class UserReportEntryFactory extends AbstractReportEntryFactory
{
    /**
     * Documents that an administrator removed the second factors of another user,
     * so it stays traceable who restored whose access.
     */
    public function createTwoFactorResetEntry(UserInterface $affectedUser): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setCategory(ReportEntry::CATEGORY_RESET_TWO_FACTOR);
        $entry->setUser($this->currentUserProvider->getUser());
        $entry->setIdentifier($affectedUser->getId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_USER);
        $entry->setMessage(Json::encode([
            'userId'    => $affectedUser->getId(),
            'userName'  => $affectedUser->getFullname(),
            'userEmail' => $affectedUser->getEmail(),
        ], JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $entry = parent::createReportEntry();
        $entry->setGroup(ReportEntry::GROUP_USER);

        return $entry;
    }
}
