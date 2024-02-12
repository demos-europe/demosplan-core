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

class DeleteProcedureCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:procedure:delete';
    protected static $defaultDescription = 'Deletes a procedure including all related content like statements, tags, News, etc.';

    private readonly Connection $dbConnection;
    private string $procedureId;
    private bool $isDryRun;
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
            'procedureId',
            InputArgument::REQUIRED,
            'The ID of the procedure you want to delete.'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.',
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

        $this->procedureId = $input->getArgument('procedureId');
        $this->isDryRun = (bool) $input->getOption('dry-run');
        $this->withoutRepopulate = (bool) $input->getOption('without-repopulate');

        $this->output->writeln("Procedure: $this->procedureId");
        $this->output->writeln("Dry-run: $this->isDryRun");

        try {
            // start doctrine transaction
            $this->dbConnection->beginTransaction();

            // deactivate foreign key checks
            $this->output->writeln('Deactivate FK Checks');
            $this->deactivateForeignKeyChecks();

            // delete all statements and connected entities
            $this->output->writeln('Deleting All Statements');
            $this->processAllStatements();

            // delete all annotated statement pdfs -> pages -> files
            $this->output->writeln('Deleting Annotated PDFs');
            $this->processAnnotatedStatementPdfs();

            // delete procedure elements -> files
            $this->output->writeln('Deleting Elements');
            $this->processElements();

            // Procedure Behavior Definition
            $this->output->writeln('Deleting Behavior Definitions');
            $this->deleteBehaviorDefinitions();

            // Procedure UI Definition
            $this->output->writeln('Deleting UI Definitions');
            $this->deleteUiDefinitions();

            // form definitions -> field definitions
            $this->output->writeln('Deleting Form Definitions');
            $this->processFormDefinitions();

            // delete gis layers
            $this->output->writeln('Deleting Gis Layers');
            $this->processGisLayers();

            // delete procedure news
            $this->output->writeln('Deleting News');
            $this->deleteProcedureNews();

            // delete tag topics -> tags
            $this->output->writeln('Deleting Tags');
            $this->processTags();

            // delete predefined text categories -> predefined texts
            $this->output->writeln('Deleting Predefined Texts');
            $this->processPredefinedTexts();

            // delete draft statements
            $this->output->writeln('Deleting Draft Statements');
            $this->deleteDraftStatements();

            // delete import_emails -> attachments
            $this->output->writeln('Deleting Import Emails');
            $this->processImportEmails();

            // delete hashed queries
            $this->output->writeln('Deleting hashed Queries');
            $this->deleteHashedQueries();

            // delete workflow places
            $this->output->writeln('Deleting Workflow Places');
            $this->deleteWorkflowPlaces();

            // delete procedure_settings
            $this->output->writeln('Deleting Procedure Settings');
            $this->deleteProcedureSettings();

            // settings
            $this->output->writeln('Deleting Settings');
            $this->deleteSettings();

            // export fields configuration
            $this->output->writeln('Deleting Export Fields Configuration');
            $this->deleteExportFieldsConfiguration();

            // maillane connection
            $this->output->writeln('Deleting Maillane Connection');
            $this->deleteMaillaneConnection();

            // procedure_settings_allowed_segment_procedures
            $this->output->writeln('Deleting Something with procedure Settings and Segments');
            $this->deleteProcedureSettingsAllowedSegmentProcedures();

            // procedure_slug
            $this->output->writeln('Deleting Procedure Slug');
            $this->deleteProcedureSlug();

            // procedure_user
            $this->output->writeln('Deleting Procedure User');
            $this->deleteProcedureUser();

            // delete remaining procedure files
            $this->output->writeln('Deleting Procedure Files');
            $this->deleteFromTableByIdentifierArray('_files', 'procedure_id', [$this->procedureId]);

            // delete procedure report entries
            $this->output->writeln('Deleting Report Entries');
            $this->deleteReportEntriesByIdentifierAndType('procedure', [$this->procedureId]);

            // delete procedure itself
            $this->output->writeln('Deleting Procedure');
            $this->deleteProcedure();

            // reactivate foreign key checks
            $this->output->writeln('Activate FK Checks');
            $this->activateForeignKeyChecks();

            // commit all changes
            $this->output->writeln('Committing all changes');
            $this->dbConnection->commit();

            // repopulate Elasticsearch
            $this->repopulateElasticsearch();

            $this->output->writeln("Procedure $this->procedureId was purged successfully!");

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
     * Find all statements + segments
     * Iterate over all Statements and delete related stuff like meta, attachments, etc.
     *
     * @throws Exception
     */
    private function processAllStatements(): void
    {
        $statementIds = array_column($this->fetchFromTableByProcedure(['_st_id'], '_statement', '_p_id'), '_st_id');

        // delete statement meta
        $this->output->writeln('Deleting Statement-Meta');
        $this->deleteStatementMeta($statementIds);
        // delete statement attachment -> files
        $this->output->writeln('Deleting Statement-Attachments');
        $this->processStatementAttachments($statementIds);
        // remove all tags from statements to prepare for later tag deletion
        $this->output->writeln('Deleting Statement-Tags');
        $this->deleteTagsFromStatements($statementIds);
        // delete similar statement submitter
        $this->output->writeln('Deleting Statement-Submitters');
        $this->deleteSimilarStatementSubmitters($statementIds);
        // delete statement gdpr consent
        $this->deleteGdprConsent($statementIds);
        // delete entity content changes
        $this->deleteStatementEntityContentChange($statementIds);
        // delete report entries related to statements
        $this->output->writeln('Deleting Statement-Report-Entries');
        $this->deleteReportEntriesByIdentifierAndType('statement', $statementIds);
        // delete statements
        $this->output->writeln('Deleting Statements');
        $this->deleteFromTableByIdentifierArray('_statement', '_p_id', [$this->procedureId]);
    }

    private function processStatementAttachments(array $statementIds): void
    {
        if (!$this->doesTableExist('statement_attachment')) {
            $this->output->writeln("No table with the name 'statement_attachment' exists in this database. Data could not be fetched.");

            return;
        }

        $statementAttachmentQueryBuilder = $this->dbConnection->createQueryBuilder();
        $statementAttachmentQueryBuilder
            ->select('id', 'file_id')
            ->from('statement_attachment')
            ->where('statement_id IN (:idList)')
            ->setParameter('idList', $statementIds, ArrayParameterType::STRING);

        $query = $statementAttachmentQueryBuilder->getSQL();
        $this->output->writeln("Attachment-SQL: $query");

        $attachmentData = $statementAttachmentQueryBuilder->fetchAllAssociative();

        // delete files first
        $this->deleteFiles(array_column($attachmentData, 'file_id'));

        // delete attachments
        $this->deleteStatementAttachment(array_column($attachmentData, 'id'));
    }

    private function processElements(): void
    {
        $elementsData = $this->fetchFromTableByProcedure(['_e_file'], '_elements', '_p_id');

        $this->deleteFiles(array_column($elementsData, '_e_file'));
        $this->deleteElements();
    }

    private function processFormDefinitions(): void
    {
        $formDefinitionsData = array_column($this->fetchFromTableByProcedure(['id'], 'statement_form_definition', 'procedure_id'), 'id');

        $this->deleteFieldDefinitions($formDefinitionsData);
        $this->deleteFormDefinitions($formDefinitionsData);
    }

    private function processGisLayers(): void
    {
        $gisCategoriesData = array_column($this->fetchFromTableByProcedure(['id'], 'gis_layer_category', 'procedure_id'), 'id');

        $this->deleteGisLayers($gisCategoriesData);
        $this->deleteGisCategories($gisCategoriesData);
    }

    private function processTags(): void
    {
        $tagTopicData = array_column($this->fetchFromTableByProcedure(['_tt_id'], '_tag_topic', '_p_id'), '_tt_id');

        $this->deleteTags($tagTopicData);
        $this->deleteTagTopics();
    }

    private function processImportEmails(): void
    {
        $importEmailData = array_column($this->fetchFromTableByProcedure(['id'], 'statement_import_email', 'procedure_id'), 'id');

        $this->processImportEmailAttachments($importEmailData);
        $this->deleteImportEmailOriginalStatements($importEmailData);
        $this->deleteStatementImportEmails($importEmailData);
    }

    private function processImportEmailAttachments(array $importEmailIds): void
    {
        if (!$this->doesTableExist('statement_import_email_attachments')) {
            $this->output->writeln("No table with the name 'statement_import_email_attachments' exists in this database. Data could not be fetched.");

            return;
        }

        $importEmailAttachmentQueryBuilder = $this->dbConnection->createQueryBuilder();
        $importEmailAttachmentQueryBuilder
            ->select('file_id')
            ->from('statement_import_email_attachments')
            ->where('statement_import_email_id IN (:idList)')
            ->setParameter('idList', $importEmailIds, ArrayParameterType::STRING);

        $attachmentData = array_column($importEmailAttachmentQueryBuilder->fetchAllAssociative(), 'file_id');

        $this->deleteFiles($attachmentData);
        $this->deleteStatementImportEmailAttachments($importEmailIds);
    }

    private function processPredefinedTexts(): void
    {
        $predefinedTextCategoriesData = array_column($this->fetchFromTableByProcedure(['ptc_id'], '_predefined_texts_category', '_p_id'), 'ptc_id');

        $this->deletePredefinedTextsCategoriesRelation($predefinedTextCategoriesData);
        $this->deletePredefinedTextsCategories();
        $this->deletePredefinedTexts();
        $this->deleteBoilerplateGroup();
    }

    private function processAnnotatedStatementPdfs(): void
    {
        $annotatedStatementPdfData = $this->fetchFromTableByProcedure(['id', 'file'], 'annotated_statement_pdf', '_procedure');

        $this->deleteAnnotatedStatementPdfPages(array_column($annotatedStatementPdfData, 'id'));
        $this->deleteAnnotatedStatementPdfs();
        $this->deleteFiles(array_column($annotatedStatementPdfData, 'file'));
    }

    private function deleteWorkflowPlaces(): void
    {
        $this->deleteFromTableByIdentifierArray('workflow_place', 'procedure_id', [$this->procedureId]);
    }

    private function deleteProcedureSettings(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure_settings', '_p_id', [$this->procedureId]);
    }

    private function deleteProcedureUser(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_user', 'procedure_id', [$this->procedureId]);
    }

    private function deleteProcedureSlug(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_slug', 'p_id', [$this->procedureId]);
    }

    private function deleteSettings(): void
    {
        $this->deleteFromTableByIdentifierArray('_settings', '_s_procedure_id', [$this->procedureId]);
    }

    private function deleteExportFieldsConfiguration(): void
    {
        $this->deleteFromTableByIdentifierArray('export_fields_configuration', 'procedure_id', [$this->procedureId]);
    }

    private function deleteMaillaneConnection(): void
    {
        $this->deleteFromTableByIdentifierArray('maillane_connection', 'procedure_id', [$this->procedureId]);
    }

    private function deleteProcedureSettingsAllowedSegmentProcedures(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_settings_allowed_segment_procedures', 'procedure__p_id', [$this->procedureId]);
    }

    private function deleteUiDefinitions(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_ui_definition', 'procedure_id', [$this->procedureId]);
    }

    private function deleteBehaviorDefinitions(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_behavior_definition', 'procedure_id', [$this->procedureId]);
    }

    private function deleteProcedure(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure', '_p_id', [$this->procedureId]);
    }

    private function deleteDraftStatements(): void
    {
        $this->deleteFromTableByIdentifierArray('_draft_statement', '_p_id', [$this->procedureId]);
    }

    private function deleteAnnotatedStatementPdfPages(array $annotatedStatementPdfIds): void
    {
        $this->deleteFromTableByIdentifierArray('annotated_statement_pdf_page', 'annotated_statement_pdf', $annotatedStatementPdfIds);
    }

    private function deleteAnnotatedStatementPdfs(): void
    {
        $this->deleteFromTableByIdentifierArray('annotated_statement_pdf', '_procedure', [$this->procedureId]);
    }

    private function deletePredefinedTextsCategoriesRelation(array $predefinedCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('predefined_texts_categories', '_ptc_id', $predefinedCategoryIds);
    }

    private function deletePredefinedTextsCategories(): void
    {
        $this->deleteFromTableByIdentifierArray('_predefined_texts_category', '_p_id', [$this->procedureId]);
    }

    private function deletePredefinedTexts(): void
    {
        $this->deleteFromTableByIdentifierArray('_predefined_texts', '_p_id', [$this->procedureId]);
    }

    private function deleteBoilerplateGroup(): void
    {
        $this->deleteFromTableByIdentifierArray('boilerplate_group', 'procedure_id', [$this->procedureId]);
    }

    private function deleteStatementImportEmailAttachments(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email_attachments', 'statement_import_email_id', $importEmailIds);
    }

    private function deleteImportEmailOriginalStatements(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email_original_statements', 'statement_import_email_id', $importEmailIds);
    }

    private function deleteStatementImportEmails(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email', 'id', $importEmailIds);
    }

    private function deleteHashedQueries(): void
    {
        $this->deleteFromTableByIdentifierArray('hashed_query', 'procedure_id', [$this->procedureId]);
    }

    private function deleteTags(array $tagTopicIds): void
    {
        $this->deleteFromTableByIdentifierArray('_tag', '_tt_id', $tagTopicIds);
    }

    private function deleteTagTopics(): void
    {
        $this->deleteFromTableByIdentifierArray('_tag_topic', '_p_id', [$this->procedureId]);
    }

    private function deleteProcedureNews(): void
    {
        $this->deleteFromTableByIdentifierArray('_news', '_p_id', [$this->procedureId]);
    }

    private function deleteStatementMeta(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('_statement_meta', '_st_id', $statementIds);
    }

    private function deleteStatementAttachment(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_attachment', 'statement_id', $statementIds);
    }

    private function deleteFiles(array $fileIds): void
    {
        $this->deleteFromTableByIdentifierArray('_files', '_f_ident', $fileIds);
    }

    private function deleteTagsFromStatements(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('_statement_tag', '_st_id', $statementIds);
    }

    private function deleteSimilarStatementSubmitters(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('similar_statement_submitter', 'statement_id', $statementIds);
    }

    private function deleteElements(): void
    {
        $this->deleteFromTableByIdentifierArray('_elements', '_p_id', [$this->procedureId]);
    }

    private function deleteFieldDefinitions(array $formDefinitionIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_field_definition', 'statement_form_definition_id', $formDefinitionIds);
    }

    private function deleteFormDefinitions(array $formDefinitionIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_form_definition', 'id', $formDefinitionIds);
    }

    private function deleteGisLayers(array $gisCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('_gis', 'category_id', $gisCategoryIds);
    }

    private function deleteGisCategories(array $gisCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('gis_layer_category', 'id', $gisCategoryIds);
    }

    private function deleteGdprConsent(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('gdpr_consent', 'statement_id', $statementIds);
    }

    private function deleteReportEntriesByIdentifierAndType(string $identifierType, array $identifierArray): void
    {
        if (!$this->doesTableExist('_report_entries')) {
            $this->output->writeln("No table with the name '_report_entries' exists in this database. Data could not be deleted.");

            return;
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete('_report_entries')
            ->where('_re_identifier_type = :identifierType')
            ->andWhere('_re_identifier IN (:idList)')
            ->setParameter('identifierType', $identifierType)
            ->setParameter('idList', $identifierArray, ArrayParameterType::STRING);

        $deleteSql = $deletionQueryBuilder->getSQL();
        $this->output->writeln("DeleteSQL: $deleteSql");

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    private function deleteStatementEntityContentChange(array $identifierArray): void
    {
        if (!$this->doesTableExist('entity_content_change')) {
            $this->output->writeln("No table with the name 'entity_content_change' exists in this database. Data could not be deleted.");

            return;
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete('entity_content_change')
            ->where('entity_type = :identifierType1 OR entity_type = :identifierType2')
            ->andWhere('entity_id IN (:idList)')
            ->setParameter('identifierType1', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Segment')
            ->setParameter('identifierType2', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Statement')
            ->setParameter('idList', $identifierArray, ArrayParameterType::STRING);

        $deleteSql = $deletionQueryBuilder->getSQL();
        $this->output->writeln("DeleteSQL: $deleteSql");

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

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

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    private function fetchFromTableByProcedure(array $targetColumns, string $tableName, string $identifier): array
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
            ->setParameter(0, $this->procedureId);

        return $fetchQueryBuilder->fetchAllAssociative();
    }

    /**
     * This is necessary to even allow us to delete all tables individually.
     */
    private function deactivateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 0;');
    }

    private function activateForeignKeyChecks(): void
    {
        $this->dbConnection->executeStatement('SET foreign_key_checks = 1;');
    }

    private function doesTableExist(string $tableName): bool
    {
        return $this->dbConnection->createSchemaManager()->tablesExist([$tableName]);
    }

    private function repopulateElasticsearch(): void
    {
        if ($this->isDryRun || $this->withoutRepopulate) {
            return;
        }

        $env = $this->parameterBag->get('kernel.environment');
        $this->output->writeln("Repopulating ES with env: $env");

        $repopulateEsCommand = 'dev' === $env ? 'dplan:elasticsearch:populate' : 'dplan:elasticsearch:populate -e prod --no-debug';
        Batch::create($this->getApplication(), $this->output)
            ->add($repopulateEsCommand)
            ->run();
    }
}
