<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;

abstract class DemosFixture extends AbstractFixture
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Our codebase depends on hard coded id's for things in some places.
     * This method aids in making sure that these exist.
     *
     * @throws Exception
     */
    protected function changeEntityId(
        string $entity,
        string $idField,
        string $id,
        string $whereField,
        string $whereValue
    ): void {
        $connection = $this->entityManager->getConnection();

        if (!$connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $connection->executeStatement('SET foreign_key_checks = 0');
        }

        $connection->executeStatement(
            "UPDATE {$entity} SET {$idField} = \"{$id}\" WHERE {$whereField} = \"{$whereValue}\" LIMIT 1"
        );

        if (!$connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $connection->executeStatement('SET foreign_key_checks = 1');
        }

        $this->entityManager->flush();
    }
}
