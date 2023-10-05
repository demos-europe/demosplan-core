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
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Procedure>
 *
 * @property-read End $master
 * @property-read End $deleted
 * @property-read End $desc
 * @property-read End $description
 * @property-read End $agencyMainEmailAddress
 * @property-read End $masterTemplate
 * @property-read AgencyEmailAddressResourceType $agencyExtraEmailAddresses
 * @property-read OrgaResourceType $orga               Do not expose! Alias usage only.
 * @property-read OrgaResourceType $owningOrganisation
 */
final class ProcedureTemplateResourceType extends DplanResourceType
{
    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->agencyMainEmailAddress)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->description)->readable()->aliasedPath($this->desc),
            $this->createToManyRelationship($this->agencyExtraEmailAddresses)->readable()->filterable(),
            $this->createToOneRelationship($this->owningOrganisation)->readable()->aliasedPath($this->orga)->sortable()->filterable(),
        ];
    }

    protected function getAccessConditions(): array
    {
        $userOrga = $this->currentUser->getUser()->getOrga();
        if (null === $userOrga) {
            // users without organisation get no access to any procedure templates
            return [$this->conditionFactory->false()];
        }

        $masterTemplateSubCondition = $this->conditionFactory->propertyHasValue(true, $this->masterTemplate);
        $normalTemplateSubCondition = $this->conditionFactory->allConditionsApply(
            // not the unique master template
            $this->conditionFactory->propertyHasValue(false, $this->masterTemplate),
            // created by the users organisation (ie.: the current user is in the owning organisation of the template)
            $this->conditionFactory->propertyHasValue($userOrga->getId(), $this->orga->id)
        );

        return [
            // a deleted template is not a valid template resource
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            // templates are never actual procedures
            $this->conditionFactory->propertyHasValue(true, $this->master),
            // the template must be either the unique master template or a "normal" template
            $this->conditionFactory->anyConditionApplies($masterTemplateSubCondition, $normalTemplateSubCondition),
        ];
    }

    public function getEntityClass(): string
    {
        return Procedure::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_procedure_templates',
            'feature_procedure_templates'
        );
    }

    public static function getName(): string
    {
        return 'ProcedureTemplate';
    }
}
