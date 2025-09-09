<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\ValueObject;

use demosplan\DemosPlanCoreBundle\Exception\ValueObjectException;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Tests\Base\UnitTestCase;

class APIPaginationTest extends UnitTestCase
{
    protected $sut;

    public function setUp(): void
    {
        $this->sut = new APIPagination();
    }

    public function testNotLocked()
    {
        $this->expectException(ValueObjectException::class);
        $this->sut->setNumber(2);
        $this->sut->getNumber();
    }

    public function testSetData()
    {
        $this->sut->setNumber(2);
        $this->sut->setSize(4);
        $this->sut->setSortBy('created');
        $this->sut->setSortDirection('desc');
        $this->sut->lock();

        self::assertEquals(2, $this->sut->getNumber());
        self::assertEquals(4, $this->sut->getSize());
        self::assertEquals('created', $this->sut->getSortBy());
        self::assertEquals('desc', $this->sut->getSortDirection());
    }

    public function testSetSortString()
    {
        $this->sut->setSortString('created');
        $this->sut->lock();

        self::assertEquals('created', $this->sut->getSortBy());
        self::assertEquals('asc', $this->sut->getSortDirection());
        self::assertEquals(['by' => 'created', 'to' => 'asc'], $this->sut->getSort());
    }

    public function testSetSortStringDesc()
    {
        $this->sut->setSortString('-created');
        $this->sut->lock();

        self::assertEquals('created', $this->sut->getSortBy());
        self::assertEquals('desc', $this->sut->getSortDirection());
        self::assertEquals(['by' => 'created', 'to' => 'desc'], $this->sut->getSort());
    }

    public function testSetSortStringEmpty()
    {
        $this->sut->setSortString('');
        $this->sut->lock();

        self::assertEmpty($this->sut->getSortBy());
        self::assertEmpty($this->sut->getSortDirection());
        self::assertEmpty($this->sut->getSort());
    }

    public function testSetSortStringNotSet()
    {
        $this->sut->lock();

        self::assertEmpty($this->sut->getSortBy());
        self::assertEmpty($this->sut->getSortDirection());
        self::assertEmpty($this->sut->getSort());
    }

    public function testSetSortStringNull()
    {
        $this->sut->setSortString(null);
        $this->sut->lock();

        self::assertEmpty($this->sut->getSortBy());
        self::assertEmpty($this->sut->getSortDirection());
        self::assertEmpty($this->sut->getSort());
    }
}
