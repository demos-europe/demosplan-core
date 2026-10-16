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

use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldExportColumnKeyCodec;
use PHPUnit\Framework\TestCase;

class CustomFieldExportColumnKeyCodecTest extends TestCase
{
    public function testExtractIdReturnsTheIdOfACustomFieldKey(): void
    {
        self::assertSame('abc-123', CustomFieldExportColumnKeyCodec::extractId(CustomFieldExportColumnKeyCodec::forId('abc-123')));
    }

    public function testExtractIdReturnsNullForOtherKeys(): void
    {
        self::assertNull(CustomFieldExportColumnKeyCodec::extractId('tags'));
        self::assertNull(CustomFieldExportColumnKeyCodec::extractId('parentStatement.procedure.id'));
    }

    public function testExtractIdReturnsNullForAKeyWithoutId(): void
    {
        self::assertNull(CustomFieldExportColumnKeyCodec::extractId('customField_'));
    }

    public function testExtractIdsReturnsOnlyTheIdsOfCustomFieldKeys(): void
    {
        self::assertSame(['a', 'b'], CustomFieldExportColumnKeyCodec::extractIds(['externId', 'customField_a', 'tags', 'customField_b']));
    }
}
