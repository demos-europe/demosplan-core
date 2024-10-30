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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseParagraphVersionResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ParagraphVersion>
 *
 * @property-read End $title
 * @property-read ParagraphResourceType $paragraph
 */
final class ParagraphVersionResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ParagraphVersion';
    }

    public function getEntityClass(): string
    {
        return ParagraphVersion::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Emulate this behaviour
     * @link \demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService::getEntityVersions
     * @link \demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService::createParagraphVersion
     * @return bool
     */
    public function isCreateAllowed(): bool
    {
        // @todo update to proper conditions
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

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $paragraphVersionConfig = $this->getConfig(BaseParagraphVersionResourceConfigBuilder::class);
        $paragraphVersionConfig->id->setReadableByPath()->setSortable()->setFilterable();
        // $paragraphVersionConfig->paragraph
        //    ->setRelationshipType($this->resourceTypeStore->getParagraphVersionResourceType())->setReadableByPath();
        $paragraphVersionConfig->title->setReadableByPath();

        return $paragraphVersionConfig;
    }
}
