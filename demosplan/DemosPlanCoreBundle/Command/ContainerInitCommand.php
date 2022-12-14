<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

class ContainerInitCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:container:init';
    protected static $defaultDescription = 'Perform startup tasks as an init container in kubernetes setup';
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->entityManager = $entityManager;
    }

    public function configure(): void
    {
        $this->addOption(
            'override-database',
            '',
            InputOption::VALUE_NONE,
            'If this is set, the existing database will be overridden. Use with care.'
        )
           ->setHelp(
               <<<EOT
Perform startup tasks as an init container in kubernetes setup. Usage:
    php bin/<project> dplan:container:init
EOT
           );
    }

    /**
     * Update demosplan.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->checkDatabase($input, $output);
            $this->migrateDatabase($output);
            $this->elasticsearchPopulate($output);
        } catch (Throwable $throwable) {
            $output->writeln($throwable->getMessage());
        }

        return 0;
    }

    private function checkDatabase(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('override-database')) {
            $output->writeln('Delete existing Database');
            $this->createDatabase($output);
        }

        $connection = $this->entityManager->getConnection();
        try {
            $connection->getDatabase();
        } catch (ConnectionException $throwable) {
            // create database, if it does not exist yet
            $this->createDatabase($output);
        }
    }

    private function elasticsearchPopulate(OutputInterface $output): void
    {
        $output->writeln('populate ES');
        Batch::create($this->getApplication(), $output)
            ->add('fos:elastica:reset -e prod --no-debug')
            ->add('fos:elastica:populate -e prod --no-debug')
            ->run();
    }

    private function migrateDatabase(OutputInterface $output): void
    {
        Batch::create($this->getApplication(), $output)
            ->add('dplan:migrate -e prod')
            ->run();
    }

    private function createDatabase(OutputInterface $output): void
    {
        Batch::create($this->getApplication(), $output)
            ->add('dplan:db:init --with-fixtures=ProdData --create-database -e prod')
            ->run();
        $output->writeln('DB created');
    }
}
