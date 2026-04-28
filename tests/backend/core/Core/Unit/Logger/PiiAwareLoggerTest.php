<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logger;

use DemosEurope\DemosplanAddon\Contracts\CurrentContextProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger;
use demosplan\DemosPlanCoreBundle\Logger\PiiLogRecord;
use demosplan\DemosPlanCoreBundle\Logger\PiiLogWriter;
use Doctrine\DBAL\Exception as DBALException;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group UnitTest
 */
class PiiAwareLoggerTest extends TestCase
{
    private LoggerInterface&MockObject $decoratedLogger;
    private PiiLogWriter&MockObject $writer;
    private CurrentContextProviderInterface&MockObject $contextProvider;
    private RequestStack $requestStack;
    private PiiAwareLogger $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoratedLogger = $this->createMock(LoggerInterface::class);
        $this->writer = $this->createMock(PiiLogWriter::class);
        $this->contextProvider = $this->createMock(CurrentContextProviderInterface::class);
        $this->contextProvider->method('getCurrentProcedure')->willReturn(null);
        $this->contextProvider->method('getCurrentUser')->willThrowException(new Exception('no user'));
        $this->requestStack = new RequestStack();

        $this->sut = new PiiAwareLogger(
            $this->decoratedLogger,
            $this->writer,
            $this->contextProvider,
            $this->requestStack,
        );
    }

    public function testWritesFullPayloadToWriterAndForwardsRedactedLine(): void
    {
        $captured = $this->captureWrittenRecord();

        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                self::stringContains('[pii ref={ref} hash={hash}]'),
                self::callback(static function (array $context): bool {
                    self::assertArrayHasKey('ref', $context);
                    self::assertArrayHasKey('hash', $context);
                    self::assertArrayHasKey('action', $context);
                    self::assertSame('login', $context['action']);
                    self::assertArrayNotHasKey('pii', $context);
                    self::assertArrayNotHasKey('email', $context);

                    foreach ($context as $value) {
                        if (is_string($value)) {
                            self::assertStringNotContainsString('user@example.com', $value);
                        }
                    }

                    return true;
                }),
            );

        $this->sut->info('User logged in', [
            'pii'    => ['email' => 'user@example.com', 'ip' => '203.0.113.5'],
            'action' => 'login',
        ]);

        $record = $captured();
        self::assertNotNull($record->piiContextJson);
        self::assertStringContainsString('user@example.com', $record->piiContextJson);
        self::assertNotNull($record->nonPiiContextJson);
        self::assertStringContainsString('"action":"login"', $record->nonPiiContextJson);
        self::assertStringNotContainsString('user@example.com', $record->nonPiiContextJson);
        self::assertSame('User logged in', $record->message);
        self::assertSame('pii', $record->channel);
    }

    public function testHashStableForIdenticalInputs(): void
    {
        $hashes = [];
        $this->writer->method('write')->willReturnCallback(static function (PiiLogRecord $r) use (&$hashes): string {
            $hashes[] = $r->contentHash;

            return 'row-id';
        });
        $this->decoratedLogger->method('log');

        $this->sut->warning('msg', ['pii' => ['email' => 'a@b.c'], 'action' => 'x']);
        $this->sut->warning('msg', ['pii' => ['email' => 'a@b.c'], 'action' => 'x']);

        self::assertCount(2, $hashes);
        self::assertSame($hashes[0], $hashes[1]);
        self::assertSame(64, strlen($hashes[0]));
    }

    public function testHashDiffersOnDifferentPii(): void
    {
        $hashes = [];
        $this->writer->method('write')->willReturnCallback(static function (PiiLogRecord $r) use (&$hashes): string {
            $hashes[] = $r->contentHash;

            return 'row-id';
        });
        $this->decoratedLogger->method('log');

        $this->sut->info('msg', ['pii' => ['email' => 'a@b.c']]);
        $this->sut->info('msg', ['pii' => ['email' => 'c@d.e']]);

        self::assertNotSame($hashes[0], $hashes[1]);
    }

    public function testDbalFailureDoesNotLeakPiiAndDoesNotPropagate(): void
    {
        $this->writer
            ->expects(self::once())
            ->method('write')
            ->willThrowException(new DBALException('boom'));

        $this->decoratedLogger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('[pii WRITE_FAILED]'),
                self::callback(static function (array $context): bool {
                    self::assertArrayHasKey('hash', $context);
                    self::assertArrayNotHasKey('pii', $context);
                    self::assertArrayNotHasKey('email', $context);

                    foreach ($context as $value) {
                        if (is_string($value)) {
                            self::assertStringNotContainsString('user@example.com', $value);
                        }
                    }

                    return true;
                }),
            );

        $this->decoratedLogger->expects(self::never())->method('log');

        $this->sut->error('something broke', [
            'pii'    => ['email' => 'user@example.com'],
            'action' => 'mail-send',
        ]);
    }

    public function testCallerCanOverrideProcedureAndOrgaIds(): void
    {
        $captured = $this->captureWrittenRecord();
        $this->decoratedLogger->method('log');

        $this->sut->info('m', [
            'procedureId' => 'proc-1234',
            'orgaId'      => 'orga-5678',
            'pii'         => ['email' => 'a@b.c'],
        ]);

        $record = $captured();
        self::assertSame('proc-1234', $record->procedureId);
        self::assertSame('orga-5678', $record->orgaId);
    }

    public function testProcedureAndRequestIdAutoResolved(): void
    {
        $request = Request::create('/');
        $request->attributes->set('request_id', 'rid-abc');
        $this->requestStack->push($request);

        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn('auto-proc');

        $contextProvider = $this->createMock(CurrentContextProviderInterface::class);
        $contextProvider->method('getCurrentProcedure')->willReturn($procedure);
        $contextProvider->method('getCurrentUser')->willThrowException(new Exception('no user'));

        $sut = new PiiAwareLogger($this->decoratedLogger, $this->writer, $contextProvider, $this->requestStack);

        $captured = $this->captureWrittenRecord();
        $this->decoratedLogger->method('log');

        $sut->info('m', ['pii' => ['x' => 1]]);

        $record = $captured();
        self::assertSame('auto-proc', $record->procedureId);
        self::assertSame('rid-abc', $record->requestId);
    }

    public function testOrgaResolvedFromCurrentUser(): void
    {
        $user = new class implements UserInterface {
            public function getOrganisationId(): string
            {
                return 'orga-99';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
                // No transient credentials held by this stub; required by Symfony's UserInterface.
            }

            public function getUserIdentifier(): string
            {
                return 'user-42';
            }
        };

        $contextProvider = $this->createMock(CurrentContextProviderInterface::class);
        $contextProvider->method('getCurrentProcedure')->willReturn(null);
        $contextProvider->method('getCurrentUser')->willReturn($user);

        $sut = new PiiAwareLogger($this->decoratedLogger, $this->writer, $contextProvider, $this->requestStack);

        $captured = $this->captureWrittenRecord();
        $this->decoratedLogger->method('log');

        $sut->info('m', ['pii' => ['x' => 1]]);

        $record = $captured();
        self::assertSame('orga-99', $record->orgaId);
    }

    public function testMessageAndLevelStored(): void
    {
        $captured = $this->captureWrittenRecord();
        $this->decoratedLogger->method('log');

        $this->sut->error('boom {x}', ['pii' => ['x' => 'sensitive'], 'note' => 'hi']);

        $record = $captured();
        self::assertSame('boom {x}', $record->message);
        self::assertSame('error', $record->levelName);
        self::assertSame(400, $record->level);
    }

    public function testEmptyContextDoesNotProduceJsonNullStrings(): void
    {
        $captured = $this->captureWrittenRecord();
        $this->decoratedLogger->method('log');

        $this->sut->info('plain message');

        $record = $captured();
        self::assertNull($record->piiContextJson);
        self::assertNull($record->nonPiiContextJson);
    }

    /** @return callable(): PiiLogRecord */
    private function captureWrittenRecord(): callable
    {
        $holder = new \stdClass();
        $holder->record = null;

        $this->writer
            ->expects(self::once())
            ->method('write')
            ->willReturnCallback(static function (PiiLogRecord $record) use ($holder): string {
                $holder->record = $record;

                return 'row-id';
            });

        return static function () use ($holder): PiiLogRecord {
            self::assertInstanceOf(PiiLogRecord::class, $holder->record);

            return $holder->record;
        };
    }
}
