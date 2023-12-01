<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use EFrane\ConsoleAdditions\Batch\Batch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DeleteOrgaCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:orga:delete';
    protected static $defaultDescription = 'Deletes a orga including all related content like address, department, user, etc.';

    private Connection $dbConnection;
    private string $orgaId;
    private bool $withoutRepopulate;
    private SymfonyStyle $output;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $parameterBag, string $name = null)
    {
        parent::__construct($parameterBag, $name);

        $this->dbConnection = $em->getConnection();
    }

    public function configure(): void
    {
        $this->addArgument(
            'orgaId',
            InputArgument::REQUIRED,
            'The ID of the orga you want to delete.'
        );

        $this->addOption(
            'without-repopulate',
            'wrp',
            InputOption::VALUE_NONE,
            'Ignores repopulating the ES. This should only be used for debugging purposes!',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = new SymfonyStyle($input, $output);
        $this->orgaId = $input->getArgument('orgaId');
        $this->withoutRepopulate = (bool) $input->getOption('without-repopulate');
        $this->output->writeln("Orga: $this->orgaId");
        try {
            // start doctrine transaction
            $this->dbConnection->beginTransaction();

            // deactivate foreign key checks
            $this->output->writeln('Deactivate FK Checks');
            $this->deactivateForeignKeyChecks();
            // delete all addresses
            $this->output->writeln('Deleting All Addresses');
            $this->deleteOrgaAddressess();
            $this->processAllAddresses();
            // delete all departments
            $this->output->writeln('Deleting All Departments');
            $this->deleteOrgaDepartments();
            $this->processAllDepartments();
            // delete all users
            $this->output->writeln('Deleting All Users');
            $this->deleteOrgaUsers();
            $this->processAllUsers();
            // delete all procedures
            $this->output->writeln('Deleting All Procedures');
            $this->deleteProcedures();
            // delete all Settings
            $this->output->writeln('Deleting All Settings');
            $this->deleteSettings();
            // delete all StatementMeta
            $this->output->writeln('Deleting All StatementMeta');
            $this->deleteStatementMeta();
            // delete all Slug
            $this->output->writeln('Deleting All Slug');
            $this->deleteOrgaSlug();
            $this->deleteSlug();
            // delete orga
            $this->output->writeln('Deleting Orga');
            $this->deleteOrga();
            // reactivate foreign key checks
            $this->output->writeln('Activate FK Checks');
            $this->activateForeignKeyChecks();

            // commit all changes
            $this->output->writeln('Committing all changes');
            $this->dbConnection->commit();

            // repopulate Elasticsearch
            $this->repopulateElasticsearch();

            $this->output->writeln("Procedure $this->orgaId was purged successfully!");

            return Command::SUCCESS;
        } catch (Exception $e) {
            // rollback all changes
            $this->dbConnection->rollBack();
            $this->output->writeln('Rolled back transaction');

            $this->output->error($e->getMessage());
            $this->output->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function deleteAddress(array $addressesID): void
    {
        $this->deleteFromTableByIdentifierArray('_address', '_a_id', $addressesID);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaAddressess(): void
    {
        $this->deleteFromTableByIdentifierArray('_orga_addresses_doctrine', '_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteDepartments(array $departmentsId): void
    {
        $this->deleteFromTableByIdentifierArray('_department', '_d_id', $departmentsId);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaDepartments(): void
    {
        $this->deleteFromTableByIdentifierArray('_orga_departments_doctrine', '_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteUsers(array $usersId): void
    {
        $this->deleteFromTableByIdentifierArray('_user', '_u_id', $usersId);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaUsers(): void
    {
        $this->deleteFromTableByIdentifierArray('_orga_users_doctrine', '_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedures(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure', '_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteSettings(): void
    {
        $this->deleteFromTableByIdentifierArray('_settings', '_s_orga_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementMeta(): void
    {
        $this->deleteFromTableByIdentifierArray('_statement_meta', '_stm_submit_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteSlug(): void
    {
        $this->deleteFromTableByIdentifierArray('slug', 'name', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteOrgaSlug(): void
    {
        $this->deleteFromTableByIdentifierArray('orga_slug', 'o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function deleteOrga(): void
    {
        $this->deleteFromTableByIdentifierArray('_orga', '_o_id', [$this->orgaId]);
    }

    /**
     * @throws Exception
     */
    private function processAllAddresses(): void
    {
        $addressesId = array_column($this->fetchFromTableByOrga(['_a_id'], '_orga_addresses_doctrine', '_o_id'), '_a_id');
        // delete addresses
        $this->output->writeln('Deleting Address');
        $this->deleteAddress($addressesId);
    }

    /**
     * @throws Exception
     */
    private function processAllDepartments(): void
    {
        $departmentsId = array_column($this->fetchFromTableByOrga(['_d_id'], '_orga_departments_doctrine', '_o_id'), '_d_id');
        // delete departments
        $this->output->writeln('Deleting Departments');
        $this->deleteDepartments($departmentsId);
    }

    /**
     * @throws Exception
     */
    private function processAllUsers(): void
    {
        $usersId = array_column($this->fetchFromTableByOrga(['_u_id'], '_orga_users_doctrine', '_o_id'), '_u_id');
        // delete users
        $this->output->writeln('Deleting Users');
        $this->deleteUsers($usersId);
    }

    /**
     * @throws Exception
     */
    private function deleteFromTableByIdentifierArray(string $tableName, string $identifier, array $ids): void
    {
        if (!$this->doesTableExist($tableName)) {
            $this->output->writeln("No table with the name $tableName exists in this database. Data could not be deleted.");

            return;
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $ids, ArrayParameterType::STRING);

        $deleteSql = $deletionQueryBuilder->getSQL();
        $this->output->writeln("DeleteSQL: $deleteSql");

        $deletionQueryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    private function fetchFromTableByOrga(array $targetColumns, string $tableName, string $identifier): array
    {
        if (!$this->doesTableExist($tableName)) {
            $this->output->writeln("No table with the name $tableName exists in this database. Data could not be fetched.");

            return [];
        }

        $fetchQueryBuilder = $this->dbConnection->createQueryBuilder();
        $fetchQueryBuilder
            ->select(...$targetColumns)
            ->from($tableName)
            ->where($identifier.' = ?')
            ->setParameter(0, $this->orgaId);

        return $fetchQueryBuilder->fetchAllAssociative();
    }

    /**
     * This is necessary to even allow us to delete all tables individually.
     *
     * @throws Exception
     */
    private function deactivateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 0;');
    }

    /**
     * @throws Exception
     */
    private function activateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 1;');
    }

    /**
     * @throws Exception
     */
    private function doesTableExist(string $tableName): bool
    {
        return $this->dbConnection->createSchemaManager()->tablesExist([$tableName]);
    }

    /**
     * @throws \Exception
     */
    private function repopulateElasticsearch(): void
    {
        $env = $this->parameterBag->get('kernel.environment');
        $this->output->writeln("Repopulating ES with env: $env");

        $repopulateEsCommand = 'dev' === $env ? 'dplan:elasticsearch:populate' : 'dplan:elasticsearch:populate -e prod --no-debug';
        Batch::create($this->getApplication(), $this->output)
            ->add($repopulateEsCommand)
            ->run();
    }
}
