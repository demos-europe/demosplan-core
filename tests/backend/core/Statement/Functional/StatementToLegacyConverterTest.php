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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementToLegacyConverter;
use Doctrine\Common\Collections\Collection;
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
        $procedure = ProcedureFactory::createOne();
        $settings = ProcedureSettingsFactory::createOne(['procedure' => $procedure]);
        $procedure->setSettings($settings->_real());
        $procedure->_save();
        $statement = StatementFactory::createOne(['procedure' => $procedure])->_real();
        $legacyStatement = $this->statementToLegacyConverter->convert($statement);

        static::assertIsArray($legacyStatement);
        static::assertNotEmpty($legacyStatement);

        static::assertArrayHasKey('createdByToeb', $legacyStatement);
        static::assertIsBool($legacyStatement['createdByToeb']);
        static::assertArrayHasKey('createdByCitizen', $legacyStatement);
        static::assertIsBool($legacyStatement['createdByCitizen']);
        static::assertArrayHasKey('submitterEmailAddress', $legacyStatement);
        static::assertTrue(
            null === $legacyStatement['submitterEmailAddress']
            || is_string($legacyStatement['submitterEmailAddress'])
        );
        static::assertArrayHasKey('numberOfAnonymVotes', $legacyStatement);
        static::assertIsInt($legacyStatement['numberOfAnonymVotes']);
        static::assertArrayHasKey('votesNum', $legacyStatement);
        static::assertIsInt($legacyStatement['votesNum']);
        static::assertArrayHasKey('categories', $legacyStatement);
        static::assertIsArray($legacyStatement['categories']);

        static::assertArrayHasKey('statementAttributes', $legacyStatement);
        static::assertTrue(
            is_array($legacyStatement['statementAttributes'])
            || $legacyStatement['statementAttributes'] instanceof Collection
        );

        static::assertArrayHasKey('element', $legacyStatement);
        static::assertTrue(
            null === $legacyStatement['element']
            || is_array($legacyStatement['element'])
        );
        $hasParagraphKey = array_key_exists('paragraph', $legacyStatement);
        if ($hasParagraphKey) {
            static::assertTrue(
                null === $legacyStatement['paragraph']
                || is_array($legacyStatement['paragraph'])
            );
        }
        static::assertArrayHasKey('paragraphId', $legacyStatement);
        static::assertTrue(
            null === $legacyStatement['paragraphId']
            || is_string($legacyStatement['paragraphId'])
        );
        static::assertArrayNotHasKey('documentId', $legacyStatement);
        static::assertArrayNotHasKey('documentTitle', $legacyStatement);
        static::assertArrayNotHasKey('document', $legacyStatement);

        static::assertArrayHasKey('procedure', $legacyStatement);
        static::assertIsArray($legacyStatement['procedure']);
        static::assertArrayHasKey('settings', $legacyStatement['procedure']);
        static::assertIsArray($legacyStatement['procedure']['settings']);
        static::assertArrayHasKey('organisation', $legacyStatement['procedure']);
        static::assertIsArray($legacyStatement['procedure']['organisation']);
        static::assertArrayHasKey('planningOffices', $legacyStatement['procedure']);
        static::assertIsArray($legacyStatement['procedure']['planningOffices']);
        static::assertArrayHasKey('planningOfficeIds', $legacyStatement['procedure']);
        static::assertIsArray($legacyStatement['procedure']['planningOfficeIds']);

        static::assertArrayHasKey('organisation', $legacyStatement);
        static::assertTrue(
            null === $legacyStatement['organisation']
            || is_array($legacyStatement['organisation'])
        );

        static::assertArrayHasKey('meta', $legacyStatement);
        static::assertIsArray($legacyStatement['meta']);

        static::assertArrayHasKey('votes', $legacyStatement);
        static::assertIsArray($legacyStatement['votes']);
    }

    public function testConvertNullReturnsNull(): void
    {
        $result = $this->statementToLegacyConverter->convert(null);
        static::assertNull($result);
    }
}
