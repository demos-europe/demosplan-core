<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadSurveyVoteData extends TestFixture implements DependentFixtureInterface
{
    final public const SURVEY_PARK_POSITIVE1 = 'surveyParkPositive1';
    final public const SURVEY_PARK_POSITIVE2 = 'surveyParkPositive2';
    final public const SURVEY_PARK_POSITIVE3 = 'surveyParkPositive3';
    final public const SURVEY_PARK_POSITIVE4 = 'surveyParkPositive4';
    final public const SURVEY_PARK_POSITIVE5 = 'surveyParkPositive5';
    final public const SURVEY_PARK_POSITIVE6 = 'surveyParkPositive6';
    final public const SURVEY_PARK_POSITIVE7 = 'surveyParkPositive7';
    final public const SURVEY_PARK_POSITIVE8 = 'surveyParkPositive8';
    final public const SURVEY_PARK_POSITIVE9 = 'surveyParkPositive9';

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        /** @var Survey $parkSurvey */
        $parkSurvey = $this->getReference(LoadSurveyData::PARK_SURVEY);

        /** @var User $citizen1 */
        $citizen1 = $this->getReference(LoadUserData::TEST_USER_CITIZEN);
        /** @var User $citizen2 */
        $citizen2 = $this->getReference(LoadUserData::TEST_USER_CITIZEN2);
        /** @var User $citizen3 */
        $citizen3 = $this->getReference(LoadUserData::TEST_USER_CITIZEN3);
        /** @var User $citizen4 */
        $citizen4 = $this->getReference(LoadUserData::TEST_USER_CITIZEN4);
        /** @var User $citizen5 */
        $citizen5 = $this->getReference(LoadUserData::TEST_USER_CITIZEN5);
        /** @var User $citizen6 */
        $citizen6 = $this->getReference(LoadUserData::TEST_USER_CITIZEN6);
        /** @var User $citizen7 */
        $citizen7 = $this->getReference(LoadUserData::TEST_USER_CITIZEN7);
        /** @var User $citizen8 */
        $citizen8 = $this->getReference(LoadUserData::TEST_USER_CITIZEN8);
        /** @var User $citizen9 */
        $citizen9 = $this->getReference(LoadUserData::TEST_USER_CITIZEN9);

        $surveyVote1 = new SurveyVote(
            false,
            'das keine gute Idee ist',
            $parkSurvey,
            $citizen1
        );
        $surveyVote1->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote2 = new SurveyVote(
            false,
            'neeee, nicht cool',
            $parkSurvey,
            $citizen2
        );
        $surveyVote2->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote3 = new SurveyVote(
            true,
            'warum nicht, klingt gut',
            $parkSurvey,
            $citizen3
        );
        $surveyVote3->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote4 = new SurveyVote(
            true,
            'es  total super wäre',
            $parkSurvey,
            $citizen4
        );
        $surveyVote4->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote5 = new SurveyVote(
            false,
            'auf keinen Fall kann man ein Park da bauen!',
            $parkSurvey,
            $citizen5
        );
        $surveyVote5->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote6 = new SurveyVote(
            true,
            'ich total dabei bin',
            $parkSurvey,
            $citizen6
        );
        $surveyVote6->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote7 = new SurveyVote(
            false,
            'man noch kein Park braucht',
            $parkSurvey,
            $citizen7
        );
        $surveyVote7->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote8 = new SurveyVote(
            true,
            'die Kindern könnten da spielen',
            $parkSurvey,
            $citizen8
        );
        $surveyVote8->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $surveyVote9 = new SurveyVote(
            true,
            'man ein bisschen firsche Lüft braucht',
            $parkSurvey,
            $citizen9
        );
        $surveyVote9->setTextReview(SurveyVote::PUBLICATION_APPROVED);

        $this->setReference(self::SURVEY_PARK_POSITIVE1, $surveyVote1);
        $this->setReference(self::SURVEY_PARK_POSITIVE2, $surveyVote2);
        $this->setReference(self::SURVEY_PARK_POSITIVE3, $surveyVote3);
        $this->setReference(self::SURVEY_PARK_POSITIVE4, $surveyVote4);
        $this->setReference(self::SURVEY_PARK_POSITIVE5, $surveyVote5);
        $this->setReference(self::SURVEY_PARK_POSITIVE6, $surveyVote6);
        $this->setReference(self::SURVEY_PARK_POSITIVE7, $surveyVote7);
        $this->setReference(self::SURVEY_PARK_POSITIVE8, $surveyVote8);
        $this->setReference(self::SURVEY_PARK_POSITIVE9, $surveyVote9);

        $manager->persist($surveyVote1);
        $manager->persist($surveyVote2);
        $manager->persist($surveyVote3);
        $manager->persist($surveyVote4);
        $manager->persist($surveyVote5);
        $manager->persist($surveyVote6);
        $manager->persist($surveyVote7);
        $manager->persist($surveyVote8);
        $manager->persist($surveyVote9);
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadSurveyData::class,
            LoadUserData::class,
        ];
    }
}
