<?php declare(strict_types=1);


namespace Tests\Core\Core\Functional;


use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class RemoveCustomerCommandTest extends FunctionalTestCase
{

    use CommandTesterTrait;

    public function testSuccessfulExecute(): void
    {
        //create via foundry: Customer, OrgaStatusInCustomer, UserRoleInCustomer, Procedure(Blueprint), County, Reports

        $commandTester = $this->getCommandTester();

        $newCustomerName = 'bge';
        $commandTester->setInputs([$newCustomerName]);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString("Customer '$newCustomerName' was successfully created", $output);
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:data:remove-customer');
    }

}
