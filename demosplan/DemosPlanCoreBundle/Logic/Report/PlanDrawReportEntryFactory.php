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

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use function PHPUnit\Framework\assertNotNull;

class PlanDrawReportEntryFactory extends AbstractReportEntryFactory
{
    /**
     * @throws JsonException
     */
    private function createPlanDrawReportEntry(string $procedureId, array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setGroup(ReportEntry::GROUP_PLAN_DRAW);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    /**
     * @throws JsonException
     */
    public function createPlanDrawEntry(
        string $procedureId,
        string $oldPlanPDF,
        string $newPlanPDF,
        string $oldPlanDrawPDF,
        string $newPlanDrawPDF,
    ): ReportEntry {
        $messageData = null;

        if (0 !== strcmp($oldPlanPDF, $newPlanPDF)) {
            $messageData['planDrawFile']['old'] = $oldPlanPDF;
            $messageData['planDrawFile']['new'] = $newPlanPDF;
        }

        if (0 !== strcmp($oldPlanDrawPDF, $newPlanDrawPDF)) {
            $messageData['planDrawingExplanation']['old'] = $oldPlanDrawPDF;
            $messageData['planDrawingExplanation']['new'] = $newPlanDrawPDF;
        }

        assertNotNull($messageData);

        $reportEntry = $this->createPlanDrawReportEntry($procedureId, $messageData);
        $reportEntry->setCategory(ReportEntry::CATEGORY_CHANGE);

        return $reportEntry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PLAN_DRAW);

        return $reportEntry;
    }

    private function getCurrentUser(): User
    {
        try {
            $currentUser = $this->currentUserProvider->getUser();
        } catch (UserNotFoundException) {
            $currentUser = new AnonymousUser();
        }

        return $currentUser;
    }
}
