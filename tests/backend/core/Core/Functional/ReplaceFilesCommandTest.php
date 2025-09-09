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

use demosplan\DemosPlanCoreBundle\Command\Data\ReplaceFilesCommand;
use demosplan\DemosPlanCoreBundle\Entity\File;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class ReplaceFilesCommandTest extends FunctionalTestCase
{
    /**
     * @var ReplaceFilesCommand
     */
    protected $sut;

    public function testExecute(): void
    {
        self::markSkippedForCIIntervention();

        $countOfFiles = $this->countEntries(File::class);

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find(ReplaceFilesCommand::$defaultName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--hostname' => '',
            '--dry-run'  => true,
            'directory'  => 'foo',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('Files found: '.$countOfFiles, $output);
    }
}
