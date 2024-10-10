<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\News\Functional;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadNewsData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Tests\Base\FunctionalTestCase;

class ProcedureNewsServiceTest extends FunctionalTestCase
{
    /** @var ProcedureNewsService */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ProcedureNewsService::class);
        DemosPlanPath::setProjectPathFromConfig('projects/planfestsh');
    }

    public function testGetNewsListStructureWithoutRolesAndSortScope(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $newsList = $this->sut->getNewsList(
            $procedure->getId(),
            $user
        );

        $expectedCount = $this->countEntries(News::class, ['pId' => $procedure->getId()]);

        static::assertIsArray($newsList);
        static::assertArrayHasKey('result', $newsList);
        static::assertCount($expectedCount, $newsList['result']);

        foreach ($newsList['result'] as $newsData) {
            $this->compareNewsArrayToNewsObject($newsData, ['roles']);
            $this->checkSingleNewsVariables($newsData);
        }

        static::assertCount(3, $newsList['result'][0]['roles']);
    }

    public function testGetNewsListStructureWithRolesAndLimit(): void
    {
        $newsList = $this->sut->getNewsList(
            $this->fixtures->getReference('testProcedure2')->getId(),
            null,
            null,
            $limit = 1,
            $roles = [Role::CITIZEN]
        );

        static::assertIsArray($newsList);
        static::assertArrayHasKey('result', $newsList);
        static::assertCount(1, $newsList['result']);

        foreach ($newsList['result'] as $newsData) {
            $this->compareNewsArrayToNewsObject($newsData, ['roles']);
            $this->checkSingleNewsVariables($newsData);
        }
        static::assertCount(3, $newsList['result'][0]['roles']);
    }

    public function testGetNewsAdminListReturnValueStructure(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');

        /** @var ManualListSort $manualListSort */
        $manualListSort = $this->fixtures->getReference('testManualListSortNews');

        $newsAdminList = $this->sut->getProcedureNewsAdminList(
            $procedure->getId(),
            $manualListSort->getContext()
        );

        $expectedCount = $this->countEntries(News::class, ['pId' => $procedure->getId()]);

        static::assertIsArray($newsAdminList);
        static::assertArrayHasKey('result', $newsAdminList);
        static::assertCount($expectedCount, $newsAdminList['result']);

        foreach ($newsAdminList['result'] as $newsData) {
            $this->compareNewsArrayToNewsObject($newsData, ['roles']);
        }

        $this->checkSingleNewsVariables($newsAdminList['result'][0]);
        static::assertCount(3, $newsAdminList['result'][1]['roles']);
        static::assertCount(6, $newsAdminList['result'][0]['roles'][1]);
        // Manuelle Sortierung muss für diesen test fertig sein!
        static::assertEquals($this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_2)->getTitle(), $newsAdminList['result'][0]['title']);
    }

    public function testGetNewsAdminListReturnWithoutProcedureId(): void
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newsList = $this->sut->getNewsList('', $user);
        static::assertIsArray($newsList);
        static::assertArrayHasKey('result', $newsList);
        static::assertCount(0, $newsList['result']);
    }

    /**
     * @throws Exception
     */
    public function testGetSingleNews(): void
    {
        /** @var News $news */
        $news = $this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_1);

        $newsData = $this->sut->getSingleNews($news->getId());
        static::assertIsArray($newsData);

        $this->compareNewsArrayToNewsObject($newsData, ['roles']);

        $this->checkSingleNewsVariables($newsData);
        static::assertCount(3, $newsData['roles']);
    }

    /**
     * @throws Exception
     */
    public function testGetSingleNewsWithEmptyIds(): void
    {
        $singleNewsId = '';

        $singleNews = $this->sut->getSingleNews($singleNewsId);
        static::assertIsArray($singleNews);
        static::assertCount(2, $singleNews);
    }

    /**
     * @throws Exception
     */
    public function testAddNews(): void
    {
        $data =
            ['title'          => 'testnews',
                'description' => 'kurztext',
                'enabled'     => true,
                'group_code'  => [Role::GLAUTH, Role::GPSORG],
                'pId'         => $this->fixtures->getReference('testProcedure2')->getId(),
            ];

        $numberOfEntriesBefore = $this->countEntries(News::class);
        $result = $this->sut->addNews($data);
        $numberOfEntriesAfter = $this->countEntries(News::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore + 1);

        $this->checkSingleNewsVariables($result);
        static::assertEquals($data['description'], $result['description']);
        static::assertEquals($data['title'], $result['title']);
        static::assertTrue($data['enabled']);
        static::assertFalse($result['deleted']);
        static::assertArrayHasKey('createDate', $result);
        static::assertTrue($this->isCurrentTimestamp($result['createDate']));
        static::assertArrayHasKey('modifyDate', $result);
        static::assertTrue($this->isCurrentTimestamp($result['modifyDate']));
        static::assertArrayHasKey('deleteDate', $result);
        static::assertTrue($this->isCurrentTimestamp($result['deleteDate']));

        static::assertCount(7, $result['roles']);
        $singleRole = $result['roles'][0];
        static::assertArrayHasKey('ident', $singleRole);
        static::assertArrayHasKey('code', $singleRole);
        static::assertArrayHasKey('name', $singleRole);
        static::assertArrayHasKey('groupCode', $singleRole);
        static::assertEquals(Role::GLAUTH, $singleRole['groupCode']);
        static::assertArrayHasKey('groupName', $singleRole);
    }

    /**
     * Assert exception in case of data array is empty.
     */
    public function testAddNewsWithEmptyDataArray(): void
    {
        $this->expectException(Exception::class);

        $this->sut->addNews([]);
    }

    /**
     * @throws Exception
     */
    public function testSetManualSort(): void
    {
        $procedureId = $this->fixtures->getReference('testProcedure2')->getId();
        $singleNews1 = $this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_1);
        $singleNews2 = $this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_2);

        $context = 'procedure:'.$procedureId;
        $sortedIds = $singleNews1->getIdent().','.$singleNews2->getIdent();

        $result = $this->sut->setManualSortOfNews($procedureId, $sortedIds);
        static::assertTrue($result);

        /** @var ManualListSort $sort */
        $sort = $this->sut->getDoctrine()->getRepository(ManualListSort::class)
            ->get($context);

        $this->assertObjectHasProperty('idents', $sort);
        static::assertEquals($sortedIds, $sort->getIdents());
    }

    /**
     * @throws Exception
     */
    public function testUpdateNews(): void
    {
        $singleNews1 = $this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_1);
        $newTitle = 'News1 Title verändert';
        $newGroupCode = [Role::GLAUTH, Role::GGUEST];

        $data = ['ident' => $singleNews1->getIdent(), 'title' => $newTitle, 'text' => 'Ich bin der Text der News1', 'description' => 'kurztextverändert', 'picturetitle' => '', 'pdftitle' => '', 'enabled' => true, 'group_code' => $newGroupCode];

        $numberOfEntriesBefore = $this->countEntries(News::class);
        $singleNews = $this->sut->updateNews($data);
        $numberOfEntriesAfter = $this->countEntries(News::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check return value
        $this->checkSingleNewsVariables($singleNews);

        // Citizens should be included in Group Guest for news
        // @see 64842a3ee6337234c
        static::assertCount(7, $singleNews['roles']);
        $singleRole = $singleNews['roles'][0];
        static::assertArrayHasKey('ident', $singleRole);
        static::assertArrayHasKey('code', $singleRole);
        static::assertArrayHasKey('name', $singleRole);
        static::assertArrayHasKey('groupCode', $singleRole);
        static::assertEquals(Role::GLAUTH, $singleRole['groupCode']);
        static::assertArrayHasKey('groupName', $singleRole);
    }

    /**
     * Does he throw an exception if, data array is empty.
     */
    public function testUpdateNewsWithNotExistingIdent(): void
    {
        $this->expectException(Exception::class);

        $newTitle = 'News1 Title verändert';
        $newGroupCode = [Role::GLAUTH, Role::GGUEST];

        $data = ['ident' => '', 'title' => $newTitle, 'text' => 'Ich bin der Text der News1', 'description' => 'kurztextverändert', 'picturetitle' => '', 'pdftitle' => '', 'enabled' => true, 'group_code' => $newGroupCode];

        $this->sut->updateNews($data);
    }

    protected function checkSingleNewsVariables($singleNews): void
    {
        $this->checkArrayIncludesId($singleNews);
        static::assertArrayHasKey('title', $singleNews);
        static::assertIsString($singleNews['title']);
        static::assertArrayHasKey('text', $singleNews);
        static::assertIsString($singleNews['text']);
        static::assertArrayHasKey('description', $singleNews);
        static::assertIsString($singleNews['description']);
        static::assertArrayHasKey('picture', $singleNews);
        static::assertIsString($singleNews['picture']);
        static::assertArrayHasKey('pictitle', $singleNews);
        static::assertIsString($singleNews['pictitle']);
        static::assertArrayHasKey('pdf', $singleNews);
        static::assertIsString($singleNews['pdf']);
        static::assertArrayHasKey('pdftitle', $singleNews);
        static::assertIsString($singleNews['pdftitle']);
        static::assertArrayHasKey('pId', $singleNews);
        $this->checkId($singleNews['pId']);
        static::assertArrayHasKey('enabled', $singleNews);
        static::assertIsBool($singleNews['enabled']);
        static::assertArrayHasKey('deleted', $singleNews);
        static::assertIsBool($singleNews['deleted']);
        static::assertArrayHasKey('createDate', $singleNews);
        static::assertIsNumeric($singleNews['createDate']);
        static::assertTrue(0 < $singleNews['createDate']);
        static::assertArrayHasKey('modifyDate', $singleNews);
        static::assertIsNumeric($singleNews['modifyDate']);
        static::assertTrue(0 < $singleNews['modifyDate']);
        static::assertArrayHasKey('roles', $singleNews);
        static::assertIsArray($singleNews['roles']);

        static::assertArrayHasKey('createDate', $singleNews);
        static::assertTrue($this->isCurrentTimestamp($singleNews['createDate']));
        static::assertArrayHasKey('modifyDate', $singleNews);
        static::assertTrue($this->isCurrentTimestamp($singleNews['modifyDate']));
        static::assertArrayHasKey('deleteDate', $singleNews);
        static::assertTrue($this->isCurrentTimestamp($singleNews['deleteDate']));
    }

    /**
     * @throws Exception
     */
    public function testSetDesignatedSwitchDate(): void
    {
        /** @var News $singleNews1 */
        $singleNews1 = $this->fixtures->getReference(LoadNewsData::TEST_SINGLE_NEWS_1);

        static::assertFalse($singleNews1->isDeterminedToSwitch());

        $newsData = [
            'ident'                => $singleNews1->getIdent(),
            'designatedSwitchDate' => new DateTime(),
            'determinedToSwitch'   => true,
        ];

        $updatedNewsArray = $this->sut->updateNews($newsData);
        /** @var News $updatedNews */
        $updatedNews = $this->find(News::class, $updatedNewsArray['ident']);

        static::assertEquals($newsData['designatedSwitchDate'], $updatedNews->getDesignatedSwitchDate());
        static::assertEquals($newsData['determinedToSwitch'], $updatedNews->isDeterminedToSwitch());
    }

    /**
     * Cover functionality of getting News to switch today.
     *
     * @throws Exception
     */
    public function testGetNewsToSwitchStateToday(): void
    {
        $newsList = $this->sut->getNewsToSetStateToday();
        $dateOfToday = Carbon::now();

        foreach ($newsList as $news) {
            $carbonDate = Carbon::instance($news->getDesignatedSwitchDate());
            static::assertTrue($dateOfToday->isSameDay($carbonDate));
        }
    }

    public function testGetElementsToAutoSwitchState(): void
    {
        $assertedNewsToSwitch = collect([]);
        /** @var News[] $newsDeterminedToSwitch */
        $newsDeterminedToSwitch = $this->getEntries(News::class, ['determinedToSwitch' => true]);

        foreach ($newsDeterminedToSwitch as $news) {
            if (null !== $news->getDesignatedSwitchDate() && null !== $news->isDeterminedToSwitch()) {
                $assertedNewsToSwitch->push($news);
            }
        }

        /** @var News[] $news */
        $news = $this->sut->getDoctrine()->getManager()
            ->getRepository(News::class)->getNewsToAutoSetState();

        static::assertCount($assertedNewsToSwitch->count(), $news);
    }

    /**
     * @param array $newsData
     */
    public function compareNewsArrayToNewsObject($newsData, array $attributesToSkip = [])
    {
        $this->checkArrayIncludesId($newsData);
        $news = $this->find(News::class, $newsData['id']);

        $this->checkIfArrayHasEqualDataToObject(
            $newsData,
            $news,
            $attributesToSkip
        );
    }
}
