<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Exception;
use Tests\Base\FunctionalTestCase;

class SettingServiceTest extends FunctionalTestCase
{
    /**
     * @var ContentService
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
        $this->sut = $this->getContainer()->get(ContentService::class);
    }

    /**
     * Testing 'getting all settings'.
     *
     * @throws Exception
     */
    public function testGetAllSettings()
    {
        $settings = $this->sut->getAllSettings();

        static::assertTrue(is_array($settings));

        static::assertCount(4, $settings);
        static::assertCount(12, $settings[0]);
        static::assertArrayHasKey('ident', $settings[0]);
        $this->checkId($settings[0]['ident']);
        static::assertArrayHasKey('procedureId', $settings[0]);
        static::assertArrayHasKey('userId', $settings[0]);
        static::assertArrayHasKey('orgaId', $settings[0]);
        static::assertArrayHasKey('key', $settings[0]);
        static::assertArrayHasKey('content', $settings[0]);
    }

    /**
     * Testing 'getting all settings'.
     */
    public function testGetAllSettingsNoResult()
    {
        $this->databaseTool->loadFixtures([]);
        $settings = $this->sut->getAllSettings();
        static::assertTrue(is_array($settings));
        static::assertEmpty($settings);
    }

    /**
     * Testing the method of getting an array of settings by key.
     *
     * @throws Exception
     */
    public function testGetSettings()
    {
        /** @var Setting $testSettings */
        $testSettings = $this->fixtures->getReference('testSettings');
        $settings = $this->sut->getSettings($testSettings->getKey());

        $this->testCommonSettings($settings);
        static::assertEquals($testSettings->getKey(), $settings[0]['key']);
        static::assertArrayHasKey('content', $settings[0]);
        static::assertEquals($testSettings->getContent(), $settings[0]['content']);
    }

    /**
     *  Testing the method getting settings by key with no existing key.
     */
    public function testGetSettingsNoResult()
    {
        $settings = $this->sut->getSettings('keydoesnotexist');
        static::assertTrue(is_array($settings));
        static::assertEmpty($settings);
    }

    /**
     * Testing the method of gettingContent of settings by key.
     *
     * @throws Exception
     */
    public function testGetSettingContent()
    {
        /** @var Setting $testSettings */
        $testSettings = $this->fixtures->getReference('testSettings');
        $settings = $this->sut->getSettingContent($testSettings->getKey());

        $this->testCommonSettings($settings);
        static::assertEquals($testSettings->getKey(), $settings[1]['key']);
        static::assertArrayHasKey('content', $settings[1]);
        static::assertEquals($testSettings->getContent(), $settings[1]['content']);

        $settings = $this->sut->getSettingContent('testkey');
        static::assertTrue(is_string($settings));
        static::assertTrue(0 < strlen($settings));
        static::assertEquals('http://urlstring', $settings);
    }

    /**
     * Testing the Method 'getSettingContent', if Key doesn't exist.
     */
    public function testGetSettingContentNoResult()
    {
        $settings = $this->sut->getSettingContent('keydoesnotexist');
        static::assertTrue(is_array($settings));
        static::assertEmpty($settings);
    }

    public function testGetSettingsFilter()
    {
        $settings = $this->sut->getSettings('emailNotificationEndingPhase');
        static::assertTrue(is_array($settings));
        static::assertCount(2, $settings);

        $filter = SettingsFilter::whereOrga($this->fixtures->getReference('testOrgaFP'))->lock();
        $settings = $this->sut->getSettings('emailNotificationEndingPhase', $filter);
        static::assertTrue(is_array($settings));
        static::assertCount(1, $settings);
    }

    /**
     * Testing saving a new setting.
     *
     * @throws Exception
     */
    public function testSetNewSetting()
    {
        // create new entry:
        $testData = [];
        $testKey = 'emailNotificationNewStatement';
        $testData['orgaId'] = $this->fixtures->getReference('testOrgaFP')->getId();

        $testData['content'] = 'true';

        // save in DB with method:
        $amountBefore = $this->countEntries(Setting::class);
        $returnValue = $this->sut->setSetting($testKey, $testData);
        self::assertInstanceOf(Setting::class, $returnValue);

        // check, if DB has +1 entry

        $amountAfter = $this->countEntries(Setting::class);
        static::assertEquals($amountBefore + 1, $amountAfter);

        // get the new entry:
        $testResultAfter = $this->sut->getSettings($testKey);
        static::assertTrue(isset($testResultAfter[0]));
        static::assertEquals(1, count($testResultAfter));
        // compare to expected data:
        static::assertEquals($testData['content'], $testResultAfter[0]['content']);
        static::assertEquals($testData['orgaId'], $testResultAfter[0]['orga']->getId());
        static::assertEquals($testData['orgaId'], $testResultAfter[0]['orgaId']);
    }

