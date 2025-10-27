<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Validator;

use demosplan\DemosPlanCoreBundle\Exception\NullByteDetectedException;
use demosplan\DemosPlanCoreBundle\Validator\InputValidator;
use Tests\Base\UnitTestCase;

class InputValidatorTest extends UnitTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new InputValidator();
    }

    public function testValidateAndEscapeString(): void
    {
        // Test basic string HTML escaping
        $input = 'Test<script>alert("xss")</script>';
        $expected = 'Test&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $result = $this->sut->validateAndEscape($input);

        self::assertEquals($expected, $result);
    }

    public function testNullBytesThrowException(): void
    {
        // Test that null bytes cause rejection instead of sanitization
        $input = "malicious\0injection";

        $this->expectException(NullByteDetectedException::class);
        $this->expectExceptionMessage('Null byte detected in input string');

        $this->sut->validateAndEscape($input);
    }

    public function testNullBytesInArrayValuesThrowException(): void
    {
        // Test that null bytes in array values cause rejection
        $input = [
            'key1' => 'normal text',
            'key2' => "malicious\0value"
        ];

        $this->expectException(NullByteDetectedException::class);

        $this->sut->validateAndEscape($input);
    }

    public function testNullBytesInArrayKeysThrowException(): void
    {
        // Test that null bytes in array keys cause rejection
        $input = [
            "malicious\0key" => 'normal value'
        ];

        $this->expectException(NullByteDetectedException::class);

        $this->sut->validateAndEscape($input);
    }

    public function testValidateAndEscapeArray(): void
    {
        // Test array validation and escaping without null bytes
        $input = [
            'key1' => 'normal text',
            'key2' => '<script>alert("xss")</script>',
            'key3' => ['nested' => '<b>bold</b>'],
            'key<script>' => 'value'
        ];

        $expected = [
            'key1' => 'normal text',
            'key2' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'key3' => ['nested' => '&lt;b&gt;bold&lt;/b&gt;'],
            'key&lt;script&gt;' => 'value'
        ];

        $result = $this->sut->validateAndEscape($input);

        self::assertEquals($expected, $result);
    }

    public function testValidateAndEscapePrimitiveTypes(): void
    {
        // Test integer
        $input = 42;
        $result = $this->sut->validateAndEscape($input);
        self::assertEquals(42, $result);

        // Test boolean
        $input = true;
        $result = $this->sut->validateAndEscape($input);
        self::assertEquals(true, $result);

        // Test null
        $input = null;
        $result = $this->sut->validateAndEscape($input);
        self::assertEquals(null, $result);
    }
}
