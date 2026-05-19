<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:files:move', description: 'Move files from one flysystem storage to another. Supports "local" and "s3" storage')]
class MoveFilesCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly FilesystemOperator $s3Storage,
        private readonly FilesystemOperator $localStorage,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'Source storage')
            ->addArgument('target', InputArgument::REQUIRED, 'Target storage')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run')
            ->addOption('no-delete', null, InputOption::VALUE_NONE, 'Do not delete files after copy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        $dryRun = $input->getOption('dry-run');
        $noDelete = $input->getOption('no-delete');

        $output->note('Moving files from '.$source.' to '.$target);
        // keep this simple as long as we only have two storage options
        $sourceStorage = 'local' === $source ? $this->localStorage : $this->s3Storage;
        $targetStorage = 'local' === $target ? $this->localStorage : $this->s3Storage;

        $filesTotal = 0;
        $filesMoved = 0;
        try {
            $files = $sourceStorage->listContents('/', true);
            foreach ($files as $file) {
                // only files could be moved via flysystem
                if ($file->isDir()) {
                    continue;
                }

                if ($dryRun) {
                    $output->writeln(sprintf('Would move %s %s ', $file->type(), $file->path()));
                    continue;
                }

                try {
                    $sourceStream = $sourceStorage->readStream($file->path());

                    // Check if file exists in target, skip existence check on error (assume doesn't exist)
                    $fileExistsInTarget = false;
                    try {
                        $fileExistsInTarget = $targetStorage->fileExists($file->path());
                    } catch (FilesystemException $existsCheckException) {
                        $output->writeln(sprintf(
                            '<comment>Warning: Could not check existence of %s (assuming not exists): %s</comment>',
                            $file->path(),
                            $existsCheckException->getMessage()
                        ), OutputInterface::VERBOSITY_VERBOSE);
                    }

                    if (!$fileExistsInTarget) {
                        $output->writeln(sprintf('Move %s %s ', $file->type(), $file->path()));
                        $targetStorage->writeStream($file->path(), $sourceStream);
                        if (!$noDelete) {
                            $sourceStorage->delete($file->path());
                        }
                        ++$filesMoved;
                    } else {
                        $output->writeln(sprintf('Skip %s %s (already exists)', $file->type(), $file->path()), OutputInterface::VERBOSITY_VERBOSE);
                    }
                    ++$filesTotal;
                } catch (FilesystemException $e) {
                    $output->error('Could not move file '.$file->path().' '.$e->getMessage());
                }
            }
        } catch (FilesystemException $e) {
            $output->error('Could not list files in source storage '.$e->getMessage());

            return Command::FAILURE;
        }

        $output->info(sprintf('Number of files: %d', $filesTotal));
        $output->info(sprintf('Successfully moved %d files from %s to %s', $filesMoved, $source, $target));

        return Command::SUCCESS;
    }
}
