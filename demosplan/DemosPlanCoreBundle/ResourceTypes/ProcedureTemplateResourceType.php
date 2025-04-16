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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Procedure>
 *
 * @property-read End $master
 * @property-read End $deleted
 * @property-read End $desc
 * @property-read End $description
 * @property-read End $agencyMainEmailAddress
 * @property-read End $masterTemplate
 * @property-read End $coordinate
 * @property-read ProcedureMapSettingResourceType $mapSetting
 * @property-read AgencyEmailAddressResourceType $agencyExtraEmailAddresses
 * @property-read CustomFieldResourceType $segmentCustomFields
 * @property-read OrgaResourceType $orga               Do not expose! Alias usage only.
 * @property-read OrgaResourceType $owningOrganisation
 */
final class ProcedureTemplateResourceType extends DplanResourceType
{
    public function __construct(private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    /**
     * @throws PathException
     */
    protected function getProperties(): array
    {
        if ($this->currentUser->hasAnyPermissions('area_public_participation', 'area_admin_map')) {
            $properties[] = $this->createToOneRelationship($this->mapSetting)->aliasedPath(Paths::procedure()->settings)->readable();
        }

        $properties[] = $this->createIdentifier()->readable()->sortable()->filterable();
        $properties[] = $this->createAttribute($this->agencyMainEmailAddress)->readable(true)->sortable()->filterable();
        $properties[] = $this->createAttribute($this->description)->readable()->aliasedPath($this->desc);
        $properties[] = $this->createToManyRelationship($this->agencyExtraEmailAddresses)->readable()->filterable();
        $properties[] = $this->createToOneRelationship($this->owningOrganisation)->readable()->aliasedPath($this->orga)->sortable()->filterable();


        if ($this->currentUser->hasAnyPermissions('area_admin_custom_fields')) {
            $properties[] = $this->createToManyRelationship($this->segmentCustomFields)
                ->readable(true, function (Procedure $procedure): ?ArrayCollection {
                    return $this->customFieldConfigurationRepository->getCustomFields('PROCEDURE_TEMPLATE', $procedure->getId(), 'SEGMENT');

                });
        }

        return $properties;
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
