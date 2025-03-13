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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementEmailSender;

use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class StatementEmailSenderTest extends FunctionalTestCase {

    /**
     * @var StatementEmailSender
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

    private Statement|Proxy|null $statement;

    private Procedure|Proxy|null $procedure;

    private Orga|Proxy|null $orga;

    private User|Proxy|null  $user;


    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementEmailSender::class);
        $this->currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
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

    public function testSendStatementMailToPublicStatement(): void
    {
        $this->setupInitialData(publicStatement: Statement::EXTERNAL, feedback: 'email');
        $this->setupStatementMeta('', 'hola-orga@test.de');
        $this->assertConfirmationMessages('citizen_only');

    }

    public function testSendStatementMailToInstitutionOnly(): void
    {
        $this->setupInitialData();
        $this->assertConfirmationMessages('institution_only');

    }

    public function testSendStatementMailToStatementMetaOrgaEmail(): void
    {
        $this->setupInitialData();
        $this->setupStatementMeta('', 'hola-orga@test.de');
        $this->assertConfirmationMessages('institution_only');

    }

    public function testSendStatementMailToInstitutionAndCoordination(): void
    {
        $this->setupInitialData('party-parrot@test-de');
        $this->setupStatementMeta('conga-parrot@test-de', '');
        $this->assertConfirmationMessages('institution_and_coordination');

    }

    private function assertConfirmationMessages(string $sentToConfirmMessageKey): void {

        // Create a mail template with the label 'dm_schlussmitteilung' because it is needed later in the sendStatementMail method
        $mailTemplate = MailTemplateFactory::createOne(['label' => 'dm_schlussmitteilung']);

        $subject = 'My subject';
        $body =' Email body';
        $sendEmailCC = 'hola@test.de';
        $emailAttachments = [];

        $isEmailSent = $this->sut->sendStatementMail($this->statement->getId(), $subject, $body, $sendEmailCC, $emailAttachments);

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

    private function setupStatementMeta(string $statementSubmitterEmail, string $statementMetaOrgaEmail): void {
        $statementSubmitter = UserFactory::createOne(['email' => $statementSubmitterEmail]);
        $statementMeta = StatementMetaFactory::createOne(['statement' => $this->statement, 'orgaEmail' => $statementMetaOrgaEmail]);
        $statementMeta->setSubmitUId($statementSubmitter->getId());
        $statementMeta->_save();
    }

    private function setupInitialData(string $userEmail = 'myemail@test.de', string $publicStatement = Statement::INTERNAL, string $feedback = ''): void {
        $this->orga = OrgaFactory::createOne(['email2' => 'hello@partipation-email.de']);
        $this->procedure = ProcedureFactory::createOne();

        $this->user = UserFactory::createOne(['email' => $userEmail, 'password' => 'xxx']);
        $this->user->setOrga($this->orga->_real());

        $this->orga->addUser($this->user->_real());

        $this->user->_save();
        $this->orga->_save();


        $this->statement = StatementFactory::createOne(['procedure' => $this->procedure, 'user' => $this->user, 'publicStatement' => $publicStatement, 'feedback' => $feedback]);

        $this->currentUserService->setUser($this->user->_real());
        $this->currentProcedureService->setProcedure($this->procedure->_real());
    }

}
