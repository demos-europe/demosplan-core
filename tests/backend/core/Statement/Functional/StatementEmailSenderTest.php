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
namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\MailTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementEmailSender;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class StatementEmailSenderTest extends FunctionalTestCase {

    /**
     * @var StatementEmailSender
     */
    protected $sut;

    private $statementEmailSender;
    private $mailService;
    private $statementService;
    private $currentProcedureService;
    private $userService;
    private $messageBag;

    private $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementEmailSender::class);

        $this->mailService = $this->getContainer()->get(MailService::class);
        $this->statementService = $this->getContainer()->get(StatementService::class);
        $this->currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $this->userService = $this->getContainer()->get(UserService::class);
        $this->messageBag = $this->getContainer()->get(MessageBagInterface::class);
        $this->translator = $this->getContainer()->get(TranslatorInterface::class);


    }

    public function testSendStatementMailWithInvalidCCEmail(): void
    {
        $procedure = ProcedureFactory::createOne();
        $statement = StatementFactory::createOne(['procedure' => $procedure]);
        $user = UserFactory::createOne();
        $this->currentUserService->setUser($user->_real());

        $statementId = $statement->getId();
        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'not-formated-email';
        $emailAttachments = [];

        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->sut->sendStatementMail($statementId, $subject, $body, $sendEmailCC, $emailAttachments);

        // Assert that there is exactly one error message
        $errorMessages = $this->messageBag->getErrorMessages();
        $this->assertCount(1, $errorMessages);

        //Get first error message
        $errorMessage =  $errorMessages->get(0);

        //Assert error message text
        $this->assertEquals( $this->translator->trans('error.statement.final.send.syntax.email.cc'), $errorMessage->getText());

    }

    public function testSendStatementMailToInstitutionOnly(): void
    {
        $orga = OrgaFactory::createOne(['email2' => 'hello@partipation-email.de']);
        $procedure = ProcedureFactory::createOne();

        $user = UserFactory::createOne();
        $user->setOrga($orga->_real());

        $orga->addUser($user->_real());

        $user->_save();
        $orga->_save();


        $statement = StatementFactory::createOne(['procedure' => $procedure, 'user' => $user]);

        $this->currentUserService->setUser($user->_real());


        $statementId = $statement->getId();
        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'hola@test.de';
        $emailAttachments = [];

        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->assertConfirmationMessages($statementId, $subject, $body, $sendEmailCC, $emailAttachments, 'institution_only');


    }

    public function testSendStatementMailToStatementMetaOrgaEmail(): void
    {
        $orga = OrgaFactory::createOne(['email2' => 'hello@partipation-email.de']);
        $procedure = ProcedureFactory::createOne();

        $user = UserFactory::createOne();
        $user->setOrga($orga->_real());

        $orga->addUser($user->_real());

        $user->_save();
        $orga->_save();


        $statement = StatementFactory::createOne(['procedure' => $procedure, 'user' => $user]);

        $statementSubmitter = UserFactory::createOne();
        $statementMeta = StatementMetaFactory::createOne(['statement' => $statement, 'orgaEmail' => 'hola-orga@test.de']);
        $statementMeta->setSubmitUId($statementSubmitter->getId());
        $statementMeta->_save();


        $this->currentUserService->setUser($user->_real());


        $statementId = $statement->getId();
        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'hola@test.de';
        $emailAttachments = [];

        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->assertConfirmationMessages($statementId, $subject, $body, $sendEmailCC, $emailAttachments, 'institution_only');


    }

    public function testSendStatementMailToInstitutionAndCoordination(): void
    {
        $orga = OrgaFactory::createOne(['email2' => 'hello@partipation-email.de']);
        $procedure = ProcedureFactory::createOne();

        $user = UserFactory::createOne(['email' => 'party-parrot@test-de']);
        $user->setOrga($orga->_real());

        $orga->addUser($user->_real());

        $user->_save();
        $orga->_save();

        $statement = StatementFactory::createOne(['procedure' => $procedure, 'user' => $user]);

        $statementSubmitter = UserFactory::createOne(['email' => 'conga-parrot@test-de']);
        $statementMeta = StatementMetaFactory::createOne(['statement' => $statement, 'orgaEmail' => '']);
        $statementMeta->setSubmitUId($statementSubmitter->getId());
        $statementMeta->_save();


        $this->currentUserService->setUser($user->_real());


        $statementId = $statement->getId();
        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'hola@test.de';
        $emailAttachments = [];

        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->assertConfirmationMessages($statementId, $subject, $body, $sendEmailCC, $emailAttachments, 'institution_and_coordination');



    }

    private function assertConfirmationMessages($statementId, $subject, $body, $sendEmailCC, $emailAttachments, $sentToConfirmMessageKey): void {

        // Create a mail template with the label 'dm_schlussmitteilung' because it is needed later in the sendStatementMail method
        $mailTemplate = MailTemplateFactory::createOne(['label' => 'dm_schlussmitteilung']);

        $isEmailSent = $this->sut->sendStatementMail($statementId, $subject, $body, $sendEmailCC, $emailAttachments);

        $this->assertTrue($isEmailSent);

        // Retrieve confirmation messages from the message bag
        $confirmMessages = $this->messageBag->getConfirmMessages();

        // Assert that there are exactly two confirmation messages
        $this->assertCount(2, $confirmMessages);

        // Define the expected confirmation messages
        $expectedMessages = [
            $this->translator->trans('confirm.statement.final.sent', ['sent_to' => $sentToConfirmMessageKey]),
            $this->translator->trans('confirm.statement.final.sent.emailCC')
        ];

        // Loop through the expected messages and assert that they match the actual confirmation messages
        foreach ($expectedMessages as $index => $expectedMessage) {
            $successMessage = $confirmMessages->get($index);
            $this->assertEquals($expectedMessage, $successMessage->getText());
        }
    }

}
