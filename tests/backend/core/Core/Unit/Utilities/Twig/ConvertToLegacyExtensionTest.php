<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ConvertToLegacyExtension;
use demosplan\DemosPlanStatementBundle\Logic\DraftStatementService;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use Tests\Base\UnitTestCase;

class ConvertToLegacyExtensionTest extends UnitTestCase
{
    /**
     * @var ConvertToLegacyExtension
     */
    private $twigExtension;

    /**
     * @var DraftStatement
     */
    private $testDraftStatements;

    /**
     * @var Statement
     */
    private $testStatements;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new ConvertToLegacyExtension(self::$container, self::$container->get(DraftStatementService::class), self::$container->get(StatementService::class));

        $this->testDraftStatements = [$this->getReference('testDraftStatement')];
        $this->testStatements = [$this->getReference('testStatement')];
    }

    public function testStatements()
    {
        $results = $this->twigExtension->convertToLegacy($this->testStatements);

        // Assertions about the result
        static::assertIsArray($results);
        static::assertCount(1, $results);
        foreach ($results as $result) {
            static::assertIsArray($result);
        }
    }

    public function testDraftStatements()
    {
        $results = $this->twigExtension->convertToLegacy($this->testDraftStatements);

        // Assertions about the result
        static::assertIsArray($results);
        static::assertCount(1, $results);
        foreach ($results as $result) {
            static::assertIsArray($result);
        }
    }
}
