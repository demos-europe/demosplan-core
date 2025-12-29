<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Exception\NoDesignatedStateException;
use demosplan\DemosPlanCoreBundle\Logic\News\ProcedureNewsService;
use demosplan\DemosPlanCoreBundle\Message\SwitchNewsStatesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SwitchNewsStatesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class SwitchNewsStatesMessageHandlerTest extends UnitTestCase
{
    private ?ProcedureNewsService $procedureNewsService = null;
    private ?LoggerInterface $logger = null;
    private ?SwitchNewsStatesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureNewsService = $this->createMock(ProcedureNewsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new SwitchNewsStatesMessageHandler(
            $this->procedureNewsService,
            $this->logger
        );
    }

    private function createLoggerInfoCallback(string $expectedNewsCountMessage): callable
    {
        return function ($message) use ($expectedNewsCountMessage) {
            if ('Maintenance: switchStatesOfNewsOfToday' === $message) {
                return;
            }
            if ($expectedNewsCountMessage === $message) {
                return;
            }
            $this->fail('Unexpected log message: '.$message);
        };
    }

    public function testInvokeSwitchesNewsStatesAndLogsSuccess(): void
    {
        // Arrange
        $news1 = $this->createMock(News::class);
        $news2 = $this->createMock(News::class);

        $this->procedureNewsService->expects($this->once())
            ->method('getNewsToSetStateToday')
            ->willReturn([$news1, $news2]);

        $this->procedureNewsService->expects($this->exactly(2))
            ->method('setState')
            ->willReturnCallback(function ($news) use ($news1, $news2) {
                $this->assertContains($news, [$news1, $news2]);
            });

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback($this->createLoggerInfoCallback('Set states of 2 news.'));

        // Act
        ($this->sut)(new SwitchNewsStatesMessage());
    }

    public function testInvokeHandlesNoDesignatedStateException(): void
    {
        // Arrange
        $news = $this->createMock(News::class);
        $exception = new NoDesignatedStateException('Designated state not defined');

        $this->procedureNewsService->expects($this->once())
            ->method('getNewsToSetStateToday')
            ->willReturn([$news]);

        $this->procedureNewsService->expects($this->once())
            ->method('setState')
            ->willThrowException($exception);

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback($this->createLoggerInfoCallback('Set states of 0 news.'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Set state of news failed, because designated state is not defined.', [$exception]);

        // Act
        ($this->sut)(new SwitchNewsStatesMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Switching news states failed');

        $this->procedureNewsService->expects($this->once())
            ->method('getNewsToSetStateToday')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: switching of news state.', [$exception]);

        // Act
        ($this->sut)(new SwitchNewsStatesMessage());
    }
}
