<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Illuminate\Support\Collection;

/**
 * Populate elasticsearch index with multiple Workers.
 *
 * @see https://github.com/FriendsOfSymfony/FOSElasticaBundle/blob/master/doc/cookbook/speed-up-populate-command.md
 */
class ElasticsearchPopulateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:elasticsearch:populate';
    protected static $defaultDescription = 'Run elasticsearch populate with many workers';

    protected $elasticsearchIndexingPoolSize;

    /**
     * @var Collection|Process[]
     */
    protected $indexingProcesses;

    public function configure(): void
    {
        $this->addOption('workers', 'j', InputOption::VALUE_OPTIONAL, 'Number of workers');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = DemosPlanPath::getProjectPath();

        $this->elasticsearchIndexingPoolSize = $this->parameterBag->get('elasticsearch_populate_workers');
        if (null !== $input->getOption('workers')) {
            $this->elasticsearchIndexingPoolSize = $input->getOption('workers');
        }

        $this->startIndexWorker();

        $output->writeln('Reset elasticsearch index');

        Batch::create($this->getApplication(), $output)
            ->add('fos:elastica:reset')
            ->run();

        $this->startWorkers($input, $output);

        $output->writeln('Start populating the Elasticsearch index');

        // display some progress bar to indicate process advancement
        $progressBar = new ProgressBar($output);
        $progressBar->setRedrawFrequency(10);

        $populateProcess = new Process(
            [
                \PHP_BINARY,
                $this->getCurrentProjectConsole(),
                'fos:elastica:populate',
                '--pager-persister=queue',
                '--no-debug',
                '--env=prod',
            ],
            DemosPlanPath::getRootPath()
        );

        $populateProcess->disableOutput();
        $populateProcess->setTimeout(0);

        $populateProcess->run(static function () use ($progressBar) {
            $progressBar->advance();
        });

        $progressBar->finish();

        // add another newline, $progressBar->finish() sometimes messes up the output
        $output->writeln('');

        $this->stopWorkers($output);
        $output->writeln('Elasticsearch populate finished');

        return Command::SUCCESS;
    }

    protected function startIndexWorker(): void
    {
        $this->indexingProcesses = collect();

        for ($i = 0; $i < $this->elasticsearchIndexingPoolSize; ++$i) {
            $indexProcess = new Process(
                [
                    \PHP_BINARY,
                    $this->getCurrentProjectConsole(),
                    'enqueue:consume',
                    '--setup-broker',
                    '--no-debug',
                    '--env=prod',
                ],
                DemosPlanPath::getProjectPath()
            );

            $indexProcess->setIdleTimeout(0);
            $indexProcess->setTimeout(0);
            $indexProcess->setTty(Process::isTtySupported());
            $indexProcess->start();

            $this->indexingProcesses->push($indexProcess);
        }
    }

    protected function stopWorkers(OutputInterface $output): void
    {
        $output->writeln('Stopping indexing workers');
        $this->indexingProcesses->each(static function (Process $process) {
            $process->stop();
        });
    }

    protected function startWorkers(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Starting index workers');

        $this->elasticsearchIndexingPoolSize = $this->parameterBag->get(
            'elasticsearch_populate_workers'
        );
        if (null !== $input->getOption('workers')) {
            $this->elasticsearchIndexingPoolSize = $input->getOption('workers');
        }

        $this->startIndexWorker();
    }

    private function getCurrentProjectConsole(): string
    {
        if (null === $this->getApplication()) {
            throw new RuntimeException('Cannot run this command without an application');
        }
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();

        return DemosPlanPath::getRootPath('bin/'.$kernel->getActiveProject());
    }
}
