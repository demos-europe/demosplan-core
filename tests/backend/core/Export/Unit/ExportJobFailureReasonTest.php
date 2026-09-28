<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use Doctrine\DBAL\Exception\DriverException;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class ExportJobFailureReasonTest extends UnitTestCase
{
    protected ?ExportJobFailureReason $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'translated:'.$key);

        $this->sut = new ExportJobFailureReason($translator);
    }

    public function testForThrowableTranslatesUserMessageOfDemosException(): void
    {
        // Arrange - getMessage() is the log message and empty here by construction
        $exception = new AssessmentTableZipExportException('error', 'error.statements.zip.export');
        self::assertSame('', $exception->getMessage());

        // Act & Assert
        self::assertSame('translated:error.statements.zip.export', $this->sut->forThrowable($exception));
    }

    public function testForThrowableFallsBackToGenericReasonForUnknownException(): void
    {
        // Act & Assert - an internal message must never reach the browser
        self::assertSame(
            'translated:error.export',
            $this->sut->forThrowable(new RuntimeException('SELECT * FROM statement WHERE ... failed'))
        );
    }

    public function testForThrowableDoesNotLeakDriverExceptionDetails(): void
    {
        // Arrange
        $reason = $this->sut->forThrowable($this->createMock(DriverException::class));

        // Assert
        self::assertSame('translated:error.export', $reason);
    }

    public function testForThrowableFallsBackWhenDemosExceptionCarriesNoUserMessage(): void
    {
        // Act & Assert
        self::assertSame('translated:error.export', $this->sut->forThrowable(new DemosException('', 'log only')));
    }
}
