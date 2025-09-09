<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Help\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Logic\Help\HelpService;
use Exception;
use Tests\Base\FunctionalTestCase;

class ContextualHelpServiceTest extends FunctionalTestCase
{
    /**
     * @var HelpService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(HelpService::class);
    }

    public function testGetAllContextualHelp()
    {
        // Does he fetch all help items in the expected way
        $helpList = $this->sut->getHelpAll();
        static::assertTrue(is_array($helpList));
        static::assertCount(2, $helpList);
        static::assertInstanceOf(ContextualHelp::class, $helpList[0]);
    }

    public function testGetSingleContextualHelp()
    {   // fetch the id
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        // Does he fetch all infos about one specific help item by id
        /** @var ContextualHelp $singleHelp */
        $singleHelp = $this->sut->getHelp($testContextualHelp->getIdent());
        static::assertInstanceOf(ContextualHelp::class, $singleHelp);
        $this->checkId($singleHelp->getId());

        // does he fetch all infos about one specific help item by key
        $singleHelp = $this->sut->getHelpByKey('help.key2');
        static::assertInstanceOf(ContextualHelp::class, $singleHelp);
        $this->checkId($singleHelp->getId());
    }

    public function testExceptionInGettingContextualHelpsWithNoExistingId()
    {
        $singleHelp = $this->sut->getHelp('12345678910');
        static::assertNull($singleHelp);
    }

    public function testExceptionInGettingContextualHelpsWithNoExistingKey()
    {
        $singleHelp = $this->sut->getHelpByKey('help.no.key');
        static::assertNull($singleHelp);
    }

    public function testExceptionInGettingContextualHelpsWithKey()
    {
        $singleHelp = $this->sut->getHelp(null);
        static::assertNull($singleHelp);
    }

    public function testUpdateOfContextualHelp()
    {
        // fetch the id
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        // Case: Text is being altered
        $data = [];
        $data['text'] = 'Ich bin die Kontexthilfe für den Weiterentwicklungsbereich.Und wurde jetzt verändert';
        $response = $this->sut->updateHelp($testContextualHelp->getIdent(), $data);
        static::assertTrue($response);

        // check, if new entry is right
        $singleHelp = $this->sut->getHelp($testContextualHelp->getIdent());
        static::assertEquals($data['text'], $singleHelp->getText());
    }

    public function testExceptionCasesByUpdatingContextualHelps()
    {
        // fetch the id
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');

        // Case: Id doesn't exist
        $data = [];
        $data['text'] = 'Ich bin die Kontexthilfe für den Weiterentwicklungsbereich.Und wurde jetzt verändert';
        try {
            $response = $this->sut->updateHelp('fakeId', $data);
            $this->fail('Case:Id doesnt exist');
        } catch (Exception $e) {
            $type = get_class($e);
            static::assertEquals('InvalidArgumentException', $type);
        }
    }

    /**
     * Case: Empty Variables.
     */
    public function testUpdateHelpWithEmptyValues()
    {
        // fetch the id
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        $data = [];
        $this->expectException(Exception::class);
        $response = $this->sut->updateHelp($testContextualHelp->getIdent(), $data);
    }

    /**
     *  Case: text-variable is null.
     */
    public function testUpdateHelpWithTextIsNull()
    {
        // case: text-variable is null
        // fetch the id
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        $data['text'] = null;
        $this->expectException(Exception::class);
        $response = $this->sut->updateHelp($testContextualHelp->getIdent(), $data);
    }

    // Check result, when Database is empty
    public function testWithEmptyDatabase()
    {
        $this->databaseTool->loadFixtures([]);
        $helpList = $this->sut->getHelpAll();
        static::assertTrue(is_array($helpList));
        static::assertCount(0, $helpList);
    }

    public function testCreateHelp()
    {
        $help_data = ['key' => 'sischer.sischer.de.schluessel',
            'text'          => 'sch bin dr text vo dr hilf. isch helf de leut bi dem was se tun.', ];
        $entity = $this->sut->createHelp($help_data);
        static::assertNotNull($entity->getIdent());
        static::assertEquals($entity->getText(), $help_data['text']);
        static::assertEquals($entity->getKey(), $help_data['key']);
    }

    public function testDeleteHelp()
    {
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        $id = $testContextualHelp->getIdent();
        $this->sut->deleteHelp($id);
        $notExistant = $this->sut->getHelp($id);
        static::assertNull($notExistant);
    }

    public function testRelationOfDeletedHelp()
    {
        $relatedGisLayer = $this->fixtures->getReference('testGisLayer5');
        $numberOfContextHelpsBefore = $this->countEntries(ContextualHelp::class);
        $numberOfGisBefore = $this->countEntries(GisLayer::class);
        $testContextualHelp = $this->fixtures->getReference('testContextualHelp');
        $id = $testContextualHelp->getIdent();

        // to test the relation minimum 1 gis have to use this ContextualHelp
        static::assertGreaterThanOrEqual(1, $numberOfGisBefore);

        $this->sut->deleteHelp($id);

        static::assertCount($numberOfContextHelpsBefore - 1, $this->getEntries(ContextualHelp::class));
        static::assertCount($numberOfGisBefore, $this->getEntries(GisLayer::class));

        // get gis->getHelp == null!
        $relatedGisLayer = $this->getEntries(GisLayer::class, ['ident' => $relatedGisLayer->getIdent()]);
        $relatedHelp = $relatedGisLayer[0]->getContextualHelp();
        static::assertNull($relatedHelp);
    }
}
