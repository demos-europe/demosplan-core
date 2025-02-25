<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use Exception;
use Tests\Base\FunctionalTestCase;

class BoilerplateServiceTest extends FunctionalTestCase
{
    /**
     * @var ProcedureService
     */
    protected $sut;
    /**
     * @var DateHelper
     */
    private $dateHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dateHelper = new DateHelper();
        $this->sut = self::$container->get(ProcedureService::class);
    }

    public function testGetBoilerplate()
    {
        $testProcedureId = $this->fixtures->getReference('testProcedure2')->getId();
        // check the boilerplateList
        $boilerplateList = $this->sut->getBoilerplateList($testProcedureId);
        $expectedCount = $this->countEntries(Boilerplate::class, ['procedure' => $testProcedureId]);
        static::assertIsArray($boilerplateList);
        static::assertCount($expectedCount, $boilerplateList);

        foreach ($boilerplateList as $boilerplate) {
            static::assertInstanceOf(Boilerplate::class, $boilerplate);
            $this->checkId($boilerplate->getId());
            $this->checkStringDateFormat($boilerplate->getModifyDate());
            $this->checkStringDateFormat($boilerplate->getCreateDate());
        }
    }

    public function testGetBoilerplateExceptions()
    {
        // case: procedureId does not exist
        $boilerplateList = $this->sut->getBoilerplateList('FakeId');
        static::assertIsArray($boilerplateList);
        static::assertCount(0, $boilerplateList);

        // case: procedureId empty
        $boilerplateList = $this->sut->getBoilerplateList('');
        static::assertIsArray($boilerplateList);
        static::assertCount(0, $boilerplateList);
    }

    public function testGetSingleBoilerplate()
    {
        $boilerplateList = $this->sut->getBoilerplateList($this->fixtures->getReference('testProcedure2')->getId());
        static::assertTrue(count($boilerplateList) > 0);
        $boilerplate = $this->sut->getBoilerplate($boilerplateList[0]->getId());
        static::assertInstanceOf(Boilerplate::class, $boilerplate);
    }

    public function testGetSingleBoilerplateException()
    {
        // case: $boilerplateId = null
        try {
            $boilerplate = $this->sut->getBoilerplate(null);
            $this->fail('case: boilerplateId = null');
        } catch (Exception $e) {
            static::assertTrue(true);
        }

        // case: $boilerplateId = non existing Id
        try {
            $boilerplate = $this->sut->getBoilerplate('fakeId');
            $this->fail('case: boilerplateId = non existing Id');
        } catch (Exception $e) {
            static::assertTrue(true);
        }

        // case: $boilerplateId = ''
        try {
            $boilerplate = $this->sut->getBoilerplate('');
            $this->fail('case: boilerplateId empty');
        } catch (Exception $e) {
            static::assertTrue(true);
        }
    }

    // Check result, when Database is empty
    public function testWithEmptyDatabase()
    {
        $this->databaseTool->loadFixtures([]);
        $boilerplateList = $this->sut->getBoilerplateList($this->fixtures->getReference('testProcedure2')->getId());
        static::assertIsArray($boilerplateList);
        static::assertCount(0, $boilerplateList);
    }

    public function testAddBoilerplate()
    {
        $numberOfEntriesBefore = $this->countEntries(Boilerplate::class);
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $toPost = [];
        $toPost['title'] = 'Title of Boilerplate post test';

        $this->sut->addBoilerplate($procedureId, $toPost);
        $numberOfEntriesAfter = $this->countEntries(Boilerplate::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        $toPost['text'] = 'Text of Boilerplate post test';

        $this->sut->addBoilerplate($procedureId, $toPost);

        $numberOfEntriesAfter = $this->countEntries(Boilerplate::class);
        static::assertEquals($numberOfEntriesBefore + 1, $numberOfEntriesAfter);

        $foundBoilerplates = $this->getEntries(Boilerplate::class, ['title' => $toPost['title'], 'text' => $toPost['text']]);
        static::assertCount(1, $foundBoilerplates);
        /** @var Boilerplate $createdBoilerplate */
        $createdBoilerplate = $foundBoilerplates[0];
        static::assertNotNull($createdBoilerplate);

        $createDate = $this->dateHelper->convertDateToString($createdBoilerplate->getCreateDate(false));
        $modifyDate = $this->dateHelper->convertDateToString($createdBoilerplate->getModifyDate(false));
        static::assertTrue($this->isCurrentDateTime($createDate));
        static::assertTrue($this->isCurrentDateTime($modifyDate));
    }

    private function getBoilerplate($title, $text)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('boilerplate')
            ->from(Boilerplate::class, 'boilerplate')
            ->where('boilerplate.text = :text')
            ->andWhere('boilerplate.title = :title')
            ->setParameter('text', $text)
            ->setParameter('title', $title)
            ->setMaxResults(2)
            ->getQuery();

        $result = $query->getResult();

        if (1 !== count($result)) {
            return null;
        }

        return $result[0];
    }

    public function testUpdateBoilerplate()
    {
        $numberOfEntriesBefore = $this->countEntries(Boilerplate::class);

        $toUpdate = $this->fixtures->getReference('testBoilerplate1');
        $update = [];
        $update['text'] = 'this text was updated';
        $this->sut->updateBoilerplate($toUpdate->getIdent(), $update);

        $numberOfEntriesAfter = $this->countEntries(Boilerplate::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        $updatedBoilerplate = $this->sut->getBoilerplate($toUpdate->getIdent());
        static::assertEquals($update['text'], $updatedBoilerplate->getText());
        static::assertEquals($toUpdate->getTitle(), $updatedBoilerplate->getTitle());
    }

    /**
     * @throws Exception
     */
    public function testDeleteBoilerplate()
    {
        $numberOfEntriesBefore = $this->countEntries(Boilerplate::class);

        $doNotDelete = $this->fixtures->getReference('testBoilerplate1');
        $toDelete = $this->fixtures->getReference('testBoilerplate2');
        $this->sut->deleteBoilerplate($toDelete->getIdent());

        $numberOfEntriesAfter = $this->countEntries(Boilerplate::class);

        static::assertEquals($numberOfEntriesBefore - 1, $numberOfEntriesAfter);
        static::assertNotNull($this->sut->getBoilerplate($doNotDelete->getIdent()));
        // getBoilerplate wirft exception bei abfrage eines nicht existenten
        try {
            $this->sut->getBoilerplate($toDelete->getIdent());
            $this->fail('Expected Exception');
        } catch (Exception $e) {
            static::assertEquals(0, $e->getCode());
        }
    }

    public function testAddBoilerplateToCategory()
    {
        $boilerplate1 = $this->fixtures->getReference('testBoilerplate1');
        $boilerplate2 = $this->fixtures->getReference('testBoilerplate2');
        $category = $this->fixtures->getReference('testBoilerplateCategory1');

        static::assertCount(0, $category->getBoilerplates());

        $category->addBoilerplate($boilerplate1);

        static::assertCount(1, $category->getBoilerplates());

        $category->addBoilerplate($boilerplate2);

        static::assertCount(2, $category->getBoilerplates());

        $category->removeBoilerplate($boilerplate2);

        static::assertCount(1, $category->getBoilerplates());

        $category->removeBoilerplate($boilerplate1);

        static::assertCount(0, $category->getBoilerplates());
    }

    public function testAttachBoilerplateToGroup()
    {
        /** @var Boilerplate $boilerplate1 */
        $boilerplate1 = $this->fixtures->getReference('testBoilerplate1');
        /** @var BoilerplateGroup $group */
        $group = $this->fixtures->getReference('testBoilerplateEmptyGroup');

        // check setup:
        static::assertNull($boilerplate1->getGroup());
        static::assertEmpty($group->getBoilerplates());

        $boilerplate1->setGroup($group);
        static::assertEquals($group->getId(), $boilerplate1->getGroupId());
        static::assertCount(1, $group->getBoilerplates());
        static::assertContains($boilerplate1, $group->getBoilerplates());
    }

    public function testDetachBoilerplateFromGroup()
    {
        /** @var Boilerplate $boilerplate1 */
        $boilerplate1 = $this->fixtures->getReference('boilerplateOfGroup1');
        /** @var BoilerplateGroup $group */
        $group = $this->fixtures->getReference('testBoilerplateTestGroup1');

        // check setup:
        static::assertEquals($group->getId(), $boilerplate1->getGroupId());
        static::assertContains($boilerplate1, $group->getBoilerplates());

        $boilerplate1->detachGroup();
        static::assertNull($boilerplate1->getGroup());
        static::assertEmpty($group->getBoilerplates());
    }

    public function testSetBoilerplatesToGroup()
    {
        /** @var Boilerplate $boilerplate1 */
        $boilerplate1 = $this->fixtures->getReference('testBoilerplate1');
        /** @var Boilerplate $boilerplate2 */
        $boilerplate2 = $this->fixtures->getReference('testBoilerplate2');
        /** @var BoilerplateGroup $group */
        $group = $this->fixtures->getReference('testBoilerplateEmptyGroup');

        // check setup:
        static::assertNull($boilerplate1->getGroup());
        static::assertNull($boilerplate2->getGroup());
        static::assertEmpty($group->getBoilerplates());

        $group->setBoilerplates([$boilerplate1, $boilerplate2]);

        $em = static::$container->get('doctrine');
        $boilerplateRepository = $em->getRepository(Boilerplate::class);
        $boilerplateRepository->updateObject($boilerplate1);

        static::assertEquals($group, $boilerplate1->getGroup());
        static::assertEquals($group, $boilerplate2->getGroup());
        static::assertCount(2, $group->getBoilerplates());
        static::assertContains($boilerplate1, $group->getBoilerplates());
        static::assertContains($boilerplate2, $group->getBoilerplates());
    }

    public function testDeleteEmptyBoilerplatesGroup()
    {
        /** @var BoilerplateGroup $group */
        $group = $this->fixtures->getReference('testBoilerplateEmptyGroup');
        $groupId = $group->getId();

        // check setup:
        static::assertEmpty($group->getBoilerplates());

        $this->sut->deleteBoilerplateGroup($group);
        $groups = $this->getEntries(BoilerplateGroup::class, ['id' => $groupId]);
        static::assertCount(0, $groups);
    }

    public function testDetachBoilerplatesOnDeleteFilledBoilerplateGroup()
    {
        /** @var BoilerplateGroup $group */
        $group = $this->fixtures->getReference('testBoilerplateTestGroup2');
        $groupId = $group->getId();
        $relatedBoilerplateIds = [];
        // check setup:
        static::assertNotEmpty($group->getBoilerplates());
        foreach ($group->getBoilerplates() as $relatedBoilerplate) {
            $relatedBoilerplateIds[] = $relatedBoilerplate->getId();
        }

        $this->sut->deleteBoilerplateGroup($group);
        $groups = $this->getEntries(BoilerplateGroup::class, ['id' => $groupId]);
        static::assertCount(0, $groups);

        foreach ($relatedBoilerplateIds as $id) {
            $boilerplates = $this->getEntries(Boilerplate::class, ['ident' => $id]);
            static::assertCount(1, $boilerplates);
        }
    }

    public function testBoilerplateCategoryCascade()
    {
        /** @var BoilerplateCategory $category */
        $category = $this->fixtures->getReference('testBoilerplateCategory1');
        $boilerplate = new Boilerplate();
        $boilerplate->setTitle('Cascade Test');
        $boilerplate->setText('This is a test for cascade delete.');
        $boilerplate->addBoilerplateCategory($category);

        $this->getEntityManager()->persist($boilerplate);
        $this->getEntityManager()->flush();

        // Ensure the boilerplate is associated with the category
        static::assertContains($boilerplate, $category->getBoilerplates());

        // Delete the category and check if the boilerplate is also deleted
        $this->sut->deleteBoilerplate($boilerplate->getId());

        $deletedCategory = $this->getEntries(BoilerplateCategory::class, ['id' => $category->getId()]);
        $deletedBoilerplate = $this->getEntries(Boilerplate::class, ['id' => $boilerplate->getId()]);

        static::assertCount(0, $deletedCategory);
        static::assertCount(0, $deletedBoilerplate);
    }

}
