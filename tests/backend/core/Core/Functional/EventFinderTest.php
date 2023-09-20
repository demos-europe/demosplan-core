<?php declare(strict_types=1);


namespace Tests\Core\Core\Functional;


use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class EventFinderTest extends FunctionalTestCase
{
    use CommandTesterTrait;


    public function testEventFinder(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '-p' => ['DPlanEvent'],
            '-s' => ['/srv/www/addons/vendor/demos-europe'],
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        //asserting some of the found events. Most likely this will be change, so this test needs to be adjusted.
        static::assertStringContainsString('"className": "DPlanEvent"', $output);
        static::assertStringContainsString('"matchingParent": "DPlanEvent"', $output);
        static::assertStringContainsString('"className": "RpcEvent', $output);
        static::assertStringContainsString('"className": "BeforeResourceUpdateEvent', $output);
        static::assertStringContainsString('"className": "ProcedureEditedEvent', $output);
        static::assertStringContainsString('"className": "PostProcedureDeletedEvent', $output);
        static::assertStringContainsString('"className": "StatementUpdatedEvent', $output);
        static::assertStringContainsString('"className": "StatementCreatedEvent', $output);
        static::assertStringContainsString('"className": "StatementActionEvent', $output);
        static::assertStringContainsString('"className": "BeforeResourceUpdateEvent', $output);
        static::assertStringContainsString('"className": "StatementActionEvent', $output);
        static::assertStringContainsString('"className": "RpcEvent', $output);
        static::assertStringContainsString('"className": "OrgaEditedEvent', $output);
        static::assertStringContainsString('"className": "GuestStatementSubmittedEvent', $output);
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:documentation:generate:demos-event-list');
    }
}
