<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<SingleDocument>
 *
 * @property-read End $title
 * @property-read PlanningDocumentCategoryResourceType $element
 * @property-read End $parentId
 * @property-read End $statementEnabled
 * @property-read End $visible
 * @property-read End $order
 * @property-read End $index
 * @property-read End $fileInfo improve T22479
 */
final class SingleDocumentResourceType extends DplanResourceType
{
    /**
     * @var SingleDocumentService
     */
    private $singleDocumentService;

    public function __construct(SingleDocumentService $singleDocumentService)
    {
        $this->singleDocumentService = $singleDocumentService;
    }

    public static function getName(): string
    {
        return 'SingleDocument';
    }

    public function getEntityClass(): string
    {
        return SingleDocument::class;
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

    public function getAccessCondition(): PathsBasedInterface
    {
        if ($this->currentUser->hasPermission('area_admin_single_document')) {
            return $this->conditionFactory->true();
        }

        return $this->conditionFactory->propertyHasValue(true, $this->visible);
    }

    protected function getProperties(): array
    {
        $properties = [];

        if ($this->currentUser->hasPermission('field_procedure_documents')) {
            $properties = array_merge($properties, [
                $this->createAttribute($this->id)->readable(true)->filterable(),
                $this->createAttribute($this->parentId)
                    ->readable(true)->filterable()->sortable()->aliasedPath($this->element->id),
                $this->createAttribute($this->title)
                    ->readable(true)->filterable()->sortable(),
                $this->createAttribute($this->fileInfo)
                    ->readable(true, function (SingleDocument $document): array {
                        return $this->singleDocumentService->getSingleDocumentInfo($document);
                    }),
                $this->createAttribute($this->index)->readable(true)->aliasedPath($this->order),
            ]);
        }

        if ($this->currentUser->hasPermission('area_admin_single_document')) {
            $properties = array_merge($properties, [
                $this->createAttribute($this->statementEnabled)->readable(),
                $this->createAttribute($this->visible)->readable(),
            ]);
        }

        return $properties;
    }
}
