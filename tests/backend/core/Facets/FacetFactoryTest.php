<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Facets;

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\FacetFactory;
use Illuminate\Support\Collection;
use ReflectionClass;
use stdClass;
use Tests\Base\FunctionalTestCase;

class FacetFactoryTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(FacetFactory::class);
    }

    public function testApplyNaturalSortingWithSimpleNumbers(): void
    {
        $collection = collect([
            $this->createTestObject('Item 11'),
            $this->createTestObject('Item 2'),
            $this->createTestObject('Item 1'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['Item 1', 'Item 2', 'Item 11'], $titles);
    }

    public function testApplyNaturalSortingWithComplexNumbers(): void
    {
        $collection = collect([
            $this->createTestObject('Tag 100'),
            $this->createTestObject('Tag 2'),
            $this->createTestObject('Tag 20'),
            $this->createTestObject('Tag 3'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['Tag 2', 'Tag 3', 'Tag 20', 'Tag 100'], $titles);
    }

    public function testApplyNaturalSortingCaseInsensitive(): void
    {
        $collection = collect([
            $this->createTestObject('Zebra'),
            $this->createTestObject('apple'),
            $this->createTestObject('Banana'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['apple', 'Banana', 'Zebra'], $titles);
    }

    public function testApplyNaturalSortingAlphabeticalOnly(): void
    {
        $collection = collect([
            $this->createTestObject('Charlie'),
            $this->createTestObject('Alpha'),
            $this->createTestObject('Bravo'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['Alpha', 'Bravo', 'Charlie'], $titles);
    }

    public function testApplyNaturalSortingEmptyCollection(): void
    {
        $collection = collect([]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        self::assertCount(0, $result);
    }

    public function testApplyNaturalSortingSingleItem(): void
    {
        $collection = collect([
            $this->createTestObject('Only One'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['Only One'], $titles);
    }

    public function testApplyNaturalSortingMixedAlphanumeric(): void
    {
        $collection = collect([
            $this->createTestObject('A10'),
            $this->createTestObject('A2'),
            $this->createTestObject('A1'),
            $this->createTestObject('B1'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['A1', 'A2', 'A10', 'B1'], $titles);
    }

    public function testApplyNaturalSortingWithSpecialCharacters(): void
    {
        $collection = collect([
            $this->createTestObject('Item #2'),
            $this->createTestObject('Item #11'),
            $this->createTestObject('Item #1'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['Item #1', 'Item #2', 'Item #11'], $titles);
    }

    public function testApplyNaturalSortingWithLeadingNumbers(): void
    {
        $collection = collect([
            $this->createTestObject('1 test'),
            $this->createTestObject('11 abc'),
            $this->createTestObject('2 apple'),
            $this->createTestObject('44 banana'),
            $this->createTestObject('4 test'),
        ]);

        $result = $this->invokeApplyNaturalSorting($collection, fn ($obj) => $obj->title);

        $titles = $result->pluck('title')->toArray();
        self::assertSame(['1 test', '2 apple', '4 test', '11 abc', '44 banana'], $titles);
    }

    /**
     * Helper method to invoke the private applyNaturalSorting method using reflection.
     */
    private function invokeApplyNaturalSorting(Collection $collection, callable $extractor): Collection
    {
        $reflection = new ReflectionClass($this->sut);
        $method = $reflection->getMethod('applyNaturalSorting');
        $method->setAccessible(true);

        return $method->invoke($this->sut, $collection, $extractor);
    }

    /**
     * Helper method to create a simple test object with a title property.
     */
    private function createTestObject(string $title): stdClass
    {
        $obj = new stdClass();
        $obj->title = $title;

        return $obj;
    }
}
