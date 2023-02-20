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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
        $this
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
        $connection = $this->entityManager->getConnection();
        try {
            $connection->getDatabase();
        } catch (ConnectionException $throwable) {
            // create database, if it does not exist yet
            Batch::create($this->getApplication(), $output)
                ->add('dplan:db:init --with-fixtures=ProdData --create-database')
                ->run();
        }

        return 0;
    }
}
