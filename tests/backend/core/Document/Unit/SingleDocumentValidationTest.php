<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @group UnitTest
 */
class SingleDocumentValidationTest extends TestCase
{
    public function testDocumentFileStringWithinLimitIsValid(): void
    {
        $document = new SingleDocument();
        $document->setDocument(str_repeat('a', 1024));

        self::assertCount(0, $this->validateDocumentField($document));
    }

    public function testTooLongDocumentFileStringIsRejected(): void
    {
        $document = new SingleDocument();
        $document->setDocument(str_repeat('a', 1025));

        self::assertCount(1, $this->validateDocumentField($document));
    }

    private function validateDocumentField(SingleDocument $document): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return $validator->validateProperty($document, 'document');
    }
}
