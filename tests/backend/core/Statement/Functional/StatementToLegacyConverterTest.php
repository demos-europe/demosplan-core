<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementToLegacyConverter;
use Tests\Base\FunctionalTestCase;

class StatementToLegacyConverterTest extends FunctionalTestCase
{
    private ?StatementToLegacyConverter $statementToLegacyConverter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statementToLegacyConverter = $this->getContainer()->get(StatementToLegacyConverter::class);
    }

    public function testConvert(): void
    {
        $statement = StatementFactory::createOne()->_real();
        $legacyStatement = $this->statementToLegacyConverter->convert($statement);

        static::assertIsArray($legacyStatement);
        static::assertNotEmpty($legacyStatement);
        // Add more assertions here to validate the structure and data of the converted legacy statement
    }

    public function testConvertNullReturnsNull(): void
    {
        $result = $this->statementToLegacyConverter->convert(null);
        static::assertNull($result);
    }
}
