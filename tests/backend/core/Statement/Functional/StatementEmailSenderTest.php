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

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var StatementService
     */
    private $statementService;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    private $statement;

    private $procedure;

    private $orga;

    private $user;


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

        $isEmailSent = $this->sut->sendStatementMail($statementId, $subject, $body, $sendEmailCC, $emailAttachments);

        $this->assertFalse($isEmailSent);
        // Assert that there is exactly one error message
        $errorMessages = $this->messageBag->getErrorMessages();
        $this->assertCount(1, $errorMessages);

        //Get first error message
        $errorMessage =  $errorMessages->get(0);

        //Assert error message text
        $this->assertEquals( $this->translator->trans('error.statement.final.send.syntax.email.cc'), $errorMessage->getText());

    }

    private function setupInitialData(): void {
        $this->orga = OrgaFactory::createOne(['email2' => 'hello@partipation-email.de']);
        $this->procedure = ProcedureFactory::createOne();

        $this->user = UserFactory::createOne();
        $this->user->setOrga($this->orga->_real());

        $this->orga->addUser($this->user->_real());

        $this->user->_save();
        $this->orga->_save();


        $this->statement = StatementFactory::createOne(['procedure' => $this->procedure, 'user' => $this->user]);
    }

    public function testSendStatementMailToInstitutionOnly(): void
    {
        $this->setupInitialData();

        $this->currentUserService->setUser($this->user->_real());
        $this->currentProcedureService->setProcedure($this->procedure->_real());
        $this->assertConfirmationMessages($this->statement->getId(), 'institution_only');

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

        $this->setupStatementMeta($statement, '', 'hola-orga@test.de');
        $this->currentUserService->setUser($user->_real());
        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->assertConfirmationMessages( $statement->getId(), 'institution_only');

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

        $this->setupStatementMeta($statement, 'conga-parrot@test-de', '');

        $this->currentUserService->setUser($user->_real());
        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->assertConfirmationMessages($statement->getId(), 'institution_and_coordination');

    }

    private function assertConfirmationMessages($statementId, $sentToConfirmMessageKey): void {

        // Create a mail template with the label 'dm_schlussmitteilung' because it is needed later in the sendStatementMail method
        $mailTemplate = MailTemplateFactory::createOne(['label' => 'dm_schlussmitteilung']);

        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'hola@test.de';
        $emailAttachments = [];

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

    private function setupStatementMeta($statement, $statementSubmitterEmail, $statementMetaOrgaEmail): void {
        $statementSubmitter = UserFactory::createOne(['email' => $statementSubmitterEmail]);
        $statementMeta = StatementMetaFactory::createOne(['statement' => $statement, 'orgaEmail' => $statementMetaOrgaEmail]);
        $statementMeta->setSubmitUId($statementSubmitter->getId());
        $statementMeta->_save();
    }

}
