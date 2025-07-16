<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\MigrationVersionsInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * MigrationVersions Entity to be able to update.
 *
 * @ORM\Table(name="migration_versions")
 *
 * @ORM\Entity
 */
class MigrationVersions implements MigrationVersionsInterface
{
    /**
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=255, nullable=false)
     *
     * @ORM\Id
     */
    protected $version;

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }
}
