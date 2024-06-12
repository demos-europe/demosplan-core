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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Municipality>
 *
 * @property-read End $name
 */
final class MunicipalityResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Municipality';
    }

    public function getEntityClass(): string
    {
        return Municipality::class;
    }

    public function isAvailable(): bool
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return false;
        }

        if (!$this->currentUser->hasPermission('area_admin_assessmenttable')) {
            $formDefinition = $procedure->getStatementFormDefinition();
            if (null === $formDefinition) {
                return false;
            }

            if (!$formDefinition->isFieldDefinitionEnabled(StatementFormDefinition::MAP_AND_COUNTY_REFERENCE)) {
                return false;
            }
        }

        return $this->currentUser->hasPermission('field_statement_municipality');
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            // @improve T22478
            $this->createAttribute($this->name)->readable(true, static fn (Municipality $municipality): string => $municipality->getName()),
        ];
    }
}
