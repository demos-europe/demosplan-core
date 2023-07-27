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
use Doctrine\Common\Collections\Collection;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @property-read StatementResourceType $statements
 */
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

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        $properties = parent::getProperties();
        $properties[] = $this->createToManyRelationship($this->statements, true)
            ->readable(true, static fn (Statement $statement): Collection => $statement->getCluster());
        $properties[] = $this->createAttribute($this->authorName)
            ->readable(true)->filterable()->aliasedPath($this->meta->authorName);
        $properties[] = $this->createAttribute($this->submitName)
            ->readable(true)->filterable()->sortable()->aliasedPath($this->meta->submitName);

        return $properties;
    }
}
