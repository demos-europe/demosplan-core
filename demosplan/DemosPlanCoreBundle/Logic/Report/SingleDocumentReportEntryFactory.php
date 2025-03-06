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
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;

class SingleDocumentReportEntryFactory extends AbstractReportEntryFactory
{
    /**
     * @throws JsonException
     */
    private function createSingleDocumentReportEntry(string $procedureId, array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setGroup(ReportEntry::GROUP_SINGLE_DOCUMENT);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    private function createMessageData(SingleDocument $singleDocument): array
    {
        return [
            'documentId'        => $singleDocument->getId(),
            'documentTitle'     => $singleDocument->getTitle(),
            'documentText'      => $singleDocument->getText(),
            'documentCategory'  => $singleDocument->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'relatedFile'       => $singleDocument->getFileInfo()->getFileName(),
            'elementCategory'   => $singleDocument->getElement()->getCategory(), // eg map, file, statement, paragraph, ..
            'elementTitle'      => $singleDocument->getElement()->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'visible'           => $singleDocument->getVisible(),
            'statement_enabled' => $singleDocument->isStatementEnabled(),
            'procedurePhase'    => $singleDocument->getProcedure()->getPhase(),
        ];
    }

    public function createSingleDocumentEntry(
        SingleDocument $singleDocument,
        string $reportCategory,
        int $date = null
    ): ReportEntry {
        $data = $this->createMessageData($singleDocument);
        $data['date'] = null === $date ? Carbon::now()->getTimestamp() : $date;
        $reportEntry = $this->createSingleDocumentReportEntry($singleDocument->getProcedure()->getId(), $data);
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
