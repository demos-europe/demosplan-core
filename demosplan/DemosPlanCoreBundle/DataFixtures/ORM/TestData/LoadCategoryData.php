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
use demosplan\DemosPlanCoreBundle\Entity\Category;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadCategoryData extends TestFixture
{
    final public const TEST_CATEGORY_FAQ = 'testCategoryFaq';
    final public const TEST_CATEGORY_FAQ_2 = 'testCategoryFaq2';
    final public const TEST_CATEGORY_NEWS = 'testCategoryNews';
    final public const TEST_CATEGORY_PRESS = 'testCategoryPress';

    public function load(ObjectManager $manager): void
    {
        $category1 = new Category();
        $category1->setName('faqcategorie');
        $category1->setTitle('Faq Kategorie Nummer 1');
        $category1->setDescription('Ich bin eine FAQ Kategorie.');
        $category1->setEnabled(true);
        $category1->setDeleted(false);
        $category1->setPicture('');
        $category1->setPictitle('');
        $category1->setCreateDate(new DateTime());
        $category1->setModifyDate(new DateTime());

        $manager->persist($category1);

        $category2 = new Category();
        $category2->setName('faqzweitecategorie');
        $category2->setTitle('Faq Kategorie Nummer 2');
        $category2->setDescription('Ich bin eine zweite FAQ Kategorie.');
        $category2->setPicture('');
        $category2->setPictitle('');
        $category2->setEnabled(true);
        $category2->setDeleted(false);
        $category2->setCreateDate(new DateTime());
        $category2->setModifyDate(new DateTime());

        $manager->persist($category2);

        $category3 = new Category();
        $category3->setName('news');
        $category3->setTitle('News Kategorie Nummer 1');
        $category3->setDescription('Ich bin eine Kategorie für die GlobalNews');
        $category3->setPicture('picturehash');
        $category3->setPictitle('Titel für Bild');
        $category3->setEnabled(true);
        $category3->setDeleted(false);
        $category3->setCreateDate(new DateTime());
        $category3->setModifyDate(new DateTime());

        $manager->persist($category3);

        $category4 = new Category();
        $category4->setName('press');
        $category4->setTitle('Press Kategorie Nummer 1');
        $category4->setDescription('Ich bin eine Press Kategorie für die GlobalNews');
        $category4->setPicture('picturehash');
        $category4->setPictitle('Titel für Bild');
        $category4->setEnabled(true);
        $category4->setDeleted(false);
        $category4->setCreateDate(new DateTime());
        $category4->setModifyDate(new DateTime());

        $manager->persist($category4);

        $this->setReference(self::TEST_CATEGORY_FAQ, $category1);
        $this->setReference(self::TEST_CATEGORY_FAQ_2, $category2);
        $this->setReference(self::TEST_CATEGORY_NEWS, $category3);
        $this->setReference(self::TEST_CATEGORY_PRESS, $category4);

        $manager->flush();
    }
}
