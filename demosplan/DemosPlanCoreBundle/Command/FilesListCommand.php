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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FilesListCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:files:list';

    protected static $defaultDescription = 'List files known to flysystem. Supports "local" and "s3" storage';

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
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage to list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $storage = $input->getArgument('storage');

        $output->note('List files for adapter '.$storage);
        // keep this simple as long as we only have two storage options
        $storageOperator = 'local' === $storage ? $this->localStorage : $this->s3Storage;

        $filesTotal = 0;
        try {
            $files = $storageOperator->listContents('/', true);
            foreach ($files as $file) {
                // only files could be moved via flysystem
                if ($file->isDir()) {
                    continue;
                }
                $output->writeln($file->path());
                ++$filesTotal;

                try {
                    if (!$storageOperator->fileExists($file->path())) {
                        $output->warning(sprintf('%s does not exist: %s ', $file->type(), $file->path()));
                    }
                } catch (FilesystemException $e) {
                    $output->error('Could not read file '.$file->path().' '.$e->getMessage());
                }
            }
        } catch (FilesystemException $e) {
            $output->error('Could not list files in storage storage '.$e->getMessage());

            return Command::FAILURE;
        }

        $output->info(sprintf('Number of files: %d', $filesTotal));

        return Command::SUCCESS;
    }
}
