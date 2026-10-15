<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Enqueue\ElasticaBundle\Persister\QueuePagerPersister;
use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Populate elasticsearch index with multiple Workers.
 *
 * @see https://github.com/FriendsOfSymfony/FOSElasticaBundle/blob/master/doc/cookbook/speed-up-populate-command.md
 */
#[AsCommand(name: 'dplan:elasticsearch:populate', description: 'Run elasticsearch populate with many workers')]
class ElasticsearchPopulateCommand extends CoreCommand
{
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
        $io = new SymfonyStyle($input, $output);

        $this->elasticsearchIndexingPoolSize = (int) $this->parameterBag->get('elasticsearch_populate_workers');
        if (null !== $input->getOption('workers')) {
            $this->elasticsearchIndexingPoolSize = (int) $input->getOption('workers');
        }

        // The multi-worker variant requires the enqueue elastica bundle and spawns N worker
        // processes, each of which boots a kernel per page. In prod, where page payloads are
        // large and memory is a hard limit, the in-place persister is the safer default.
        $useQueuePersister = $this->elasticsearchIndexingPoolSize > 1
            && class_exists(QueuePagerPersister::class);

        if (!$useQueuePersister && !class_exists(InPlacePagerPersister::class)) {
            $io->error('No pager persister available for fos:elastica:populate.');

            return Command::FAILURE;
        }

        $output->writeln('Reset elasticsearch index');

        Batch::create($this->getApplication(), $output)
            ->add('fos:elastica:reset')
            ->run();

        if ($useQueuePersister) {
            // Start the consumers only after the reset, right before populating. Spawning them
            // earlier would boot a second, leaked set of worker processes (see startWorkers()).
            $this->startWorkers($output);
        }

        $pagerPersister = $useQueuePersister
            ? QueuePagerPersister::NAME
            : InPlacePagerPersister::NAME;

        $output->writeln(sprintf(
            'Start populating the Elasticsearch index (%s persister)',
            $pagerPersister
        ));

        // display some progress bar to indicate process advancement
        $progressBar = new ProgressBar($output);
        $progressBar->setRedrawFrequency(10);

        $populateProcess = new Process(
            [
                \PHP_BINARY,
                $this->getCurrentProjectConsole(),
                'fos:elastica:populate',
                '--pager-persister='.$pagerPersister,
                '--no-debug',
                '--env=prod',
            ],
            DemosPlanPath::getRootPath(),
            ['ACTIVE_PROJECT' => $this->getActiveProject()]
        );

        $populateProcess->disableOutput();
        $populateProcess->setTimeout(0);

        $populateProcess->run(static function () use ($progressBar) {
            $progressBar->advance();
        });

        $progressBar->finish();

        // add another newline, $progressBar->finish() sometimes messes up the output
        $output->writeln('');

        if ($useQueuePersister) {
            $this->stopWorkers($output);
        }

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
                DemosPlanPath::getProjectPath(),
                ['ACTIVE_PROJECT' => $this->getActiveProject()]
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

    protected function startWorkers(OutputInterface $output): void
    {
        $output->writeln('Starting index workers');

        $this->startIndexWorker();
    }

    private function getCurrentProjectConsole(): string
    {
        return DemosPlanPath::getRootPath('bin/console');
    }

    private function getActiveProject(): string
    {
        if (!$this->getApplication() instanceof ConsoleApplication) {
            throw new RuntimeException('Cannot run this command without an application');
        }
        /** @var DemosPlanKernel $kernel */
        $kernel = $this->getApplication()->getKernel();

        return $kernel->getActiveProject();
    }
}
