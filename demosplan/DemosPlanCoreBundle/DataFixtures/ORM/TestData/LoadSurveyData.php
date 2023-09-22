<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadSurveyData extends TestFixture implements DependentFixtureInterface
{
    final public const PARK_SURVEY = 'parkSurvey';

    final public const POOL_SURVEY = 'poolSurvey';

    final public const SCHOOL_SURVEY = 'schoolSurvey';

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);

        /** @var Survey $parkSurvey */
        $parkSurvey = new Survey();
        $parkSurvey->setId('ef047db7-8b19-4feb-8505-003a66afa51f');
        $parkSurvey->setTitle('My awesome first survey');
        $parkSurvey->setDescription('Wollen wir auf diesen Grundstück einen Park bauen?');
        $startDate = new DateTime('2020-05-02');
        $parkSurvey->setStartDate($startDate);
        $endDate = new DateTime('2020-06-12');
        $parkSurvey->setEndDate($endDate);
        $parkSurvey->setStatus('configuration');
        $parkSurvey->setProcedure($procedure);

        $this->setReference(self::PARK_SURVEY, $parkSurvey);
        $manager->persist($parkSurvey);

        $poolSurvey = new Survey();
        $poolSurvey->setId('b24544aa-1547-45c1-b090-3294c685e489');
        $poolSurvey->setTitle('My awesome second survey');
        $poolSurvey->setDescription('Wollen wir auf diesen Grundstück einen Schimmbad '.
                                    'bauen?');
        $startDate = new DateTime('2020-04-17');
        $poolSurvey->setStartDate($startDate);
        $endDate = new DateTime('2020-05-03');
        $poolSurvey->setEndDate($endDate);
        $poolSurvey->setStatus('configuration');
        $poolSurvey->setProcedure($procedure);

        $this->setReference(self::POOL_SURVEY, $poolSurvey);
        $manager->persist($poolSurvey);

        /** @var Procedure $procedure2 */
        $procedure2 = $this->getReference('testProcedure2');

        $schoolSurvey = new Survey();
        $schoolSurvey->setId('8222085d-5e74-411b-8a3b-a1c5e85ca966');
        $schoolSurvey->setTitle('My awesome second survey');
        $schoolSurvey->setDescription('Wollen wir auf diesen Grundstück eine Schule bauen?');
        $startDate = new DateTime('2020-04-18');
        $schoolSurvey->setStartDate($startDate);
        $endDate = new DateTime('2020-04-27');
        $schoolSurvey->setEndDate($endDate);
        $schoolSurvey->setStatus('configuration');
        $schoolSurvey->setProcedure($procedure2);

        $this->setReference(self::SCHOOL_SURVEY, $schoolSurvey);
        $manager->persist($schoolSurvey);
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
