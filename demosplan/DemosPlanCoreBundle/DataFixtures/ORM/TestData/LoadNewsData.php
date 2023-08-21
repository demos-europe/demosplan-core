<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadNewsData extends TestFixture implements DependentFixtureInterface
{
    final public const TEST_SINGLE_NEWS_1 = 'testSingleNews1';
    final public const TEST_SINGLE_NEWS_2 = 'testSingleNews2';
    final public const TEST_SINGLE_NEWS_3 = 'testSingleNews3';
    final public const TEST_SINGLE_NEWS_4 = 'testSingleNews4';

    public function load(ObjectManager $manager): void
    {
        $procedureId = $this->getReference('testProcedure2')->getId();

        $news1 = new News();
        $news1->setTitle('News1 Title');
        $news1->setDescription('Ich bin die Description der News1');
        $news1->setText('Ich bin der Text der News1');
        $news1->setPId($procedureId);
        $news1->setPicture('');
        $news1->setPictitle('');
        $news1->setPdf('');
        $news1->setPdftitle('');
        $news1->setEnabled(true);
        $news1->setDeleted(false);
        $news1->setCreateDate(new DateTime());
        $news1->setModifyDate(new DateTime());
        $news1->setDeleteDate(new DateTime());
        $news1->setRoles(
            [
                $this->getReference('testRoleFP'),
                $this->getReference('testRolePublicAgencyCoordination'),
                $this->getReference('testRoleCitiz'),
            ]
        );
        $manager->persist($news1);

        $news2 = new News();
        $news2->setTitle('News2 Title');
        $news2->setDescription('Ich bin die Description der News2');
        $news2->setText('Ich bin der Text der News2');
        $news2->setPId($procedureId);
        $news2->setPicture('');
        $news2->setPictitle('');
        $news2->setPdf('');
        $news2->setPdftitle('');
        $news2->setEnabled(true);
        $news2->setDeleted(false);
        $news2->setCreateDate(new DateTime());
        $news2->setModifyDate(new DateTime());
        $news2->setDeleteDate(new DateTime());
        $news2->setRoles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')]);
        $manager->persist($news2);

        $news3 = new News();
        $news3->setTitle('News3 Title');
        $news3->setDescription('Ich bin die Description der News3');
        $news3->setText('Ich bin der Text der News3');
        $news3->setPId($procedureId);
        $news3->setPicture('');
        $news3->setPictitle('');
        $news3->setPdf('');
        $news3->setPdftitle('');
        $news3->setEnabled(true);
        $news3->setDeleted(false);
        $news3->setCreateDate(new DateTime());
        $news3->setModifyDate(new DateTime());
        $news3->setDeleteDate(new DateTime());
        $news3->setDesignatedSwitchDate(new DateTime());
        $news3->setDeterminedToSwitch(true);
        $news3->setRoles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')]);
        $manager->persist($news3);

        $news4 = new News();
        $news4->setTitle('news4 Title');
        $news4->setDescription('Ich bin die Description der news4');
        $news4->setText('Ich bin der Text der news4');
        $news4->setPId($procedureId);
        $news4->setPicture('');
        $news4->setPictitle('');
        $news4->setPdf('');
        $news4->setPdftitle('');
        $news4->setEnabled(true);
        $news4->setDeleted(false);
        $news4->setCreateDate(new DateTime());
        $news4->setModifyDate(new DateTime());
        $news4->setDeleteDate(new DateTime());
        $news4->setDesignatedSwitchDate(Carbon::now()->addWeek()->toDateTime());
        $news4->setDeterminedToSwitch(true);
        $news4->setRoles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')]);
        $manager->persist($news4);

        $manager->flush();

        $this->setReference(LoadNewsData::TEST_SINGLE_NEWS_1, $news1);
        $this->setReference(LoadNewsData::TEST_SINGLE_NEWS_2, $news2);
        $this->setReference(LoadNewsData::TEST_SINGLE_NEWS_3, $news3);
        $this->setReference(LoadNewsData::TEST_SINGLE_NEWS_4, $news4);
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
