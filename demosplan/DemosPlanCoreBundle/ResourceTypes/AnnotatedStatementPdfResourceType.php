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

use function array_key_exists;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\CreatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use EDT\PathBuilding\End;
use EDT\JsonApi\ResourceTypes\SetableProperty;
use EDT\Querying\Contracts\FunctionInterface;
use Exception;

/**
 * @template-implements CreatableDqlResourceTypeInterface<AnnotatedStatementPdf>
 * @template-extends DplanResourceType<AnnotatedStatementPdf>
 *
 * @property-read End $status
 * @property-read End $text
 * @property-read End $statementText
 * @property-read AnnotatedStatementPdfPageResourceType $annotatedStatementPdfPages
 * @property-read ProcedureResourceType $procedure
 * @property-read StatementResourceType $statement
 * @property-read FileResourceType $file
 * @property-read End $fileName
 * @property-read End $creationDate
 * @property-read End $created
 */
final class AnnotatedStatementPdfResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface
{
    public function getEntityClass(): string
    {
        return AnnotatedStatementPdf::class;
    }

    public static function getName(): string
    {
        return 'AnnotatedStatementPdf';
    }

    public function getAccessCondition(): FunctionInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            ...$this->procedure->id
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_import_statement_pdf');
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('feature_import_statement_pdf');
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function createObject(array $properties): ResourceChange
    {
        $entity = new AnnotatedStatementPdf();
        $entity->setFile($properties[$this->file->getAsNamesInDotNotation()]);
        $entity->setAnnotatedStatementPdfPages($properties[$this->annotatedStatementPdfPages->getAsNamesInDotNotation()]);
        $entity->setStatus(AnnotatedStatementPdf::PENDING);
        /** @var Procedure $procedure */
        $procedure = $properties[$this->procedure->getAsNamesInDotNotation()];
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure || $procedure->getId() !== $currentProcedure->getId()) {
            throw new InvalidArgumentException('Procedure IDs given in request header and request data do not match');
        }
        $entity->setProcedure($procedure);

        $this->resourceTypeService->validateObject($entity);

        $resourceChange = new ResourceChange($entity, $this, $properties);
        $resourceChange->addEntityToPersist($entity);

        return $resourceChange;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws MessageBagException
     */
    public function addCreationErrorMessage(array $parameters): void
    {
        if (!array_key_exists('file', $parameters) || !$parameters['file'] instanceof File) {
            $this->messageBag->add('error', 'generic.error');
        } else {
            $file = $parameters['file'];
            $this->messageBag->add(
                'error',
                'error.document.not.saved',
                ['documentName' => $file->getFilename()]
            );
        }
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->id)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->status)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->text)
                ->readable(true)->sortable()->filterable()->aliasedPath($this->statementText),
            $this->createToOneRelationship($this->file)->readable()->sortable()->filterable()
                ->initializable(),
            $this->createToManyRelationship($this->annotatedStatementPdfPages, true)
                ->readable(true)->sortable()->filterable()->initializable(),
            $this->createToOneRelationship($this->procedure, true)
                ->readable(true)->sortable()->filterable()->initializable(),
            $this->createToOneRelationship($this->statement, true)
                ->readable(true)->sortable()->filterable(),
        ];

        if ($this->currentUser->hasPermission('feature_import_statement_pdf')) {
            $properties = $this->addDenormalizedFileName($properties);
            $properties[] = $this->createAttribute($this->creationDate)->readable()
                ->aliasedPath($this->created);
        }

        return $properties;
    }

    /**
     * Denormalized file name to avoid include for better performance in large list.
     *
     * Warning: only readable (no filtering/sorting) due to sanitization in
     * {@link File::getFileName}.
     *
     * @param array<int, SetableProperty> $properties
     *
     * @return array<int, SetableProperty>
     */
    private function addDenormalizedFileName(array $properties): array
    {
        $properties[] = $this->createAttribute($this->fileName)->readable()
            ->aliasedPath($this->file->filename);

        return $properties;
    }
}
