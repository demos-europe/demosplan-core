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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

class ProcedurePhaseDefinitionReportEntryFactory extends AbstractReportEntryFactory
{
    /**
     * @throws JsonException
     */
    public function createProcedurePhaseDefinitionUpdateEntry(
        ProcedurePhaseDefinition $procedurePhaseDefinition,
        ProcedurePhaseDefinitionUpdatableField $field,
        mixed $oldValue,
        mixed $newValue,
    ): ReportEntry {
        $data = [
            ProcedurePhaseDefinitionReportEntryField::PHASE_DEFINITION_ID->value   => $procedurePhaseDefinition->getId(),
            ProcedurePhaseDefinitionReportEntryField::PHASE_DEFINITION_NAME->value => $procedurePhaseDefinition->getName(),
            ProcedurePhaseDefinitionReportEntryField::FIELD->value                 => $field->value,
            ProcedurePhaseDefinitionReportEntryField::OLD_VALUE->value             => $oldValue,
            ProcedurePhaseDefinitionReportEntryField::NEW_VALUE->value             => $newValue,
        ];

        $entry = $this->createReportEntry();
        /** @var User $currentUser */
        $currentUser = $this->currentUserProvider->getUser();
        $entry->setUser($currentUser);
        $entry->setGroup(ReportEntry::GROUP_PROCEDURE_PHASE_DEFINITION);
        $entry->setCategory(ReportEntry::CATEGORY_UPDATE);
        $entry->setIdentifier($procedurePhaseDefinition->getId());
        $entry->setIdentifierType(ReportEntry::IDENTIFIER_TYPE_PROCEDURE_PHASE_DEFINITION);
        $entry->setMessage(Json::encode($data, JSON_UNESCAPED_UNICODE));

        return $entry;
    }
}
