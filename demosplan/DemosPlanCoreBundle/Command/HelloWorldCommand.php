<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HelloWorldCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:hello-world';
    protected static $defaultDescription = 'Display a friendly hello world message';

    public function __construct(ParameterBagInterface $parameterBag)
    {
        parent::__construct($parameterBag);
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command displays a friendly hello world message with the current date.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name to greet', 'World');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Don't set up IO in quiet mode to prevent any output
        if ($output->isQuiet()) {
            return Command::SUCCESS;
        }
        
        $io = $this->setupIo($input, $output);
        $name = $input->getArgument('name');
        $currentDate = date('Y-m-d H:i:s');
        
        $io->title('DemosPlan Hello World');
        $io->success("Hello, $name!");
        $io->text("Current date and time: $currentDate");
        
        return Command::SUCCESS;
    }
}