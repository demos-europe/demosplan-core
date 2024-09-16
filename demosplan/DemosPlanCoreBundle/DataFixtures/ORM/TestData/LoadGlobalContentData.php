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
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadGlobalContentData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $globalContent1 = new GlobalContent();
        $globalContent1->setType('faq');
        $globalContent1->setTitle('Häufige Frage Nummer 1');
        $globalContent1->setText('Ich bin eine häufige Frage.');
        $globalContent1->setEnabled(true);
        $globalContent1->setDeleted(false);
        $globalContent1->setPicture('');
        $globalContent1->setPictitle('');
        $globalContent1->setPdf('');
        $globalContent1->setPdftitle('');
        $globalContent1->setCreateDate(new DateTime());
        $globalContent1->setModifyDate(new DateTime());
        $globalContent1->setDeleteDate(new DateTime());
        $globalContent1->setRoles([$this->getReference('testRoleGuest')]);
        $globalContent1->setCategories([$this->getReference('testCategoryFaq')]);
        $globalContent1->setCustomer($this->getReference('testCustomer'));

        $manager->persist($globalContent1);

        $globalContent2 = new GlobalContent();
        $globalContent2->setType('faq');
        $globalContent2->setTitle('Häufige Frage Nummer 2');
        $globalContent2->setText('Ich bin eine zweite häufige Frage.');
        $globalContent2->setPicture('');
        $globalContent2->setPictitle('');
        $globalContent2->setPdf('');
        $globalContent2->setPdftitle('');
        $globalContent2->setEnabled(true);
        $globalContent2->setDeleted(false);
        $globalContent2->setCreateDate(new DateTime());
        $globalContent2->setModifyDate(new DateTime());
        $globalContent2->setDeleteDate(new DateTime());
        $globalContent2->setRoles([$this->getReference('testRoleGuest')]);
        $globalContent2->setCategories([$this->getReference('testCategoryFaq2')]);
        $globalContent2->setCustomer($this->getReference('testCustomer'));

        $manager->persist($globalContent2);

        $globalContent3 = new GlobalContent();
        $globalContent3->setType('news');
        $globalContent3->setTitle('GlobalNews1 Title');
        $globalContent3->setDescription('Ich bin die Description der GlobalNews1');
        $globalContent3->setText('Ich bin der Text der GlobalNews1');
        $globalContent3->setPicture('');
        $globalContent3->setPictitle('');
        $globalContent3->setPdf('');
        $globalContent3->setPdftitle('');
        $globalContent3->setEnabled(true);
        $globalContent3->setDeleted(false);
        $globalContent3->setCreateDate(new DateTime());
        $globalContent3->setModifyDate(new DateTime());
        $globalContent3->setDeleteDate(new DateTime());
        $globalContent3->setRoles([$this->getReference('testRoleGuest'), $this->getReference('testRolePublicAgencyCoordination'), $this->getReference('testRoleFP')]);
        $globalContent3->setCategories([$this->getReference('testCategoryNews')]);
        $globalContent3->setCustomer($this->getReference('testCustomer'));

        $manager->persist($globalContent3);

        $globalContent4 = new GlobalContent();
        $globalContent4->setType('news');
        $globalContent4->setTitle('GlobalNews2 Title');
        $globalContent4->setDescription('Ich bin die Description der GlobalNews2');
        $globalContent4->setText('Ich bin der Text der GlobalNews2');
        $globalContent4->setPicture('picturehash');
        $globalContent4->setPictitle('Pictesttitle');
        $globalContent4->setPdf('');
        $globalContent4->setPdftitle('');
        $globalContent4->setEnabled(true);
        $globalContent4->setDeleted(false);
        $globalContent4->setCreateDate(new DateTime());
        $globalContent4->setModifyDate(new DateTime());
        $globalContent4->setDeleteDate(new DateTime());
        $globalContent4->setRoles([$this->getReference('testRolePublicAgencyCoordination'), $this->getReference('testRoleFP')]);
        $globalContent4->setCategories([$this->getReference('testCategoryNews')]);
        $globalContent4->setCustomer($this->getReference('testCustomer'));

        $manager->persist($globalContent4);

        $globalContent5 = new GlobalContent();
        $globalContent5->setType('faq');
        $globalContent5->setTitle('Häufige Frage Nummer 3');
        $globalContent5->setText('Ich bin eine dritte häufige Frage.');
        $globalContent5->setPicture('');
        $globalContent5->setPictitle('');
        $globalContent5->setPdf('');
        $globalContent5->setPdftitle('');
        $globalContent5->setEnabled(true);
        $globalContent5->setDeleted(false);
        $globalContent5->setCreateDate(new DateTime());
        $globalContent5->setModifyDate(new DateTime());
        $globalContent5->setDeleteDate(new DateTime());
        $globalContent5->setRoles([$this->getReference('testRoleFP')]);
        $globalContent5->setCategories([$this->getReference('testCategoryFaq')]);
        $globalContent5->setCustomer($this->getReference('testCustomer'));

        $manager->persist($globalContent5);

        $manager->flush();
        $this->setReference('testFaq1', $globalContent1);
        $this->setReference('testFaq2', $globalContent5);
        $this->setReference('testGlobalNews1', $globalContent3);
        $this->setReference('testGlobalNews2', $globalContent4);
    }

    public function getDependencies()
    {
        return [
            LoadUserData::class,
        ];
    }
}
