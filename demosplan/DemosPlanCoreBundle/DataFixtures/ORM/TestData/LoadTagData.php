<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadTagData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $fixtureTopic1 = new TagTopic('DataFixtureTopic_1', $this->getReference('masterBlaupause'));
        $manager->persist($fixtureTopic1);
        $this->setReference('testFixtureTopic_1', $fixtureTopic1);

        $fixtureTopic2 = new TagTopic('DataFixtureTopic_2', $this->getReference('testProcedure2'));
        $manager->persist($fixtureTopic2);
        $this->setReference('testFixtureTopic_2', $fixtureTopic2);

        $fixtureTag1 = new Tag('DataFixtureTag_1', $fixtureTopic1);
        $manager->persist($fixtureTag1);
        $this->setReference('testFixtureTag_1', $fixtureTag1);

        $fixtureTag2 = new Tag('DataFixtureTag_2', $fixtureTopic1);
        $manager->persist($fixtureTag2);
        $this->setReference('testFixtureTag_2', $fixtureTag2);

        $fixtureTag3 = new Tag('DataFixtureTag_3', $fixtureTopic1);
        $manager->persist($fixtureTag3);
        $this->setReference('testFixtureTag_3', $fixtureTag3);

        $fixtureTag4 = new Tag('DataFixtureTag_4', $fixtureTopic2);
        $manager->persist($fixtureTag4);
        $this->setReference('testFixtureTag_4', $fixtureTag4);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);

        $topic1 = new TagTopic('topic1', $testProcedure);
        $manager->persist($topic1);
        $this->setReference('topic1', $topic1);

        $tag1 = new Tag('tag1', $topic1);
        $manager->persist($tag1);
        $this->setReference('tag1', $tag1);

        $tag2 = new Tag('tag2', $topic1);
        $manager->persist($tag2);
        $this->setReference('tag2', $tag2);

        $tag3 = new Tag('tag3', $topic1);
        $manager->persist($tag3);
        $this->setReference('tag3', $tag3);

        $tag4 = new Tag('tag4', $topic1);
        $manager->persist($tag4);
        $this->setReference('tag4', $tag4);

        $tag5 = new Tag('tag5', $topic1);
        $manager->persist($tag5);
        $this->setReference('tag5', $tag5);

        $tag6 = new Tag('tag6', $topic1);
        $manager->persist($tag6);
        $this->setReference('tag6', $tag6);

        $tag7 = new Tag('tag7', $topic1);
        $manager->persist($tag7);
        $this->setReference('tag7', $tag7);

        $tag8 = new Tag('tag8', $topic1);
        $manager->persist($tag8);
        $this->setReference('tag8', $tag8);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