    public function testSetBoolSetting()
    {
        // create new entry:
        $testData = [];
        $testKey = 'emailNotificationReleasedStatement';
        $testData['userId'] = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId();

        $testData['content'] = true;

        // save in DB with method:
        $amountBefore = $this->countEntries(Setting::class);
        $returnValue = $this->sut->setSetting($testKey, $testData);
        self::assertInstanceOf(Setting::class, $returnValue);

        // check, if DB has +1 entry

        $amountAfter = $this->countEntries(Setting::class);
        static::assertEquals($amountBefore + 1, $amountAfter);

        // get the new entry:
        $testResultAfter = $this->sut->getSettings($testKey);
        static::assertTrue(isset($testResultAfter[0]));
        static::assertEquals(1, count($testResultAfter));
        // compare to expected data:
        // bool should be saved as string
        static::assertEquals('true', $testResultAfter[0]['content']);
        static::assertEquals($testData['userId'], $testResultAfter[0]['userId']);
        static::assertEquals($testData['userId'], $testResultAfter[0]['user']->getId());
        static::assertEquals(null, $testResultAfter[0]['orgaId']);

        // test false value
        $testData['content'] = false;
        $returnValue = $this->sut->setSetting($testKey, $testData);
        self::assertInstanceOf(Setting::class, $returnValue);
        // bool should be saved as string
        static::assertEquals('true', $testResultAfter[0]['content']);
    }

    public function testSetBoolSettingObject()
    {
        // create new entry:
        $testData = [];
        $testKey = 'emailNotificationReleasedStatement';
        $testData['userId'] = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId();

        $testData['content'] = true;

        // save in DB with method:
        $amountBefore = $this->countEntries(Setting::class);
        $returnValue = $this->sut->setSetting($testKey, $testData);
        self::assertInstanceOf(Setting::class, $returnValue);

        // check, if DB has +1 entry

        $amountAfter = $this->countEntries(Setting::class);
        static::assertEquals($amountBefore + 1, $amountAfter);

        // get the new entry:
        $testResultAfter = $this->sut->getSettings($testKey, null, false);
        static::assertTrue(isset($testResultAfter[0]));
        static::assertEquals(1, count($testResultAfter));
        // compare to expected data:
        // bool should be saved as string
        static::assertEquals('true', $testResultAfter[0]->getContent());
        static::assertEquals(true, $testResultAfter[0]->getContentBool());
        static::assertEquals($testData['userId'], $testResultAfter[0]->getUserId());
        static::assertEquals($testData['userId'], $testResultAfter[0]->getUser()->getId());
        static::assertEquals(null, $testResultAfter[0]->getOrgaId());

        // test false value
        $testData['content'] = false;
        $returnValue = $this->sut->setSetting($testKey, $testData);
        self::assertInstanceOf(Setting::class, $returnValue);
        $testResultAfter = $this->sut->getSettings($testKey, null, false);
        // bool should be saved as string
        static::assertEquals('false', $testResultAfter[0]->getContent());
        static::assertEquals(false, $testResultAfter[0]->getContentBool());
    }

    /**
     *  Testing saving a new setting with unsufficient variables.
     */
    public function testSetSettingWithMissingVariables()
    {
        // create new entry with no required content:
        $testData = [];
        $testKey = 'emailNotificationNewStatement';
        $testData['orgaId'] = '9c2a287a-5t67-4910-865a-4e758248e4f1';
        $amountBefore = $this->countEntries(Setting::class);

        // save in DB with method:
        try {
            $returnValue = @$this->sut->setSetting($testKey, $testData);
            $this->fail('Case: Content is empty');
        } catch (Exception $e) {
            $type = get_class($e);
            static::assertEquals('Doctrine\DBAL\Exception\NotNullConstraintViolationException', $type);
        }

        // create new entry with empty/not defined key:
        $testData = [];
        $testKey = '';
        $testData['orgaId'] = '9c2a287a-5t67-4910-865a-4e758248e4f1';
        $testData['content'] = true;

        // save in DB with method:
        try {
            $this->sut->setSetting($testKey, $testData);
            $this->fail('Case: Key is not defined');
        } catch (Exception $e) {
            $type = get_class($e);
            static::assertEquals('Exception', $type);
        }

        // check, if DB has still the same amount of entries
        $amountAfter = $this->countEntries(Setting::class);
        static::assertEquals($amountBefore, $amountAfter);
    }

