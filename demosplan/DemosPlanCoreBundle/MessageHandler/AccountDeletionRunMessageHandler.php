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
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use demosplan\DemosPlanCoreBundle\Message\AccountDeletionRunMessage;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Traits\InitializesAnonymousUserPermissionsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

#[AsMessageHandler]
final class AccountDeletionRunMessageHandler
{
    use InitializesAnonymousUserPermissionsTrait;

    private const BODY_TEMPLATE_FIRST_WARNING = '@DemosPlanCore/DemosPlanUser/email_account_deletion_warning_first.html.twig';
    private const BODY_TEMPLATE_SECOND_WARNING = '@DemosPlanCore/DemosPlanUser/email_account_deletion_warning_second.html.twig';
    private const BODY_TEMPLATE_COMPLETED = '@DemosPlanCore/DemosPlanUser/email_account_deletion_completed.html.twig';

    private const SUBJECT_KEY_FIRST_WARNING = 'email.subject.account_deletion.warning_first';
    private const SUBJECT_KEY_SECOND_WARNING = 'email.subject.account_deletion.warning_second';
    private const SUBJECT_KEY_COMPLETED = 'email.subject.account_deletion.completed';

    public function __construct(
        private readonly PermissionsInterface $permissions,
        private readonly AccountDeletionTrackingRepository $trackingRepository,
        private readonly UserRepository $userRepository,
        private readonly LastLoginActivityChecker $activityChecker,
        private readonly MailService $mailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
        private readonly PiiAwareLogger $piiLogger,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly GlobalConfigInterface $globalConfig,
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

            $candidates = $this->userRepository->findInactivityDeletionCandidates(
                new DateTime(sprintf('-%d days', $firstWarningDays)),
                $this->getProtectedUserIds(),
            );

            foreach ($candidates as $user) {
                try {
                    $this->processCandidate($user);
                } catch (Exception $exception) {
                    $this->piiLogger->error(
                        'Account deletion: failed to process candidate',
                        [
                            'pii'       => ['userId' => $user->getId()],
                            'orgaId'    => $user->getOrganisationId(),
                            'exception' => $exception->getMessage(),
                        ]
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
        $step = $this->activityChecker->evaluateInactivityStep($user, $tracking);

        if (null === $step) {
            return;
        }

        match ($step) {
            AccountDeletionStep::SendFirstWarning       => $this->dispatchFirstWarning($user, $tracking),
            AccountDeletionStep::SendSecondWarning      => $this->dispatchSecondWarning($user, $tracking),
            AccountDeletionStep::Delete                 => $this->finalizeDeletion($user, $tracking),
            AccountDeletionStep::DeleteWithoutWarnings  => $this->silentDeletion($user, $tracking),
        };

        $this->entityManager->flush();
    }

    private function dispatchFirstWarning(UserInterface $user, ?AccountDeletionTracking $tracking): void
    {
        $tracking = $this->ensureTracking($user, $tracking);
        $mail = $this->queueMail(
            $user,
            self::BODY_TEMPLATE_FIRST_WARNING,
            self::SUBJECT_KEY_FIRST_WARNING,
            [
                'deletion_date' => $this->computeDeletionDate($user),
                'link_section'  => $this->computeLinkSection($user),
            ],
        );
        if (null !== $mail) {
            $tracking->setFirstWarningMail($mail);
        }
    }

    private function dispatchSecondWarning(UserInterface $user, ?AccountDeletionTracking $tracking): void
    {
        $tracking = $this->ensureTracking($user, $tracking);
        $mail = $this->queueMail(
            $user,
            self::BODY_TEMPLATE_SECOND_WARNING,
            self::SUBJECT_KEY_SECOND_WARNING,
            [
                'deletion_date' => $this->computeDeletionDate($user),
                'link_section'  => $this->computeLinkSection($user),
            ],
        );
        if (null !== $mail) {
            $tracking->setSecondWarningMail($mail);
        }
    }

    private function finalizeDeletion(UserInterface $user, ?AccountDeletionTracking $tracking): void
    {
        $this->queueMail(
            $user,
            self::BODY_TEMPLATE_COMPLETED,
            self::SUBJECT_KEY_COMPLETED,
        );

        if ($user instanceof User) {
            $user->setDeleted(true);
        }

        if (null !== $tracking) {
            $this->entityManager->remove($tracking);
        }

        $this->piiLogger->info(
            'Account deletion: user soft-deleted after warning cascade',
            [
                'pii' => [
                    'userId' => $user->getId(),
                    'login'  => $user->getLogin(),
                ],
                'orgaId' => $user->getOrganisationId(),
            ]
        );
    }

    /**
     * Soft-deletes a user that fell into the legacy / abandoned-invitation path
     * (null lastLogin, creation date past the deletion window). No mail sent —
     * those mailboxes are by definition unmonitored and a notification would
     * generate support questions rather than provide value.
     */
    private function silentDeletion(UserInterface $user, ?AccountDeletionTracking $tracking): void
    {
        if ($user instanceof User) {
            $user->setDeleted(true);
        }

        if (null !== $tracking) {
            $this->entityManager->remove($tracking);
        }

        $this->piiLogger->info(
            'Account deletion: user soft-deleted silently (legacy / never-logged-in)',
            [
                'pii' => [
                    'userId' => $user->getId(),
                    'login'  => $user->getLogin(),
                ],
                'orgaId' => $user->getOrganisationId(),
            ]
        );
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
     * Renders body + subject ourselves and hands them to the generic `dm_stellungnahme`
     * carrier template (placeholders `${mailbody}` / `${mailsubject}`). The MailTemplate
     * entity is deprecated for new content; this is the same pattern as the existing
     * assigned-tasks digest in {@see EntityContentChangeService::sendUserAssignedTasksNotificationMail}.
     *
     * Returns null on any rendering or send failure; callers in {@see self::dispatchFirstWarning}
     * and {@see self::dispatchSecondWarning} treat that as "warning not yet attempted"
     * (no MailSend FK on the tracking row) so the next cron run retries the same stage.
     *
     * @param array<string, scalar> $vars values used for both the twig body
     *                                    (under `templateVars`) and any ICU subject
     *                                    parameters; unknown keys are ignored by trans()
     */
    private function queueMail(
        UserInterface $user,
        string $bodyTemplate,
        string $subjectKey,
        array $vars = [],
    ): ?MailSend {
        try {
            $supportEmail = (string) $this->parameterBag->get('account_deletion.support_email');
            $replyTo = '' !== $supportEmail ? $supportEmail : $this->globalConfig->getEmailSystem();

            $body = $this->twig->load($bodyTemplate)->renderBlock(
                'body_plain',
                [
                    'templateVars' => array_merge(
                        [
                            'firstname'     => $user->getFirstname(),
                            'lastname'      => $user->getLastname(),
                            'support_email' => $supportEmail,
                        ],
                        $vars,
                    ),
                ],
            );
            $subject = $this->translator->trans($subjectKey, $vars);

            return $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $user->getEmail(),
                $replyTo,
                '',
                '',
                MailSend::MAIL_SCOPE_EXTERN,
                [
                    'mailsubject' => $subject,
                    'mailbody'    => $body,
                ],
            );
        } catch (Throwable $exception) {
            $this->piiLogger->error(
                'Account deletion: failed to render or queue notification mail',
                [
                    'pii'          => ['userId' => $user->getId()],
                    'orgaId'       => $user->getOrganisationId(),
                    'bodyTemplate' => $bodyTemplate,
                    'subjectKey'   => $subjectKey,
                    'exception'    => $exception->getMessage(),
                ]
            );

            return null;
        }
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
            $this->piiLogger->warning(
                'Account deletion: failed to compute deletion date',
                [
                    'pii' => [
                        'userId'    => $user->getId(),
                        'lastLogin' => $lastLogin->format('Y-m-d H:i:s'),
                    ],
                    'orgaId'    => $user->getOrganisationId(),
                    'totalDays' => $totalDays,
                    'exception' => $exception->getMessage(),
                ]
            );

            return '';
        }
    }

    /**
     * Returns either an empty string (project domain unset, or no resolvable
     * customer subdomain) or a multi-line block with the login link, including
     * the leading blank line so it slots cleanly into the warning templates
     * between the body text and the signature.
     *
     * The URL is built from the deployment-wide GlobalConfig (scheme, project
     * domain, optional path prefix) plus the user's customer subdomain — the
     * same host-building convention as customer_settings.html.twig
     * (`[customer.subdomain, projectDomain]|join('.')`). This is exactly the
     * host the user must reach to keep their account, so it cannot drift from
     * the real login host.
     */
    private function computeLinkSection(UserInterface $user): string
    {
        $domain = $this->globalConfig->getProjectDomain();
        $subdomain = $this->resolveCustomerSubdomain($user);
        if ('' === $domain || null === $subdomain) {
            return '';
        }

        $url = sprintf(
            '%s://%s.%s%s/',
            $this->globalConfig->getUrlScheme(),
            $subdomain,
            $domain,
            $this->globalConfig->getUrlPathPrefix(),
        );

        $intro = $this->translator->trans('email.body.account_deletion.platform_link_intro');

        // Wrap the URL in an <a> tag so it renders as a clickable link in the HTML
        // mail variant. MailService runs nl2br() on the content for the HTML body
        // and falls back to a markdown-stripped plain-text variant; both keep the
        // URL intact and most plain-text clients auto-linkify bare URLs anyway.
        return sprintf("\n\n%s\n<a href=\"%s\">%s</a>", $intro, $url, $url);
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
