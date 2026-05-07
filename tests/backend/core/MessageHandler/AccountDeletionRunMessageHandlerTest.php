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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use demosplan\DemosPlanCoreBundle\Message\AccountDeletionRunMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AccountDeletionRunMessageHandler;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\UnitTestCase;

class AccountDeletionRunMessageHandlerTest extends UnitTestCase
{
    private const TEST_EMAIL = 'test@example.com';

    private $permissions;
    private $trackingRepository;
    private $activityChecker;
    private $mailService;
    private $entityManager;
    private $parameterBag;
    private $logger;
    private $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->trackingRepository = $this->createMock(AccountDeletionTrackingRepository::class);
        $this->activityChecker = $this->createMock(LastLoginActivityChecker::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Feature enabled with a 30-day first-warning threshold.
        $this->parameterBag->method('has')->willReturnCallback(
            static fn (string $name) => 'account_deletion.first_warning_days' === $name
        );
        $this->parameterBag->method('get')->willReturnCallback(
            static fn (string $name) => match ($name) {
                'account_deletion.first_warning_days' => 30,
                default => null,
            }
        );

        $this->sut = new AccountDeletionRunMessageHandler(
            $this->permissions,
            $this->trackingRepository,
            $this->activityChecker,
            $this->mailService,
            $this->entityManager,
            $this->parameterBag,
            $this->logger
        );
    }

    public function testEmptyCandidateSetDoesNothing(): void
    {
        $this->trackingRepository
            ->method('findInactivityDeletionCandidates')
            ->willReturn([]);

        $this->mailService->expects($this->never())->method('sendMail');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('remove');

        ($this->sut)(new AccountDeletionRunMessage());
    }

    public function testFirstWarningStepSendsMailAndPersistsTracking(): void
    {
        $user = $this->buildUserMock();

        $this->trackingRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn(null);
        $this->activityChecker->method('evaluateInactivitySteps')
            ->with($user, null)
            ->willReturn([AccountDeletionStep::SendFirstWarning]);

        $mailSend = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                AccountDeletionRunMessageHandler::TEMPLATE_FIRST_WARNING,
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->callback(fn (array $vars) => 'Test' === ($vars['firstname'] ?? null)
                    && 'User' === ($vars['lastname'] ?? null)
                    && array_key_exists('deletion_date', $vars)
                    && array_key_exists('link_section', $vars))
            )
            ->willReturn($mailSend);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AccountDeletionTracking::class));
        $this->entityManager->expects($this->once())->method('flush');

        ($this->sut)(new AccountDeletionRunMessage());
    }

    public function testSecondWarningStepReusesExistingTracking(): void
    {
        $user = $this->buildUserMock();
        $existingTracking = new AccountDeletionTracking($user);
        $existingTracking->setFirstWarningMail($this->createMock(MailSend::class));

        $this->trackingRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn($existingTracking);
        $this->activityChecker->method('evaluateInactivitySteps')
            ->with($user, $existingTracking)
            ->willReturn([AccountDeletionStep::SendSecondWarning]);

        $secondMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                AccountDeletionRunMessageHandler::TEMPLATE_SECOND_WARNING,
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->anything()
            )
            ->willReturn($secondMail);

        // No persist of a new tracking row — we re-use the existing one.
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        ($this->sut)(new AccountDeletionRunMessage());

        $this->assertSame($secondMail, $existingTracking->getSecondWarningMail());
    }

    public function testDeleteStepSendsFinalNotificationSoftDeletesAndRemovesTracking(): void
    {
        $user = $this->buildUserMock();
        $tracking = new AccountDeletionTracking($user);
        $tracking->setFirstWarningMail($this->createMock(MailSend::class));
        $tracking->setSecondWarningMail($this->createMock(MailSend::class));

        $this->trackingRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn($tracking);
        $this->activityChecker->method('evaluateInactivitySteps')
            ->with($user, $tracking)
            ->willReturn([AccountDeletionStep::Delete]);

        $finalMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                AccountDeletionRunMessageHandler::TEMPLATE_FINAL_NOTIFICATION,
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->anything()
            )
            ->willReturn($finalMail);

        $user->expects($this->once())->method('setDeleted')->with(true);
        $this->entityManager->expects($this->once())->method('remove')->with($tracking);
        $this->entityManager->expects($this->once())->method('flush');

        ($this->sut)(new AccountDeletionRunMessage());
    }

    public function testMailerFailureOnOneCandidateDoesNotAbortOthers(): void
    {
        $user1 = $this->buildUserMock('user1@example.com', 'user1-id');
        $user2 = $this->buildUserMock('user2@example.com', 'user2-id');

        $this->trackingRepository->method('findInactivityDeletionCandidates')
            ->willReturn([$user1, $user2]);
        $this->trackingRepository->method('findOneByUser')->willReturn(null);
        $this->activityChecker->method('evaluateInactivitySteps')
            ->willReturn([AccountDeletionStep::SendFirstWarning]);

        // First call throws, second call succeeds.
        $secondMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->exactly(2))
            ->method('sendMail')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('SMTP exploded')),
                $secondMail
            );

        // Inner per-candidate try/catch: error log fires once for the failing user.
        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                'Account deletion: failed to process candidate',
                $this->callback(fn (array $context) => 'user1-id' === ($context['userId'] ?? null))
            );

        ($this->sut)(new AccountDeletionRunMessage());
    }

    /**
     * @return User&MockObject
     */
    private function buildUserMock(string $email = self::TEST_EMAIL, string $id = 'test-user-id'): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getLogin')->willReturn($email);
        $user->method('getEmail')->willReturn($email);
        $user->method('getLanguage')->willReturn('de_DE');
        $user->method('getFirstname')->willReturn('Test');
        $user->method('getLastname')->willReturn('User');
        $user->method('getCustomers')->willReturn([]);
        $user->method('getLastLogin')->willReturn(new DateTime('-31 days'));

        return $user;
    }
}
