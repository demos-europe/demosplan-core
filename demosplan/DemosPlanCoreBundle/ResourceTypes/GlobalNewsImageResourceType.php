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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceCreateFlushEvent;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\PathBuilding\End;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\CreationDataInterface;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<StatementAttachment>
 *
 * @property-read End                   $attachmentType
 * @property-read FileResourceType      $file
 * @property-read GlobalNewsResourceType $globalNews
 */
final class GlobalNewsImageResourceType extends DplanResourceType
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly GlobalNewsResourceType $globalNewsResourceType,
    ) {
    }

    public static function getName(): string
    {
        return 'File';
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

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        // The access to an attachment is allowed only if access to the corresponding
        // statement is granted.
        return [$this->conditionFactory->true()];
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->attachmentType)
                ->readable(true)
                ->aliasedPath(Paths::statementAttachment()->type)
                ->sortable()
                ->filterable()
                ->initializable(),
        ];

        if ($this->isCreateAllowed()) {
            $properties[] = $this->createToOneRelationship($this->globalNews)->initializable()->readable();
        }

        return $properties;
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $attributes = $entityData->getAttributes();
                    $toOneRelationships = $entityData->getToOneRelationships();

                    $globalNewsRef = $toOneRelationships[$this->globalNews->getAsNamesInDotNotation()];
                    Assert::notNull($globalNewsRef);
                    /** @var GlobalContent $globalContent */
                    $globalContent = $this->resourceTypeStore->getGlobalNewsResourceType()->getEntity($globalNewsRef[ContentField::ID]);

                    $fileRef = $toOneRelationships[$this->file->getAsNamesInDotNotation()];
                    Assert::notNull($fileRef);
                    /** @var File $file */
                    $file = $this->resourceTypeStore->getFileResourceType()->getEntity($fileRef[ContentField::ID]);

                    $attachmentType = $attributes[$this->attachmentType->getAsNamesInDotNotation()];
                    Assert::stringNotEmpty($attachmentType);

                    $attachment = match ($attachmentType) {
                        StatementAttachmentInterface::SOURCE_STATEMENT => throw new InvalidArgumentException('Creation of non-generic attachments not available.'),
                        StatementAttachmentInterface::GENERIC          => $this->createGenericAttachment($globalContent, $file),
                        default                                        => throw new InvalidArgumentException("Attachment type not available: $attachmentType"),
                    };
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

    /**
     * Adds a generic attachment to the {@link Statement::$files} array via {@link FileContainer},
     * thus circumventing the usage of {@link Statement::$attachments} and an actual
     * {@link StatementAttachment} entity.
     *
     * This is a workaround to allow the creation of generic attachments via this resource type,
     * to avoid the need to adjust the requests later when {@link Statement::$files} is migrated
     * to {@link Statement::$attachments} in the backend.
     *
     * The {@link StatementAttachment} instance available in the return
     * *must not* be persisted. It exists only to
     * return a `StatementAttachment` resource to the client, as is required by the JSON:API
     * implementation.
     */
    private function createGenericAttachment(GlobalContent $globalContent): StatementAttachment
    {
        $this->fileService->addStatementFileContainer(
            $statement->getId(),
            $file->getId(),
            $file->getFileString()
        );

        $attachment = new StatementAttachment();
        $attachment->setId('');
        $attachment->setFile($file);
        $attachment->setStatement($statement);
        $attachment->setType(StatementAttachmentInterface::GENERIC);

        return $attachment;
    }
}
