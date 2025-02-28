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
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;

class ParagraphReportEntryFactory extends AbstractReportEntryFactory
{
    public function __construct(
        CurrentUserInterface $currentUserProvider,
        CustomerService $currentCustomerProvider,
    ) {
        parent::__construct($currentUserProvider, $currentCustomerProvider);
    }

    /**
     * @throws JsonException
     */
    private function createParagraphReportEntry(string $procedureId, array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setGroup(ReportEntry::GROUP_PARAGRAPH);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    private function createData(Paragraph $paragraph): array
    {
        return [
            'paragraphId'       => $paragraph->getId(),
            'paragraphTitle'    => $paragraph->getTitle(),
            'paragraphText'     => $paragraph->getText(),
            'paragraphCategory' => $paragraph->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'elementCategory'   => $paragraph->getElement()->getCategory(), // eg map, file, statement, paragraph, ..
            'elementTitle'      => $paragraph->getElement()->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'visible'           => $paragraph->getVisible(),
            'procedurePhase'    => $paragraph->getProcedure()->getPhase(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function createParagraphCreateEntry(Paragraph $paragraph): ReportEntry
    {
        $data = $this->createData($paragraph);
        $data['date'] = $paragraph->getCreateDate()->getTimestamp();
        $reportEntry = $this->createParagraphReportEntry($paragraph->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_ADD);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createParagraphUpdateEntry(Paragraph $paragraph): ReportEntry
    {
        $data = $this->createData($paragraph);
        $data['date'] = $paragraph->getModifyDate()->getTimestamp();
        $reportEntry = $this->createParagraphReportEntry($paragraph->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_UPDATE);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createParagraphDeleteEntry(Paragraph $paragraph): ReportEntry
    {
        $data = $this->createData($paragraph);
        $data['date'] = Carbon::now()->getTimestamp();
        $reportEntry = $this->createParagraphReportEntry($paragraph->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_DELETE);

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
