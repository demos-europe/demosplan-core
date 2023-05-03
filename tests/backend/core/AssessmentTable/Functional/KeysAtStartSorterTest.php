<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable\Functional;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\KeysAtStartSorter;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use Tests\Base\FunctionalTestCase;
use TypeError;

class KeysAtStartSorterTest extends FunctionalTestCase
{
    public function testSortArrayInvalidNull()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtStartSorter([1, 2, 3]);
        $sorter->sortArray(6);
    }

    public function testSortArrayInvalidNumber()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtStartSorter([1, 2, 3]);
        $sorter->sortArray('abc');
    }

    public function testSortArrayInvalidString()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtStartSorter([1, 2, 3]);
        $sorter->sortArray(null);
    }

    public function testConstructInvalidNull()
    {
        $this->expectException(TypeError::class);
        new KeysAtStartSorter(null);
    }

    public function testConstructInvalidNumber()
    {
        $this->expectException(TypeError::class);
        new KeysAtStartSorter(6);
    }

    public function testConstructInvalidString()
    {
        $this->expectException(TypeError::class);
        new KeysAtStartSorter('abc');
    }

    public function testConstructInvalidEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        new KeysAtStartSorter([]);
    }

    public function testSortArrayValidKeysNumbers()
    {
        $sorter = new KeysAtStartSorter([1, 2, 3]);

        $unsortedGroups = $this->getUnsortedGroupsNumbered();

        $expectedGroups = [
            1 => $unsortedGroups[1],
            2 => $unsortedGroups[2],
            3 => $unsortedGroups[3],
            0 => $unsortedGroups[0],
            4 => $unsortedGroups[4],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysStrings()
    {
        $sorter = new KeysAtStartSorter(['b', 'c', '']);

        $unsortedGroups = $this->getUnsortedGroupsKeys();

        $expectedGroups = [
            'b' => $unsortedGroups['b'],
            'c' => $unsortedGroups['c'],
            ''  => $unsortedGroups[''],
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysMixed()
    {
        $sorter = new KeysAtStartSorter(['b', 2, '']);

        $unsortedGroups = $this->getUnsortedGroupsMixed();

        $expectedGroups = [
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
            ''  => $unsortedGroups[''],
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysNumbersUnsorted()
    {
        $sorter = new KeysAtStartSorter([3, 1, 2]);

        $unsortedGroups = $this->getUnsortedGroupsNumbered();

        $expectedGroups = [
            3 => $unsortedGroups[3],
            1 => $unsortedGroups[1],
            2 => $unsortedGroups[2],
            0 => $unsortedGroups[0],
            4 => $unsortedGroups[4],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysStringsUnsorted()
    {
        $sorter = new KeysAtStartSorter(['', 'b', 'c']);

        $unsortedGroups = $this->getUnsortedGroupsKeys();

        $expectedGroups = [
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            'c' => $unsortedGroups['c'],
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysMixedUnsorted()
    {
        $sorter = new KeysAtStartSorter(['', 'b', 2]);

        $unsortedGroups = $this->getUnsortedGroupsMixed();

        $expectedGroups = [
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysEmpty()
    {
        $sorter = new KeysAtStartSorter(['', 'b', 2]);

        $sortedGroups = $sorter->sortArray([]);
        static::assertSame([], $sortedGroups);
    }

    /**
     * Makes a difference for isset/array_key_exists.
     */
    public function testSortArrayValidKeysNullvalues()
    {
        $sorter = new KeysAtStartSorter(['', 'b', 2]);

        $unsortedGroups = [
            'a' => null,
            'b' => null,
            2   => null,
            ''  => null,
            'e' => null,
        ];

        $expectedGroups = [
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    private function getUnsortedGroupsNumbered(): array
    {
        return [
            new StatementEntityGroup('I'),
            new StatementEntityGroup('B'),
            new StatementEntityGroup('K'),
            new StatementEntityGroup('H'),
            new StatementEntityGroup('E'),
        ];
    }

    private function getUnsortedGroupsKeys(): array
    {
        return [
            'a' => new StatementEntityGroup('I'),
            'b' => new StatementEntityGroup('B'),
            'c' => new StatementEntityGroup('K'),
            ''  => new StatementEntityGroup('H'),
            'e' => new StatementEntityGroup('E'),
        ];
    }

    private function getUnsortedGroupsMixed(): array
    {
        return [
            'a' => new StatementEntityGroup('I'),
            'b' => new StatementEntityGroup('B'),
            2   => new StatementEntityGroup('K'),
            ''  => new StatementEntityGroup('H'),
            'e' => new StatementEntityGroup('E'),
        ];
    }
}
