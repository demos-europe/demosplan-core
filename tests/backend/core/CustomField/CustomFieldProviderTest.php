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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldProvider;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class CustomFieldProviderTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?CustomFieldProvider $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldProvider::class);
    }

    public function testFindCustomFieldByCriteriaReturnsTheFieldWithItsId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $config = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);

        $field = $this->sut->findCustomFieldByCriteria('PROCEDURE', $procedure->getId(), 'SEGMENT', $config->getId());

        self::assertNotNull($field);
        self::assertSame($config->getId(), $field->getId());
        self::assertSame('Priority', $field->getName());
        self::assertCount(2, $field->getOptions());
    }

    public function testFindCustomFieldByCriteriaReturnsNullForAnUnknownField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $this->createSegmentCustomField($procedure, 'Priority', ['High']);

        self::assertNull($this->sut->findCustomFieldByCriteria('PROCEDURE', $procedure->getId(), 'SEGMENT', 'unknown-id'));
    }

    public function testFindCustomFieldByCriteriaDoesNotReturnTheFieldOfAnotherProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $config = $this->createSegmentCustomField($otherProcedure, 'Priority', ['High']);

        self::assertNull($this->sut->findCustomFieldByCriteria('PROCEDURE', $procedure->getId(), 'SEGMENT', $config->getId()));
    }

    public function testFindCustomFieldByCriteriaDoesNotReturnAFieldOfAnotherTargetEntity(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $config = $this->createSegmentCustomField($procedure, 'Priority', ['High']);

        self::assertNull($this->sut->findCustomFieldByCriteria('PROCEDURE', $procedure->getId(), 'STATEMENT', $config->getId()));
    }
}
