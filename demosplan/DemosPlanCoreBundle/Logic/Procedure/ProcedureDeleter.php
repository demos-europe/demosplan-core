<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Logic\Orga\OrgaDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Doctrine\DBAL\ArrayParameterType;
use Exception;

class ProcedureDeleter
{
    public function __construct(
        private readonly SqlQueriesService $queriesService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function deleteProcedures(array $procedureIds, bool $isDryRun): void
    {
        // delete all statements and connected entities
        $this->processAllStatements($procedureIds, $isDryRun);

        // delete all annotated statement pdfs -> pages -> files
        $this->processAnnotatedStatementPdfs($procedureIds, $isDryRun);

        // delete procedure elements -> files
        $this->processElements($procedureIds, $isDryRun);

        // Procedure Behavior Definition
        $this->deleteBehaviorDefinitions($procedureIds, $isDryRun);

        // Procedure UI Definition
        $this->deleteUiDefinitions($procedureIds, $isDryRun);

        // form definitions -> field definitions
        $this->processFormDefinitions($procedureIds, $isDryRun);

        // delete gis layers
        $this->processGisLayers($procedureIds, $isDryRun);

        // delete procedure news
        $this->deleteProcedureNews($procedureIds, $isDryRun);

        // delete tag topics -> tags
        $this->processTags($procedureIds, $isDryRun);

        // delete predefined text categories -> predefined texts
        $this->processPredefinedTexts($procedureIds, $isDryRun);

        // delete draft statements attributes
        // ATTENTION
        // the order matters here as we need to query the draftStatementIds in order to delete the attributes
        $this->deleteDraftStatementAttributes($procedureIds, $isDryRun);
        // delete draft statements Files
        // ATTENTION
        // the order matters here as we need to query the draftStatementIds in order to delete the files
        $this->deleteDraftStatementsFiles($procedureIds, $isDryRun);
        // delete draft statement versions
        $this->deleteDraftStatementsVersions($procedureIds, $isDryRun);
        // delete draft statements
        $this->deleteDraftStatements($procedureIds, $isDryRun);

        // delete institutions mails
        $this->deleteInstitutionsMails($procedureIds, $isDryRun);

        // delete para docs
        $this->deleteParaDocs($procedureIds, $isDryRun);

        // delete para docs versions
        $this->deleteParaDocVersions($procedureIds, $isDryRun);

        // delete procedure doctrine orgas
        $this->deleteProcedureOrgaDoctrines($procedureIds, $isDryRun);

        // delete single docs
        $this->deleteSingleDocs($procedureIds, $isDryRun);

        // delete single docs versions
        $this->deleteSingleDocVersions($procedureIds, $isDryRun);

        // delete manual list sorts
        $this->deleteManualListSorts($procedureIds, $isDryRun);

        // delete procedure plannungoffices
        $this->deleteProcedurePlannungOffices($procedureIds, $isDryRun);

        // delete import_emails -> attachments
        $this->processImportEmails($procedureIds, $isDryRun);

        // delete user filter sets
        $this->deleteUserFilterSets($procedureIds, $isDryRun);

        // delete hashed queries
        $this->deleteHashedQueries($procedureIds, $isDryRun);

        // delete procedure-category relations
        $this->deleteProcedureCategoryRelation($procedureIds, $isDryRun);

        // delete procedure persons
        $this->deleteProcedurePersons($procedureIds, $isDryRun);

        // delete procedure Oata-input-orga relations
        $this->deleteProcedureDataInputOrgaRelations($procedureIds, $isDryRun);

        // delete procedure couple tokens
        $this->deleteProcedureCoupleTokens($procedureIds, $isDryRun);

        // delete procedure agency extra email addresses
        $this->deleteProcedureAgencyExtraEmailAddresses($procedureIds, $isDryRun);

        // delete notification receivers
        $this->deleteProcedureNotificationReceivers($procedureIds, $isDryRun);

        // delete workflow places
        $this->deleteWorkflowPlaces($procedureIds, $isDryRun);

        // delete procedure_settings
        $this->deleteProcedureSettings($procedureIds, $isDryRun);

        // settings
        $this->deleteSettings($procedureIds, $isDryRun);

        // export fields configuration
        $this->deleteExportFieldsConfiguration($procedureIds, $isDryRun);

        // maillane connection fixme does this table exist in all projects?
        $this->deleteMaillaneConnection($procedureIds, $isDryRun);

        // procedure_settings_allowed_segment_procedures
        $this->deleteProcedureSettingsAllowedSegmentProcedures($procedureIds, $isDryRun);

        // procedure_slug
        $this->deleteProcedureSlug($procedureIds, $isDryRun);

        // procedure_user
        $this->deleteProcedureUser($procedureIds, $isDryRun);

        // delete remaining procedure files
        $this->queriesService->deleteFromTableByIdentifierArray('_files', 'procedure_id', $procedureIds, $isDryRun);

        // delete procedure report entries
        $this->deleteReportEntriesByIdentifierAndType($procedureIds, $isDryRun);

        // delete procedures
        $this->deleteProcedure($procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    public function beginTransactionAndDisableForeignKeyChecks(): void
    {
        // deactivate foreign key checks
        $this->queriesService->deactivateForeignKeyChecks();
        // start doctrine transaction
        $this->queriesService->beginTransaction();
    }

    /**
     * @throws Exception
     */
    public function commitTransactionAndEnableForeignKeyChecks(): void
    {
        // commit all changes
        $this->queriesService->commitTransaction();
        // reactivate foreign key checks
        $this->queriesService->activateForeignKeyChecks();
    }

    /**
     * Find all statements + segments
     * Iterate over all Statements and delete related stuff like meta, attachments, etc.
     *
     * @throws Exception
     */
    private function processAllStatements(array $procedureIds, bool $isDryRun): void
    {
        $statementIds = array_column($this->queriesService->fetchFromTableByParameter(['_st_id'], '_statement', '_p_id', $procedureIds), '_st_id');

        // delete statement likes
        $this->deleteStatementLikes($statementIds, $isDryRun);
        // delete statement import email original statement
        $this->deleteStatementImportEmailOriginalStatement($statementIds, $isDryRun);
        // delete statement fragment and relations
        $this->deleteStatementFragmentAndRelations($statementIds, $isDryRun);
        // delete original statement anonymizations
        $this->deleteOriginalStatementAnonymizations($statementIds, $isDryRun);
        // delete gdpr consent revoke token statements
        $this->deleteGdprConsentRevokeTokenStatements($statementIds, $isDryRun);
        // delete consultation token
        $this->deleteConsultationTokens($statementIds, $isDryRun);
        // delete statement votes
        $this->deleteStatementVotes($statementIds, $isDryRun);
        // delete statement version fields
        $this->deleteStatementVersionFields($statementIds, $isDryRun);
        // delete statement priority areas
        $this->deleteStatementPriorityAreas($statementIds, $isDryRun);
        // delete statement municipalities
        $this->deleteStatementMunicipalities($statementIds, $isDryRun);
        // delete statement county
        $this->deleteStatementCounties($statementIds, $isDryRun);
        // delete statement attributes
        $this->deleteStatementAttributes($statementIds, $isDryRun);
        // delete statement meta
        $this->deleteStatementMeta($statementIds, $isDryRun);
        // delete statement attachment -> files
        $this->processStatementAttachments($statementIds, $isDryRun);
        // delete additional attachments -> file container
        $this->processAdditionalAttachments($statementIds, $isDryRun);
        // remove all tags from statements to prepare for later tag deletion
        $this->deleteTagsFromStatements($statementIds, $isDryRun);
        // delete similar statement submitter
        $this->deleteSimilarStatementSubmitters($statementIds, $isDryRun);
        // delete statement gdpr consent
        $this->deleteGdprConsent($statementIds, $isDryRun);
        // delete entity content changes
        $this->deleteStatementEntityContentChange($statementIds, $isDryRun);
        // delete report entries related to statements
        $this->deleteReportEntriesByIdentifierAndType($statementIds, $isDryRun);
        // delete statements
        $this->queriesService->deleteFromTableByIdentifierArray('_statement', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processAdditionalAttachments(array $statementIds, bool $isDryRun): void
    {
        $fileIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['file_id'],
                'file_container',
                'entity_id',
                $statementIds
            ),
            'file_id'
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_files',
            '_f_ident',
            $fileIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'file_container',
            'entity_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function processStatementAttachments(array $statementIds, $isDryRun): void
    {
        $attachmentData = $this->queriesService->fetchFromTableByParameter(['id', 'file_id'], 'statement_attachment', 'statement_id', $statementIds);

        // delete files first
        $this->deleteFiles(array_column($attachmentData, 'file_id'), $isDryRun);

        // delete attachments
        $this->deleteStatementAttachment(array_column($attachmentData, 'id'), $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processElements(array $procedureIds, bool $isDryRun): void
    {
        $elementsData = $this->queriesService->fetchFromTableByParameter(['_e_file'], '_elements', '_p_id', $procedureIds);

        $this->deleteFiles(array_column($elementsData, '_e_file'), $isDryRun);
        $this->deleteElements($procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processFormDefinitions(array $procedureIds, bool $isDryRun): void
    {
        $formDefinitionsData = array_column($this->queriesService->fetchFromTableByParameter(['id'], 'statement_form_definition', 'procedure_id', $procedureIds), 'id');

        $this->deleteFieldDefinitions($formDefinitionsData, $isDryRun);
        $this->deleteFormDefinitions($formDefinitionsData, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processGisLayers($procedureIds, bool $isDryRun): void
    {
        $gisCategoriesData = array_column($this->queriesService->fetchFromTableByParameter(['id'], 'gis_layer_category', 'procedure_id', $procedureIds), 'id');

        $this->deleteGisLayers($gisCategoriesData, $isDryRun);
        $this->deleteGisCategories($gisCategoriesData, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processTags(array $procedureIds, bool $isDryRun): void
    {
        $tagTopicData = array_column($this->queriesService->fetchFromTableByParameter(['_tt_id'], '_tag_topic', '_p_id', $procedureIds), '_tt_id');

        $this->deleteTags($tagTopicData, $isDryRun);
        $this->deleteTagTopics($procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processImportEmails(array $procedureIds, bool $isDryRun): void
    {
        $importEmailData = array_column($this->queriesService->fetchFromTableByParameter(['id'], 'statement_import_email', 'procedure_id', $procedureIds), 'id');

        $this->processImportEmailAttachments($importEmailData, $isDryRun);
        $this->deleteImportEmailOriginalStatements($importEmailData, $isDryRun);
        $this->deleteStatementImportEmails($importEmailData, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processImportEmailAttachments(array $importEmailIds, bool $isDryRun): void
    {
        $attachmentData = $this->queriesService->fetchFromTableByParameter(['file_id'], 'statement_import_email_attachments', 'statement_import_email_id', $importEmailIds);

        $this->deleteFiles($attachmentData, $isDryRun);
        $this->deleteStatementImportEmailAttachments($importEmailIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processPredefinedTexts(array $procedureIds, bool $isDryRun): void
    {
        $predefinedTextCategoriesData = array_column($this->queriesService->fetchFromTableByParameter(['ptc_id'], '_predefined_texts_category', '_p_id', $procedureIds), 'ptc_id');

        $this->deletePredefinedTextsCategoriesRelation($predefinedTextCategoriesData, $isDryRun);
        $this->deletePredefinedTextsCategories($procedureIds, $isDryRun);
        $this->deletePredefinedTexts($procedureIds, $isDryRun);
        $this->deleteBoilerplateGroup($procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function processAnnotatedStatementPdfs(array $procedureIds, bool $isDryRun): void
    {
        $annotatedStatementPdfData = $this->queriesService->fetchFromTableByParameter(['id', 'file'], 'annotated_statement_pdf', '_procedure', $procedureIds);

        $this->deleteAnnotatedStatementPdfPages(array_column($annotatedStatementPdfData, 'id'), $isDryRun);
        $this->deleteAnnotatedStatementPdfs($procedureIds, $isDryRun);
        $this->deleteFiles(array_column($annotatedStatementPdfData, 'file'), $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteWorkflowPlaces(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('workflow_place', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSettings(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_procedure_settings', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureUser(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_user', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSlug(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_slug', 'p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteSettings(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_settings', '_s_procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteExportFieldsConfiguration(array $procedureIds, $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('export_fields_configuration', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteMaillaneConnection(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('maillane_connection', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureSettingsAllowedSegmentProcedures(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_settings_allowed_segment_procedures', 'procedure__p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteUiDefinitions(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_ui_definition', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteBehaviorDefinitions(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_behavior_definition', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedure(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_procedure', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatements(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_draft_statement', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatementsFiles(array $procedureIds, bool $isDryRun): void
    {
        $draftStatementIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['_ds_id'],
                '_draft_statement',
                '_p_id',
                $procedureIds
            ),
            '_ds_id'
        );
        $fileIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['file_id'],
                'draft_statement_file',
                'draft_statement_id',
                $draftStatementIds
            ),
            'file_id'
        );

        $this->deleteFiles($fileIds, $isDryRun);
        $this->queriesService->deleteFromTableByIdentifierArray(
            'draft_statement_file',
            'draft_statement_id',
            $draftStatementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatementAttributes($procedureIds, bool $isDryRun): void
    {
        $draftStatementIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['_ds_id'],
                '_draft_statement',
                '_p_id',
                $procedureIds
            ),
            '_ds_id'
        );

        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_attribute',
            '_sta_ds_id',
            $draftStatementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteDraftStatementsVersions(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_draft_statement_versions', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteInstitutionsMails(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('institution_mail', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteParaDocs(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_para_doc', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteParaDocVersions(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_para_doc_version', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureOrgaDoctrines(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_procedure_orga_doctrine', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteSingleDocs(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_single_doc', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteSingleDocVersions(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_single_doc_version', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteManualListSorts(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_manual_list_sort', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     *
     * Planning offices and procedures share a manyToMany relation and therefore we have to delete these relations
     * from both sides { @see OrgaDeleter::deleteProcedurePlannungOffices() }
     */
    private function deleteProcedurePlannungOffices(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('procedure_planningoffices', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteAnnotatedStatementPdfPages(array $annotatedStatementPdfIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('annotated_statement_pdf_page', 'annotated_statement_pdf', $annotatedStatementPdfIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteAnnotatedStatementPdfs(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('annotated_statement_pdf', '_procedure', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTextsCategoriesRelation(array $predefinedCategoryIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('predefined_texts_categories', '_ptc_id', $predefinedCategoryIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTextsCategories(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_predefined_texts_category', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deletePredefinedTexts(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_predefined_texts', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteBoilerplateGroup(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('boilerplate_group', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementImportEmailAttachments(array $importEmailIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_import_email_attachments', 'statement_import_email_id', $importEmailIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteImportEmailOriginalStatements(array $importEmailIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_import_email_original_statements', 'statement_import_email_id', $importEmailIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementImportEmails(array $importEmailIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_import_email', 'id', $importEmailIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteUserFilterSets(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'user_filter_set',
            'procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteHashedQueries(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('hashed_query', 'procedure_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureCategoryRelation(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_procedure_category_doctrine',
            'procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteProcedurePersons(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_person',
            'procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureDataInputOrgaRelations(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_orga_datainput',
            '_p_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureCoupleTokens(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_couple_token',
            'source_procedure_id',
            $procedureIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_couple_token',
            'target_procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureAgencyExtraEmailAddresses(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'procedure_agency_extra_email_address',
            'procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureNotificationReceivers(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'notification_receiver',
            'procedure_id',
            $procedureIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteTags(array $tagTopicIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_tag', '_tt_id', $tagTopicIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteTagTopics(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_tag_topic', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteProcedureNews(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_news', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementLikes(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_likes', 'st_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementImportEmailOriginalStatement(array $statementIds, bool $isDryRun): void
    {
        $statementImportEmailIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['statement_import_email_id'],
                'statement_import_email_original_statements',
                'original_statement_id',
                $statementIds
            ),
            'statement_import_email_id'
        );
        $fileIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['file_id'],
                'statement_import_email_attachments',
                'statement_import_email_id',
                $statementImportEmailIds
            ),
            'file_id'
        );

        $this->deleteFiles($fileIds, $isDryRun);
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_import_email_attachments',
            'statement_import_email_id',
            $statementImportEmailIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_import_email',
            'id',
            $statementImportEmailIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_import_email_original_statements',
            'original_statement_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementFragmentAndRelations(array $statementIds, bool $isDryRun): void
    {
        $statementFragmentIds = array_column(
            $this->queriesService->fetchFromTableByParameter(
                ['sf_id'],
                'statement_fragment',
                'statement_id',
                $statementIds
            ),
            'sf_id'
        );

        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_fragment_county',
            'sf_id',
            $statementFragmentIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_fragment_municipality',
            'sf_id',
            $statementFragmentIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_fragment_priority_area',
            'sf_id',
            $statementFragmentIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_fragment_tag',
            'sf_id',
            $statementFragmentIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_fragment_version',
            'statement_fragment_id',
            $statementFragmentIds,
            $isDryRun
        );
        $this->queriesService->deleteFromTableByIdentifierArray(
            'statement_fragment',
            'statement_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteOriginalStatementAnonymizations(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'original_statement_anonymization',
            'statement_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteGdprConsentRevokeTokenStatements(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'gdpr_consent_revoke_token_statements',
            'statement_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteConsultationTokens(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            'consultation_token',
            'statement_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementVotes(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_votes',
            '_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementVersionFields(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_version_fields',
            '_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementPriorityAreas(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_priority_area',
            '_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementMunicipalities(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_municipality',
            '_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementCounties(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_county',
            '_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementAttributes(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray(
            '_statement_attribute',
            '_sta_st_id',
            $statementIds,
            $isDryRun
        );
    }

    /**
     * @throws Exception
     */
    private function deleteStatementMeta(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_statement_meta', '_st_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementAttachment(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_attachment', 'statement_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteFiles(array $fileIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_files', '_f_ident', $fileIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteTagsFromStatements(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_statement_tag', '_st_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteSimilarStatementSubmitters(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('similar_statement_submitter', 'statement_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteElements(array $procedureIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_elements', '_p_id', $procedureIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteFieldDefinitions(array $formDefinitionIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_field_definition', 'statement_form_definition_id', $formDefinitionIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteFormDefinitions(array $formDefinitionIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('statement_form_definition', 'id', $formDefinitionIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteGisLayers(array $gisCategoryIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_gis', 'category_id', $gisCategoryIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteGisCategories(array $gisCategoryIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('gis_layer_category', 'id', $gisCategoryIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteGdprConsent(array $statementIds, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('gdpr_consent', 'statement_id', $statementIds, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteReportEntriesByIdentifierAndType(array $identifierArray, bool $isDryRun): void
    {
        $this->queriesService->deleteFromTableByIdentifierArray('_report_entries', '_re_identifier', $identifierArray, $isDryRun);
    }

    /**
     * @throws Exception
     */
    private function deleteStatementEntityContentChange(array $identifierArray, bool $isDryRun): void
    {
        if (!$this->queriesService->doesTableExist('entity_content_change')) {
            throw new Exception("No table with the name 'entity_content_change' exists in this database. Data could not be fetched.");
        }

        $dbConnection = $this->queriesService->getConnection();
        $deletionQueryBuilder = $dbConnection->createQueryBuilder();
        $deletionQueryBuilder
            ->delete('entity_content_change')
            ->where('entity_type = :identifierType1 OR entity_type = :identifierType2')
            ->andWhere('entity_id IN (:idList)')
            ->setParameter('identifierType1', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Segment')
            ->setParameter('identifierType2', 'demosplan\DemosPlanCoreBundle\Entity\Statement\Statement')
            ->setParameter('idList', $identifierArray, ArrayParameterType::STRING);

        if ($isDryRun) {
            return;
        }

        $deletionQueryBuilder->executeStatement();
    }
}
