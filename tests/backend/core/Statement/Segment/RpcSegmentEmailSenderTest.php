<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcSegmentEmailSender;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentEmailSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Pure unit test for the RPC solver orchestration in RpcSegmentEmailSender:
 * method support, the permission gate, and parameter mapping/delegation to
 * SegmentEmailSender. All collaborators are mocked.
 */
class RpcSegmentEmailSenderTest extends TestCase
{
    protected ?RpcSegmentEmailSender $sut = null;

    public function testSupportsOnlySegmentEmailSenderMethod(): void
    {
        $this->sut = $this->buildSut();

        self::assertTrue($this->sut->supports('segment.email.sender'));
        self::assertFalse($this->sut->supports('statement.email.sender'));
        self::assertFalse($this->sut->supports('some.other.method'));
    }

    public function testIsNotTransactional(): void
    {
        $this->sut = $this->buildSut();

        self::assertFalse($this->sut->isTransactional());
    }

    public function testValidateRpcRequestThrowsAccessDeniedWithoutPermission(): void
    {
        $this->sut = $this->buildSut(hasPermission: false);

        $this->expectException(AccessDeniedException::class);
        $this->sut->validateRpcRequest(new stdClass());
    }

    public function testValidateRpcRequestPassesWithPermission(): void
    {
        $this->sut = $this->buildSut(hasPermission: true);

        $this->expectNotToPerformAssertions();
        $this->sut->validateRpcRequest(new stdClass());
    }

    public function testExecuteDelegatesToSenderWithMappedParams(): void
    {
        $sender = $this->createMock(SegmentEmailSender::class);
        $sender->expects(self::once())
            ->method('sendSegmentsMail')
            ->with(
                ['segment-1', 'segment-2'],
                'procedure-id',
                'recipient@test.de',
                'Subject',
                'Body',
                'cc@test.de',
                'reply@test.de',
            )
            ->willReturn(true);

        $this->sut = $this->buildSut(hasPermission: true, sender: $sender);

        $result = $this->sut->execute(
            $this->procedure('procedure-id'),
            $this->rpcRequest('req-1', [
                'segmentIds'   => ['segment-1', 'segment-2'],
                'recipientMail' => 'recipient@test.de',
                'subject'      => 'Subject',
                'body'         => 'Body',
                'sendEmailCC'  => 'cc@test.de',
                'replyTo'      => 'reply@test.de',
            ])
        );

        self::assertCount(1, $result);
        self::assertSame('2.0', $result[0]->jsonrpc);
        self::assertTrue($result[0]->result);
        self::assertSame('req-1', $result[0]->id);
    }

    public function testExecuteDefaultsReplyToToNullWhenAbsent(): void
    {
        $sender = $this->createMock(SegmentEmailSender::class);
        $sender->expects(self::once())
            ->method('sendSegmentsMail')
            ->with(
                ['segment-1'],
                'procedure-id',
                'recipient@test.de',
                'Subject',
                'Body',
                'cc@test.de',
                null,
            )
            ->willReturn(true);

        $this->sut = $this->buildSut(hasPermission: true, sender: $sender);

        // params intentionally omit `replyTo` — the solver must default it to null.
        $result = $this->sut->execute(
            $this->procedure('procedure-id'),
            $this->rpcRequest('req-1', [
                'segmentIds'   => ['segment-1'],
                'recipientMail' => 'recipient@test.de',
                'subject'      => 'Subject',
                'body'         => 'Body',
                'sendEmailCC'  => 'cc@test.de',
            ])
        );

        self::assertTrue($result[0]->result);
    }

    public function testExecuteReturnsServerErrorWhenPermissionDenied(): void
    {
        $errorObject = new stdClass();
        $errorGenerator = $this->createMock(RpcErrorGenerator::class);
        $errorGenerator->expects(self::once())
            ->method('serverError')
            ->willReturn($errorObject);

        // The sender must never be reached once the permission check fails.
        $sender = $this->createMock(SegmentEmailSender::class);
        $sender->expects(self::never())->method('sendSegmentsMail');

        $this->sut = $this->buildSut(hasPermission: false, sender: $sender, errorGenerator: $errorGenerator);

        $result = $this->sut->execute(
            $this->procedure('procedure-id'),
            $this->rpcRequest('req-1', [
                'segmentIds'   => ['segment-1'],
                'recipientMail' => 'recipient@test.de',
                'subject'      => 'Subject',
                'body'         => 'Body',
                'sendEmailCC'  => null,
            ])
        );

        self::assertSame([$errorObject], $result);
    }

    private function buildSut(
        bool $hasPermission = true,
        ?SegmentEmailSender $sender = null,
        ?RpcErrorGenerator $errorGenerator = null,
    ): RpcSegmentEmailSender {
        $currentUser = $this->createMock(CurrentUserInterface::class);
        $currentUser->method('hasPermission')->willReturn($hasPermission);

        return new RpcSegmentEmailSender(
            $currentUser,
            $this->createMock(LoggerInterface::class),
            $errorGenerator ?? $this->createMock(RpcErrorGenerator::class),
            $sender ?? $this->createMock(SegmentEmailSender::class),
        );
    }

    private function procedure(string $id): ProcedureInterface
    {
        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn($id);

        return $procedure;
    }

    private function rpcRequest(string $id, array $params): stdClass
    {
        $rpcRequest = new stdClass();
        $rpcRequest->id = $id;
        $rpcRequest->params = (object) $params;

        return $rpcRequest;
    }
}
