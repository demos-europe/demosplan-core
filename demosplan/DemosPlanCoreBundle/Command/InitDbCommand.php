<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use DomainException;
use EFrane\ConsoleAdditions\Batch\Batch;
use PDOException;
use SessionHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class InitDbCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:db:init';

    protected static $defaultDescription = 'Initialize a db for the project';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly SessionHandlerInterface $sessionHandler,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'with-fixtures',
                null,
                InputOption::VALUE_REQUIRED,
                'Populate the db with the given fixture group.'
            )
            ->addOption(
                'create-database',
                null,
                InputOption::VALUE_NONE,
                'Create configured database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        if ($input->getOption('create-database')) {
            Batch::create($this->getApplication(), $output)
                ->add('doctrine:database:drop -n --force')
                ->add('doctrine:database:create -n')
                ->run();
        }

        $schemaSuccess = Batch::create($this->getApplication(), $output)
            ->add('doctrine:schema:create -n')
            ->run();

        $sessionsTableSuccess = true;
        try {
            if ($this->sessionHandler instanceof PdoSessionHandler) {
                $this->sessionHandler->createTable();
                $output->note('Session table created');
            } else {
                $output->note('Sessions are not configured to be stored in the database, sessions table will not be created.');
            }
        } catch (PDOException|DomainException) {
            $sessionsTableSuccess = false;
        }

        $fixtureGroup = $input->getOption('with-fixtures');
        $fixtureSuccess = true;
        $projectMigrationsSuccess = true;
        if (null !== $fixtureGroup) {
            $application = $this->getApplication();
            $input = new StringInput('doctrine:fixtures:load -n --group '.$fixtureGroup);
            $fixtureSuccess = $application->run($input, $output);
            // set project migrations as migrated
            $input = new StringInput('doctrine:migrations:version --add --all --configuration '.DemosPlanPath::getProjectPath('app/config/project_migrations.yml'));
            $projectMigrationsSuccess = $application->run($input, $output);
        }

        return ($schemaSuccess && $sessionsTableSuccess && $fixtureSuccess && $projectMigrationsSuccess) ? Command::SUCCESS : Command::FAILURE;
    }
}
