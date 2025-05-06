<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Faq\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadFaqData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use Tests\Base\FunctionalTestCase;

class FaqHandlerTest extends FunctionalTestCase
{
    /**
     * @var FaqHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FaqHandler::class);
    }

    public function testGetFaqListStructure()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var FaqCategory $faqCategory */
        $faqCategory = $this->fixtures->getReference('testCategoryFaq5');

        $faqList = $this->sut->getEnabledFaqList($faqCategory, $user);

        static::assertInstanceOf(Faq::class, $faqList[0]);
        static::assertCount(1, $faqList[0]->getRoles());
        static::assertEquals('Häufige Frage Nummer 3', $faqList[0]->getTitle());
    }

    public function testGetFaqListStructureWithLimit()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var FaqCategory $faqCategory */
        $faqCategory = $this->fixtures->getReference('testCategoryFaq5');
        $faqList = $this->sut->getEnabledFaqList($faqCategory, $user);

        static::assertCount(1, $faqList);
        static::assertInstanceOf(Faq::class, $faqList[0]);
        static::assertCount(1, $faqList[0]->getRoles());
    }

    public function testGetFaqAdminListReturnValueStructure()
    {
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $category = $this->fixtures->getReference('testCategoryFaq5');
        $faqList = $this->sut->getEnabledFaqList($category, $user);

        static::assertCount(1, $faqList);
        self::assertInstanceOf(Faq::class, $faqList[0]);

        static::assertCount(1, $faqList[0]->getRoles());
        static::assertEquals($this->fixtures->getReference(LoadFaqData::FAQ_PLANNER)->getTitle(), $faqList[0]->getTitle());
    }

    public function testGetSingleFaq()
    {
        $singleFaqId = $this->fixtures->getReference(LoadFaqData::FAQ_PLANNER)->getId();

        $singleFaq = $this->sut->getFaq($singleFaqId);
        static::assertCount(1, $singleFaq->getRoles());
    }

    public function testGetSingleFaqWithEmptyIdents()
    {
        $nullFaq = $this->sut->getFaq('');
        self::assertNull($nullFaq);
    }

    public function testAddFaq()
    {
        /** @var Category $categoryToUpdateFaq */
        $categoryToUpdateFaq = $this->fixtures->getReference('testCategoryFaq5');

        $data = [
            'r_title'       => 'testfaq',
            'r_text'        => 'faqtext',
            'r_enable'      => '1',
            'r_group_code'  => [Role::GGUEST, Role::GPSORG],
            'r_category_id' => $categoryToUpdateFaq->getId(),
        ];

        $numberOfEntriesBefore = $this->countEntries(Faq::class);
        $singleFaq = $this->sut->addOrUpdateFaq($data);
        static::assertInstanceOf(Faq::class, $singleFaq);
        $numberOfEntriesAfter = $this->countEntries(Faq::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore + 1);

        static::assertCount(3, $singleFaq->getRoles());
        static::assertEquals('Faq Kategorie2', $singleFaq->getCategory()->getTitle());
    }

    public function testAddFAQWithEmptyDataArray()
    {
        // Data for new layer
        $singleFaq = $this->sut->addOrUpdateFaq([]);
        self::assertNull($singleFaq);
    }

    public function testDeleteFaqs()
    {
        $singleFaq1 = $this->fixtures->getReference(LoadFaqData::FAQ_GUEST);
        $singleFaq1Id = $this->fixtures->getReference(LoadFaqData::FAQ_GUEST)->getId();

        $this->sut->deleteFaq($singleFaq1);

        $singleFaq1 = $this->sut->getFaq($singleFaq1Id);
        static::assertNull($singleFaq1);
    }

    public function testSetManualSort()
    {
        $singleFaq3 = $this->fixtures->getReference(LoadFaqData::FAQ_GUEST);
        $singleFaq4 = $this->fixtures->getReference(LoadFaqData::FAQ_PLANNER);
        $context = 'global:faq:category:faqcategorie';
        $sortIds = $singleFaq4->getId().','.$singleFaq3->getId();

        $result = $this->sut->setManualSort($context, $sortIds);

        static::assertTrue($result);
    }

    public function testUpdateFaq()
    {
        /** @var Faq $singleFaq1 */
        $singleFaq1 = $this->fixtures->getReference(LoadFaqData::FAQ_PLANNER);
        $newTitle = 'Faq1 Title verändert';

        // Data for new layer
        $singleFaq1->setTitle($newTitle);
        $singleFaq1->setText('Ich bin der Text der Faq4');
        $singleFaq1->setEnabled(true);
        $singleFaq1->setRoles([$this->getReference('testRoleGuest')]);
        $singleFaq1->setCategory($this->fixtures->getReference('testCategoryFaq5'));

        $numberOfEntriesBefore = $this->countEntries(Faq::class);
        $singleFaq = $this->sut->updateFAQ($singleFaq1);

        // check entries of DB
        $numberOfEntriesAfter = $this->countEntries(Faq::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check return value
        self::assertEquals($newTitle, $singleFaq->getTitle());
    }

    public function testGetCategory()
    {
        /** @var FaqCategory $referenceCategory */
        $referenceCategory = $this->fixtures->getReference('testCategoryFaq4');
        $category = $this->sut->getFaqCategory($referenceCategory->getId());

        static::assertInstanceOf(FaqCategory::class, $category);
        static::assertEquals($referenceCategory->getTitle(), $category->getTitle());
        static::assertEquals($referenceCategory->getType(), $category->getType());
        static::assertEquals($referenceCategory->getCustomer(), $category->getCustomer());
    }

    public function testUpdateCategory()
    {
        /** @var Faq $testFaq */
        $testFaq = $this->fixtures->getReference(LoadFaqData::FAQ_PLANNER);

        /** @var FaqCategory $referenceCategory */
        $referenceCategory = $this->fixtures->getReference('testCategoryFaq5');
        static::assertEquals($testFaq->getCategory()->getTitle(), $referenceCategory->getTitle());
        static::assertEquals($testFaq->getCategory()->getType(), $referenceCategory->getType());

        // chance object:
        $referenceCategory->setTitle('newTitle');
        $referenceCategory->setType(FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY[0]);

        // execute update:
        $updatedCategory = $this->sut->updateFaqCategory($referenceCategory);
        static::assertInstanceOf(FaqCategory::class, $updatedCategory);

        // get updated category from DB
        $updatedCategory = $this->sut->getFaqCategory($referenceCategory->getId());
        static::assertEquals($updatedCategory->getTitle(), 'newTitle');
        static::assertEquals($updatedCategory->getType(), FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY[0]);
    }

    public function testUpdateCategoryToDuplicate()
    {
        /** @var FaqCategory $referenceCategory */
        $referenceCategory = $this->fixtures->getReference('testCategoryFaq4');
        /** @var FaqCategory $referenceCategory2 */
        $referenceCategory2 = $this->fixtures->getReference('testCategoryFaq5');

        $referenceCategory->setTitle($referenceCategory2->getTitle());
        $updatedCategory = $this->sut->updateFaqCategory($referenceCategory);
        // it may be possible to update title to an existing name
        static::assertEquals($referenceCategory2->getTitle(), $updatedCategory->getTitle());
    }

    public function testDeleteFilledCategory()
    {
        /** @var FaqCategory $referenceCategory */
        $referenceCategory = $this->fixtures->getReference('testCategoryFaq4');

        static::assertNotEmpty($referenceCategory->getTitle());
        $result = $this->sut->deleteFaqCategory($referenceCategory);
        static::assertFalse($result);
    }

    public function testCreateCategory()
    {
        $newCategory = $this->sut->createFaqCategory([
            'r_category_title' => 'title of new created category',
        ]);
        static::assertInstanceOf(FaqCategory::class, $newCategory);

        $newCategory = $this->sut->getFaqCategory($newCategory->getId());

        static::assertInstanceOf(FaqCategory::class, $newCategory);
        static::assertEquals('title of new created category', $newCategory->getTitle());
        static::assertEquals('custom_category', $newCategory->getType());
        static::assertTrue($newCategory->isCustom());
    }

    public function testCreateDuplicateCategory()
    {
        /** @var FaqCategory $referenceCategory */
        $referenceCategory = $this->fixtures->getReference('testCategoryFaq4');
        $newCategory = $this->sut->createFaqCategory(['r_category_title' => $referenceCategory->getTitle()]);
        static::assertInstanceOf(FaqCategory::class, $newCategory);
    }

    public function testChangeCategory()
    {
        /** @var Faq $singleFaq1 */
        $singleFaq1 = $this->fixtures->getReference(LoadFaqData::FAQ_PLANNER);
        /** @var FaqCategory $currentCategory */
        $currentCategory = $this->fixtures->getReference('testCategoryFaq4');
        /** @var FaqCategory $newCategory */
        $newCategory = $this->fixtures->getReference('testCategoryFaq5');

        static::assertInstanceOf(FaqCategory::class, $singleFaq1->getCategory());

        /** @var FaqCategory $categoryToUpdateFaq */
        $categoryToUpdateFaq = $this->fixtures->getReference('testCategoryFaq5');

        $singleFaq1->setCategory($categoryToUpdateFaq);

        $updatedFaq = $this->sut->updateFAQ($singleFaq1);

        static::assertInstanceOf(FaqCategory::class, $updatedFaq->getCategory());
        static::assertEquals($newCategory->getId(), $updatedFaq->getCategory()->getId());
    }
}
