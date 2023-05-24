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
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Tests\Base\UnitTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ValueObjectTest extends UnitTestCase
{
    /**
     * @var TestValueObject
     */
    protected $sut;

    public function setUp(): void
    {
        $this->sut = new TestValueObject();
    }

    public function testSerializeDoesntIncludeRootProperties()
    {
        $this->sut->setMyProp(23);
        $this->sut->setAnotherProp('forty-two');

        $this->sut->lock();

        $this->assertEquals(
            [
                'myProp'      => 23,
                'anotherProp' => 'forty-two',
            ],
            $this->sut->jsonSerialize()
        );
    }

    public function testCannotGetUnlessLocked()
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage(ValueObjectException::mustLockFirst()->getMessage());

        $this->sut->setMyProp(1);
        $this->sut->getMyProp();
    }

    public function testDisallowsChangeWhenLocked()
    {
        $this->expectException(ValueObjectException::class);
        $this->expectExceptionMessage(ValueObjectException::noChangeAllowedWhenLocked()->getMessage());

        $this->sut->setMyProp(42);
        $this->sut->lock();

        $this->sut->setMyProp(23);
    }

    public function testCallFromTwig()
    {
        $twig = new Environment(new ArrayLoader());

        $template = $twig->createTemplate('{{ valueObject.myProp }}');

        $this->sut->setMyProp(42);
        $this->sut->lock();

        $result = $twig->render($template, ['valueObject' => $this->sut]);

        self::assertEquals(42, $result);
    }
}

/**
 * Class to test extending from ValueObject and testing its usage.
 *
 * @method setMyProp(int $value)
 * @method getMyProp(): int
 * @method setAnotherProp(string $value)
 * @method getAnotherProp(): string
 */
final class TestValueObject extends ValueObject
{
    protected $myProp;
    protected $anotherProp;
}
