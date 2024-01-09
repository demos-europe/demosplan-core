<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exception;
use EFrane\ConsoleAdditions\Batch\Batch;
use Symfony\Component\Console\Command\Command;

class ProcedureDeleter extends CoreService
{
    private array $procedureIds;

    private bool $isDryRun;

    private bool $withoutRepopulate;

    public function __construct(
        private readonly Connection $dbConnection,
    ) {
    }

    public function setProcedureIds(array $procedureIds)
    {
        $this->procedureIds = $procedureIds;
    }

    public function setIsDryRun(bool $isDryRun)
    {
        $this->isDryRun = $isDryRun;
    }

    public function setRepopulate(bool $withoutRepopulate = false)
    {
        $this->withoutRepopulate = $withoutRepopulate;
    }

    /**
     * @throws Exception
     */
    public function deleteProcedures(): int
    {
        try {
            // start doctrine transaction
            $this->dbConnection->beginTransaction();

            // deactivate foreign key checks
            $this->deactivateForeignKeyChecks();

            // delete all statements and connected entities
            $this->processAllStatements();

            // delete all annotated statement pdfs -> pages -> files
            $this->processAnnotatedStatementPdfs();

            // delete procedure elements -> files
            $this->processElements();

            // Procedure Behavior Definition
            $this->deleteBehaviorDefinitions();

            // Procedure UI Definition
            $this->deleteUiDefinitions();

            // form definitions -> field definitions
            $this->processFormDefinitions();

            // delete gis layers
            $this->processGisLayers();

            // delete procedure news
            $this->deleteProcedureNews();

            // delete tag topics -> tags
            $this->processTags();

            // delete predefined text categories -> predefined texts
            $this->processPredefinedTexts();

            // delete draft statements
            $this->deleteDraftStatements();

            // delete draft statement versions
            $this->deleteDraftStatementsVersions();

            // delete institutions mails
            $this->deleteInstitutionsMails();

            // delete para docs
            $this->deleteParaDocs();

             // delete para docs versions
            $this->deleteParaDocVersions();

            // delete procedure doctrine orgas
            $this->deleteProcedureOrgaDoctrines();

            // delete import_emails -> attachments
            $this->processImportEmails();

            // delete hashed queries
            $this->deleteHashedQueries();

            // delete workflow places
            $this->deleteWorkflowPlaces();

            // delete procedure_settings
            $this->deleteProcedureSettings();

            // settings
            $this->deleteSettings();

            // export fields configuration
            $this->deleteExportFieldsConfiguration();

            // maillane connection
            $this->deleteMaillaneConnection();

            // procedure_settings_allowed_segment_procedures
            $this->deleteProcedureSettingsAllowedSegmentProcedures();

            // procedure_slug
            $this->deleteProcedureSlug();

            // procedure_user
            $this->deleteProcedureUser();

            // delete remaining procedure files
            $this->deleteFromTableByIdentifierArray('_files', 'procedure_id', $this->procedureIds);

            // delete procedure report entries
            $this->deleteReportEntriesByIdentifierAndType('procedure', $this->procedureIds);

            // delete procedure itself
            $this->deleteProcedure();

            // reactivate foreign key checks
            $this->activateForeignKeyChecks();

            // commit all changes
            $this->dbConnection->commit();

            // repopulate Elasticsearch
           // $this->repopulateElasticsearch();

            return Command::SUCCESS;
        } catch (Exception $e) {
            // rollback all changes
            $this->dbConnection->rollBack();
            throw $e;
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
        $statementIds = array_column($this->fetchFromTableByProcedures(['_st_id'], '_statement', '_p_id'), '_st_id');

        // delete statement meta
        $this->deleteStatementMeta($statementIds);
        // delete statement attachment -> files
        $this->processStatementAttachments($statementIds);
        // remove all tags from statements to prepare for later tag deletion
        $this->deleteTagsFromStatements($statementIds);
        // delete similar statement submitter
        $this->deleteSimilarStatementSubmitters($statementIds);
        // delete statement gdpr consent
        $this->deleteGdprConsent($statementIds);
        // delete entity content changes
        $this->deleteStatementEntityContentChange($statementIds);
        // delete report entries related to statements
        $this->deleteReportEntriesByIdentifierAndType('statement', $statementIds);
        // delete statements
        $this->deleteFromTableByIdentifierArray('_statement', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function processStatementAttachments(array $statementIds): void
    {
        if (!$this->doesTableExist('statement_attachment')) {
            throw new Exception("No table with the name 'statement_attachment' exists in this database. Data could not be fetched.");
        }

        $statementAttachmentQueryBuilder = $this->dbConnection->createQueryBuilder();
        $statementAttachmentQueryBuilder
            ->select('id', 'file_id')
            ->from('statement_attachment')
            ->where('statement_id IN (:idList)')
            ->setParameter('idList', $statementIds, ArrayParameterType::STRING);

        $attachmentData = $statementAttachmentQueryBuilder->fetchAllAssociative();

        // delete files first
        $this->deleteFiles(array_column($attachmentData, 'file_id'));

        // delete attachments
        $this->deleteStatementAttachment(array_column($attachmentData, 'id'));
    }

    /**
     * @throws Exception
     */
    private function processElements(): void
    {
        $elementsData = $this->fetchFromTableByProcedures(['_e_file'], '_elements', '_p_id');

        $this->deleteFiles(array_column($elementsData, '_e_file'));
        $this->deleteElements();
    }

    /**
     * @throws Exception
     */
    private function processFormDefinitions(): void
    {
        $formDefinitionsData = array_column($this->fetchFromTableByProcedures(['id'], 'statement_form_definition', 'procedure_id'), 'id');

        $this->deleteFieldDefinitions($formDefinitionsData);
        $this->deleteFormDefinitions($formDefinitionsData);
    }

    /**
     * @throws Exception
     */
    private function processGisLayers(): void
    {
        $gisCategoriesData = array_column($this->fetchFromTableByProcedures(['id'], 'gis_layer_category', 'procedure_id'), 'id');

        $this->deleteGisLayers($gisCategoriesData);
        $this->deleteGisCategories($gisCategoriesData);
    }

    /**
     * @throws Exception
     */
    private function processTags(): void
    {
        $tagTopicData = array_column($this->fetchFromTableByProcedures(['_tt_id'], '_tag_topic', '_p_id'), '_tt_id');

        $this->deleteTags($tagTopicData);
        $this->deleteTagTopics();
    }

    /**
     * @throws Exception
     */
    private function processImportEmails(): void
    {
        $importEmailData = array_column($this->fetchFromTableByProcedures(['id'], 'statement_import_email', 'procedure_id'), 'id');

        $this->processImportEmailAttachments($importEmailData);
        $this->deleteImportEmailOriginalStatements($importEmailData);
        $this->deleteStatementImportEmails($importEmailData);
    }

    /**
     * @throws Exception
     */
    private function processImportEmailAttachments(array $importEmailIds): void
    {
        if (!$this->doesTableExist('statement_import_email_attachments')) {
            throw Exception::invalidTableName('statement_import_email_attachments');
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
        $predefinedTextCategoriesData = array_column($this->fetchFromTableByProcedures(['ptc_id'], '_predefined_texts_category', '_p_id'), 'ptc_id');

        $this->deletePredefinedTextsCategoriesRelation($predefinedTextCategoriesData);
        $this->deletePredefinedTextsCategories();
        $this->deletePredefinedTexts();
        $this->deleteBoilerplateGroup();
    }

    private function processAnnotatedStatementPdfs(): void
    {
        $annotatedStatementPdfData = $this->fetchFromTableByProcedures(['id', 'file'], 'annotated_statement_pdf', '_procedure');

        $this->deleteAnnotatedStatementPdfPages(array_column($annotatedStatementPdfData, 'id'));
        $this->deleteAnnotatedStatementPdfs();
        $this->deleteFiles(array_column($annotatedStatementPdfData, 'file'));
    }

    private function deleteWorkflowPlaces(): void
    {
        $this->deleteFromTableByIdentifierArray('workflow_place', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSettings(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure_settings', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureUser(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_user', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSlug(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_slug', 'p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteSettings(): void
    {
        $this->deleteFromTableByIdentifierArray('_settings', '_s_procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteExportFieldsConfiguration(): void
    {
        $this->deleteFromTableByIdentifierArray('export_fields_configuration', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteMaillaneConnection(): void
    {
        if ($this->CheckColumnInTable('maillane_connection', 'procedure_id')) {
            $this->deleteFromTableByIdentifierArray('maillane_connection', 'procedure_id', $this->procedureIds);
        }
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSettingsAllowedSegmentProcedures(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_settings_allowed_segment_procedures', 'procedure__p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteUiDefinitions(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_ui_definition', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteBehaviorDefinitions(): void
    {
        $this->deleteFromTableByIdentifierArray('procedure_behavior_definition', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedure(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatements(): void
    {
        $this->deleteFromTableByIdentifierArray('_draft_statement', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatementsVersions(): void
    {
        $this->deleteFromTableByIdentifierArray('_draft_statement_versions', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteInstitutionsMails(): void
    {
        $this->deleteFromTableByIdentifierArray('institution_mail', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteParaDocs(): void
    {
        $this->deleteFromTableByIdentifierArray('_para_doc', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteParaDocVersions(): void
    {
        $this->deleteFromTableByIdentifierArray('_para_doc_version', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureOrgaDoctrines(): void
    {
        $this->deleteFromTableByIdentifierArray('_procedure_orga_doctrine', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteAnnotatedStatementPdfPages(array $annotatedStatementPdfIds): void
    {
        $this->deleteFromTableByIdentifierArray('annotated_statement_pdf_page', 'annotated_statement_pdf', $annotatedStatementPdfIds);
    }

    /**
     * @throws Exception
     */
    private function deleteAnnotatedStatementPdfs(): void
    {
        $this->deleteFromTableByIdentifierArray('annotated_statement_pdf', '_procedure', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTextsCategoriesRelation(array $predefinedCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('predefined_texts_categories', '_ptc_id', $predefinedCategoryIds);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTextsCategories(): void
    {
        $this->deleteFromTableByIdentifierArray('_predefined_texts_category', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTexts(): void
    {
        $this->deleteFromTableByIdentifierArray('_predefined_texts', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteBoilerplateGroup(): void
    {
        $this->deleteFromTableByIdentifierArray('boilerplate_group', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementImportEmailAttachments(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email_attachments', 'statement_import_email_id', $importEmailIds);
    }

    /**
     * @throws Exception
     */
    private function deleteImportEmailOriginalStatements(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email_original_statements', 'statement_import_email_id', $importEmailIds);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementImportEmails(array $importEmailIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_import_email', 'id', $importEmailIds);
    }

    /**
     * @throws Exception
     */
    private function deleteHashedQueries(): void
    {
        $this->deleteFromTableByIdentifierArray('hashed_query', 'procedure_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteTags(array $tagTopicIds): void
    {
        $this->deleteFromTableByIdentifierArray('_tag', '_tt_id', $tagTopicIds);
    }

    /**
     * @throws Exception
     */
    private function deleteTagTopics(): void
    {
        $this->deleteFromTableByIdentifierArray('_tag_topic', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureNews(): void
    {
        $this->deleteFromTableByIdentifierArray('_news', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementMeta(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('_statement_meta', '_st_id', $statementIds);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementAttachment(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_attachment', 'statement_id', $statementIds);
    }

    /**
     * @throws Exception
     */
    private function deleteFiles(array $fileIds): void
    {
        $this->deleteFromTableByIdentifierArray('_files', '_f_ident', $fileIds);
    }

    /**
     * @throws Exception
     */
    private function deleteTagsFromStatements(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('_statement_tag', '_st_id', $statementIds);
    }

    /**
     * @throws Exception
     */
    private function deleteSimilarStatementSubmitters(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('similar_statement_submitter', 'statement_id', $statementIds);
    }

    /**
     * @throws Exception
     */
    private function deleteElements(): void
    {
        $this->deleteFromTableByIdentifierArray('_elements', '_p_id', $this->procedureIds);
    }

    /**
     * @throws Exception
     */
    private function deleteFieldDefinitions(array $formDefinitionIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_field_definition', 'statement_form_definition_id', $formDefinitionIds);
    }

    /**
     * @throws Exception
     */
    private function deleteFormDefinitions(array $formDefinitionIds): void
    {
        $this->deleteFromTableByIdentifierArray('statement_form_definition', 'id', $formDefinitionIds);
    }

    /**
     * @throws Exception
     */
    private function deleteGisLayers(array $gisCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('_gis', 'category_id', $gisCategoryIds);
    }

    /**
     * @throws Exception
     */
    private function deleteGisCategories(array $gisCategoryIds): void
    {
        $this->deleteFromTableByIdentifierArray('gis_layer_category', 'id', $gisCategoryIds);
    }

    /**
     * @throws Exception
     */
    private function deleteGdprConsent(array $statementIds): void
    {
        $this->deleteFromTableByIdentifierArray('gdpr_consent', 'statement_id', $statementIds);
    }

    /**
     * @throws Exception
     */
    private function deleteReportEntriesByIdentifierAndType(string $identifierType, array $identifierArray): void
    {
        if (!$this->doesTableExist('_report_entries')) {
            throw Exception::invalidTableName('_report_entries');
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete('_report_entries')
            ->where('_re_identifier_type = :identifierType')
            ->andWhere('_re_identifier IN (:idList)')
            ->setParameter('identifierType', $identifierType)
            ->setParameter('idList', $identifierArray, ArrayParameterType::STRING);

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    private function deleteStatementEntityContentChange(array $identifierArray): void
    {
        if (!$this->doesTableExist('entity_content_change')) {
            throw Exception::invalidTableName('entity_content_change');
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete('entity_content_change')
            ->where('entity_type = :identifierType1 OR entity_type = :identifierType2')
            ->andWhere('entity_id IN (:idList)')
            ->setParameter('identifierType1', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Segment')
            ->setParameter('identifierType2', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Statement')
            ->setParameter('idList', $identifierArray, ArrayParameterType::STRING);

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    private function deleteFromTableByIdentifierArray(string $tableName, string $identifier, array $ids): void
    {
        if (!$this->doesTableExist($tableName)) {
            throw Exception::invalidTableName($tableName);
        }

        $deletionQueryBuilder = $this->dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $ids, ArrayParameterType::STRING);

        if ($this->isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function fetchFromTableByProcedures(array $targetColumns, string $tableName, string $identifier): array
    {
        if (!$this->doesTableExist($tableName)) {
            throw Exception::invalidTableName($tableName);
        }

        $fetchQueryBuilder = $this->dbConnection->createQueryBuilder();
        $fetchQueryBuilder
            ->select(...$targetColumns)
            ->from($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $this->procedureIds, ArrayParameterType::STRING);

        return $fetchQueryBuilder->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function fetchFromTableByParameter(array $targetColumns, string $tableName, string $identifier, array $parameter): array
    {
        if (!$this->doesTableExist($tableName)) {
            throw Exception::invalidTableName($tableName);
        }

        $fetchQueryBuilder = $this->dbConnection->createQueryBuilder();
        $fetchQueryBuilder
            ->select(...$targetColumns)
            ->from($tableName)
            ->where($identifier.' IN (:idList)')
            ->setParameter('idList', $parameter, ArrayParameterType::STRING);

        return $fetchQueryBuilder->fetchAllAssociative();
    }

    /**
     * This is necessary to even allow us to delete all tables individually.
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

    /**
     * @throws Exception
     */
    private function CheckColumnInTable(string $tableName, string $columnName): bool
    {
        if (!$this->doesTableExist($tableName)) {
            throw new Exception("No table with the name $tableName exists in this database. Data could not be fetched.");
        }

        $tableColumns = $this->dbConnection->createSchemaManager()->listTableColumns($tableName);

        if (in_array($columnName, $tableColumns)) {
            return true;
        }

        return false;
    }
}
