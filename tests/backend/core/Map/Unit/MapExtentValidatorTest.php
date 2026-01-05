<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Unit;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapExtentValidator;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class MapExtentValidatorTest extends UnitTestCase
{
    /** @var MapExtentValidator */
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $messageBag = $this->createMock(MessageBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->validator = new MapExtentValidator($messageBag, $logger);
    }

    public function testValidExtentContainsBoundingBox(): void
    {
        $mapExtent = [1000000, 6000000, 1500000, 6500000];
        $boundingBox = [1100000, 6100000, 1400000, 6400000];

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidExtentContainsBoundingBoxWithStructuredFormat(): void
    {
        $mapExtent = [
            'start' => ['latitude' => 1000000, 'longitude' => 6000000],
            'end'   => ['latitude' => 1500000, 'longitude' => 6500000],
        ];
        $boundingBox = [
            'start' => ['latitude' => 1100000, 'longitude' => 6100000],
            'end'   => ['latitude' => 1400000, 'longitude' => 6400000],
        ];

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);

        $this->assertTrue(true);
    }

    public function testBoundingBoxMinXOutOfBounds(): void
    {
        $mapExtent = [1000000, 6000000, 1500000, 6500000];
        $boundingBox = [999999, 6100000, 1400000, 6400000];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Startkartenausschnitt (boundingBox) muss vollständig innerhalb der Kartenbegrenzung (mapExtent) liegen');

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);
    }

    public function testBoundingBoxMaxXOutOfBounds(): void
    {
        $mapExtent = [1000000, 6000000, 1500000, 6500000];
        $boundingBox = [1100000, 6100000, 1500001, 6400000];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Startkartenausschnitt (boundingBox) muss vollständig innerhalb der Kartenbegrenzung (mapExtent) liegen');

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);
    }

    public function testBoundingBoxMinYOutOfBounds(): void
    {
        $mapExtent = [1000000, 6000000, 1500000, 6500000];
        $boundingBox = [1100000, 5999999, 1400000, 6400000];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Startkartenausschnitt (boundingBox) muss vollständig innerhalb der Kartenbegrenzung (mapExtent) liegen');

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);
    }

    public function testBoundingBoxMaxYOutOfBounds(): void
    {
        $mapExtent = [1000000, 6000000, 1500000, 6500000];
        $boundingBox = [1100000, 6100000, 1400000, 6500001];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Startkartenausschnitt (boundingBox) muss vollständig innerhalb der Kartenbegrenzung (mapExtent) liegen');

        $this->validator->validateExtentContainsBoundingBox($mapExtent, $boundingBox);
    }

    public function testNullExtentSkipsValidation(): void
    {
        $this->validator->validateExtentContainsBoundingBox(null, [1100000, 6100000, 1400000, 6400000]);
        $this->assertTrue(true);
    }

    public function testNullBoundingBoxSkipsValidation(): void
    {
        $this->validator->validateExtentContainsBoundingBox([1000000, 6000000, 1500000, 6500000], null);
        $this->assertTrue(true);
    }

    public function testEmptyArraysSkipValidation(): void
    {
        $this->validator->validateExtentContainsBoundingBox([], []);
        $this->assertTrue(true);
    }

    public function testBoundingBoxEqualToExtent(): void
    {
        $extent = [1000000, 6000000, 1500000, 6500000];

        // Bounding box exactly equals extent (edge case - should be valid)
        $this->validator->validateExtentContainsBoundingBox($extent, $extent);

        $this->assertTrue(true);
    }
}
