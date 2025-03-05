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
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;

class ElementReportEntryFactory extends AbstractReportEntryFactory
{
    /**
     * @throws JsonException
     */
    private function createElementReportEntry(string $procedureId, array $data): ReportEntry
    {
        $entry = $this->createReportEntry();
        $entry->setUser($this->getCurrentUser());
        $entry->setGroup(ReportEntry::GROUP_ELEMENT);
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $entry->setIdentifier($procedureId);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }

    private function createMessageData(Elements $element): array
    {
        return [
            'elementId'           => $element->getId(),
            'elementTitle'        => $element->getTitle(),
            'elementText'         => $element->getText(),
            'elementCategory'     => $element->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'fileName'            => $element->getFileInfo()['name'], // Planungsdokument als Datei
            'parentCategory'      => $element->getParent()?->getCategory(), // eg map, file, statement, paragraph, ..
            'parentTitle'         => $element->getParent()?->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'enabled'             => $element->getEnabled(),
            'procedurePhase'      => $element->getProcedure()->getPhase(),
            'organisations'       => $element->getOrganisationNames(true),
        ];
    }

    /**
     * @throws JsonException
     */
    public function createElementCreateEntry(Elements $element): ReportEntry
    {
        $data = $this->createMessageData($element);
        $data['date'] = $element->getCreateDate()->getTimestamp();
        $reportEntry = $this->createElementReportEntry($element->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_ADD);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createElementUpdateEntry(Elements $element): ReportEntry
    {
        $data = $this->createMessageData($element);
        $data['date'] = $element->getModifyDate()->getTimestamp();
        $reportEntry = $this->createElementReportEntry($element->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_UPDATE);

        return $reportEntry;
    }

    /**
     * @throws JsonException
     */
    public function createElementDeleteEntry(Elements $element): ReportEntry
    {
        $data = $this->createMessageData($element);
        $data['date'] = Carbon::now()->getTimestamp();
        $reportEntry = $this->createElementReportEntry($element->getProcedure()->getId(), $data);
        $reportEntry->setCategory(ReportEntry::CATEGORY_DELETE);

        return $reportEntry;
    }

    protected function createReportEntry(): ReportEntry
    {
        $reportEntry = parent::createReportEntry();
        $reportEntry->setGroup(ReportEntry::GROUP_ELEMENT);

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
