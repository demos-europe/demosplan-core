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

use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Paragraph>
 *
 * @property-read End $title
 * @property-read PlanningDocumentCategoryResourceType $element
 * @property-read End $deleted
 */
final class ParagraphResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Paragraph';
    }

    public function getEntityClass(): string
    {
        return Paragraph::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('field_procedure_documents');
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
        return [$this->conditionFactory->propertyHasValue(false, $this->deleted)];
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable()->filterable(),
            $this->createAttribute($this->title)->readable(true)->sortable()->filterable(),
        ];
        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $properties[] = $this->createToOneRelationship($this->element)->readable();
        }

        return $properties;
    }
}
