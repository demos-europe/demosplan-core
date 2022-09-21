<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class DemosFixture extends AbstractFixture implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|null
     */
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
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
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $connection->executeStatement(
            "UPDATE {$entity} SET {$idField} = \"{$id}\" WHERE {$whereField} = \"{$whereValue}\" LIMIT 1"
        );

        $em->flush();
    }
}
