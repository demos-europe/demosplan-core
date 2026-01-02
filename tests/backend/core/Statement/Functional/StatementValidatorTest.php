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

use DateTime;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Tests\Base\FunctionalTestCase;

class StatementValidatorTest extends FunctionalTestCase
{
    /** @var StatementValidator */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementValidator::class);
    }

    public function testValidate(): void
    {
        self::markSkippedForCIIntervention();

        // ***********************
        // Correct Statement
        // ***********************
        $successObject = $this->getStatementReference('testStatementOrig');
        $meta = $successObject->getMeta();
        $result = $this->sut->validate($successObject);
        static::assertCount(0, $result);

        // *********************
        // Wrong Statement
        // *********************
        $errorMeta = $meta;
        $errorMeta->setOrgaName(null);
        $errorMeta->setOrgaDepartmentName(null);
        $errorMeta->setOrgaPostalCode(null);
        $errorMeta->setOrgaCity(null);
        $errorMeta->setOrgaEmail(null);
        $errorMeta->setAuthoredDate(new DateTime());
        $errorMeta->setAuthorName(null);
        $errorMeta->setSubmitName(null);

        $errorObject = $successObject;
        $errorObject->setText(null);
        $errorObject->setMeta($errorMeta);
        $errorObject->setSubmitType('error');

        $resultError = $this->sut->validate($errorObject, ['import']);
        // Expectations
        $expectedErrorPaths = [
            'meta.authorName',
            'meta.orgaName',
            'meta.orgaDepartmentName',
            'meta.orgaPostalCode',
            'meta.orgaCity',
            'meta.orgaEmail',
            'meta.submitName',
            'submit',
            'submitType',
            'text',
        ];
        $returnedErrorPaths = [];

        // Assertions
        static::assertCount(10, $resultError);
        // Checking that there are no unexpected errors
        /** @var ConstraintViolationInterface $error */
        foreach ($resultError as $error) {
            static::assertContains($error->getPropertyPath(), $expectedErrorPaths);
            $returnedErrorPaths[] = $error->getPropertyPath();
        }
        // checking if all expected errors are present
        foreach ($expectedErrorPaths as $expected) {
            static::assertContains($expected, $returnedErrorPaths);
        }
    }
}
