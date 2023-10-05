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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<StatementAttachment>
 *
 * @property-read End                   $type
 * @property-read FileResourceType      $file
 * @property-read StatementResourceType $statement
 */
final class StatementAttachmentResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly StatementResourceType $statementResourceType
    ) {
    }

    public static function getName(): string
    {
        return 'StatementAttachment';
    }

    public function getEntityClass(): string
    {
        return StatementAttachment::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        // Attachments are not supposed to be accessed directly, but as include only.
        // However, when creating an attachment it needs to be directly accessible.
        return $this->isCreatable();
    }

    public function isReferencable(): bool
    {
        return true;
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
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->type)->readable(true)->sortable()->filterable()->initializable(),
        ];

        if ($this->currentUser->hasPermission('feature_read_source_statement_via_api')) {
            $properties[] = $this->createToOneRelationship($this->file)->readable()->sortable()->filterable()->initializable();
        }

        if ($this->isCreatable()) {
            $properties[] = $this->createToOneRelationship($this->statement)->initializable()->readable();
        }

        return $properties;
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('feature_generic_statement_attachment_add');
    }

    public function createObject(array $properties): ResourceChange
    {
        /** @var Statement $statement */
        $statement = $properties[$this->statement->getAsNamesInDotNotation()];
        /** @var File $file */
        $file = $properties[$this->file->getAsNamesInDotNotation()];
        /** @var string|mixed $type */
        $type = $properties[$this->type->getAsNamesInDotNotation()];

        switch ($type) {
            case StatementAttachment::SOURCE_STATEMENT:
                throw new InvalidArgumentException('Creation of non-generic attachments not available.');
            case StatementAttachment::GENERIC:
                return $this->createGenericAttachment($statement, $file, $properties);
            default:
                throw new InvalidArgumentException('Attachment type not available.');
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
     * The {@link StatementAttachment} instance available in the return via
     * {@link ResourceChange::getTargetResource()} *must not* be persisted. It exists only to
     * return a `StatementAttachment` resource to the client, as is required by the JSON:API
     * implementation.
     *
     * @param array<string,mixed> $properties
     */
    private function createGenericAttachment(Statement $statement, File $file, array $properties): ResourceChange
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
        $attachment->setType(StatementAttachment::GENERIC);

        return new ResourceChange($attachment, $this, $properties);
    }
}
