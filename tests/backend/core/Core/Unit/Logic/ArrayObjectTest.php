<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ArrayObject;
use Tests\Base\UnitTestCase;

/**
 * Teste  ArrayObject.
 *
 * @group UnitTest
 */
class ArrayObjectTest extends UnitTestCase
{
    /**
     * @var \demosplan\DemosPlanCoreBundle\Logic\ArrayObject
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        // Use Procedure entity to test magic array access permissions
        // as it extends \demosplan\DemosPlanCoreBundle\Logic\ArrayObject
        $this->sut = new Procedure();
    }

    public function testArrayAccess()
    {
        self::markSkippedForCIIntervention();
        // ArrayAccess on Entites is not yet implemented

        // Test php \ArrayObject for reference
        $ao = new \ArrayObject(['key' => 'val']);
        $ao['next'] = 'value';
        static::assertTrue(array_key_exists('key', $ao), 'array_key_exists fails');
        static::assertTrue(array_key_exists('next', $ao), 'array_key_exists fails');

        // test Custom ArrayObject with constructor injection
        $ao = new ArrayObject(['key' => 'val']);
        $ao['next'] = 'value';
        static::assertTrue(array_key_exists('key', $ao), 'array_key_exists fails');
        static::assertTrue(array_key_exists('next', $ao), 'array_key_exists fails');

        // test Custom ArrayObject with "setter" injection
        $ao = new ArrayObject();
        $ao['next'] = 'value';
        static::assertTrue(array_key_exists('next', $ao), 'array_key_exists fails');

        // test implementation
        static::assertTrue(array_key_exists('name', $this->sut), 'array_key_exists fails');
        static::assertNull($this->sut['one']);
        $this->sut['one'] = 'one is set';
        static::assertEquals('one is set', $this->sut['one']);
        $this->sut->setName('procedureName');
        static::assertEquals('procedureName', $this->sut->getName());
        static::assertEquals('procedureName', $this->sut['name']);
        static::assertTrue(isset($this->sut['name']));
        static::assertFalse(isset($this->sut['nameNotExistent']));
        $this->sut['nameNotExistent'] = 'now it is';
        static::assertTrue(isset($this->sut['nameNotExistent']));
        unset($this->sut['nameNotExistent']);
        static::assertFalse(isset($this->sut['nameNotExistent']));
        static::assertFalse(isset($this->sut[0]));
        $this->sut[] = 'dynamic Array access';
        static::assertTrue(isset($this->sut[0]));
        static::assertTrue(0 < count($this->sut));
        static::assertTrue(array_key_exists('orgaId', $this->sut), 'array_key_exists fails');
    }

    public function testArraySetter()
    {
        self::markSkippedForCIIntervention();
        // ArrayAccess on Entites is not yet implemented

        // test implementation
        static::assertTrue(array_key_exists('name', $this->sut), 'array_key_exists fails');
        $nameObjectBefore = $this->sut->getName();
        $nameArrayBefore = $this->sut['name'];
        static::assertEquals($nameArrayBefore, $nameObjectBefore);

        // array setter
        $newVal = 'newName';
        $this->sut['name'] = $newVal;
        $nameObjectBefore = $this->sut->getName();
        $nameArrayBefore = $this->sut['name'];
        static::assertEquals($nameArrayBefore, $nameObjectBefore);
        static::assertEquals($newVal, $nameObjectBefore);

        // object setter
        $newVal = 'modifiedName';
        $this->sut->setName($newVal);
        $nameObjectBefore = $this->sut->getName();
        $nameArrayBefore = $this->sut['name'];
        static::assertEquals($nameArrayBefore, $nameObjectBefore);
        static::assertEquals($newVal, $nameObjectBefore);
    }

    public function testArrayIterator()
    {
        self::markSkippedForCIIntervention();
        // getIterator() is undefined
        // Why does the variable set via setter not occur in sut->getIterator()? Is this a problem? UI seems to work

        $iterator = $this->sut->getIterator();
        static::assertEquals($this->sut->getName(), $iterator->offsetGet('name'));

        // object setter
        $newVal = 'modifiedName';
        $this->sut->setName($newVal);
        $iterator = $this->sut->getIterator();

        // array setter
        $newVal = 'newName';
        $this->sut['name'] = $newVal;
        $iterator = $this->sut->getIterator();
        static::assertEquals($this->sut->getName(), $iterator->offsetGet('name'));
    }
}
