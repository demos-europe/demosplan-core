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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementEmailSender;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementEmailSender::class);

        $this->mailService = $this->getContainer()->get(MailService::class);
        $this->statementService = $this->getContainer()->get(StatementService::class);
        $this->currentProcedureService = $this->getContainer()->get(CurrentProcedureService::class);
        $this->userService = $this->getContainer()->get(UserService::class);
       // $this->messageBag = $this->getContainer()->get(MessageBagInterface::class);


    }

    public function testSendStatementMailWithValidInput(): void
    {


        $procedure = ProcedureFactory::createOne();
        $statement = StatementFactory::createOne(['procedure' => $procedure]);
        $user = UserFactory::createOne();
        $this->currentUserService->setUser($user->_real());


        $rParams = [
            'request' => [
                'send_body' => 'This is the body of the email.',
                'send_title' => 'Email Subject',
                'ident' => $statement->getId(),
                'send_emailCC' => 'cc@example.com;cc2@example.com'
            ]
        ];

        $this->currentProcedureService->setProcedure($procedure->_real());

        $this->sut->sendStatementMail($rParams);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }


}
