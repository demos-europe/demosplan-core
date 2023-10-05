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
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<Paragraph>
 *
 * @property-read End $title
 * @property-read PlanningDocumentCategoryResourceType $element
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

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
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
            $this->createAttribute($this->id)->readable(true)->filterable(),
            $this->createAttribute($this->title)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->element)->readable(),
        ];
    }
}
