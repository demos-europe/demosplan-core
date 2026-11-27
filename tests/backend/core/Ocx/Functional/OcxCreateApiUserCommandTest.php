<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Ocx\Functional;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Ocx\OcxCreateApiUserCommand;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class OcxCreateApiUserCommandTest extends FunctionalTestCase
{
    private ?CommandTester $commandTester = null;

    protected function setUp(): void
    {
        parent::setUp();

        $command = $this->getContainer()->get(OcxCreateApiUserCommand::class);
        $application = new ConsoleApplication(self::$kernel, false);
        $application->add($command);
        $this->commandTester = new CommandTester($command);
    }

    public function testCreatesRolelessUserInTheOrganisationAndPrintsAWorkingToken(): void
    {
        $orga = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE)->getOrga();
        $customer = $orga->getCustomers()->first();

        $this->commandTester->execute(['orgaId' => $orga->getId(), '--customer' => $customer->getSubdomain()]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertSame(1, preg_match('/(ocx-api-[0-9a-f]{12}@ocx\.invalid)/', $this->commandTester->getDisplay(), $login));
        self::assertSame(1, preg_match('/^(dplan_ocx_[a-z2-9]{44})$/m', $this->commandTester->getDisplay(), $token));
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['login' => $login[1]]);
        self::assertInstanceOf(User::class, $user);
        self::assertSame($orga->getId(), $user->getOrga()?->getId());
        self::assertSame([], $user->getDplanRolesArray());
        self::assertSame($user->getId(), $this->getContainer()->get(OcxApiTokenService::class)->findActiveToken($token[1])?->getUser()->getId());
    }

    public function testUnknownOrganisationFails(): void
    {
        $status = $this->commandTester->execute(['orgaId' => '00000000-0000-4000-8000-000000000000']);

        self::assertSame(Command::FAILURE, $status);
    }
}
