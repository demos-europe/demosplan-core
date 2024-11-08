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
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\GenericStatementAttachmentConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use Webmozart\Assert\Assert;

/**
 * @template T of EntityInterface
 *
 * @template-extends DplanResourceType<T>
 *
 * @property-read StatementResourceType $statement
 * /// Generic attachment resource type because when deleting I really lnow that it belongs to the stament.
 * /// also overrite delet emethod to double check the statement id
 */
class GenericStatementAttachmentResourceType extends DplanResourceType
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly FileContainerRepository $fileContainerRepository,
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
       // $configBuilder->statement->setRelationshipType($this->resourceTypeStore->getStatementResourceType())->readable();

        return $configBuilder;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
        // The access to an attachment is allowed only if access to the corresponding
        // statement is granted.
        //$fileContainer = $this->fileContainerRepository->get($this->entityId);
        //Assert::notNull($fileContainer);

        $statement = $this->statementRepository->get($this->entityId);
        Assert::notNull($statement);
        $valueObject = [
            'entityId' => $statement->getId(),
            'entityClass' => Statement::class,
        ];

        new StatementResourceType($this->statementRepository);
        return $this->getTypes()->getStatementResourceType()->buildAccessConditions($valueObject, true);
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

    public function deleteEntity(string $entityIdentifier): void
    {
        // Double check if $entityIdentifier belongs to a statement
        $fileContainer = $this->fileContainerRepository->get($entityIdentifier);
        Assert::notNull($fileContainer);

        $statement = $this->statementRepository->get($fileContainer->getEntityId());
        Assert::notNull($statement);

        parent::deleteEntity($entityIdentifier);
    }
}
