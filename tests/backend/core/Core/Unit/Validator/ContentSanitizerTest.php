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

use demosplan\DemosPlanCoreBundle\Validator\ContentSanitizer;
use Tests\Base\UnitTestCase;

class ContentSanitizerTest extends UnitTestCase
{
    protected ContentSanitizer $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new ContentSanitizer();
    }

    public function testSanitizeString(): void
    {
        // Test basic string sanitization
        $input = 'Test<script>alert("xss")</script>';
        $expected = 'Test&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $result = $this->sut->sanitize($input);
        
        self::assertEquals($expected, $result);
    }
    
    public function testSanitizeNullBytes(): void
    {
        // Test null byte removal
        $input = "malicious\0injection";
        $expected = 'maliciousinjection';
        $result = $this->sut->sanitize($input);
        
        self::assertEquals($expected, $result);
    }
    
    public function testSanitizeArray(): void
    {
        // Test array sanitization
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
        
        $result = $this->sut->sanitize($input);
        
        self::assertEquals($expected, $result);
    }
    
    public function testSanitizePrimitiveTypes(): void
    {
        // Test integer
        $input = 42;
        $result = $this->sut->sanitize($input);
        self::assertEquals(42, $result);
        
        // Test boolean
        $input = true;
        $result = $this->sut->sanitize($input);
        self::assertEquals(true, $result);
        
        // Test null
        $input = null;
        $result = $this->sut->sanitize($input);
        self::assertEquals(null, $result);
    }
}