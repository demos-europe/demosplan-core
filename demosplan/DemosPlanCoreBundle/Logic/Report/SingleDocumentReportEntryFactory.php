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

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;

class SingleDocumentReportEntryFactory extends AbstractReportEntryFactory
{
    private function createMessageData(SingleDocument $singleDocument): array
    {
        return [
            'id'                        => $singleDocument->getId(),
            'title'                     => $singleDocument->getTitle(),
            'text'                      => $singleDocument->getText(),
            'category'                  => $singleDocument->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'fileName'               => $singleDocument->getFileInfo()->getFileName(),
            'relatedElementCategory'    => $singleDocument->getElement()->getCategory(), // eg map, file, statement, paragraph, ..
            'relatedElementTitle'       => $singleDocument->getElement()->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'visible'                   => $singleDocument->getVisible(),
            'statement_enabled'         => $singleDocument->isStatementEnabled(),
            'keyOfInternalPhase'        => $singleDocument->getProcedure()->getPhase(),
            'keyOfEternalPhase'         => $singleDocument->getProcedure()->getPublicParticipationPhase(),
            //The translation of the time the report is created is the important one, not the key
            'nameOfInternalPhase'       => $singleDocument->getProcedure()->getPhaseName(),
            'nameOfExternalPhase'       => $singleDocument->getProcedure()->getPublicParticipationPhaseName(),
        ];
    }

    public function createSingleDocumentEntry(
        SingleDocument $singleDocument,
        string $reportCategory,
        int $date = null
    ): ReportEntry {
        $data = $this->createMessageData($singleDocument);
        $data['date'] = null === $date ? Carbon::now()->getTimestamp() : $date;
        $reportEntry = $this->createReportEntry();
        $reportEntry->setUser($this->getCurrentUser());
        $reportEntry->setGroup(ReportEntry::GROUP_SINGLE_DOCUMENT);
        $reportEntry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $reportEntry->setIdentifier($singleDocument->getProcedure()->getId());
        $reportEntry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));
        $reportEntry->setCategory($reportCategory);

        return $reportEntry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_SINGLE_DOCUMENT);

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
