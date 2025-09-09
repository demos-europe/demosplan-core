<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\StatementFragment;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\Normalizer;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementFragmentUpdate;
use InvalidArgumentException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\FunctionalTestCase;

class StatementFragmentUpdateTest extends FunctionalTestCase
{
    /** @var ValidatorInterface */
    protected $validator;
    /**
     * @var string
     */
    private $procedureId = '5eacb03f-8ddd-1136-81dd-30505ace0004';

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = self::getContainer()->get('validator');
    }

    public function testConsiderationAddition()
    {
        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "ABC",
             "statementFragmentIds": [ "5eb3b03f-8c5f-1136-81dd-305056ae0004" ]
          }
        }}'
        );

        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate(
            $this->procedureId,
            $resourceObject,
            $this->validator
        );
        $statementFragmentUpdate->lock();

        self::assertSame('ABC', $statementFragmentUpdate->getConsiderationAddition());
    }

    public function testConsiderationAdditionNonString()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": 1,
             "statementFragmentIds": [ "5eb3b03f-8c5f-1136-81dd-305056ae0004" ]
          }
        }}'
        );

        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate(
            $this->procedureId,
            $resourceObject,
            $this->validator
        );
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    /**
     * This test fails because @Assert\Length(min=1) is not validated in StatementFragmentUpdate for some reason.
     */
    public function testConsiderationAdditionEmptyString()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "",
             "statementFragmentIds": [ "5eb3b03f-8c5f-1136-81dd-305056ae0004" ]
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate($this->procedureId, $resourceObject, $this->validator);
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    public function testInvalidProcedureId()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "ABC",
             "statementFragmentIds": [ "5eb3b03f-8c5f-1136-81dd-305056ae0004" ]
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate('-8ddd--81dd-30505ace0004', $resourceObject, $this->validator);
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    public function testMissingStatementFragmentIds()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "ABC"
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate($this->procedureId, $resourceObject, $this->validator);
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    public function testNoStatementFragmentIds()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "ABC",
             "statementFragmentIds": [ ]
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate($this->procedureId, $resourceObject, $this->validator);
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    public function testInvalidStatementFragmentIds()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(ViolationsException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
             "considerationAddition": "ABC",
             "statementFragmentIds": [ "5eb3b03f-a-a-81dd-305056ae0004" ]
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $statementFragmentUpdate = new StatementFragmentUpdate($this->procedureId, $resourceObject, $this->validator);
        $statementFragmentUpdate->lock();
        $statementFragmentUpdate->getConsiderationAddition();
    }

    public function testNoAttributes()
    {
        $this->expectException(InvalidArgumentException::class);

        $normalizer = new Normalizer();
        $topLevel = $normalizer->normalize(
            '{
        "data": {
          "type": "statement-fragment-update",
          "id": "5eb3b03f-8c5f-1136-81dd-305056ae0004",
          "attributes": {
          }
        }}'
        );
        $resourceObject = $topLevel->getFirst('statement-fragment-update');
        $sfu = new StatementFragmentUpdate($this->procedureId, $resourceObject, $this->validator);
        self::fail('When '.$sfu.' is set, this test definitely failed');
    }
}
