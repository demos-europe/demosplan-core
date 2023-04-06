<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanStatementBundle\Logic\StatementDeleter;
use Tests\Base\FunctionalTestCase;

class StatementDeleterTest extends FunctionalTestCase
{
    /** @var StatementDeleter */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementDeleter::class);
        $user = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $this->logIn($user);
    }

    public function testEmtpyInternIdOfOriginalInCaseOfDeleteLastChild(): void
    {
        $this->enablePermissions(['feature_auto_delete_original_statement']);

        $testStatement = $this->getStatementReference('testStatementWithInternID');
        $testStatementId = $testStatement->getId();
        $relatedOriginal = $testStatement->getOriginal();
        static::assertInstanceOf(Statement::class, $relatedOriginal);
        static::assertNotNull($testStatement->getInternId());
        static::assertNotNull($relatedOriginal->getInternId());
        static::assertCount(1, $testStatement->getOriginal()->getChildren());

        $this->sut->deleteStatementObject($testStatement);
        static::assertNull($this->find(Statement::class, $testStatementId));
        static::assertNull($testStatement->getInternId());
    }
}
