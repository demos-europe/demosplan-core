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
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use demosplan\DemosPlanCoreBundle\Message\AccountDeletionRunMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AccountDeletionRunMessageHandler;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class AccountDeletionRunMessageHandlerTest extends UnitTestCase
{
    private const TEST_EMAIL = 'test@example.com';

    private $permissions;
    private $trackingRepository;
    private $userRepository;
    private $activityChecker;
    private $mailService;
    private $entityManager;
    private $parameterBag;
    private $logger;
    private $piiLogger;
    private $twig;
    private $translator;
    private $globalConfig;
    private $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->trackingRepository = $this->createMock(AccountDeletionTrackingRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->activityChecker = $this->createMock(LastLoginActivityChecker::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->piiLogger = $this->createMock(PiiAwareLogger::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);

        // Real twig from the kernel container — TemplateWrapper is final and
        // can't be mocked, and a real renderer also gives us a smoke check
        // that the production templates load and render without errors.
        $this->twig = self::getContainer()->get('twig');

        // Feature enabled with a 30-day first-warning threshold.
        $this->parameterBag->method('has')->willReturnCallback(
            static fn (string $name) => 'account_deletion.first_warning_days' === $name
        );
        $this->parameterBag->method('get')->willReturnCallback(
            static fn (string $name) => match ($name) {
                'account_deletion.first_warning_days' => 30,
                'account_deletion.support_email'      => 'support@test.example',
                default                               => null,
            }
        );

        $this->translator->method('trans')->willReturnArgument(0);
        $this->globalConfig->method('getProjectName')->willReturn('TestProject');

        $this->sut = new AccountDeletionRunMessageHandler(
            $this->permissions,
            $this->trackingRepository,
            $this->userRepository,
            $this->activityChecker,
            $this->mailService,
            $this->entityManager,
            $this->parameterBag,
            $this->logger,
            $this->piiLogger,
            $this->twig,
            $this->translator,
            $this->globalConfig
        );
    }

    public function testEmptyCandidateSetDoesNothing(): void
    {
        $this->userRepository
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

        $this->userRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn(null);
        $this->activityChecker->method('evaluateInactivityStep')
            ->with($user, null)
            ->willReturn(AccountDeletionStep::SendFirstWarning);

        $mailSend = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                'dm_stellungnahme',
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->callback(fn (array $mail) => 'email.subject.account_deletion.warning_first' === ($mail['mailsubject'] ?? null)
                    && str_contains($mail['mailbody'] ?? '', 'Test')
                    && str_contains($mail['mailbody'] ?? '', 'User')
                    && str_contains($mail['mailbody'] ?? '', 'DEMOS Support Team'))
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

        $this->userRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn($existingTracking);
        $this->activityChecker->method('evaluateInactivityStep')
            ->with($user, $existingTracking)
            ->willReturn(AccountDeletionStep::SendSecondWarning);

        $secondMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                'dm_stellungnahme',
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->callback(fn (array $mail) => 'email.subject.account_deletion.warning_second' === ($mail['mailsubject'] ?? null))
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

        $this->userRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);
        $this->trackingRepository->method('findOneByUser')->with($user)->willReturn($tracking);
        $this->activityChecker->method('evaluateInactivityStep')
            ->with($user, $tracking)
            ->willReturn(AccountDeletionStep::Delete);

        $finalMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->once())
            ->method('sendMail')
            ->with(
                'dm_stellungnahme',
                'de_DE',
                self::TEST_EMAIL,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                MailSend::MAIL_SCOPE_EXTERN,
                $this->callback(fn (array $mail) => 'email.subject.account_deletion.completed' === ($mail['mailsubject'] ?? null))
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

        $this->userRepository->method('findInactivityDeletionCandidates')
            ->willReturn([$user1, $user2]);
        $this->trackingRepository->method('findOneByUser')->willReturn(null);
        $this->activityChecker->method('evaluateInactivityStep')
            ->willReturn(AccountDeletionStep::SendFirstWarning);

        // First call throws, second call succeeds.
        $secondMail = $this->createMock(MailSend::class);
        $this->mailService->expects($this->exactly(2))
            ->method('sendMail')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('SMTP exploded')),
                $secondMail
            );

        // queueMail's Throwable catch routes the failing user through piiLogger
        // (userId is PII); the outer per-candidate try/catch is no longer reached
        // because the inner catch swallows the exception and returns null
        // (callers treat that as "warning not yet attempted" so the FK stays unset
        // on the tracking row and the next cron run retries the stage).
        $this->piiLogger->expects($this->once())
            ->method('error')
            ->with(
                'Account deletion: failed to render or queue notification mail',
                $this->callback(fn (array $context) => 'user1-id' === ($context['pii']['userId'] ?? null))
            );

        ($this->sut)(new AccountDeletionRunMessage());
    }

    public function testIdentityProviderUserIsSkippedBeforeAnyDeletionStep(): void
    {
        $user = $this->buildUserMock();
        $user->method('isProvidedByIdentityProvider')->willReturn(true);

        $this->userRepository->method('findInactivityDeletionCandidates')->willReturn([$user]);

        // The IdP guard short-circuits at the top of processCandidate: no step
        // evaluation, no tracking lookup, no mail, no soft-deletion, no flush.
        $this->trackingRepository->expects($this->never())->method('findOneByUser');
        $this->activityChecker->expects($this->never())->method('evaluateInactivityStep');
        $this->mailService->expects($this->never())->method('sendMail');
        $user->expects($this->never())->method('setDeleted');
        $this->entityManager->expects($this->never())->method('flush');

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
        $user->method('getFirstname')->willReturn('Test');
        $user->method('getLastname')->willReturn('User');
        $user->method('getCustomers')->willReturn([]);
        $user->method('getLastLogin')->willReturn(new DateTime('-31 days'));

        return $user;
    }
}
