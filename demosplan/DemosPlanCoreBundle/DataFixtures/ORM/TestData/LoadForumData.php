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
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStoryVote;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntryFile;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadForumData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $forumThread1 = new ForumThread();
        $forumThread1->setClosed(false);
        $forumThread1->setClosingReason('grund');
        $forumThread1->setProgression('false');

        $manager->persist($forumThread1);

        $forumThread2 = new ForumThread();
        $forumThread2->setClosed(false);
        $forumThread2->setProgression('false');

        $manager->persist($forumThread2);

        $developmentThread = new ForumThread();
        $developmentThread->setClosed(false);
        $developmentThread->setProgression('true');

        $manager->persist($developmentThread);
        $manager->flush();

        $this->setReference('testForumThread1', $forumThread1);
        $this->setReference('testForumThread2', $forumThread2);
        $this->setReference('testDevelopmentThread', $developmentThread);

        $forumEntry1 = new ForumEntry();
        $forumEntry1->setText('Beitrag zum ersten testforum');
        $forumEntry1->setInitialEntry(true);
        $forumEntry1->setThread($this->getReference('testForumThread1'));
        $forumEntry1->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $forumEntry1->setUserRoles('RPSOCO,RMOPSA');

        $manager->persist($forumEntry1);

        $forumEntry2 = new ForumEntry();
        $forumEntry2->setText('Beitrag zum zweiten testforum');
        $forumEntry2->setInitialEntry(true);
        $forumEntry2->setThread($this->getReference('testForumThread2'));
        $forumEntry2->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $forumEntry2->setUserRoles('RPSOCO,RMOPSA');

        $manager->persist($forumEntry2);

        $manager->flush();

        $this->setReference('testForumEntry1', $forumEntry1);
        $this->setReference('testForumEntry2', $forumEntry2);

        $forumEntryFile1 = new ForumEntryFile();
        $forumEntryFile1->setEntry($this->getReference('testForumEntry1'));
        $forumEntryFile1->setCreateDate(new DateTime());
        $forumEntryFile1->setModifyDate(new DateTime());
        $forumEntryFile1->setDeleted(false);
        $forumEntryFile1->setBlocked(false);
        $forumEntryFile1->setOrder(0);
        $forumEntryFile1->setString('testfileforum.pdf:'.$this->getReference('testFile2')->getIdent().':879394:image/jpeg');
        $forumEntryFile1->setHash($this->getReference('testFile2')->getIdent());

        $manager->persist($forumEntryFile1);

        $forumEntryFile2 = new ForumEntryFile();
        $forumEntryFile2->setEntry($this->getReference('testForumEntry1'));
        $forumEntryFile2->setCreateDate(new DateTime());
        $forumEntryFile2->setModifyDate(new DateTime());
        $forumEntryFile2->setDeleted(false);
        $forumEntryFile2->setBlocked(false);
        $forumEntryFile2->setOrder(0);
        $forumEntryFile2->setString('7025_283_Testfile.pdf:'.$this->getReference('testFile')->getIdent().':899394:application/x-pdf');
        $forumEntryFile2->setHash($this->getReference('testFile')->getIdent());

        $manager->persist($forumEntryFile2);

        $this->setReference('testForumEntryFile1', $forumEntryFile1);
        $this->setReference('testForumEntryFile2', $forumEntryFile2);

        $manager->flush();

        $release1 = new DevelopmentRelease();
        $release1->setTitle('Release eins');
        $release1->setDescription('description release eins');
        $release1->setPhase('configuration');
        $release1->setStartDate(new DateTime());
        $release1->setEndDate(new DateTime());
        $release1->setCreateDate(new DateTime());
        $release1->setModifiedDate(new DateTime());

        $manager->persist($release1);

        $release2 = new DevelopmentRelease();
        $release2->setTitle('Release eins');
        $release2->setPhase('configuration');
        $release2->setCreateDate(new DateTime());
        $release2->setModifiedDate(new DateTime());

        $manager->persist($release2);

        $this->setReference('testRelease1', $release1);
        $this->setReference('testRelease2', $release2);

        $manager->flush();

        $userStory1 = new DevelopmentUserStory();
        $userStory1->setTitle('UserStory eins');
        $userStory1->setDescription('description userstory eins');
        $userStory1->setRelease($this->getReference('testRelease1'));
        $userStory1->setThread($this->getReference('testDevelopmentThread'));
        $userStory1->setOnlineVotes(3);
        $userStory1->setOfflineVotes(0);
        $userStory1->setCreateDate(new DateTime());
        $userStory1->setModifiedDate(new DateTime());

        $manager->persist($userStory1);

        $userStory2 = new DevelopmentUserStory();
        $userStory2->setTitle('UserStory zwei');
        $userStory2->setDescription('description userstory zwei');
        $userStory2->setRelease($this->getReference('testRelease1'));
        $userStory2->setThread($this->getReference('testDevelopmentThread'));
        $userStory2->setOnlineVotes(0);
        $userStory2->setOfflineVotes(3);
        $userStory2->setCreateDate(new DateTime());
        $userStory2->setModifiedDate(new DateTime());

        $manager->persist($userStory2);

        $this->setReference('testUserStory1', $userStory1);
        $this->setReference('testUserStory2', $userStory2);

        $manager->flush();

        $userStoryVote1 = new DevelopmentUserStoryVote();
        $userStoryVote1->setOrga($this->getReference('testOrgaInvitableInstitution'));
        $userStoryVote1->setUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $userStoryVote1->setUserStory($this->getReference('testUserStory1'));
        $userStoryVote1->setNumberOfVotes(2);
        $userStoryVote1->setCreateDate(new DateTime());
        $userStoryVote1->setModifiedDate(new DateTime());

        $manager->persist($userStoryVote1);

        $userStoryVote2 = new DevelopmentUserStoryVote();
        $userStoryVote2->setOrga($this->getReference('testOrgaPB'));
        $userStoryVote2->setUser($this->getReference('testUserPlanningOffice'));
        $userStoryVote2->setUserStory($this->getReference('testUserStory1'));
        $userStoryVote2->setNumberOfVotes(1);
        $userStoryVote2->setCreateDate(new DateTime());
        $userStoryVote2->setModifiedDate(new DateTime());
        $manager->persist($userStoryVote2);

        $this->setReference('testUserStoryVote1', $userStoryVote1);
        $this->setReference('testUserStoryVote2', $userStoryVote2);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadUserData::class,
            LoadFileData::class,
        ];
    }
}
