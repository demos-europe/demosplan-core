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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadBoilerplateData extends TestFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Procedure $procedure2 * */
        $procedure2 = $this->getReference('testProcedure2');
        /** @var Procedure $blueprintWithBoilerplates */
        $blueprintWithBoilerplates = $this->getReference('testmasterProcedureWithBoilerplates');

        $boilerplate1 = new Boilerplate();
        $boilerplate1->setTitle('Schlussmitteilung');
        $boilerplate1->setText('Ich bin der Text für die Schlussmitteilung.');
        $boilerplate1->setProcedure($procedure2);
        $boilerplate1->setCreateDate(new DateTime());
        $boilerplate1->setModifyDate(new DateTime());

        $this->setReference('testBoilerplate1', $boilerplate1);
        $manager->persist($boilerplate1);

        $boilerplate2 = new Boilerplate();
        $boilerplate2->setTitle('Einladung');
        $boilerplate2->setText('Ich bin der Text für die Einladung.');
        $boilerplate2->setProcedure($procedure2);
        $boilerplate2->setCreateDate(new DateTime());
        $boilerplate2->setModifyDate(new DateTime());

        $manager->persist($boilerplate2);
        $this->setReference('testBoilerplate2', $boilerplate2);

        $category4 = new BoilerplateCategory();
        $category4->setTitle('consideration');
        $category4->setDescription('description');
        $category4->setProcedure($procedure2);
        $category4->setCreateDate(new DateTime());
        $category4->setModifyDate(new DateTime());

        $manager->persist($category4);
        $this->setReference('testBoilerplatecategory4', $category4);

        $boilerplate3 = new Boilerplate();
        $boilerplate3->setTitle('Boilerplate3');
        $boilerplate3->setText('Ich bin der Text für den Texbaustein 3.');
        $boilerplate3->setProcedure($procedure2);
        $boilerplate3->setCreateDate(new DateTime());
        $boilerplate3->setModifyDate(new DateTime());
        $boilerplate3->setCategories([$category4]);

        $manager->persist($boilerplate3);
        $this->setReference('testBoilerplate3', $boilerplate3);

        $boilerplate4 = new Boilerplate();
        $boilerplate4->setTitle('Boilerplate4');
        $boilerplate4->setText('Ich bin der Text für den Texbaustein 4.');
        $boilerplate4->setProcedure($procedure2);
        $boilerplate4->setCreateDate(new DateTime());
        $boilerplate4->setModifyDate(new DateTime());
        $boilerplate4->setCategories([$category4]);

        $manager->persist($boilerplate4);
        $this->setReference('testboilerplate4', $boilerplate4);

        $category1 = new BoilerplateCategory();
        $category1->setTitle('Kategorie 1');
        $category1->setDescription('実際にUtf8をサポートしていますか？');
        $category1->setProcedure($procedure2);
        $category1->setCreateDate(new DateTime());
        $category1->setModifyDate(new DateTime());

        $manager->persist($category1);
        $this->setReference('testBoilerplateCategory1', $category1);

        $category2 = new BoilerplateCategory();
        $category2->setTitle('email');
        $category2->setDescription('');
        $category2->setProcedure($blueprintWithBoilerplates);
        $category2->setCreateDate(new DateTime());
        $category2->setModifyDate(new DateTime());
        $manager->persist($category2);
        $this->setReference('testBoilerplatecategory2', $category2);

        $category3 = new BoilerplateCategory();
        $category3->setTitle('news.notes');
        $category3->setDescription('');
        $category3->setProcedure($blueprintWithBoilerplates);
        $category3->setCreateDate(new DateTime());
        $category3->setModifyDate(new DateTime());
        $manager->persist($category3);
        $this->setReference('testBoilerplatecategory3', $category3);

        $newsBoilerplate = new Boilerplate();
        $newsBoilerplate->setTitle('eine aktuelle Mitteilung');
        $newsBoilerplate->setText('tolle Mitteilung');
        $newsBoilerplate->setProcedure($blueprintWithBoilerplates);
        $newsBoilerplate->setCreateDate(new DateTime());
        $newsBoilerplate->setModifyDate(new DateTime());
        $newsBoilerplate->setCategories([$category3]);
        $this->setReference('testNewsBoilerplate', $newsBoilerplate);
        $manager->persist($newsBoilerplate);

        $mailBoilerplate = new Boilerplate();
        $mailBoilerplate->setTitle('mail');
        $mailBoilerplate->setText('mailtext');
        $mailBoilerplate->setProcedure($blueprintWithBoilerplates);
        $mailBoilerplate->setCreateDate(new DateTime());
        $mailBoilerplate->setModifyDate(new DateTime());
        $mailBoilerplate->setCategories([$category2]);
        $this->setReference('testMailBoilerplate', $mailBoilerplate);
        $manager->persist($mailBoilerplate);

        $multipleBoilerplate = new Boilerplate();
        $multipleBoilerplate->setTitle('aktuelle Mitteilung und mail');
        $multipleBoilerplate->setText('vieles');
        $multipleBoilerplate->setProcedure($blueprintWithBoilerplates);
        $multipleBoilerplate->setCreateDate(new DateTime());
        $multipleBoilerplate->setModifyDate(new DateTime());
        $multipleBoilerplate->setCategories([$category3, $category2]);
        $this->setReference('testMultipleBoilerplate', $multipleBoilerplate);
        $manager->persist($multipleBoilerplate);

        $emptyCategory = new BoilerplateCategory();
        $emptyCategory->setTitle('empty');
        $emptyCategory->setDescription('');
        $emptyCategory->setProcedure($blueprintWithBoilerplates);
        $emptyCategory->setCreateDate(new DateTime());
        $emptyCategory->setModifyDate(new DateTime());
        $manager->persist($emptyCategory);
        $this->setReference('testBoilerplateEmptyCategory', $emptyCategory);

        $emptyGroup = new BoilerplateGroup('empty', $blueprintWithBoilerplates);
        $emptyGroup->setCreateDate(new DateTime());
        $manager->persist($emptyGroup);
        $this->setReference('testBoilerplateEmptyGroup', $emptyGroup);

        $testGroup1 = new BoilerplateGroup('testGroup1', $blueprintWithBoilerplates);
        $testGroup1->setCreateDate(new DateTime());
        $manager->persist($testGroup1);
        $this->setReference('testBoilerplateTestGroup1', $testGroup1);

        $testGroup2 = new BoilerplateGroup('testGroup2', $blueprintWithBoilerplates);
        $testGroup2->setCreateDate(new DateTime());
        $manager->persist($testGroup2);
        $this->setReference('testBoilerplateTestGroup2', $testGroup2);

        $boilerplateOfGroup1 = new Boilerplate();
        $boilerplateOfGroup1->setTitle('boilerplateOfGroup1');
        $boilerplateOfGroup1->setText('Ich bin der Text für die Schlussmitteilung.');
        $boilerplateOfGroup1->setProcedure($blueprintWithBoilerplates);
        $boilerplateOfGroup1->setCreateDate(new DateTime());
        $boilerplateOfGroup1->setModifyDate(new DateTime());
        $boilerplateOfGroup1->setGroup($testGroup1);
        $manager->persist($boilerplateOfGroup1);
        $this->setReference('boilerplateOfGroup1', $boilerplateOfGroup1);

        $boilerplate1OfGroup2 = new Boilerplate();
        $boilerplate1OfGroup2->setTitle('boilerplate1OfGroup2');
        $boilerplate1OfGroup2->setText('Ich bin der Text für die Schlussmitteilung.');
        $boilerplate1OfGroup2->setProcedure($blueprintWithBoilerplates);
        $boilerplate1OfGroup2->setCreateDate(new DateTime());
        $boilerplate1OfGroup2->setModifyDate(new DateTime());
        $boilerplate1OfGroup2->setCategories([$category3]);
        $boilerplate1OfGroup2->setGroup($testGroup2);
        $manager->persist($boilerplate1OfGroup2);
        $this->setReference('boilerplate1OfGroup2', $boilerplate1OfGroup2);

        $boilerplate2OfGroup2 = new Boilerplate();
        $boilerplate2OfGroup2->setTitle('boilerplate2OfGroup2');
        $boilerplate2OfGroup2->setText('Ich bin der Text für die Schlussmitteilung.');
        $boilerplate2OfGroup2->setProcedure($blueprintWithBoilerplates);
        $boilerplate2OfGroup2->setCreateDate(new DateTime());
        $boilerplate2OfGroup2->setModifyDate(new DateTime());
        $boilerplate2OfGroup2->setGroup($testGroup2);
        $boilerplate2OfGroup2->setCategories([$category3, $category2]);
        $manager->persist($boilerplate2OfGroup2);
        $this->setReference('boilerplate2OfGroup2', $boilerplate2OfGroup2);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadProcedureData::class,
        ];
    }
}
