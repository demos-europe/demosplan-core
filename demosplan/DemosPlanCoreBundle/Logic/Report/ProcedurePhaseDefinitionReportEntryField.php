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

enum ProcedurePhaseDefinitionReportEntryField: string
{
    case PHASE_DEFINITION_ID = 'phaseDefinitionId';
    case PHASE_DEFINITION_NAME = 'phaseDefinitionName';
    case FIELD = 'field';
    case OLD_VALUE = 'oldValue';
    case NEW_VALUE = 'newValue';
}
