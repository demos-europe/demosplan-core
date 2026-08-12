<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DateInterval;
use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\MailTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentDeadlineReminderService;
use Tests\Base\FunctionalTestCase;

class SegmentDeadlineReminderServiceTest extends FunctionalTestCase
{
    protected ?SegmentDeadlineReminderService $sut = null;
    private ?MailService $mailService = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SegmentDeadlineReminderService::class);
        $this->mailService = self::getContainer()->get(MailService::class);

        // The service is gated on this global feature permission.
        $this->logIn($this->fixtures->getReference('testUser'));
        $this->enablePermissions(['feature_statement_deadline_mail']);

        // The generic mail shell the reminder is queued through; not part of the test DB fixtures.
        MailTemplateFactory::createOne([
            'label'    => 'dm_stellungnahme',
            'language' => 'de_DE',
            'title'    => '${mailsubject}',
            'content'  => '${mailbody}',
        ]);
    }

    public function testSendsOneBundledMailForSegmentsSharingDeadline(): void
    {
        $assignee = $this->createAssignee('reminder-bundled@test.example');
        $deadline = $this->todayPlus('P7D');
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $deadline]);
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $deadline]);

        $this->sut->sendSegmentDeadlineReminderMails();

        self::assertSame(1, $this->countQueuedMailsTo($assignee->getEmail()));
    }

    public function testDoesNotSendToOptedOutAssignee(): void
    {
        $assignee = $this->createAssignee('reminder-optedout@test.example');
        $assignee->setSegmentDeadlineReminderEnabled(false);
        $assignee->_save();
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $this->todayPlus('P7D')]);

        $this->sut->sendSegmentDeadlineReminderMails();

        self::assertSame(0, $this->countQueuedMailsTo($assignee->getEmail()));
    }

    public function testSendsSeparateMailsForEachDeadlineWindow(): void
    {
        $assignee = $this->createAssignee('reminder-windows@test.example');
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $this->todayPlus('P7D')]);
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $this->todayPlus('P0D')]);

        $this->sut->sendSegmentDeadlineReminderMails();

        self::assertSame(2, $this->countQueuedMailsTo($assignee->getEmail()));
    }

    public function testDoesNotSendWhenFeaturePermissionDisabled(): void
    {
        $this->disablePermissions(['feature_statement_deadline_mail']);
        $assignee = $this->createAssignee('reminder-featureoff@test.example');
        SegmentFactory::createOne(['assignee' => $assignee, 'deadline' => $this->todayPlus('P7D')]);

        $this->sut->sendSegmentDeadlineReminderMails();

        self::assertSame(0, $this->countQueuedMailsTo($assignee->getEmail()));
    }

    private function createAssignee(string $email): object
    {
        return UserFactory::createOne(['email' => $email, 'deleted' => false]);
    }

    private function todayPlus(string $intervalSpec): DateTime
    {
        return (new DateTime('today'))->add(new DateInterval($intervalSpec));
    }

    private function countQueuedMailsTo(string $email): int
    {
        $count = 0;
        foreach ($this->mailService->getMailsToSend() as $mail) {
            if ($email === $mail->getTo()) {
                ++$count;
            }
        }

        return $count;
    }
}
