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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\BoilerplateDeletionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\BoilerplateVO;
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
        $this->sut = self::getContainer()->get(ProcedureService::class);
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

    public function testUpdateBoilerplateVOResetsVerifiedOnContentChange()
    {
        /** @var Boilerplate $boilerplate */
        $boilerplate = $this->fixtures->getReference('testBoilerplate1');
        $boilerplate->setVerified(true);
        $this->getEntityManager()->flush();

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setText('this text differs from the blueprint original');

        $updatedBoilerplate = $this->sut->updateBoilerplateVO($boilerplateVO);

        static::assertFalse($updatedBoilerplate->isVerified());
    }

    public function testUpdateBoilerplateVOKeepsVerifiedWithoutContentChange()
    {
        /** @var Boilerplate $boilerplate */
        $boilerplate = $this->fixtures->getReference('testBoilerplate1');
        $boilerplate->setVerified(true);
        $this->getEntityManager()->flush();

        $boilerplateVO = new BoilerplateVO($boilerplate);

        $updatedBoilerplate = $this->sut->updateBoilerplateVO($boilerplateVO);

        static::assertTrue($updatedBoilerplate->isVerified());
    }

    public function testUpdateBoilerplateVOExplicitVerifiedWinsOverContentChange()
    {
        /** @var Boilerplate $boilerplate */
        $boilerplate = $this->fixtures->getReference('testBoilerplate1');
        $boilerplate->setVerified(true);
        $this->getEntityManager()->flush();

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setText('this text differs from the blueprint original');
        $boilerplateVO->setVerified(true);

        $updatedBoilerplate = $this->sut->updateBoilerplateVO($boilerplateVO);

        static::assertTrue($updatedBoilerplate->isVerified());
    }

    public function testUpdateBoilerplateVOExplicitVerifiedUnsetsVerified()
    {
        /** @var Boilerplate $boilerplate */
        $boilerplate = $this->fixtures->getReference('testBoilerplate1');
        $boilerplate->setVerified(true);
        $this->getEntityManager()->flush();

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setVerified(false);

        $updatedBoilerplate = $this->sut->updateBoilerplateVO($boilerplateVO);

        static::assertFalse($updatedBoilerplate->isVerified());
    }

    public function testUpdateBoilerplateVOExplicitVerifiedSetsVerified()
    {
        /** @var Boilerplate $boilerplate */
        $boilerplate = $this->fixtures->getReference('testBoilerplate1');
        static::assertFalse($boilerplate->isVerified());

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setVerified(true);

        $updatedBoilerplate = $this->sut->updateBoilerplateVO($boilerplateVO);

        static::assertTrue($updatedBoilerplate->isVerified());
    }

    public function testAddBoilerplateVOSetsVerifiedWhenExplicit()
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();

        $boilerplateVO = new BoilerplateVO();
        $boilerplateVO->setTitle('verified on create');
        $boilerplateVO->setText('some text');
        $boilerplateVO->setVerified(true);

        $createdBoilerplate = $this->sut->addBoilerplateVO($procedureId, $boilerplateVO);

        static::assertInstanceOf(Boilerplate::class, $createdBoilerplate);
        static::assertTrue($createdBoilerplate->isVerified());
    }

    public function testAddBoilerplateVODefaultsToUnverified()
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();

        $boilerplateVO = new BoilerplateVO();
        $boilerplateVO->setTitle('unverified on create');
        $boilerplateVO->setText('some text');

        $createdBoilerplate = $this->sut->addBoilerplateVO($procedureId, $boilerplateVO);

        static::assertInstanceOf(Boilerplate::class, $createdBoilerplate);
        static::assertFalse($createdBoilerplate->isVerified());
    }

    /**
     * DPLAN-18271: prepareBoilerplateDeletion() only flags the row for asynchronous
     * materialize-and-delete ({@see BoilerplateDeletionService}) —
     * it does not remove it. The former testDeleteBoilerplate asserted an immediate row
     * removal, which is no longer this method's behavior.
     *
     * @throws Exception
     */
    public function testPrepareBoilerplateDeletionFlagsWithoutRemovingRow()
    {
        $numberOfEntriesBefore = $this->countEntries(Boilerplate::class);

        $doNotFlag = $this->fixtures->getReference('testBoilerplate1');
        $toFlag = $this->fixtures->getReference('testBoilerplate2');
        $result = $this->sut->prepareBoilerplateDeletion($toFlag->getIdent());

        static::assertTrue($result);
        static::assertEquals($numberOfEntriesBefore, $this->countEntries(Boilerplate::class));

        $untouched = $this->sut->getBoilerplate($doNotFlag->getIdent());
        static::assertNotNull($untouched);
        static::assertFalse($untouched->isPendingDeletion());

        $flagged = $this->sut->getBoilerplate($toFlag->getIdent());
        static::assertNotNull($flagged);
        static::assertTrue($flagged->isPendingDeletion());
    }

    public function testPrepareBoilerplateDeletionReturnsFalseForNonExistentBoilerplate()
    {
        $result = $this->sut->prepareBoilerplateDeletion('does-not-exist');

        static::assertFalse($result);
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

        $em = self::getContainer()->get('doctrine');
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
        $category = $this->fixtures->getReference('testBoilerplateEmptyCategory');

        /** @var Procedure $blueprintWithBoilerplates */
        $blueprintWithBoilerplates = $this->getReference('testmasterProcedureWithBoilerplates');

        $boilerplate = new Boilerplate();
        $boilerplate->setTitle('Cascade Test');
        $boilerplate->setText('This is a test for cascade delete.');
        $boilerplate->setProcedure($blueprintWithBoilerplates);
        $boilerplate->addBoilerplateCategory($category);

        $this->getEntityManager()->persist($boilerplate);
        $this->getEntityManager()->flush();

        // Ensure the boilerplate is associated with the category
        static::assertContains($boilerplate, $category->getBoilerplates());

        // DPLAN-18271: the row is only physically removed by BoilerplateDeletionService
        // (the async materialize-and-delete job), not by prepareBoilerplateDeletion()
        // (which only flags it) — this test is specifically about cascade behavior on
        // physical row removal, so it drives the deletion service directly.
        //
        // Instantiated directly rather than fetched from the container: it has no
        // consumers yet at this point in the implementation, so the DI container's
        // dead-code elimination removes it as an unused private service (same reasoning
        // as BoilerplateTagSubstitutionServiceTest).
        $boilerplateDeletionService = new BoilerplateDeletionService(
            self::getContainer()->get(BoilerplateTagSubstitutionService::class),
            self::getContainer()->get(BoilerplateRepository::class),
            self::getContainer()->get(TransactionService::class),
            self::getContainer()->get(EntityContentChangeService::class),
        );
        $boilerplateDeletionService->materializeAndDelete($boilerplate);

        $deletedBoilerplate = $this->getEntries(Boilerplate::class, ['ident' => $boilerplate->getId()]);
        static::assertCount(0, $deletedBoilerplate);

        // Category must still exist — it's a ManyToMany relation, deleting one
        // boilerplate must not cascade-delete the category
        $remainingCategory = $this->getEntries(BoilerplateCategory::class, ['id' => $category->getId()]);
        static::assertCount(1, $remainingCategory);
    }
}
