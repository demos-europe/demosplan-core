<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldFilterPathCodec;
use PHPUnit\Framework\TestCase;

class CustomFieldFilterPathCodecTest extends TestCase
{
    private const FIELD_ID = '3f2a1c4e-5b6d-4e8f-9a0b-1c2d3e4f5a6b';
    private const PATH = 'customField_3f2a1c4e5b6d4e8f9a0b1c2d3e4f5a6b';

    public function testForIdLeavesTheHyphensOut(): void
    {
        self::assertSame(self::PATH, CustomFieldFilterPathCodec::forId(self::FIELD_ID));
    }

    public function testThePathConsistsOfWordCharactersOnly(): void
    {
        // The filter validation (EDT Patterns::PROPERTY_PATH) only accepts a path like this
        self::assertMatchesRegularExpression('/^[a-z]\w*$/', CustomFieldFilterPathCodec::forId(self::FIELD_ID));
    }

    public function testExtractIdRestoresTheUuid(): void
    {
        self::assertSame(self::FIELD_ID, CustomFieldFilterPathCodec::extractId(self::PATH));
    }

    public function testExtractIdIsTheInverseOfForId(): void
    {
        self::assertSame(self::FIELD_ID, CustomFieldFilterPathCodec::extractId(CustomFieldFilterPathCodec::forId(self::FIELD_ID)));
    }

    /**
     * @dataProvider provideNoCustomFieldPaths
     */
    public function testExtractIdReturnsNullForOtherPaths(string $path): void
    {
        self::assertNull(CustomFieldFilterPathCodec::extractId($path));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNoCustomFieldPaths(): iterable
    {
        yield 'static filter' => ['tags'];
        yield 'nested path' => ['assignee.department'];
        yield 'prefix only' => ['customField_'];
        yield 'too short' => ['customField_3f2a1c4e'];
        yield 'with hyphens' => ['customField_'.self::FIELD_ID];
        yield 'not hexadecimal' => ['customField_zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'];
    }
}
