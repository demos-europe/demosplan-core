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

use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\TextSectionResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<TextSection>
 *
 * @property-read End $orderInStatement
 * @property-read End $textRaw
 * @property-read End $text
 */
final class TextSectionResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'TextSection';
    }

    public function getEntityClass(): string
    {
        return TextSection::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_statement',
            'area_statement_segmentation'
        );
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isGetAllowed(): bool
    {
        return true;
    }

    public function isListAllowed(): bool
    {
        return true;
    }

    public function isUpdateAllowed(): bool
    {
        return false;
    }

    public function isCreateAllowed(): bool
    {
        return false;
    }

    public function isDeleteAllowed(): bool
    {
        return false;
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $config = $this->getConfig(TextSectionResourceConfigBuilder::class);

        $config->id->setReadableByPath();
        $config->orderInStatement->setReadableByPath();
        $config->textRaw->setReadableByPath();
        $config->text->setReadableByPath();
        $config->statement->setReadableByPath();

        return $config;
    }
}
