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
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;

class ParagraphReportEntryFactory extends AbstractReportEntryFactory
{
    private function createMessageData(Paragraph $paragraph): array
    {
        return [
            'id'                        => $paragraph->getId(),
            'title'                     => $paragraph->getTitle(),
            'text'                      => $paragraph->getText(),
            'category'                  => $paragraph->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'relatedElementCategory'    => $paragraph->getElement()->getCategory(), // eg map, file, statement, paragraph, ..
            'relatedElementTitle'       => $paragraph->getElement()->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'visible'                   => $paragraph->getVisible(),
            'keyOfInternalPhase'        => $paragraph->getProcedure()->getPhase(),
            'keyOfEternalPhase'         => $paragraph->getProcedure()->getPublicParticipationPhase(),
            // The translation of the time the report is created is the important one, not the key
            'nameOfInternalPhase'       => $paragraph->getProcedure()->getPhaseName(),
            'nameOfExternalPhase'       => $paragraph->getProcedure()->getPublicParticipationPhaseName(),
        ];
    }

    public function createParagraphEntry(
        Paragraph $paragraph,
        string $reportCategory,
        ?int $date = null,
    ): ReportEntry {
        $data = $this->createMessageData($paragraph);
        $data['date'] = null === $date ? Carbon::now()->getTimestamp() : $date;
        $reportEntry = $this->createReportEntry();
        $reportEntry->setUser($this->getCurrentUser());
        $reportEntry->setGroup(ReportEntry::GROUP_PARAGRAPH);
        $reportEntry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $reportEntry->setIdentifier($paragraph->getProcedure()->getId());
        $reportEntry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));
        $reportEntry->setCategory($reportCategory);

        return $reportEntry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_PARAGRAPH);

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
