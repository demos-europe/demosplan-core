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

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use Tests\Base\FunctionalTestCase;

class StatementDeleterTest extends FunctionalTestCase
{
    public function testEmtpyInternIdOfOriginalInCaseOfDeleteLastChild()
    {
        // get statement with OSTN (with set internID) with only one child
        // delete statement
        // check if related original stn has null as internid

        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testStatement2 = $this->getStatementReference('testStatement2');
        static::assertInstanceOf(StatementMeta::class, $testStatement2->getMeta());

        $amountOfMetasBefore = $this->countEntries(StatementMeta::class);

        $amountOfTagsBefore = count($testStatement2->getTags());
        $entireAmountOfTagsBefore = count($this->getEntries(Tag::class));

        $initialAmountOfStatementsOfTag1 = count($testTag1->getStatements());
        $this->sut->addTagToStatement($testTag1, $testStatement2);

        // total amount of tags in DB has not changed
        static::assertCount($entireAmountOfTagsBefore, $this->getEntries(Tag::class));
        static::assertCount($initialAmountOfStatementsOfTag1 + 1, $testTag1->getStatements());
        static::assertContains($testStatement2, $testTag1->getStatements());
        static::assertContains($testTag1, $testStatement2->getTags());
        $tags = $testStatement2->getTags();
        static::assertCount($amountOfTagsBefore + 1, $tags);

        // the actually deletion:
        $result = $this->sut->deleteStatement($testStatement2->getId());

        static::assertTrue($result);
        static::assertCount($initialAmountOfStatementsOfTag1, $testTag1->getStatements());
        static::assertNotContains($testStatement2, $testTag1->getStatements());
        // total amount of tags in DB has still not changed
        static::assertCount($entireAmountOfTagsBefore, $this->getEntries(Tag::class));

        // total amount of StatementMeta in DB is decremeted
        static::assertSame(
            $amountOfMetasBefore - 1,
            $this->countEntries(StatementMeta::class)
        );
    }
}
