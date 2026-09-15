<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TextSectionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use Tests\Base\FunctionalTestCase;

class TextSectionFactoryTest extends FunctionalTestCase
{
    public function testFactoryCreatesTextSectionWithDefaults(): void
    {
        // Arrange & Act
        $textSection = TextSectionFactory::createOne();

        // Assert
        self::assertInstanceOf(TextSection::class, $textSection->object());
        self::assertNotNull($textSection->getId());
        self::assertNotNull($textSection->getStatement());
        self::assertEquals(1, $textSection->getOrderInStatement());
        self::assertNotEmpty($textSection->getTextRaw());
        self::assertNotEmpty($textSection->getText());
    }

    public function testFactoryCanCreatePreambleSection(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $textSection = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 1,
        ]);

        // Assert
        self::assertSame($statement->object(), $textSection->getStatement());
        self::assertEquals(1, $textSection->getOrderInStatement());
    }

    public function testFactoryCanCreateConclusionSection(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $textSection = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 5,
        ]);

        // Assert
        self::assertSame($statement->object(), $textSection->getStatement());
        self::assertEquals(5, $textSection->getOrderInStatement());
    }

    public function testFactoryCanCreateMultipleSectionsForSameStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $preamble = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 1,
        ]);

        $interlude = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 2,
        ]);

        $conclusion = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 3,
        ]);

        // Assert
        self::assertSame($statement->object(), $preamble->getStatement());
        self::assertSame($statement->object(), $interlude->getStatement());
        self::assertSame($statement->object(), $conclusion->getStatement());
        self::assertNotSame($preamble->getId(), $interlude->getId());
        self::assertNotSame($interlude->getId(), $conclusion->getId());
    }
}
