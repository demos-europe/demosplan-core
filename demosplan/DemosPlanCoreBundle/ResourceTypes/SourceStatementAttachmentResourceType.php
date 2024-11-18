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

use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceCreateFlushEvent;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<StatementAttachment>
 *
 * @property-read FileResourceType      $file
 * @property-read StatementResourceType $statement
 */
final class SourceStatementAttachmentResourceType extends DplanResourceType
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly StatementResourceType $statementResourceType, private readonly StatementAttachmentService $statementAttachmentService,
    ) {
    }

    public static function getName(): string
    {
        return 'SourceStatementAttachment';
    }

    public function getEntityClass(): string
    {
        return StatementAttachment::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_read_source_statement_via_api');
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        // The access to an attachment is allowed only if access to the corresponding
        // statement is granted.
        return $this->statementResourceType->buildAccessConditions($this->statement, true);
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable()->sortable()->filterable(),
        ];

        if ($this->currentUser->hasPermission('feature_read_source_statement_via_api')) {
            $properties[] = $this->createToOneRelationship($this->file)->readable()->sortable()->filterable()->initializable();
        }

        if ($this->isCreateAllowed()) {
            $properties[] = $this->createToOneRelationship($this->statement)->initializable()->readable();
        }

        return $properties;
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_generic_statement_attachment_add');
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $attributes = $entityData->getAttributes();
                    $toOneRelationships = $entityData->getToOneRelationships();

                    $statementRef = $toOneRelationships[$this->statement->getAsNamesInDotNotation()];
                    Assert::notNull($statementRef);
                    /** @var Statement $statement */
                    $statement = $this->resourceTypeStore->getStatementResourceType()->getEntity($statementRef[ContentField::ID]);

                    $fileRef = $toOneRelationships[$this->file->getAsNamesInDotNotation()];
                    Assert::notNull($fileRef);
                    /** @var File $file */
                    $file = $this->resourceTypeStore->getFileResourceType()->getEntity($fileRef[ContentField::ID]);

                    $attachment = $this->createAttachment($statement, $file);

                    $modifiedEntity = new ModifiedEntity($attachment, []);

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

    private function createAttachment(Statement $statement, File $file): StatementAttachment
    {
        $originalAttachment = $this->statementAttachmentService->createAttachment($statement, $file, StatementAttachment::SOURCE_STATEMENT);
        $this->entityManager->persist($originalAttachment);

        return $originalAttachment;
    }
}
