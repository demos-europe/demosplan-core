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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @property-read End $assignee @deprecated refactor frontend and backend to use a relationship instead
 * @property-read End $documentParentId @deprecated Use {@link StatementResourceType::$document} instead
 * @property-read End $documentTitle @deprecated Use a relationship to {@link SingleDocumentVersion} instead
 * @property-read End $elementId @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read End $elementTitle @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read End $originalId @deprecated Use a relationship instead
 * @property-read End $paragraphParentId @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read End $paragraphTitle @deprecated Use {@link StatementResourceType::$paragraph} instead
 */
final class ClusterStatementResourceType extends AbstractStatementResourceType
{
    public static function getName(): string
    {
        return 'Cluster';
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_cluster');
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('area_admin_assessmenttable');
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyIsNotNull($this->original),
            $this->conditionFactory->propertyHasValue(true, $this->clusterStatement),
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            // only get non-clusterMember to avoid getting cluster of cluster and
            // bring result into line with ES result in ATable:
            $this->conditionFactory->propertyIsNull($this->headStatement),
            // statement placeholders are not considered actual statement resources
            $this->conditionFactory->propertyIsNull($this->movedStatement),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id)
        );
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->externId),
        ];
    }

    protected function getProperties(): array
    {
        if (!$this->currentUser->hasPermission('feature_statement_cluster')) {
            return [
                $this->createAttribute($this->id)->readable(true),
            ];
        }

        $properties = parent::getProperties();
        $additionalProperties = [
            $this->createAttribute($this->documentParentId)
                ->readable(true, static function (Statement $statement): ?string {
                    return $statement->getDocumentParentId();
                }),
            $this->createAttribute($this->documentTitle)
                ->readable(true, static function (Statement $statement): ?string {
                    return $statement->getDocumentTitle();
                }),
            $this->createAttribute($this->elementId)
                ->readable(true)->aliasedPath($this->element->id),
            $this->createAttribute($this->elementTitle)
                ->readable(true)->aliasedPath($this->element->title),
            $this->createAttribute($this->originalId)
                ->readable(true)->aliasedPath($this->original->id),
            $this->createAttribute($this->paragraphParentId)
                ->readable(true)->aliasedPath($this->paragraph->paragraph->id),
            $this->createAttribute($this->paragraphTitle)
                ->readable(true)->aliasedPath($this->paragraph->title),
            $this->createAttribute($this->assignee)
                ->readable(true, static function (Statement $statement): ?array {
                    $assignee = $statement->getAssignee();
                    if (null === $assignee) {
                        return null;
                    }

                    return [
                        'id'       => $assignee->getId(),
                        'name'     => $assignee->getName(),
                        'orgaName' => $assignee->getOrgaName(),
                    ];
                }),
            $this->createAttribute($this->authorName)
                ->readable(true)->filterable()->aliasedPath($this->meta->authorName),
            $this->createAttribute($this->submitName)
                ->readable(true)->filterable()->sortable()->aliasedPath($this->meta->submitName),
        ];

        return array_merge($properties, $additionalProperties);
    }
}
