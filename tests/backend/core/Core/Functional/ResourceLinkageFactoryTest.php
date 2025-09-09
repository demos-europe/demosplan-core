<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceLinkageFactory;
use Exception;
use JsonException;
use Tests\Base\FunctionalTestCase;
use TypeError;

class ResourceLinkageFactoryTest extends FunctionalTestCase
{
    /**
     * @var ResourceLinkageFactory
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new ResourceLinkageFactory();
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonA()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->createFromJsonRequestString(
            '{"data" : { "type" : "publicAffairsAgent", "id": "123" }, "moreData" : "for you"}'
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonB()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->createFromJsonRequestString(
            '{ "type": "publicAffairsAgent", "id": "123" }'
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonC()
    {
        $this->expectException(JsonException::class);
        $this->sut->createFromJsonRequestString(
            '{"data": [{ "type": "publicAffairsAgent", "id: "123" }]}'
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonD()
    {
        $this->expectException(JsonException::class);
        $this->sut->createFromJsonRequestString(
            '{"data": [{ "type": "publicAffairsAgent", "id: "123" }], "foo": 42}'
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonE()
    {
        $this->expectException(TypeError::class);
        $this->sut->createFromJsonRequestString('true');
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonF()
    {
        $this->expectException(TypeError::class);
        $this->sut->createFromJsonRequestString('true');
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonG()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->createFromJsonRequestString(
            '{"foo": [{ "type": "publicAffairsAgent", "id": "123" }]}'
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestStringWithInvalidJsonH()
    {
        $this->expectException(TypeError::class);
        $this->sut->createFromJsonRequestString('{"data": [true]}');
    }

    /**
     * @throws Exception
     */
    public function testCreateFromJsonRequestString()
    {
        self::markSkippedForCIIntervention();

        $resourceLinkage = $this->sut->createFromJsonRequestString(
            '{"data": [{ "type": "publicAffairsAgent", "id": "123" }]}'
        );
        self::assertFalse($resourceLinkage->getCardinality()->isToOne());
        self::assertTrue($resourceLinkage->getCardinality()->isToMany());
        $resourceIdentifierObjects = $resourceLinkage->getResourceIdentifierObjects();
        self::assertCount(1, $resourceIdentifierObjects);
        foreach ($resourceIdentifierObjects as $resourceIdentifierObject) {
            self::assertSame('publicAffairsAgent', $resourceIdentifierObject->getType());
            self::assertSame('123', $resourceIdentifierObject->getId());
        }
    }
}
