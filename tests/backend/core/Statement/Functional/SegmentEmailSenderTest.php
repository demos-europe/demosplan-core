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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\MailTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentEmailSender;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class SegmentEmailSenderTest extends FunctionalTestCase
{
    private const RECIPIENT = 'recipient@test.de';
    private const SUBJECT = 'My subject';
    private const BODY = 'Email body';

    /**
     * @var SegmentEmailSender
     */
    protected $sut;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentEmailSender::class);
        $this->currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $this->messageBag = $this->getContainer()->get(MessageBagInterface::class);
        $this->translator = $this->getContainer()->get(TranslatorInterface::class);

        // determineChanger() (version-history entry) reads the current user.
        $this->currentUserService->setUser(UserFactory::createOne()->_real());
    }

    public function testSendSegmentMailWithInvalidCCEmail(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->currentProcedureService->setProcedure($procedure);

        $isEmailSent = $this->sut->sendSegmentsMail(
            [$segment->getId()],
            $procedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            self::BODY,
            'not-formatted-email',
            null
        );

        $this->assertFailedWithSyntaxError($isEmailSent);
    }

    public function testSendSegmentMailWithInvalidRecipientEmail(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->currentProcedureService->setProcedure($procedure);

        $isEmailSent = $this->sut->sendSegmentsMail(
            [$segment->getId()],
            $procedure->getId(),
            'not-a-valid-email',
            self::SUBJECT,
            self::BODY,
            null,
            null
        );

        $this->assertFailedWithSyntaxError($isEmailSent);
    }

    public function testSendSegmentMailWithNonExistentSegmentFails(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $this->currentProcedureService->setProcedure($procedure);

        $isEmailSent = $this->sut->sendSegmentsMail(
            ['00000000-0000-0000-0000-000000000000'],
            $procedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            self::BODY,
            null,
            null
        );

        $this->assertFailedWithSyntaxError($isEmailSent);
    }

    public function testSendSegmentMailFromDifferentProcedureFails(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $otherProcedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->currentProcedureService->setProcedure($otherProcedure);

        // Segment belongs to $procedure, but we claim it is part of $otherProcedure.
        $isEmailSent = $this->sut->sendSegmentsMail(
            [$segment->getId()],
            $otherProcedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            self::BODY,
            null,
            null
        );

        $this->assertFailedWithSyntaxError($isEmailSent);
    }

    public function testSendSegmentMailSucceeds(): void
    {
        $this->createSegmentMailTemplate();
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->currentProcedureService->setProcedure($procedure);

        $mailsBefore = $this->countMails();

        $isEmailSent = $this->sut->sendSegmentsMail(
            [$segment->getId()],
            $procedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            self::BODY,
            'valid-cc@test.de',
            'reply@test.de'
        );

        static::assertTrue($isEmailSent);

        // Exactly one confirmation message.
        $confirmMessages = $this->messageBag->getConfirmMessages();
        static::assertCount(1, $confirmMessages);
        static::assertSame(
            $this->translator->trans('confirm.segment.sent'),
            $confirmMessages->get(0)->getText()
        );

        // Exactly one mail was queued, carrying the reply-to.
        static::assertSame($mailsBefore + 1, $this->countMails());
        static::assertSame('reply@test.de', $this->getLatestMail()['_ms_reply_to']);
        static::assertSame('dm_schlussmitteilung', $this->getLatestMail()['_ms_mt_template']);

        // A version-history entry was written for the segment.
        static::assertSame(1, $this->countSentViaMailEntriesFor($segment));
    }

    public function testSendSegmentMailBulkSendsOneMailAndOneHistoryEntryPerSegment(): void
    {
        $this->createSegmentMailTemplate();
        $procedure = ProcedureFactory::createOne()->_real();
        $segments = [
            SegmentFactory::createOne(['procedure' => $procedure])->_real(),
            SegmentFactory::createOne(['procedure' => $procedure])->_real(),
            SegmentFactory::createOne(['procedure' => $procedure])->_real(),
        ];
        $this->currentProcedureService->setProcedure($procedure);

        $mailsBefore = $this->countMails();

        $isEmailSent = $this->sut->sendSegmentsMail(
            array_map(static fn (Segment $segment): string => $segment->getId(), $segments),
            $procedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            self::BODY,
            null,
            null
        );

        static::assertTrue($isEmailSent);

        // One combined mail for the whole batch.
        static::assertSame($mailsBefore + 1, $this->countMails());

        // One version-history entry per segment.
        foreach ($segments as $segment) {
            static::assertSame(1, $this->countSentViaMailEntriesFor($segment));
        }
    }

    public function testSendSegmentMailObscuresPersonalDataInBody(): void
    {
        $this->createSegmentMailTemplate();
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->currentProcedureService->setProcedure($procedure);

        $body = 'Public info <dp-obscure>SECRET-NAME</dp-obscure> stays visible';

        $isEmailSent = $this->sut->sendSegmentsMail(
            [$segment->getId()],
            $procedure->getId(),
            self::RECIPIENT,
            self::SUBJECT,
            $body,
            null,
            null
        );

        static::assertTrue($isEmailSent);

        $content = (string) $this->getLatestMail()['_ms_content'];
        // The obscured personal data must not leak, the surrounding text stays.
        static::assertStringNotContainsString('SECRET-NAME', $content);
        static::assertStringContainsString('█', $content);
        static::assertStringContainsString('Public info', $content);
    }

    private function assertFailedWithSyntaxError(bool $isEmailSent): void
    {
        static::assertFalse($isEmailSent);

        $errorMessages = $this->messageBag->getErrorMessages();
        static::assertCount(1, $errorMessages);
        static::assertSame(
            $this->translator->trans('error.segment.send.syntax.email'),
            $errorMessages->get(0)->getText()
        );
    }

    private function createSegmentMailTemplate(): void
    {
        MailTemplateFactory::createOne([
            'label'   => 'dm_schlussmitteilung',
            'title'   => '${mailsubject}',
            'content' => '${mailbody}',
        ]);
    }

    private function countMails(): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM _mail_send')
            ->fetchOne();
    }

    private function getLatestMail(): array
    {
        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery('SELECT * FROM _mail_send ORDER BY _ms_id DESC LIMIT 1')
            ->fetchAssociative();
    }

    private function countSentViaMailEntriesFor(Segment $segment): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT COUNT(*) FROM entity_content_change WHERE entity_id = :id AND entity_field = :field',
                ['id' => $segment->getId(), 'field' => 'sentViaMail'],
            )
            ->fetchOne();
    }
}
