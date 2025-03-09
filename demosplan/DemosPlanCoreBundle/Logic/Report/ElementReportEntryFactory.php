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
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;

class ElementReportEntryFactory extends AbstractReportEntryFactory
{
    private function createMessageData(Elements $element): array
    {
        return [
            'elementId'           => $element->getId(),
            'elementTitle'        => $element->getTitle(),
            'elementText'         => $element->getText(),
            'elementCategory'     => $element->getCategory(), // eg. file, e_unterlagen, arbeitskreis, informationen,...
            'fileName'            => $element->getFileInfo()->getFileName(), // Planungsdokument als Datei
            'parentCategory'      => $element->getParent()?->getCategory(), // eg map, file, statement, paragraph, ..
            'parentTitle'         => $element->getParent()?->getTitle(), // eg Fehlanzeige, Begründung, Ergänzende Unterlagen, Planzeichnung
            'enabled'             => $element->getEnabled(),
            'procedurePhase'      => $element->getProcedure()->getPhase(),
            'organisations'       => $element->getOrganisationNames(true),
        ];
    }

    public function createElementEntry(Elements $element, string $reportCategory, int $date = null): ReportEntry
    {
        $data = $this->createMessageData($element);
        $data['date'] = null === $date ? Carbon::now()->getTimestamp() : $date;
        $reportEntry = $this->createReportEntry();
        $reportEntry->setUser($this->getCurrentUser());
        $reportEntry->setGroup(ReportEntry::GROUP_ELEMENT);
        $reportEntry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE);
        $reportEntry->setIdentifier($element->getProcedure()->getId());
        $reportEntry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        $reportEntry->setCategory($reportCategory);

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
