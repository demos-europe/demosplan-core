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
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Populate elasticsearch index with multiple Workers.
 *
 * @see https://github.com/FriendsOfSymfony/FOSElasticaBundle/blob/master/doc/cookbook/speed-up-populate-command.md
 */
class ElasticsearchPopulateCommand extends CoreCommand
{
    private const ENVPARAM = '--env=';
    protected static $defaultName = 'dplan:elasticsearch:populate';
    protected static $defaultDescription = 'Run elasticsearch populate with many workers';

    protected int $elasticsearchIndexingPoolSize;

    /**
     * @var Collection|Process[]
     */
    protected $indexingProcesses;
    protected string $environment = 'prod';
    protected string $queueName = 'async_elastica';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addOption('workers', 'j', InputOption::VALUE_OPTIONAL, 'Number of workers');
        $this->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue name to use', 'async_elastica');
    }

    /**
     * Get the number of messages in the queue
     */
    private function getQueueMessageCount(): int
    {
        try {
            // For Doctrine transport
            $queueTable = 'messenger_messages';
            $queueName = 'default';

            $sql = "SELECT COUNT(*) as count FROM $queueTable WHERE queue_name = :queue_name";
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery(['queue_name' => $queueName]);

            return (int)$result->fetchOne();
        } catch (\Exception $e) {
            // If there's an error, return 0
            $this->logger->error('Error counting queue messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Delete all messages from the queue
     */
    private function clearQueueMessages(): bool
    {
        try {
            // For Doctrine transport
            $queueTable = 'messenger_messages';
            $queueName = 'default';

            $sql = "DELETE FROM $queueTable WHERE queue_name = :queue_name";
            $stmt = $this->connection->prepare($sql);
            $stmt->executeStatement(['queue_name' => $queueName]);

            return true;
        } catch (\Exception $e) {
            // If there's an error, log it and return false
            $this->logger->error('Error deleting queue messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Initialize command settings
        $this->initializeSettings($input, $output);

        // Reset Elasticsearch index (required before populating)
        if (!$this->resetElasticsearchIndex($output)) {
            return Command::FAILURE;
        }

        // Clear existing queue messages if any
        $this->clearExistingQueueMessages($output);

        // Queue indexing tasks using FOSElasticaBundle's populate command
        if (!$this->queueIndexingTasks($output)) {
            return Command::FAILURE;
        }

        // Start worker processes to consume the tasks
        $this->startIndexWorker($output);

        // Wait a moment to let messages be populated in the queue
        sleep(2);

        // Monitor progress of queue processing
        $this->monitorQueueProgress($output);

        // Stop workers and clean up
        sleep(2); // Give workers a moment to finish
        $this->stopWorkers($output);

        $output->writeln('Elasticsearch populate finished');
        $this->logger->info('Elasticsearch populate command completed');

        return Command::SUCCESS;
    }

    /**
     * Initialize command settings from input options
     */
    private function initializeSettings(InputInterface $input, OutputInterface $output): void
    {
        $this->elasticsearchIndexingPoolSize = (int)$this->parameterBag->get('elasticsearch_populate_workers');
        if (null !== $input->getOption('workers')) {
            $this->elasticsearchIndexingPoolSize = $input->getOption('workers');
        }

        // Set environment and queue name from input options
        $this->environment = $input->getOption('env') ?? 'prod';
        $this->queueName = $input->getOption('queue');

        $output->writeln(sprintf(
            'Using environment: %s, queue: %s, workers: %d',
            $this->environment,
            $this->queueName,
            $this->elasticsearchIndexingPoolSize
        ));

        $this->logger->info('Starting elasticsearch populate command', [
            'environment' => $this->environment,
            'queue' => $this->queueName,
            'workers' => $this->elasticsearchIndexingPoolSize
        ]);
    }

    /**
     * Reset the Elasticsearch index
     */
    private function resetElasticsearchIndex(OutputInterface $output): bool
    {
        $output->writeln('Reset elasticsearch index');

        $resetProcess = new Process(
            [
                \PHP_BINARY,
                $this->getCurrentProjectConsole(),
                'fos:elastica:reset',
                self::ENVPARAM .$this->environment,
            ],
            DemosPlanPath::getRootPath()
        );
        $resetProcess->setTimeout(300);
        $resetProcess->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$resetProcess->isSuccessful()) {
            $output->writeln('<error>Error resetting Elasticsearch index</error>');
            $output->writeln($resetProcess->getErrorOutput());
            $this->logger->error('Error resetting Elasticsearch index', [
                'error_output' => $resetProcess->getErrorOutput()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Clear existing queue messages if any
     */
    private function clearExistingQueueMessages(OutputInterface $output): void
    {
        $initialCount = $this->getQueueMessageCount();
        if ($initialCount > 0) {
            $output->writeln(sprintf('Clearing %d existing messages from queue', $initialCount));
            $this->logger->info('Clearing existing messages from queue', [
                'count' => $initialCount
            ]);

            if ($this->clearQueueMessages()) {
                $output->writeln('Queue messages cleared successfully');
                $this->logger->info('Queue messages cleared successfully');
            } else {
                $output->writeln('<error>Failed to clear queue messages</error>');
                $this->logger->warning('Failed to clear queue messages');
            }
        }
    }

    /**
     * Queue indexing tasks using FOSElasticaBundle's populate command
     */
    private function queueIndexingTasks(OutputInterface $output): bool
    {
        $output->writeln('Start populating the Elasticsearch index');

        $populateProcess = new Process(
            [
                \PHP_BINARY,
                $this->getCurrentProjectConsole(),
                'fos:elastica:populate',
                '--pager-persister=async',
                '--no-debug',
                self::ENVPARAM .$this->environment,
            ],
            DemosPlanPath::getRootPath()
        );

        $populateProcess->setTimeout(0);
        $populateProcess->run();

        if (!$populateProcess->isSuccessful()) {
            $output->writeln('<error>Error running populate command:</error>');
            $output->writeln($populateProcess->getErrorOutput());
            $this->logger->error('Error running populate command', [
                'error_output' => $populateProcess->getErrorOutput()
            ]);
            return false;
        }

        $output->writeln('Tasks queued, starting workers and monitoring progress...');
        return true;
    }

    /**
     * Monitor the progress of queue processing
     */
    private function monitorQueueProgress(OutputInterface $output): void
    {
        // Get an initial count of messages in the queue to set max on progress bar
        $queueSize = $this->getQueueMessageCount();
        $output->writeln(sprintf('Found %d messages in queue', $queueSize));
        $this->logger->info('Queue status', ['messages_count' => $queueSize]);

        if ($queueSize > 0) {
            $this->displayProgressBar($output, $queueSize);
        } else {
            $output->writeln('No messages found in queue to process.');
            $this->logger->warning('No messages found in queue');
        }
    }

    /**
     * Display and update the progress bar during queue processing
     */
    private function displayProgressBar(OutputInterface $output, int $queueSize): void
    {
        // Create a progress bar with better formatting
        $progressBar = new ProgressBar($output, $queueSize);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        // Monitor the queue until it's empty
        $prevQueueSize = $queueSize;
        $unchangedCount = 0;
        $maxUnchanged = 10; // If queue size doesn't change for this many iterations, we'll consider it done

        while ($queueSize > 0 && $unchangedCount < $maxUnchanged) {
            sleep(2);
            $queueSize = $this->getQueueMessageCount();

            // If queue size decreased, update progress
            if ($queueSize < $prevQueueSize) {
                $progress = $progressBar->getMaxSteps() - $queueSize;
                $progressBar->setProgress($progress);
                $this->logger->info('Queue progress', [
                    'remaining' => $queueSize,
                    'processed' => $prevQueueSize - $queueSize,
                    'total' => $progressBar->getMaxSteps()
                ]);
                $prevQueueSize = $queueSize;
                $unchangedCount = 0;
            } else {
                $unchangedCount++;
            }
        }

        $this->finishProgressBar($output, $progressBar, $queueSize);
    }

    /**
     * Finish the progress bar with appropriate message
     */
    private function finishProgressBar(OutputInterface $output, ProgressBar $progressBar, int $queueSize): void
    {
        if ($queueSize > 0) {
            $output->writeln(sprintf("\nQueue processing stalled with %d messages remaining.", $queueSize));
            $this->logger->warning('Queue processing stalled', [
                'remaining_messages' => $queueSize
            ]);
        } else {
            $progressBar->finish();
            $output->writeln("\nQueue processing complete!");
            $this->logger->info('Queue processing complete');
        }
    }

    protected function startIndexWorker(OutputInterface $output): void
    {
        $this->indexingProcesses = collect();

        for ($i = 0; $i < $this->elasticsearchIndexingPoolSize; ++$i) {
            $indexProcess = new Process(
                [
                    \PHP_BINARY,
                    $this->getCurrentProjectConsole(),
                    'messenger:consume',
                    $this->queueName,
                    '--no-debug',
                    '--verbose',
                    '--failure-limit=1',  // Stop immediately on first error
                    self::ENVPARAM .$this->environment,
                ],
                DemosPlanPath::getProjectPath()
            );

            $indexProcess->setIdleTimeout(0);
            $indexProcess->setTimeout(0);
            $indexProcess->setTty(Process::isTtySupported());

            // Enable output streaming to show errors in real-time
            $indexProcess->start(function ($type, $buffer) use ($output, $i) {
                if (Process::ERR === $type) {
                    // Print error output with worker ID for easier tracking
                    $errorMessage = sprintf("\n[Worker %d ERROR] %s", $i, $buffer);
                    $output->writeln($errorMessage);

                    // Also log to system logger
                    $this->logger->error('Elasticsearch worker error', [
                        'worker_id' => $i,
                        'error' => $buffer,
                        'type' => 'stderr'
                    ]);
                } elseif (str_contains($buffer, 'ERROR') || str_contains($buffer, 'Exception')) {
                    // Also print regular output that contains error messages
                    $errorMessage = sprintf("\n[Worker %d] %s", $i, $buffer);
                    $output->writeln($errorMessage);

                    // Also log to system logger
                    $this->logger->error('Elasticsearch worker error', [
                        'worker_id' => $i,
                        'error' => $buffer,
                        'type' => 'stdout'
                    ]);
                }
            });

            $this->indexingProcesses->push($indexProcess);
            $this->logger->info('Started Elasticsearch worker', ['worker_id' => $i]);
        }
    }

    protected function stopWorkers(OutputInterface $output): void
    {
        $output->writeln('Stopping indexing workers');

        // Check for any failed workers before stopping them
        $this->indexingProcesses->each(function (Process $process, $index) use ($output) {
            if (!$process->isRunning()) {
                $exitCode = $process->getExitCode();
                if ($exitCode !== 0) {
                    $errorMessage = sprintf('Worker %d failed with exit code %d', $index, $exitCode);
                    $output->writeln('<error>' . $errorMessage . '</error>');
                    $output->writeln('<error>Error output:</error>');
                    $errorOutput = $process->getErrorOutput();
                    $output->writeln($errorOutput);

                    // Log the failure
                    $this->logger->error('Elasticsearch worker failed', [
                        'worker_id' => $index,
                        'exit_code' => $exitCode,
                        'error_output' => $errorOutput
                    ]);
                } else {
                    // Log successful completion
                    $this->logger->info('Elasticsearch worker completed successfully', [
                        'worker_id' => $index
                    ]);
                }
            } else {
                // Log stopped worker
                $this->logger->info('Stopping Elasticsearch worker', [
                    'worker_id' => $index
                ]);
            }
            $process->stop();
        });
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
