<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\MigrationVersions;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Finder\Finder;

class LoadMigrationVersionData extends ProdFixture
{
    /**
     * Dynamical returns all version numbers of filename from all generated core migrations.
     *
     * @return string[] - number of versions
     */
    private function getMigrationVersions()
    {
        $versionNumbers = [];
        $finder = new Finder();

        $finder->files()->in(__DIR__.'/../../../DoctrineMigrations/');

        foreach ($finder as $file) {
            // Version20161011100406
            $filename = $file->getFilename();
            $versionNumbers[] = substr($filename, 7, strlen($filename) - 11);
        }

        return $versionNumbers;
    }

    public function load(ObjectManager $manager): void
    {
        $versionNumbers = $this->getMigrationVersions();

        foreach ($versionNumbers as $versionNumber) {
            $version = new MigrationVersions();
            $version->setVersion($versionNumber);
            $manager->persist($version);
        }

        $manager->flush();
    }
}
