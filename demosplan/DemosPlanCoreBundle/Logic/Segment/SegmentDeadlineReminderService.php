<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DateInterval;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Sends the deadline reminder ("Wiedervorlage") mails for assigned segments.
 *
 * Once per daily run it reminds each assignee about the segments whose
 * deadline is one week away and, separately, about those due on the day
 * itself. Segments sharing a deadline are bundled into a single mail per
 * assignee; deadlines in the past are never matched, so no mail goes out
 * after a deadline has lapsed. Assignees who turned the reminder off keep
 * receiving none.
 */
class SegmentDeadlineReminderService
{
    /**
     * Interval before the deadline at which the "one week left" reminder is sent.
     */
    private const REMINDER_ONE_WEEK_BEFORE = 'P7D';

    /**
     * Interval before the deadline at which the "due today" reminder is sent.
     */
    private const REMINDER_ON_DEADLINE = 'P0D';

    public function __construct(
        private readonly Environment $twig,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly LoggerInterface $logger,
        private readonly MailService $mailService,
        private readonly PermissionsInterface $permissions,
        private readonly RouterInterface $router,
        private readonly SegmentRepository $segmentRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Sends the reminders for both the one-week-ahead and the day-of window.
     */
    public function sendSegmentDeadlineReminderMails(): void
    {
        if (!$this->permissions->hasPermission('feature_statement_deadline_mail')) {
            return;
        }

        $this->sendRemindersForInterval(new DateInterval(self::REMINDER_ONE_WEEK_BEFORE));
        $this->sendRemindersForInterval(new DateInterval(self::REMINDER_ON_DEADLINE));
    }

    /**
     * Sends one bundled reminder per assignee for the segments whose deadline
     * lies the given interval ahead of today.
     */
    private function sendRemindersForInterval(DateInterval $timeUntilDeadline): void
    {
        $segmentsByAssignee = $this->segmentRepository->findSegmentsForAssigneesByDeadlineInterval($timeUntilDeadline);

        foreach ($segmentsByAssignee as $segments) {
            $assignee = $segments[0]->getAssignee();
            if (!$assignee instanceof User) {
                continue;
            }
            if (!$assignee->getSegmentDeadlineReminderEnabled()) {
                continue;
            }
            $this->sendReminderMail($assignee, $segments);
        }
    }

    /**
     * @param list<Segment> $segments all sharing the same deadline
     */
    private function sendReminderMail(User $assignee, array $segments): void
    {
        try {
            $deadline = $segments[0]->getDeadline();
            $mail = [];
            $mail['mailbody'] = $this->twig->load('@DemosPlanCore/DemosPlanUser/email_segment_deadline_reminder.html.twig')
                ->renderBlock(
                    'body_plain',
                    [
                        'templateVars' => [
                            'deadline' => $deadline,
                            'entries'  => $this->buildSegmentEntries($segments),
                        ],
                        'projectName'  => $this->globalConfig->getProjectName(),
                    ]
                );
            $mail['mailsubject'] = $this->translator->trans(
                'email.subject.segment.deadline.reminder',
                ['date' => $deadline?->format('d.m.Y')]
            );
            $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $assignee->getEmail(),
                '',
                '',
                '',
                'extern',
                $mail
            );
        } catch (Exception $exception) {
            $this->logger->error(
                'Segment deadline reminder mail entry could not be inserted during mail send preparation.',
                ['assigneeId' => $assignee->getId(), 'exception' => $exception]
            );
        }
    }

    /**
     * Builds the nested procedure -> workflow place -> segment structure the
     * template renders, carrying the direct link for each segment.
     *
     * @param list<Segment> $segments
     *
     * @return array<string, array<string, array<string, string>>>
     */
    private function buildSegmentEntries(array $segments): array
    {
        $entries = [];
        foreach ($segments as $segment) {
            $procedureName = $segment->getProcedure()->getName();
            $workflowPlace = $segment->getPlace()->getName();
            $entries[$procedureName][$workflowPlace][$segment->getExternId()] = $this->generateSegmentLink($segment);
        }

        return $entries;
    }

    /**
     * Builds the absolute deep link that opens the parent statement's segment
     * list focused on the given segment. Reachable only when the recipient is
     * logged in.
     */
    private function generateSegmentLink(Segment $segment): string
    {
        $link = $this->router->generate(
            'dplan_statement_segments_list',
            [
                'procedureId' => $segment->getProcedure()->getId(),
                'statementId' => $segment->getParentStatementOfSegment()->getId(),
            ],
            RouterInterface::ABSOLUTE_URL
        );

        return $link.'?segment='.$segment->getId();
    }
}
