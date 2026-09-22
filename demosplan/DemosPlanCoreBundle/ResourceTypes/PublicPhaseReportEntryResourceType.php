<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use EDT\Querying\Contracts\PathException;

final class PublicPhaseReportEntryResourceType extends ReportEntryResourceType
{
    public static function getName(): string
    {
        return 'PublicPhaseReport';
    }

    /**
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [
            $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->allConditionsApply(
                    $this->conditionFactory->propertyHasValue($procedure->getId(), $this->identifier),
                    $this->conditionFactory->propertyHasValue(ReportEntry::GROUP_PROCEDURE, $this->group),
                    $this->conditionFactory->propertyHasValue(ReportEntry::CATEGORY_CHANGE_PHASES, $this->category),
                ),
                $this->conditionFactory->allConditionsApply(
                    $this->conditionFactory->propertyHasValue(ReportEntry::GROUP_PROCEDURE_PHASE_DEFINITION, $this->group),
                    $this->conditionFactory->propertyHasValue(ReportEntry::CATEGORY_UPDATE, $this->category),
                ),
            ),
            $this->conditionFactory->propertyHasValue($customer->getId(), $this->customer->id),
        ];
    }

    protected function getGroups(): array
    {
        return [ReportEntry::GROUP_PROCEDURE, ReportEntry::GROUP_PROCEDURE_PHASE_DEFINITION];
    }

    protected function getCategories(): array
    {
        return [ReportEntry::CATEGORY_CHANGE_PHASES, ReportEntry::CATEGORY_UPDATE];
    }
}
