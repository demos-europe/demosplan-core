<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Controller\Segment\SegmentsExportController;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\ProcedureExportJobService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentsExportResponseBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Message\ExportSegmentsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tests {@see SegmentsExportController::startAsyncExport()}.
 */
class SegmentsExportControllerAsyncExportTest extends TestCase
{
    public function testStartAsyncExportQueuesSegmentsMessageWithQueryParams(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(['tagsFilter' => ['tagIds' => ['t1']]]));
        $sut = new SegmentsExportController(
            $this->createMock(NameGenerator::class),
            $this->createMock(ProcedureHandler::class),
            $requestStack,
            $this->createMock(SegmentsExportResponseBuilder::class),
            $this->createMock(StatementExportTagFilter::class),
            $this->createMock(TranslatorInterface::class),
        );

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('u1');
        $currentUserService = $this->createMock(CurrentUserService::class);
        $currentUserService->method('getUser')->willReturn($user);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('c1');
        $customerService = $this->createMock(CustomerService::class);
        $customerService->method('getCurrentCustomer')->willReturn($customer);

        $exportJobService = $this->createMock(ProcedureExportJobService::class);
        $exportJobService->expects(self::once())
            ->method('start')
            ->willReturnCallback(static function (string $userId, string $procedureId, string $parametersHash, callable $createMessage): string {
                $message = $createMessage('job-1');
                self::assertInstanceOf(ExportSegmentsMessage::class, $message);
                self::assertSame('u1', $userId);
                self::assertSame('proc-1', $procedureId);
                self::assertSame(SegmentsExportResponseBuilder::TYPE_DOCX, $message->getExportType());
                self::assertSame(['tagsFilter' => ['tagIds' => ['t1']]], $message->getQueryParams());
                self::assertSame('c1', $message->getCustomerId());

                return 'job-1';
            });

        $response = $sut->startAsyncExport(
            $currentUserService,
            $customerService,
            $exportJobService,
            'proc-1',
            SegmentsExportResponseBuilder::TYPE_DOCX,
        );

        self::assertSame(['jobId' => 'job-1'], json_decode((string) $response->getContent(), true));
    }
}
