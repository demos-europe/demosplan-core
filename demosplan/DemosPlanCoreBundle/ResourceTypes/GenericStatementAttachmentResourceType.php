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
use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceCreateFlushEvent;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\GenericStatementAttachmentConfigBuilder;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template T of EntityInterface
 *
 * @template-extends DplanResourceType<T>
 *
 * @property-read StatementResourceType $statement
 * @property-read FileResourceType $file
 */
class GenericStatementAttachmentResourceType extends DplanResourceType
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly StatementRepository $statementRepository,
    ) {
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(GenericStatementAttachmentConfigBuilder::class);
        $configBuilder->id
            ->setReadableByPath();
        $configBuilder->file
            ->setRelationshipType($this->getTypes()->getFileResourceType())
            ->setReadableByPath()
            ->addPathCreationBehavior();

        $configBuilder->statement
            ->setRelationshipType($this->getTypes()->getStatementResourceType())
            ->addPathCreationBehavior();

        return $configBuilder;
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $toOneRelationships = $entityData->getToOneRelationships();

                    $statementRef = $toOneRelationships[$this->statement->getAsNamesInDotNotation()];
                    Assert::notNull($statementRef);
                    /** @var Statement $statement */
                    $statement = $this->resourceTypeStore->getStatementResourceType()->getEntity($statementRef[ContentField::ID]);

                    $fileRef = $toOneRelationships[$this->file->getAsNamesInDotNotation()];
                    Assert::notNull($fileRef);
                    /** @var File $file */
                    $file = $this->resourceTypeStore->getFileResourceType()->getEntity($fileRef[ContentField::ID]);

                    $fileContainer = $this->fileService->addStatementFileContainer(
                        $statement->getId(),
                        $file->getId(),
                        $file->getFileString(),
                        false
                    );

                    $modifiedEntity = new ModifiedEntity($fileContainer, []);

                    $this->eventDispatcher->dispatch(new BeforeResourceCreateFlushEvent(
                        $this,
                        $modifiedEntity->getEntity()
                    ));

                    return $modifiedEntity;
                }
            );
        } catch (Exception $exception) {
            $this->addCreationErrorMessage([]);

            throw $exception;
        }
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        return [];
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        // Since the FileContainer does not have a direct reference to the Statement, we need to check the access conditions of the Statement.
        // Therefore, we retrieve the Statement from the FileContainer and verify its access conditions.

        $fileContainer = $this->getEntity($entityIdentifier);
        Assert::notNull($fileContainer);

        $statementConditions = $this->getTypes()->getStatementResourceType()->buildAccessConditions($this->getTypes()->getStatementResourceType());
        $statementConditions[] = $this->conditionFactory->propertyHasValue($fileContainer->getEntityId(), 'id');

        $statement = $this->statementRepository->getEntities($statementConditions, []);

        Assert::notNull($statement);

        parent::deleteEntity($entityIdentifier);
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
        return true;
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_read_source_statement_via_api');
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_generic_statement_attachment_add');
    }
}
