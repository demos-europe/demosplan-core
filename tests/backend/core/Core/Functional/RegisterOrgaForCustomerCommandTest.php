<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RegisterOrgaForCustomerCommandTest extends FunctionalTestCase
{
    private const ORGA_EMAIL = 'fp-only-orga@example.test';

    public function testSuccessfulExecute(): void
    {
        $this->prepareOrgaWithEmail();
        /** @var Customer $source */
        $source = $this->fixtures->getReference(LoadCustomerData::HINDSIGHT);
        /** @var Customer $target */
        $target = $this->fixtures->getReference(LoadCustomerData::BB);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $source->getSubdomain(),
            $target->getSubdomain(),
            self::ORGA_EMAIL,
            'y',
        ]);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        self::assertStringContainsString('Orga successfully registered', $output);
        self::assertStringContainsString($target->getSubdomain(), $output);
    }

    public function testInvalidEmailExecute(): void
    {
        /** @var Customer $source */
        $source = $this->fixtures->getReference(LoadCustomerData::HINDSIGHT);
        /** @var Customer $target */
        $target = $this->fixtures->getReference(LoadCustomerData::BB);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $source->getSubdomain(),
            $target->getSubdomain(),
            'nonexistent@example.test',
        ]);

        $tester->execute([]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No Orga found for the given email', $tester->getDisplay());
    }

    public function testSourceEqualsTargetExecute(): void
    {
        /** @var Customer $source */
        $source = $this->fixtures->getReference(LoadCustomerData::HINDSIGHT);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $source->getSubdomain(),
            $source->getSubdomain(),
        ]);

        $tester->execute([]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Source and target customer must differ', $tester->getDisplay());
    }

    public function testOrgaNotInSourceExecute(): void
    {
        $this->prepareOrgaWithEmail();
        /** @var Customer $source */
        $source = $this->fixtures->getReference(LoadCustomerData::BB);
        /** @var Customer $target */
        $target = $this->fixtures->getReference(LoadCustomerData::HINDSIGHT);

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $source->getSubdomain(),
            $target->getSubdomain(),
            self::ORGA_EMAIL,
        ]);

        $tester->execute([]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('not registered in source customer', $tester->getDisplay());
    }

    public function testOrgaAlreadyInTargetExecute(): void
    {
        $orga = $this->prepareOrgaWithEmail();
        /** @var Customer $source */
        $source = $this->fixtures->getReference(LoadCustomerData::HINDSIGHT);
        /** @var Customer $target */
        $target = $this->fixtures->getReference(LoadCustomerData::BB);

        $sourceOrgaType = null;
        foreach ($orga->getStatusInCustomers() as $status) {
            if ($status->getCustomer()->getId() === $source->getId()) {
                $sourceOrgaType = $status->getOrgaType();
                break;
            }
        }
        self::assertNotNull($sourceOrgaType, 'Fixture orga should have an OrgaType in the source customer.');

        $orga->addCustomerAndOrgaType($target, $sourceOrgaType);
        $this->getEntityManager()->flush();

        $tester = $this->getCommandTester();
        $tester->setInputs([
            $source->getSubdomain(),
            $target->getSubdomain(),
            self::ORGA_EMAIL,
        ]);

        $tester->execute([]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already registered in target customer', $tester->getDisplay());
    }

    private function prepareOrgaWithEmail(): Orga
    {
        /** @var Orga $orga */
        $orga = $this->fixtures->getReference('testOrgaFPOnly');
        $orga->setEmail2(self::ORGA_EMAIL);
        $this->getEntityManager()->flush();

        return $orga;
    }

    private function getCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('dplan:data:register-orga-for-customer');

        return new CommandTester($command);
    }
}
