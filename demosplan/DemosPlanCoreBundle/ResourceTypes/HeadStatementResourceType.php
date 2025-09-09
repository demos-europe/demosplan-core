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
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class HeadStatementResourceType extends AbstractStatementResourceType
{
    public static function getName(): string
    {
        return 'Headstatement';
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions('area_admin_assessmenttable', 'feature_statement_cluster');
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var StatementResourceConfigBuilder $configBuilder */
        $configBuilder = parent::getProperties();
        $configBuilder->statements
            ->setRelationshipType($this->getTypes()->getStatementResourceType())
            ->readable(true, static fn (Statement $statement): Collection => $statement->getCluster(), true);
        $configBuilder->authorName
            ->readable(true)->filterable()->aliasedPath(Paths::statement()->meta->authorName);
        $configBuilder->submitName
            ->readable(true)->filterable()->sortable()->aliasedPath(Paths::statement()->meta->submitName);

        return $configBuilder;
    }
}
