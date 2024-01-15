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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

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

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_assessmenttable');
    }

    public function isListAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_assessmenttable');
    }

    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyIsNotNull($this->original),
            $this->conditionFactory->propertyHasValue(true, $this->clusterStatement),
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            // only get non-clusterMember to avoid getting cluster of cluster and
            // bring result into line with ES result in ATable:
            $this->conditionFactory->propertyIsNull($this->headStatement),
            // statement placeholders are not considered actual statement resources
            $this->conditionFactory->propertyIsNull($this->movedStatement),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
        ];
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->externId),
        ];
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        if (!$this->currentUser->hasPermission('feature_statement_cluster')) {
            $configBuilder = $this->getConfig(StatementResourceConfigBuilder::class);
            $configBuilder->id->readable();

            return $configBuilder;
        }

        /** @var StatementResourceConfigBuilder $configBuilder */
        $configBuilder = parent::getProperties();
        $configBuilder->documentParentId
            ->readable(true, static fn (Statement $statement): ?string => $statement->getDocumentParentId());
        $configBuilder->documentTitle
            ->readable(true, static fn (Statement $statement): ?string => $statement->getDocumentTitle());
        $configBuilder->elementId
            ->readable(true)->aliasedPath(Paths::statement()->element->id);
        $configBuilder->elementTitle
            ->readable(true)->aliasedPath(Paths::statement()->element->title);
        $configBuilder->originalId
            ->readable(true)->aliasedPath(Paths::statement()->original->id);
        $configBuilder->paragraphParentId
            ->readable(true)->aliasedPath(Paths::statement()->paragraph->paragraph->id);
        $configBuilder->paragraphTitle
            ->readable(true)->aliasedPath(Paths::statement()->paragraph->title);
        $configBuilder->assignee
            ->setRelationshipType($this->resourceTypeStore->getClaimResourceType())
            ->readable(true);
        $configBuilder->authorName
            ->readable(true)->filterable()->aliasedPath(Paths::statement()->meta->authorName);
        $configBuilder->submitName
            ->readable(true)->filterable()->sortable()->aliasedPath(Paths::statement()->meta->submitName);

        return $configBuilder;
    }
}