    /**
     * Testing deleting a single setting by ident.
     */
    public function testDeleteSingleSettings()
    {
        $testSettings = $this->fixtures->getReference('testSettings');
        $this->sut->deleteSetting($testSettings->getIdent());
        $amountOfEntries = $this->countEntries(Setting::class);
        static::assertEquals(3, $amountOfEntries);
    }

    /**
     * testing deleting a single setting with empty/notexisting ident.
     */
    public function testDeleteSingleSettingsException()
    {
        $this->expectException(Exception::class);

        $this->sut->deleteSetting('');
    }

    public function testUpdateSetting()
    {
        $settingFixture = $this->fixtures->getReference('testSettings');

        $data = [];
        $data['content'] = 'false';
        $data['orgaId'] = $settingFixture->getOrga()->getId();
        $key = $settingFixture->getKey();

        $numberOfEntriesBefore = $this->countEntries(Setting::class);
        $this->sut->setSetting($key, $data);
        $numberOfEntriesAfter = $this->countEntries(Setting::class);

        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        $setting = $this->getEntityManager()->find(Setting::class, $settingFixture->getIdent());
        $currentModifyDate = $this->dateHelper->convertDateToString($setting->getModified());
        static::assertTrue($this->isCurrentDateTime($currentModifyDate));
        static::assertEquals($data['content'], $setting->getContent());
    }

    public function testCorruptKeyUpdateSetting()
    {
        $this->expectException(Exception::class);

        $corruptKey = 'notExsisting';
        $this->sut->setSetting($corruptKey, []);
    }

    /**
     * @dataProvider getSettingFieldStatusOfProcedure
     */
    public function testStoreFieldStatus(string $key, string $content, string $procedureReference): void
    {
        $procedureId = $this->getProcedureReference($procedureReference)->getId();

        $filter = SettingsFilter::whereProcedureId($procedureId)
            ->andContent($content)
            ->lock();
        $result = $this->sut->getSettings($key, $filter, false);
        static::assertEquals([], $result);

        $this->sut->setSetting($key, ['procedureId' => $procedureId, 'content' => $content]);
        $result = $this->sut->getSettings($key, $filter, false);
        static::assertCount(1, $result);
        static::assertInstanceOf(Setting::class, $result[0]);

        $setting = $result[0];
        static::assertEquals($setting->getContent(), $content);
        static::assertEquals($key, $setting->getKey());
        static::assertInstanceOf(Procedure::class, $setting->getProcedure());
        static::assertEquals($procedureId, $setting->getProcedureId());
        static::assertNull($setting->getOrga());
        static::assertNull($setting->getOrgaId());
        static::assertNull($setting->getUser());
        static::assertNull($setting->getUserId());
    }

    /**
     * DataProvider.
     *
     * @return array<int, array<int, string>>
     */
    public function getSettingFieldStatusOfProcedure(): array
    {
        return [
            ['nameUrlComplete', 'true', LoadProcedureData::TESTPROCEDURE],
            ['infoComplete', 'true', LoadProcedureData::TESTPROCEDURE],
            ['locationComplete', 'true', LoadProcedureData::TESTPROCEDURE],
            ['phaseInternalComplete', 'true', LoadProcedureData::TESTPROCEDURE],
            ['phaseExternalComplete', 'true', LoadProcedureData::TESTPROCEDURE],
            ['internalComplete', 'true', LoadProcedureData::TESTPROCEDURE],
        ];
    }

    private function testCommonSettings(?array $settings): void
    {
        static::assertIsArray($settings);
        static::assertCount(2, $settings);
        static::assertArrayHasKey('ident', $settings[0]);
        $this->checkId($settings[0]['ident']);
        static::assertArrayHasKey('orgaId', $settings[0]);
        $this->checkId($settings[0]['orgaId']);
        static::assertArrayHasKey('orga', $settings[0]);
        static::assertInstanceOf(Orga::class, $settings[0]['orga']);
        static::assertArrayHasKey('key', $settings[0]);
    }
}
