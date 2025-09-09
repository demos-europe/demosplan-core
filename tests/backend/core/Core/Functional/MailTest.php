<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\MailTemplate;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use Exception;
use Tests\Base\FunctionalTestCase;

class MailTest extends FunctionalTestCase
{
    /**
     * @var MailService
     */
    protected $sut;

    /**
     * @var MailSend
     */
    protected $testMailSend;
    /**
     * @var MailTemplate
     */
    protected $testMailTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        // get testdata:
        $this->testMailSend = $this->fixtures->getReference('testMailSend');
        $this->testMailTemplate = $this->fixtures->getReference('testMailTemplate');

        $this->sut = self::getContainer()->get(MailService::class);
    }

    public function testGetSingleMail()
    {
        $expectedMail = $this->testMailSend;
        $mail = $this->sut->getMailFromQueue($expectedMail->getId());
        static::assertEquals($expectedMail->getId(), $mail->getId());
        static::assertEquals($expectedMail->getTo(), $mail->getTo());
    }

    public function testSaveNewMail()
    {
        $template = $this->testMailTemplate->getLabel();
        $lang = 'de_DE';
        $to = 'sendmeto@mail.org';
        $from = 'sentfrom@mail.org';
        $cc = 'sendtocc@mail.org';
        $bcc = 'sendtobcc@mail.org';
        $scope = 'extern';
        $variables = ['mailtitle' => 'my Subject', 'mailbody' => 'my body'];

        $mailList = $this->sut->getMailsToSend();
        $mailIds = [];
        foreach ($mailList as $mail) {
            $mailIds[] = $mail->getId();
        }

        static::assertNotNull($mailList);
        $initialCount = count($mailList);

        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            $from,
            $cc,
            $bcc,
            $scope,
            $variables
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        static::assertCount($initialCount + 1, $mailListAfterInsert);

        $insertedMail = null;
        foreach ($mailListAfterInsert as $mail) {
            if (!in_array($mail->getId(), $mailIds)) {
                $insertedMail = $mail;
            }
        }

        static::assertEquals('my body', $insertedMail->getContent());
        static::assertEquals('Testtemplate Title my Subject', $insertedMail->getTitle());
        static::assertEquals($to, $insertedMail->getTo());
        static::assertEquals($from, $insertedMail->getFrom());
        static::assertEquals($cc, $insertedMail->getCc());
        static::assertEquals($bcc, $insertedMail->getBcc());
    }

    public function testSaveNewMailMultipleAddresses()
    {
        $template = $this->testMailTemplate->getLabel();
        $lang = 'de_DE';
        $to = ['sendmeto@mail.org', 'sendmetoo@mail.org'];
        $from = 'sentfrom@mail.org';
        $cc = ['sendtocc@mail.org', 'sendtocc2@mail.org'];
        $bcc = ['sendtobcc@mail.org', 'sendtobcc@mail.org'];
        $scope = 'extern';

        $mailList = $this->sut->getMailsToSend();
        $initialCount = count($mailList);

        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            $from,
            $cc,
            $bcc,
            $scope
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        static::assertCount($initialCount + 1, $mailListAfterInsert);

        $insertedMail = $mailListAfterInsert[0];
        static::assertEquals(implode(', ', $to), $insertedMail->getTo());
        static::assertEquals($from, $insertedMail->getFrom());
        static::assertEquals(implode(', ', $cc), $insertedMail->getCc());
        static::assertEquals(implode(', ', $bcc), $insertedMail->getBcc());
    }

    public function testSetSystemEmailOnEmptyFrom()
    {
        $template = $this->testMailTemplate->getLabel();
        $lang = 'de_DE';
        $to = 'sendmeto@mail.org';
        $cc = 'sendtocc@mail.org';
        $bcc = 'sendtobcc@mail.org';
        $scope = 'extern';

        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            '',
            $cc,
            $bcc,
            $scope
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        $insertedMail = $mailListAfterInsert[0];
        static::assertEquals(self::getContainer()->getParameter('email_system'), $insertedMail->getFrom());
    }

    public function testEmptyBcc()
    {
        $template = $this->testMailTemplate->getLabel();
        $lang = 'de_DE';
        $from = 'sentfrom@mail.org';
        $to = 'sendmeto@mail.org';
        $cc = 'sendtocc@mail.org';
        $bcc = '';
        $scope = 'extern';

        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            $from,
            $cc,
            $bcc,
            $scope
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        $insertedMail = $mailListAfterInsert[0];
        static::assertEquals('', $insertedMail->getBcc());
    }

    public function testTemplateEntry()
    {
        $template = $this->testMailTemplate->getLabel();
        $lang = 'de_DE';
        $from = 'sentfrom@mail.org';
        $to = 'sendmeto@mail.org';
        $cc = 'sendtocc@mail.org';
        $bcc = '';
        $scope = 'extern';

        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            $from,
            $cc,
            $bcc,
            $scope
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        $insertedMail = $mailListAfterInsert[0];
        static::assertEquals($this->testMailTemplate->getLabel(), $insertedMail->getTemplate());
    }

    public function testCheckEMail()
    {
        $validAdresses = [
            'yo@mama.com',
            'yogibär@yellowstone.nationalpark.com',
            'störtebeker@bier.de',
            'über@cool.cc',
            'вовлечённость@wodka.ru',
        ];

        $invalidAdresses = [
            'foobar',
            'deine@mudder',
            'www.foobar.de',
            'fooobar.de',
            '"nachname, vorname" <foo@bar.de>',
            '"vorname nachname" <foobar@bla.de>',
        ];

        foreach ($validAdresses as $valid) {
            static::assertEquals($valid, $this->sut->checkEMail($valid));
        }
        foreach ($invalidAdresses as $invalid) {
            static::assertNull($this->sut->checkEMail($invalid));
        }
    }

    public function testSetSubjectPrefix()
    {
        $template = 'dm_toebeinladung';
        $lang = 'de_DE';
        $to = 'sendmeto@mail.org';
        $cc = 'sendtocc@mail.org';
        $bcc = 'sendtobcc@mail.org';
        $scope = 'extern';
        $variables = ['mailsubject' => 'my Subject Testmail', 'mailbody' => 'my body Subjecttestmail'];

        // Test empty prefix
        $subjectPrefix = $this->getContainer()->getParameter('email_subject_prefix');
        $this->sut->sendMail(
            $template,
            $lang,
            $to,
            '',
            $cc,
            $bcc,
            $scope,
            $variables
        );

        $mailListAfterInsert = $this->sut->getMailsToSend();
        $insertedMail = $mailListAfterInsert[0];
        // How to reliably test parameter-Variables?
        if ('' === $subjectPrefix) {
            static::assertEquals(
                $variables['mailsubject'],
                $insertedMail->getTitle()
            );
        } else {
            static::assertTrue(
                0 === stripos(
                    $insertedMail->getTitle(),
                    $this->getContainer()->getParameter('email_subject_prefix')
                )
            );
        }
    }

    public function testSendMailException()
    {
        $this->expectException(Exception::class);
        $this->sut->sendMail('', '', '', '', '', '', '', '');
    }

    public function testGetMailsToSend()
    {
        $mailList = $this->sut->getMailsToSend();
        static::assertCount(2, $mailList);
    }

    public function testSendEmailFromQueue()
    {
        $this->sut->sendMailsFromQueue();

        // no exception was thrown
        self::assertTrue(true);
    }

    public function testConvertHtmlToCustomMarkdown()
    {
        $array = [
            [
                '<h1>title</h1>',
                '# title',
            ],
            [
                '<b>bold</b>',
                '**bold**',
            ],
            [
                '<strong>strong</strong>',
                '**strong**',
            ],
            [
                '<u>underline</u>',
                '<editor.underline>underline</editor.underline>',
            ],
            [
                '<s>strikethrough</s>',
                '<durchgestrichen>strikethrough</durchgestrichen>',
            ],
            [
                '<ul><li>list item</li></ul>',
                '- list item',
            ],
        ];
        foreach ($array as $comparison) {
            $markdown = $this->sut->convertHtmlToCustomMarkdown($comparison[0]);

            $this::assertEquals($comparison[1], $markdown);
        }
    }
}
