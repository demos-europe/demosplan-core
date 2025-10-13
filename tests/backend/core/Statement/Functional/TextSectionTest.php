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
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\ValueObject\TextSectionType;
use Tests\Base\FunctionalTestCase;

class TextSectionTest extends FunctionalTestCase
{
    public function testCreateTextSection(): void
    {
        $statement = StatementFactory::createOne();

        $textSection = new TextSection();
        $textSection->setStatement($statement->object());
        $textSection->setOrderInStatement(1);
        $textSection->setTextRaw('<p>Preamble text</p>');
        $textSection->setText('Preamble text');
        $textSection->setSectionType(TextSectionType::PREAMBLE);

        $this->getEntityManager()->persist($textSection);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        // Reload from database
        $loaded = $this->getEntityManager()->find(TextSection::class, $textSection->getId());

        self::assertNotNull($loaded);
        self::assertEquals('<p>Preamble text</p>', $loaded->getTextRaw());
        self::assertEquals('Preamble text', $loaded->getText());
        self::assertEquals(1, $loaded->getOrderInStatement());
        self::assertEquals(TextSectionType::PREAMBLE->value, $loaded->getSectionType());
        self::assertSame($statement->object()->getId(), $loaded->getStatement()->getId());
    }

    public function testTextSectionRequiresStatement(): void
    {
        // Arrange
        $textSection = new TextSection();
        $textSection->setOrderInStatement(1);
        $textSection->setTextRaw('<p>Test</p>');
        $textSection->setText('Test');
        $textSection->setSectionType(TextSectionType::INTERLUDE);

        // Act & Assert - Should fail with NOT NULL constraint
        $this->expectException(\Doctrine\DBAL\Exception\NotNullConstraintViolationException::class);

        $this->getEntityManager()->persist($textSection);
        $this->getEntityManager()->flush();
    }

    public function testTextSectionRequiresOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $textSection = new TextSection();
        $textSection->setStatement($statement->object());
        $textSection->setTextRaw('<p>Test</p>');
        $textSection->setText('Test');
        $textSection->setSectionType(TextSectionType::INTERLUDE);
        // Note: orderInStatement not set

        // Act & Assert - Should fail with NOT NULL constraint
        $this->expectException(\Doctrine\DBAL\Exception\NotNullConstraintViolationException::class);

        $this->getEntityManager()->persist($textSection);
        $this->getEntityManager()->flush();
    }

    public function testTextSectionTypesAvailable(): void
    {
        self::assertEquals('preamble', TextSectionType::PREAMBLE->value);
        self::assertEquals('interlude', TextSectionType::INTERLUDE->value);
        self::assertEquals('conclusion', TextSectionType::CONCLUSION->value);
    }
}
