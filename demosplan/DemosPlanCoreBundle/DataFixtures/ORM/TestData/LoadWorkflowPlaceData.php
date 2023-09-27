<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadWorkflowPlaceData extends TestFixture implements DependentFixtureInterface
{
    final public const PLACE_REPLY = 'reply';
    final public const PLACE_TECHNICAL_REVIEW = 'technicalReview';
    final public const PLACE_LEGAL_REVIEW = 'legalExamination';
    final public const PLACE_EDITORIAL = 'editorial';
    final public const PLACE_COMPLETED = 'completed';

    public function load(ObjectManager $manager): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);
        $reply = new Place($procedure, 'Erwiderung verfassen', 0);
        $manager->persist($reply);
        $this->setReference(self::PLACE_REPLY, $reply);

        $technicalReview = new Place($procedure, 'Fachtechnische Prüfung', 1);
        $manager->persist($technicalReview);
        $this->setReference(self::PLACE_TECHNICAL_REVIEW, $technicalReview);

        $legalExamination = new Place($procedure, 'Juristische Prüfung', 2);
        $manager->persist($legalExamination);
        $this->setReference(self::PLACE_LEGAL_REVIEW, $legalExamination);

        $editorial = new Place($procedure, 'Lektorat', 3);
        $manager->persist($editorial);
        $this->setReference(self::PLACE_EDITORIAL, $editorial);

        $completed = new Place($procedure, 'Abgeschlossen', 4);
        $manager->persist($completed);
        $this->setReference(self::PLACE_COMPLETED, $completed);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [LoadProcedureData::class];
    }
}
