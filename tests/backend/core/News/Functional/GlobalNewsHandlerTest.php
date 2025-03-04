<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\News\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Category;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\News\GlobalNewsHandler;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use InvalidArgumentException;
use Tests\Base\FunctionalTestCase;

class GlobalNewsHandlerTest extends FunctionalTestCase
{
    /**
     * @var GlobalNewsHandler
     */
    protected $sut;
    /**
     * @var ManagerRegistry|object|null
     */
    protected $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(GlobalNewsHandler::class);
        $this->doctrine = self::$container->get('doctrine');
    }

    public function testGetGlobalNewsListStructure()
    {
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newsList = $this->sut->getNewsList($user);
        static::assertCount(2, $newsList);
        $this->checkSingleGlobalContentVariables($newsList[0]);
        static::assertCount(18, $newsList[0]);
        static::assertCount(3, $newsList[0]['roles']);
        static::assertCount(1, $newsList[0]['categories']);
        static::assertEquals('GlobalNews1 Title', $newsList[0]['title']);
    }

    public function testGetNewsListStructureWithLimit()
    {
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newsList = $this->sut->getNewsList($user, 1);
        static::assertTrue(is_array($newsList));
        static::assertCount(1, $newsList);
        $this->checkSingleGlobalContentVariables($newsList[0]);
        static::assertCount(18, $newsList[0]);
        static::assertCount(3, $newsList[0]['roles']);
    }

    public function testGetGlobalNewsAdminListReturnValueStructure()
    {
        $newsList = $this->sut->getGlobalNewsAdminList();
        static::assertCount(2, $newsList);
        static::assertCount(18, $newsList[0]);
        $this->checkSingleGlobalContentVariables($newsList[0]);
        static::assertCount(2, $newsList[1]['roles']);
        static::assertCount(6, $newsList[0]['roles'][1]);
        // Manuelle Sortierung muss für diesen test fertig sein!
        static::assertEquals($this->fixtures->getReference('testGlobalNews1')->getTitle(), $newsList[0]['title']);
        static::assertEquals('News Kategorie Nummer 1', $newsList[0]['categories'][0]['title']);
    }

    public function testGetSingleGlobalNews()
    {
        $singleNewsId = $this->fixtures->getReference('testGlobalNews1');

        $singleNews = $this->sut->getSingleNews($singleNewsId);
        static::assertTrue(is_array($singleNews));
        static::assertCount(18, $singleNews);
        $this->checkSingleGlobalContentVariables($singleNews);
        static::assertCount(3, $singleNews['roles']);
        static::assertCount(1, $singleNews['categories']
        );
    }

    public function testGetGlobalSingleNewsWithEmptyIdents()
    {
        $singleNewsId = '';

        $this->expectException(InvalidArgumentException::class);
        $singleNews = $this->sut->getSingleNews($singleNewsId);
    }

    public function testAddGlobalNews()
    {
        self::markSkippedForCIIntervention();
        /** @var Category $category */
        $category = $this->fixtures->getReference('testCategoryNews');
        $data = ['title' => 'testnews', 'description' => 'kurztext', 'enabled' => true, 'group_code' => [Role::GLAUTH, Role::GPSORG], 'category_id' => $category->getId()];

        $numberOfEntriesBefore = $this->countEntries(GlobalContent::class);
        $identsBefore = $this->doctrine->getManager()->getRepository(ManualListSort::class)->get('global:news')->getIdents();
        $singleNews = $this->sut->addNews($data);
        $numberOfEntriesAfter = $this->countEntries(GlobalContent::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore + 1);

        $this->checkSingleGlobalContentVariables($singleNews);
        static::assertCount(7, $singleNews['roles']);
        static::assertCount(1, $singleNews['categories']);
        static::assertEquals('News Kategorie Nummer 1', $singleNews['categories'][0]['title']);
        static::assertTrue($this->isCurrentTimestamp($singleNews['createDate']));
        static::assertTrue($this->isCurrentTimestamp($singleNews['modifyDate']));
        static::assertTrue($this->isCurrentTimestamp($singleNews['deleteDate']));
        static::assertEquals($data['title'], $singleNews['title']);
        static::assertEquals($data['description'], $singleNews['description']);
        static::assertEquals('', $singleNews['text']);
        static::assertEquals('', $singleNews['picture']);
        static::assertEquals('', $singleNews['pdf']);
        static::assertEquals('', $singleNews['pdftitle']);
        static::assertTrue($singleNews['enabled']);
        static::assertFalse($singleNews['deleted']);

        $manualSortList = $this->doctrine->getManager()->getRepository(ManualListSort::class)->get('global:news');
        static::assertEquals('global', $manualSortList->getPId());
        static::assertEquals('global:news', $manualSortList->getContext());
        static::assertEquals('content:news', $manualSortList->getNamespace());
        static::assertEquals($singleNews['ident'].','.$identsBefore, $manualSortList->getIdents());
    }

    public function testAddGlobalNewsWithEmptyDataArray()
    {
        // Data for new layer
        $data = [];
        $singleNews = $this->sut->addNews($data);
        $this->checkSingleGlobalContentVariables($singleNews);
        static::assertFalse($singleNews['enabled']);
        static::assertFalse($singleNews['deleted']);
        static::assertTrue($this->isCurrentTimestamp($singleNews['createDate']));
        static::assertEquals('', $singleNews['title']);
        static::assertEquals('', $singleNews['text']);
        static::assertEquals('', $singleNews['description']);
        static::assertEquals('', $singleNews['picture']);
        static::assertEquals('', $singleNews['pdf']);
        static::assertEquals('', $singleNews['pdftitle']);
        static::assertEquals('', $singleNews['pictitle']);
    }

    public function testSetManualSort()
    {
        $singleNews1 = $this->fixtures->getReference('testGlobalNews1');
        $singleNews2 = $this->fixtures->getReference('testGlobalNews2');
        $context = 'global:news';
        $sortIds = $singleNews1->getIdent().', '.$singleNews2->getIdent();

        $result = $this->sut->setManualSortOfGlobalNews($context, $sortIds);

        static::assertTrue($result);
    }

    public function testUpdateGlobalNews()
    {
        $singleNews1 = $this->fixtures->getReference('testGlobalNews1');
        $newTitle = 'GlobalNews1 Title verändert';
        $newGroupCode = [Role::GLAUTH, Role::GGUEST];

        // Data for new layer
        $data = [
            'ident'         => $singleNews1->getIdent(),
            'title'         => $newTitle,
            'text'          => 'Ich bin der Text der News1',
            'description'   => 'kurztextverändert',
            'picturetitle'  => '',
            'pdftitle'      => '',
            'enabled'       => true,
            'group_code'    => $newGroupCode,
            'category_name' => 'press',
        ];

        $numberOfEntriesBefore = $this->countEntries(GlobalContent::class);
        $singleNews = $this->sut->updateNews($data);

        // check entries of DB
        $numberOfEntriesAfter = $this->countEntries(GlobalContent::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check return value
        $this->checkSingleGlobalContentVariables($singleNews);
        static::assertCount(6, $singleNews['roles']);
    }

    /**
     * Does he throw an exception if, data array is empty.
     */
    public function testUpdateNewsWithNotExistingIdent()
    {
        $this->expectException(Exception::class);

        $newTitle = 'News1 Title verändert';
        $newGroupCode = [Role::GLAUTH, Role::GGUEST];

        // Data for new layer
        $data = ['ident' => '', 'title' => $newTitle, 'text' => 'Ich bin der Text der News1', 'description' => 'kurztextverändert', 'picturetitle' => '', 'pdftitle' => '', 'enabled' => true, 'group_code' => $newGroupCode];

        $this->sut->updateNews($data);
    }

    protected function checkSingleGlobalContentVariables($newsArray)
    {
        static::assertArrayHasKey('ident', $newsArray);
        $this->checkId($newsArray['ident']);
        static::assertArrayHasKey('title', $newsArray);
        static::assertTrue(is_string($newsArray['title']));
        static::assertArrayHasKey('text', $newsArray);
        static::assertTrue(is_string($newsArray['text']));
        static::assertArrayHasKey('description', $newsArray);
        static::assertTrue(is_string($newsArray['description']));
        static::assertArrayHasKey('picture', $newsArray);
        static::assertTrue(is_string($newsArray['picture']));
        static::assertArrayHasKey('pictitle', $newsArray);
        static::assertTrue(is_string($newsArray['pictitle']));
        static::assertArrayHasKey('pdf', $newsArray);
        static::assertTrue(is_string($newsArray['pdf']));
        static::assertArrayHasKey('pdftitle', $newsArray);
        static::assertTrue(is_string($newsArray['pdftitle']));
        static::assertArrayHasKey('enabled', $newsArray);
        static::assertTrue(is_bool($newsArray['enabled']));
        static::assertArrayHasKey('deleted', $newsArray);
        static::assertTrue(is_bool($newsArray['deleted']));
        static::assertArrayHasKey('createDate', $newsArray);
        static::assertTrue(is_numeric($newsArray['createDate']));
        static::assertTrue(0 < $newsArray['createDate']);
        static::assertArrayHasKey('modifyDate', $newsArray);
        static::assertTrue(is_numeric($newsArray['modifyDate']));
        static::assertTrue(0 < $newsArray['modifyDate']);
        static::assertArrayHasKey('deleteDate', $newsArray);
        static::assertTrue(is_numeric($newsArray['deleteDate']));
        static::assertTrue(0 < $newsArray['deleteDate']);
        static::assertArrayHasKey('roles', $newsArray);
        static::assertTrue(is_array($newsArray['roles']));
        static::assertArrayHasKey('categories', $newsArray);
        static::assertTrue(is_array($newsArray['categories']));
    }
}
