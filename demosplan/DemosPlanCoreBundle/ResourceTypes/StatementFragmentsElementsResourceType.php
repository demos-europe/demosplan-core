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

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementFragment>
 *
 * @property-read ParagraphVersionResourceType $paragraph
 * @property-read End $paragraphTitle @deprecated use {@link StatementFragmentsElementsResourceType::$paragraph} instead
 * @property-read End $elementTitle @deprecated use {@link StatementFragmentsElementsResourceType::$element} instead
 * @property-read PlanningDocumentCategoryResourceType $element
 */
final class StatementFragmentsElementsResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'StatementFragmentsElements';
    }

    public function getEntityClass(): string
    {
        return StatementFragment::class;
    }

    public function isAvailable(): bool
    {
        return true;
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
            $this->createIdentifier()->readable()->sortable()
                ->filterable(),
            $this->createAttribute($this->paragraphTitle)->readable(true)
                ->sortable()->filterable()->aliasedPath($this->paragraph->title),
            $this->createAttribute($this->elementTitle)->readable(true)
                ->filterable()->sortable()->aliasedPath($this->element->title),
        ];
    }
}
