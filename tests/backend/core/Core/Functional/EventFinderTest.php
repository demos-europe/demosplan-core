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

use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class EventFinderTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    public function testEventFinder(): void
    {
        // this test can only be run when an addon folder exists
        if (!is_dir('/srv/www/addons/vendor/demos-europe')) {
            static::markTestSkipped('No addon folder found');
        }

        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '-p' => ['DPlanEvent'],
            '-s' => ['/srv/www/addons/vendor/demos-europe/demosplan-addon'],
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        // asserting some of the found events. Most likely this will be change, so this test needs to be adjusted.
        static::assertStringContainsString('"className": "DPlanEvent"', $output);
        static::assertStringContainsString('"matchingParent": "DPlanEvent"', $output);
        static::assertStringContainsString('"className": "RpcEvent', $output);
        static::assertStringContainsString('"className": "ProcedureEditedEvent', $output);
        static::assertStringContainsString('"className": "PostProcedureDeletedEvent', $output);
        static::assertStringContainsString('"className": "StatementUpdatedEvent', $output);
        static::assertStringContainsString('"className": "StatementCreatedEvent', $output);
        static::assertStringContainsString('"className": "StatementActionEvent', $output);
        static::assertStringContainsString('"className": "StatementActionEvent', $output);
        static::assertStringContainsString('"className": "RpcEvent', $output);
        static::assertStringContainsString('"className": "OrgaEditedEvent', $output);
        static::assertStringContainsString('"className": "GuestStatementSubmittedEvent', $output);
    }

    private function getCommandTester(): CommandTester
    {
        return $this->getCommandTesterByName(self::bootKernel(), 'dplan:documentation:generate:event-list');
    }
}
