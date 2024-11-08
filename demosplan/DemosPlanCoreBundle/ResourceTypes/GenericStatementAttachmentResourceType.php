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

use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\GenericStatementAttachmentConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * @template T of EntityInterface
 *
 * @template-extends DplanResourceType<T>
 *
 * @property-read StatementResourceType $statement
 */
class GenericStatementAttachmentResourceType extends DplanResourceType
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly FileContainerRepository $fileContainerRepository,
        private readonly StatementResourceType $statementResourceType,
    ) {
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(GenericStatementAttachmentConfigBuilder::class);
        $configBuilder->id
            ->setReadableByPath();
        $configBuilder->file
            ->setRelationshipType($this->getTypes()->getFileResourceType())
            ->setReadableByPath();
        $configBuilder->statement
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
            ->readable();

        return $configBuilder;
    }

    protected function getAccessConditions(): array
    {
        return $this->statementResourceType->buildAccessConditions($this->statement, true);
    }

    public static function getName(): string
    {
        return 'GenericStatementAttachment';
    }

    public function getEntityClass(): string
    {
        return FileContainer::class;
    }

    public function isAvailable(): bool
    {
        // @todo doublecheck if this is the right permission
        return $this->currentUser->hasPermission('feature_read_source_statement_via_api');
    }

    public function isDeleteAllowed(): bool
    {
        // @todo doublecheck if this is the right permission
        return $this->currentUser->hasPermission('feature_read_source_statement_via_api');
    }
}
