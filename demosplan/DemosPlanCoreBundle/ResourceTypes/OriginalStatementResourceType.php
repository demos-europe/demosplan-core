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

use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\IsOriginalStatementAvailableEventInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\OriginalStatementResourceTypeInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\IsOriginalStatementAvailableEvent;
use demosplan\DemosPlanCoreBundle\Exception\UndefinedPhaseException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementProcedurePhaseResolver;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\OriginalStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryStatement;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use Elastica\Index;

/**
 * @template-extends DplanResourceType<StatementInterface>
 *
 * @property-read ProcedureResourceType $procedure
 * @property-read StatementResourceType $original
 * @property-read End                   $deleted
 * @property-read StatementResourceType $headStatement
 * @property-read StatementResourceType $movedStatement
 * @property-read StatementResourceType $parentStatementOfSegment Do not expose! Alias usage only.
 */
final class OriginalStatementResourceType extends DplanResourceType implements OriginalStatementResourceTypeInterface, ReadableEsResourceTypeInterface
{
    public function __construct(
        private readonly QueryStatement $esQuery,
        private readonly FileService $fileService,
        private readonly StatementService $statementService,
        private readonly StatementProcedurePhaseResolver $statementProcedurePhaseResolver,
        private readonly FileContainerRepository $fileContainerRepository,
        private readonly JsonApiEsService $jsonApiEsService
    ) {
    }

    public static function getName(): string
    {
        return 'OriginalStatement';
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public function isAvailable(): bool
    {
        /** @var IsOriginalStatementAvailableEvent $event * */
        $event = $this->eventDispatcher->dispatch(new IsOriginalStatementAvailableEvent(), IsOriginalStatementAvailableEventInterface::class);

        return $event->isOriginalStatementeAvailable() || $this->hasAccessPermissions();
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyIsNull($this->original->id),
            $this->conditionFactory->propertyIsNull($this->headStatement->id),
            $this->conditionFactory->propertyIsNull($this->movedStatement),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
            // filter out segments
            $this->conditionFactory->propertyIsNull($this->parentStatementOfSegment),
        ];
    }

    public function isGetAllowed(): bool
    {
        return $this->hasAccessPermissions();
    }

    public function isListAllowed(): bool
    {
        return $this->hasAccessPermissions();
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $originalStatementConfig = $this->getConfig(OriginalStatementResourceConfigBuilder::class);
        $originalStatementConfig->id->setReadableByPath()->SetFilterable();
        $originalStatementConfig->externId->setReadableByPath();
        $originalStatementConfig->meta->setRelationshipType(
            $this->getTypes()->getStatementMetaResourceType()
        )->setReadableByPath();
        $originalStatementConfig->submitDate->setReadableByPath()->setAliasedPath(Paths::statement()->submit);
        $originalStatementConfig->isSubmittedByCitizen
            ->setReadableByCallable(static fn (Statement $statement): bool => $statement->isSubmittedByCitizen());
        $originalStatementConfig->fullText->setReadableByPath()->setAliasedPath(Paths::statement()->text);
        $originalStatementConfig->shortText->setReadableByCallable(
            static fn (Statement $statement): string => $statement->getTextShort()
        )->setAliasedPath(Paths::statement()->text);
        $originalStatementConfig->textIsTruncated
            ->setReadableByCallable(static fn (Statement $statement): bool => $statement->getText() !== $statement->getTextShort());
        $originalStatementConfig->procedurePhase
            ->setReadableByCallable(function (Statement $statement): ?array {
                try {
                    return $this->statementProcedurePhaseResolver->getProcedurePhaseVO($statement->getPhase(), $statement->isSubmittedByCitizen())->jsonSerialize();
                } catch (UndefinedPhaseException $e) {
                    $this->logger->error($e->getMessage());

                    return null;
                }
            });
        $originalStatementConfig->elements
        ->setRelationshipType($this->resourceTypeStore->getPlanningDocumentCategoryDetailsResourceType())
        ->setReadableByPath()->aliasedPath(Paths::statement()->element);
        $originalStatementConfig->document
        ->setRelationshipType($this->resourceTypeStore->getSingleDocumentResourceType())
        ->setReadableByCallable(
            static fn (Statement $statement): ?SingleDocumentInterface => $statement->getDocument()?->getSingleDocument()
        );
        $originalStatementConfig->paragraph
        ->setRelationshipType($this->resourceTypeStore->getParagraphVersionResourceType())
        ->setReadableByPath();
        $originalStatementConfig->polygon->setReadableByPath();
        $originalStatementConfig->attachmentsDeleted->setReadableByCallable(
            static fn (Statement $statement): bool => $statement->isAttachmentsDeleted()
        );
        $originalStatementConfig->submitterAndAuthorMetaDataAnonymized->setReadableByCallable(
            static fn (Statement $statement): bool => $statement->isSubmitterAndAuthorMetaDataAnonymized()
        );
        $originalStatementConfig->textPassagesAnonymized->setReadableByCallable(
            static fn (Statement $statement): bool => $statement->isTextPassagesAnonymized()
        );
        $originalStatementConfig->sourceAttachment
            ->setRelationshipType($this->resourceTypeStore->getSourceStatementAttachmentResourceType())
            ->setReadableByPath()
            ->aliasedPath(Paths::statement()->attachments);
        $originalStatementConfig->genericAttachments
            ->setRelationshipType($this->resourceTypeStore->getGenericStatementAttachmentResourceType())
            ->readable(false, function (Statement $statement): ?array {
                $fileContainers = $this->fileContainerRepository->getStatementFileContainers($statement->getId());

                return $fileContainers;
            });

        $originalStatementConfig->procedure
            ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
            ->setReadableByPath()
            ->setFilterable();

        return $originalStatementConfig;
    }

    private function hasAccessPermissions(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_original_statement');
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return $this->esQuery->getScopes();
    }

    public function getSearchType(): Index
    {
        return $this->jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public function getFacetDefinitions(): array
    {
        return [];
    }
}
