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
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Tests\Base\FunctionalTestCase;

class StatementSegmentationStatusTest extends FunctionalTestCase
{
    public function testStatementDefaultsToUnsegmented(): void
    {
        // Arrange & Act
        $statement = StatementFactory::createOne();

        // Assert
        self::assertEquals(SegmentationStatus::UNSEGMENTED->value, $statement->getSegmentationStatus());
        self::assertFalse($statement->isSegmented());
    }

    public function testStatementCanBeMarkedAsSegmented(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        // Assert
        self::assertEquals(SegmentationStatus::SEGMENTED->value, $statement->getSegmentationStatus());
        self::assertTrue($statement->isSegmented());
    }

    public function testSegmentationStatusPersistedToDatabase(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();
        $statementId = $statement->object()->getId();

        // Act - Clear and reload from DB
        $this->getEntityManager()->clear();
        $loaded = $this->getEntityManager()->find(\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement::class, $statementId);

        // Assert
        self::assertNotNull($loaded);
        self::assertEquals(SegmentationStatus::SEGMENTED->value, $loaded->getSegmentationStatus());
        self::assertTrue($loaded->isSegmented());
    }

    public function testSegmentationStatusDefaultValue(): void
    {
        // Arrange & Act
        $statement = StatementFactory::createOne();
        $statementId = $statement->object()->getId();

        // Clear and reload to check DB default
        $this->getEntityManager()->clear();
        $loaded = $this->getEntityManager()->find(\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement::class, $statementId);

        // Assert
        self::assertNotNull($loaded);
        self::assertEquals(SegmentationStatus::UNSEGMENTED->value, $loaded->getSegmentationStatus());
    }
}
