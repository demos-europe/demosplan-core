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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use Psr\Log\NullLogger;
use Tests\Base\FunctionalTestCase;

class EntityHelperTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new EntityHelper(new NullLogger());
    }

    public function testExtractIdOfObject(): void
    {
        /** @var Statement $object */
        $object = $this->fixtures->getReference('testStatement');
        $id = $this->sut->extractId($object);
        static::assertEquals($object->getId(), $id);
    }

    public function testExtractIdOArray(): void
    {
        /** @var Statement $object */
        $object = $this->fixtures->getReference('testStatement');
        /** @var [] $array */
        $array = $this->sut->toArray($object);
        $id = $this->sut->extractId($array);
        static::assertEquals($object->getId(), $id);
    }

    public function testArrayToArray(): void
    {
        $array = $this->sut->toArray([]);
        static::assertIsArray($array, 'Variable should be of type array but isn\'t.');
        static::assertCount(0, $array);
    }

    public function testToArray(): void
    {
        // test Statement to Array:
        $objectToConvert = $this->fixtures->getReference('testStatement');

        $this->checkIfArrayHasEqualDataToObject($this->sut->toArray($objectToConvert), $objectToConvert, [Statement::class]);

        // test Procedure to Array:
        $objectToConvert = $this->fixtures->getReference('testProcedure2');
        $this->checkIfArrayHasEqualDataToObject($this->sut->toArray($objectToConvert), $objectToConvert, [Procedure::class]);

        // test DraftStatement to Array:
        $objectToConvert = $this->fixtures->getReference('testDraftStatement');
        $this->checkIfArrayHasEqualDataToObject($this->sut->toArray($objectToConvert), $objectToConvert, [DraftStatement::class]);

        // test Tag to Array:
        $objectToConvert = $this->fixtures->getReference('testFixtureTag_1');
        $this->checkIfArrayHasEqualDataToObject($this->sut->toArray($objectToConvert), $objectToConvert, [Tag::class]);
    }
}
