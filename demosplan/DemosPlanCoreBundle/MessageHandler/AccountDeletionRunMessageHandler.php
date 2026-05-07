<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DateTime;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use demosplan\DemosPlanCoreBundle\Message\AccountDeletionRunMessage;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AccountDeletionRunMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    public const TEMPLATE_FIRST_WARNING = 'account_deletion_warning_first';
    public const TEMPLATE_SECOND_WARNING = 'account_deletion_warning_second';
    public const TEMPLATE_FINAL_NOTIFICATION = 'account_deletion_completed';

    public function __construct(
        private readonly PermissionsInterface $permissions,
        private readonly AccountDeletionTrackingRepository $trackingRepository,
        private readonly LastLoginActivityChecker $activityChecker,
        private readonly MailService $mailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AccountDeletionRunMessage $message): void
    {
        $this->initializeAnonymousUserPermissions();

        try {
            $firstWarningDays = $this->readIntParam('account_deletion.first_warning_days');

            if (null === $firstWarningDays) {
                return;
            }

            $candidates = $this->trackingRepository->findInactivityDeletionCandidates(
                new DateTime(sprintf('-%d days', $firstWarningDays)),
                $this->getProtectedUserIds(),
            );

            foreach ($candidates as $user) {
                try {
                    $this->processCandidate($user);
                } catch (Exception $exception) {
                    $this->logger->error(
                        'Account deletion: failed to process candidate',
                        ['userId' => $user->getId(), 'exception' => $exception->getMessage()]
                    );
                }
            }
        } catch (Exception $exception) {
            $this->logger->error(
                'Account deletion: cron run failed',
                ['exception' => $exception->getMessage()]
            );
        }
    }

    private function processCandidate(UserInterface $user): void
    {
        $tracking = $this->trackingRepository->findOneByUser($user);
        $steps = $this->activityChecker->evaluateInactivitySteps($user, $tracking);

        if ([] === $steps) {
            return;
        }

        foreach ($steps as $step) {
            $tracking = match ($step) {
                AccountDeletionStep::SendFirstWarning       => $this->dispatchFirstWarning($user, $tracking),
                AccountDeletionStep::SendSecondWarning      => $this->dispatchSecondWarning($user, $tracking),
                AccountDeletionStep::Delete                 => $this->finalizeDeletion($user, $tracking),
                AccountDeletionStep::DeleteWithoutWarnings  => $this->silentDeletion($user, $tracking),
            };
        }

        $this->entityManager->flush();
    }

    private function dispatchFirstWarning(UserInterface $user, ?AccountDeletionTracking $tracking): AccountDeletionTracking
    {
        $tracking = $this->ensureTracking($user, $tracking);
        $tracking->setFirstWarningMail($this->queueMail($user, self::TEMPLATE_FIRST_WARNING, [
            'deletion_date' => $this->computeDeletionDate($user),
            'link_section'  => $this->computeLinkSection($user),
        ]));

        return $tracking;
    }

    private function dispatchSecondWarning(UserInterface $user, ?AccountDeletionTracking $tracking): AccountDeletionTracking
    {
        $tracking = $this->ensureTracking($user, $tracking);
        $tracking->setSecondWarningMail($this->queueMail($user, self::TEMPLATE_SECOND_WARNING, [
            'deletion_date' => $this->computeDeletionDate($user),
            'link_section'  => $this->computeLinkSection($user),
        ]));

        return $tracking;
    }

    private function finalizeDeletion(UserInterface $user, ?AccountDeletionTracking $tracking): null
    {
        $this->queueMail($user, self::TEMPLATE_FINAL_NOTIFICATION);

        if ($user instanceof User) {
            $user->setDeleted(true);
        }

        if (null !== $tracking) {
            $this->entityManager->remove($tracking);
        }

        $this->logger->info(
            'Account deletion: user soft-deleted after warning cascade',
            ['userId' => $user->getId(), 'login' => $user->getLogin()]
        );

        return null;
    }

    /**
     * Soft-deletes a user that fell into the legacy / abandoned-invitation path
     * (null lastLogin, creation date past the deletion window). No mail sent —
     * those mailboxes are by definition unmonitored and a notification would
     * generate support questions rather than provide value.
     */
    private function silentDeletion(UserInterface $user, ?AccountDeletionTracking $tracking): null
    {
        if ($user instanceof User) {
            $user->setDeleted(true);
        }

        if (null !== $tracking) {
            $this->entityManager->remove($tracking);
        }

        $this->logger->info(
            'Account deletion: user soft-deleted silently (legacy / never-logged-in)',
            ['userId' => $user->getId(), 'login' => $user->getLogin()]
        );

        return null;
    }

    private function ensureTracking(UserInterface $user, ?AccountDeletionTracking $tracking): AccountDeletionTracking
    {
        if (null !== $tracking) {
            return $tracking;
        }

        if (!$user instanceof User) {
            throw new InvalidArgumentException('Tracking creation requires the concrete User entity.');
        }

        $tracking = new AccountDeletionTracking($user);
        $this->entityManager->persist($tracking);

        return $tracking;
    }

    /**
     * @param array<string, scalar> $extraVars
     */
    private function queueMail(UserInterface $user, string $template, array $extraVars = []): MailSend
    {
        return $this->mailService->sendMail(
            $template,
            $user->getLanguage(),
            $user->getEmail(),
            '',
            '',
            '',
            MailSend::MAIL_SCOPE_EXTERN,
            array_merge(
                [
                    'firstname' => $user->getFirstname(),
                    'lastname'  => $user->getLastname(),
                ],
                $extraVars,
            ),
        );
    }

    /**
     * Returns the user-facing date (d.m.Y) when the account will be soft-deleted —
     * `lastLogin + first_warning_days + 2 * warning_step_days`. Empty string if the
     * user has no lastLogin (legacy / never-logged-in path uses no warning mails
     * anyway, so this is a defensive fallback).
     */
    private function computeDeletionDate(UserInterface $user): string
    {
        $lastLogin = $user->getLastLogin();
        if (!$lastLogin instanceof DateTimeInterface) {
            return '';
        }

        $totalDays = ($this->readIntParam('account_deletion.first_warning_days') ?? 0)
            + 2 * ($this->readIntParam('account_deletion.warning_step_days') ?? 30);

        try {
            $cutoff = DateTime::createFromInterface($lastLogin);
            $cutoff->modify(sprintf('+%d days', $totalDays));

            return $cutoff->format('d.m.Y');
        } catch (Exception $exception) {
            $this->logger->warning(
                'Account deletion: failed to compute deletion date',
                [
                    'userId'    => $user->getId(),
                    'lastLogin' => $lastLogin->format('Y-m-d H:i:s'),
                    'totalDays' => $totalDays,
                    'exception' => $exception->getMessage(),
                ]
            );

            return '';
        }
    }

    /**
     * Returns either an empty string (no homepage URL template configured, or no
     * resolvable customer subdomain) or a multi-line block with the link,
     * including the leading blank line so it slots cleanly into the warning
     * templates between the body text and the signature.
     *
     * The template parameter may include a `${subdomain}` placeholder that gets
     * substituted with the user's customer subdomain (multi-tenant deployments).
     * Single-customer projects can set a fixed URL with no placeholder; the
     * `str_replace` is then a no-op.
     */
    private function computeLinkSection(UserInterface $user): string
    {
        $template = (string) $this->parameterBag->get('account_deletion.homepage_url_template');
        if ('' === $template) {
            return '';
        }

        $subdomain = $this->resolveCustomerSubdomain($user);
        if (null === $subdomain) {
            return '';
        }

        $url = str_replace('${subdomain}', $subdomain, $template);

        return "\n\nSie erreichen unsere Plattform unter:\n$url";
    }

    /**
     * Returns the first non-empty customer subdomain associated with the user
     * (via `roleInCustomers`), or null if no usable subdomain exists. Any
     * customer is sufficient for the deletion-cascade link — the user can log
     * in via any of their customer subdomains to keep the account.
     */
    private function resolveCustomerSubdomain(UserInterface $user): ?string
    {
        foreach ($user->getCustomers() as $customer) {
            $subdomain = $customer->getSubdomain();
            if ('' !== $subdomain) {
                return $subdomain;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getProtectedUserIds(): array
    {
        $ids = [UserInterface::ANONYMOUS_USER_ID];
        $additional = (array) $this->parameterBag->get('account_deletion.additional_protected_user_ids');
        foreach ($additional as $id) {
            $ids[] = (string) $id;
        }

        return $ids;
    }

    private function readIntParam(string $name): ?int
    {
        if (!$this->parameterBag->has($name)) {
            return null;
        }

        $value = $this->parameterBag->get($name);

        return null === $value ? null : (int) $value;
    }
}
