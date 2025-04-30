<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Permissions\CachingYamlPermissionCollection;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionResolver;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

/**
 * Teste Permissions
 * Class PermissionsTest.
 *
 * @see: https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/technical/ permissions structure & rules
 *
 * @group UnitTest
 */
class PermissionsTest extends FunctionalTestCase
{
    /**
     * @var Permissions
     */
    protected $permissions;

    protected $userOrgaId = 'TestOrgaId';

    protected $procedureOrgaId = 'TestOrgaId';

    protected $foreignOrgaId = 'ForeignOrgaId';
    /** @var array<int,string> */
    protected static $rolesAllowed;
    /** @var array<int,string> */
    protected static $testedRoles;

    /**
     * @var array
     */
    protected $procedure;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = [
            'orgaId'            => $this->userOrgaId,
            'organisation'      => [$this->userOrgaId],
            'phase'             => 'participation',
            'authorizedUsers'   => new ArrayCollection([$testUser]),
            'authorizedUserIds' => [$testUser->getId()],
        ];

        $this->procedure = $procedure;

        self::$rolesAllowed = self::getContainer()->get(GlobalConfigInterface::class)->getRolesAllowed();
    }

    /**
     * Check if all enabled roles of a project have been tested.
     *
     * This does not apply to the core permissions test, therefore
     * we only need to to call the parent tear down in that case.
     */
    public static function tearDownAfterClass(): void
    {
        if (__CLASS__ === static::class) {
            parent::tearDownAfterClass();

            return;
        }

        if (0 !== count(array_diff(self::$rolesAllowed, self::$testedRoles))) {
            echo 'Some defined Roles have not been tested! '.
                var_export(array_diff(self::$rolesAllowed, self::$testedRoles), true);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Get Instance to test.
     *
     * @throws Exception
     */
    protected function getPermissionsInstance(bool $ownsProcedure, bool $inviteOrgaForDataInput): Permissions
    {
        // reconfigure logger to send messages to STDERR instead of STDOUT
        $logger = new Logger('UnitTest');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

        // generiere ein Stub vom GlobalConfig
        /** @var MockObject|GlobalConfigInterface $globalConfig */
        $globalConfig = self::getContainer()->get(GlobalConfigInterface::class);
        $corePermissions = self::getContainer()->get(CachingYamlPermissionCollection::class);
        $permissionsResolver = self::getContainer()->get(PermissionResolver::class);
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $procedureRepository = $this->getProcedureRepositoryMock();
        $permissionsClass = $this->getPermissionsClass();
        $accessControlService = self::getContainer()->get(AccessControlService::class);

        $customerService = static::$container->get(CustomerService::class);
        $addonRegistry = static::$container->get(AddonRegistry::class);

        $tokenMockMethods = [
            new MockMethodDefinition('isOwningProcedure', $ownsProcedure),
            new MockMethodDefinition('isAllowedAsDataInputOrga', $inviteOrgaForDataInput),
        ];
        $procedureAccessEvaluator = $this->getMock(ProcedureAccessEvaluator::class, $tokenMockMethods);
        /** @var Permissions $permissions */
        $permissions = (new ReflectionClass($permissionsClass))
            ->newInstance(
                $addonRegistry,
                $customerService,
                $logger,
                $globalConfig,
                $corePermissions,
                $permissionsResolver,
                $procedureAccessEvaluator,
                $procedureRepository,
                $validator,
                $accessControlService
            );

        return $permissions;
    }

    protected function getPermissionsClass(): string
    {
        return Permissions::class;
    }

    /**
     * Beschreibt die Rollendefinitionen mit den Permissions.
     *
     * In der procedurePhase können mit dem ||-Operator mehrere Phasen definiert
     * werden, für die die Tests gelten sollen
     */
    public function permissionsTests(): array
    {
        return [
            // ############### AI (Demos PI) User ########################
            'ai api user #1'                    => [
                'roles'                             => [Role::API_AI_COMMUNICATOR],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'feature_read_source_statement_via_api',
                    'field_statement_recommendation',
                ],
                'features_denied'                   => [
                    'area_admin_dashboard',
                    'area_demosplan',
                    'area_institution_tag_manage',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_json_rpc_post',
                    'feature_procedure_export_include_assessment_table_original',
                ],
            ],

            'ai api user #2'                    => [
                'roles'                             => [Role::API_AI_COMMUNICATOR],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                ],
                'features_denied'                   => [
                    'area_admin_dashboard',
                    'area_demosplan',
                    'area_institution_tag_manage',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_json_rpc_post',
                    'feature_procedure_export_include_assessment_table_original',
                ],
            ],

            'ai api user #3'                    => [
                'roles'                             => [Role::API_AI_COMMUNICATOR],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => 'participation',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                ],
                'features_denied'                   => [
                    'area_admin_dashboard',
                    'area_demosplan',
                    'area_institution_tag_manage',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_json_rpc_post',
                    'feature_procedure_export_include_assessment_table_original',
                ],
            ],

            // ############### Customer Master User ######################
            'customer master user #1'           => [
                'roles'                             => [Role::CUSTOMER_MASTER_USER],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_preferences',
                    'feature_orga_edit_all_fields',
                    'feature_procedure_report_public_phase',
                    'field_statement_recommendation',
                    'feature_orga_edit',
                    'feature_organisation_user_list',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin_consultations',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_institution_tag_manage',
                    'area_software_licenses',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_show_free_disk_space',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                ],
            ],
            // ############### Fachplaner Admin ######################
            'planning agency admin #1'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin_dashboard',
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_portal_user',
                    'area_preferences',
                    'feature_admin_export_procedure',
                    'feature_assessmenttable_export',
                    'feature_documents_category_use_file',
                    'feature_documents_category_use_paragraph',
                    'feature_json_api_get',
                    'feature_json_rpc_post',
                    'feature_list_restricted_external_links',
                    'feature_map_search_location',
                    'feature_original_statements_export',
                    'feature_procedure_change_phase',
                    'feature_procedure_filter_external_orga_name',
                    'feature_procedure_get_base_data',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'feature_send_final_email_cc_to_self',
                    'feature_statement_data_input_orga',
                    'feature_statement_meta_house_number_export',
                    'field_procedure_name',
                    'field_statement_feedback',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin_analysis',
                    'area_admin_consultations',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_manage_segment_places',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_software_licenses',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'area_use_mastertoeblist',
                    'feature_admin_assessmenttable_export_docx_condensed',
                    'feature_admin_element_edit',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_assign_procedure_fachplaner_roles',
                    'feature_assign_procedure_invitable_institution_roles',
                    'feature_assign_system_roles',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_change_submission_type',
                    'feature_citizen_registration',
                    'feature_create_procedure_proposal',
                    'feature_department_add',
                    'feature_department_delete',
                    'feature_department_edit',
                    'feature_documents_new_statement',
                    'feature_draft_statement_add_address_to_institutions',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_layer_groups_alternate_visibility',
                    'feature_map_category',
                    'feature_map_deactivate',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_mastertoeblist',
                    'feature_new_statement',
                    'feature_obscure_text',
                    'feature_optional_tag_propagation',
                    'feature_orga_add',
                    'feature_orga_delete',
                    'feature_orga_registration',
                    'feature_organisation_email_reviewer_admin',
                    'feature_password_recovery',
                    'feature_plain_language',
                    'feature_procedure_all_orgas_invited',
                    'feature_procedure_categories',
                    'feature_procedure_categories_edit',
                    'feature_procedure_default_filter_extern',
                    'feature_procedure_default_filter_intern',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_procedure_require_location',
                    'feature_procedure_user_filter_sets',
                    'feature_require_locality_confirmation',
                    'feature_screenshot_map_nonpublic_printlayers',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_assignment',
                    'feature_statement_cluster',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_create_autofill_submitter_citizens',
                    'feature_statement_create_autofill_submitter_institutions',
                    'feature_statement_create_autofill_submitter_invited',
                    'feature_statement_data_protection',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_public_allowed_needs_verification',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_tag',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'feature_switchorga',
                    'feature_toggle_public_participation_publication',
                    'feature_user_add',
                    'feature_user_delete',
                    'feature_user_edit',
                    'feature_user_get',
                    'feature_user_list',
                    'feature_xplan_defaultlayers',
                    'field_customer_accessibility_explanation_edit',
                    'field_organisation_contact_person',
                    'field_organisation_email_reviewer_admin',
                    'field_procedure_linkbox',
                    'field_procedure_pictogram',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_intern_id',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            'planning agency admin #2'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_json_api_get',
                    'feature_list_restricted_external_links',
                    'feature_procedure_get_base_data',
                    'feature_procedure_single_document_upload_zip',
                    'feature_statement_data_input_orga',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_manage_segment_places',
                    'area_organisation_email_notifications',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_admin_element_edit',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_category',
                    'feature_map_deactivate',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_new_statement',
                    'feature_notification_statement_new',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_cluster',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency admin #3'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_export_protocol',
                    'feature_json_rpc_post',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_notification_ending_phase',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_categories_edit',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_cluster',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency admin #4'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_cluster',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency admin #5'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_consultations',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_cluster',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency admin #6'          => [
                'roles'                             => [Role::PLANNING_AGENCY_ADMIN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_export_protocol',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ############### Fachplaner-Masteruser ######################
            'planning agency master user #1'    => [
                'roles'                             => [Role::ORGANISATION_ADMINISTRATION],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_mydata',
                    'area_portal_user',
                    'area_preferences',
                    'feature_organisation_user_list',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_get_base_data',
                    'feature_statement_data_input_orga',
                    'field_statement_file',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_manage_orgas',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_admin_assessmenttable_export_docx',
                    'feature_admin_delete_procedure',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_admin_export_procedure',
                    'feature_admin_new_procedure',
                    'feature_assign_procedure_fachplaner_roles',
                    'feature_assign_procedure_invitable_institution_roles',
                    'feature_assign_system_roles',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_department_add',
                    'feature_department_delete',
                    'feature_department_edit',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_category',
                    'feature_map_deactivate',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_obscure_text',
                    'feature_optional_tag_propagation',
                    'feature_orga_add',
                    'feature_orga_delete',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_all_orgas_invited',
                    'feature_procedure_export_include_public_interest_bodies_member_list',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'feature_user_edit',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            'planning agency master user #2'    => [
                'roles'                             => [Role::ORGANISATION_ADMINISTRATION],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_json_api_create',
                    'feature_json_api_delete',
                    'feature_json_api_list',
                    'feature_json_api_update',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_assign_procedure_fachplaner_roles',
                    'feature_assign_procedure_invitable_institution_roles',
                    'feature_assign_system_roles',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                ],
            ],
            'planning agency master user #3'    => [
                'roles'                             => [Role::ORGANISATION_ADMINISTRATION],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'feature_json_api_create',
                    'feature_json_api_delete',
                    'feature_json_api_list',
                    'feature_json_api_update',
                ],
                'featuresDenied'                    => [
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_update',
                    'feature_institution_tag_read',
                    'area_institution_tag_manage',
                ],
            ],
            'planning agency master user #4'    => [
                'roles'                             => [Role::ORGANISATION_ADMINISTRATION],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'feature_json_api_create',
                    'feature_json_api_delete',
                    'feature_json_api_list',
                    'feature_json_api_update',
                ],
                'featuresDenied'                    => [
                    'area_institution_tag_manage',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                ],
            ],
            // ############### Planungsbüro ######################
            'private planning agency #1'        => [
                'roles'                             => [Role::PRIVATE_PLANNING_AGENCY],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_manage_orgadata',
                    'area_portal_user',
                    'area_preferences',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'field_statement_file',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_invitable_institution',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_admin_element_edit',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_deactivate',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_change_phase',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'private planning agency #2'        => [
                'roles'                             => [Role::PRIVATE_PLANNING_AGENCY],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_manage_orgadata',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'private planning agency #3'        => [
                'roles'                             => [Role::PRIVATE_PLANNING_AGENCY],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_manage_orgadata',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_invitable_institution',
                    'area_admin_news',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'private planning agency #4'        => [
                'roles'                             => [Role::PRIVATE_PLANNING_AGENCY],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_manage_orgadata',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_invitable_institution',
                    'area_admin_news',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'private planning agency #5'        => [
                'roles'                             => [Role::PRIVATE_PLANNING_AGENCY],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin_news',
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_manage_orgadata',
                    'feature_admin_export_procedure',
                    'feature_list_restricted_external_links',
                    'feature_procedure_change_phase',
                    'feature_procedure_get_base_data',
                    'feature_procedure_single_document_upload_zip',
                    'feature_statement_data_input_orga',
                ],
                'featuresDenied'                    => [
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ###############  Fachplaner-Sachbearbeiter ###################
            'planning agency worker #1'         => [
                'roles'                             => [Role::PLANNING_AGENCY_WORKER],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_mydata_organisation',
                    'area_portal_user',
                    'area_preferences',
                    'feature_admin_export_procedure',
                    'feature_json_api_get',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'feature_statement_meta_house_number_export',
                    'field_statement_file',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_invitable_institution',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_manage_segment_places',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_admin_element_edit',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_category',
                    'feature_map_deactivate',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_filter_external_public_participation_phase',
                    'feature_procedure_filter_external_public_participation_phase_permissionset',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_content_changes_save',
                    'feature_statement_content_changes_view',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency worker #2'         => [
                'roles'                             => [Role::PLANNING_AGENCY_WORKER],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_combined_participation_area',
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_json_api_get',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_admin_element_edit',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_deactivate',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                ],
            ],
            'planning agency worker #3'         => [
                'roles'                             => [Role::PLANNING_AGENCY_WORKER],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => true,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_json_api_get',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_invitable_institution',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statements',
                    'area_survey_management',
                    'feature_admin_assessmenttable_export_docx',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_export_protocol',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'planning agency worker #4'         => [
                'roles'                             => [Role::PLANNING_AGENCY_WORKER],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin_procedures',
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_admin_export_procedure',
                    'feature_json_api_get',
                    'feature_list_restricted_external_links',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_invitable_institution',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_element_export',
                    'feature_export_protocol',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_layer_contextual_help',
                    'feature_map_new_statement',
                    'feature_map_wmts',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Toeb-Koordinator###################
            'public agency coordinator #1'      => [
                'roles'                             => [Role::PUBLIC_AGENCY_COORDINATION],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_portal_user',
                    'feature_admin_export_procedure',
                    'feature_map_use_drawing_tools',
                    'feature_participation_area_procedure_detail_map_use_baselayerbox',
                    'feature_procedure_export_include_statement_final_group',
                    'feature_procedure_export_include_statement_released',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_public',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_elements_use_negative_report',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_category',
                    'feature_map_hint',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_all_orgas_invited',
                    'feature_procedure_default_filter_extern',
                    'feature_procedure_default_filter_intern',
                    'feature_procedure_export_include_public_interest_bodies_member_list',
                    'feature_procedure_filter_external_public_participation_phase',
                    'feature_procedure_filter_external_public_participation_phase_permissionset',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_statements_participation_area_always_citizen',
                    'feature_statements_represent_orga',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_organisation_agreement_showname',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            'public agency coordinator #2'      => [
                'roles'                             => [Role::PUBLIC_AGENCY_COORDINATION],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_statements',
                    'feature_procedure_single_document_upload_zip',
                    'feature_statement_data_input_orga',
                    'feature_statements_public',
                ],
                'featuresDenied'                    => [
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'public agency coordinator #3'      => [
                'roles'                             => [Role::PUBLIC_AGENCY_COORDINATION],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_statements',
                    'feature_documents_new_statement',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_procedure_single_document_upload_zip',
                    'feature_statement_data_input_orga',
                    'feature_statements_public',
                ],
                'featuresDenied'                    => [
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_notification_invitable_institution_statement_submitted',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_participation_area_procedure_detail_map_use_get_legend_graphic',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_public_allowed_needs_verification',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Toeb-Sachbearbeiter ###################
            'public agency worker #1'           => [
                'roles'                             => [Role::PUBLIC_AGENCY_WORKER],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin',
                    'area_admin_dashboard',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_mydata_organisation',
                    'area_portal_user',
                    'area_statements',
                    'area_statements_public',
                    'feature_admin_export_procedure',
                    'feature_map_use_drawing_tools',
                    'feature_participation_area_procedure_detail_map_use_baselayerbox',
                    'feature_procedure_export_include_statement_final_group',
                    'feature_procedure_export_include_statement_released',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'feature_statement_data_input_orga',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_admin_statements_tag',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_preferences',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements_public_published',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_elements_use_negative_report',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_map_hint',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_all_orgas_invited',
                    'feature_procedure_export_include_assessment_table_anonymous',
                    'feature_procedure_export_include_assessment_table_original',
                    'feature_procedure_export_include_public_interest_bodies_member_list',
                    'feature_procedure_filter_external_public_participation_phase',
                    'feature_procedure_filter_external_public_participation_phase_permissionset',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_public_allowed_needs_verification',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_participation_area_always_citizen',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            'public agency worker #2'           => [
                'roles'                             => [Role::PUBLIC_AGENCY_WORKER],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_new_statement',
                    'feature_orga_registration',
                    'feature_participation_area_procedure_detail_map_use_get_legend_graphic',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statements_public',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'public agency worker #3'           => [
                'roles'                             => [Role::PUBLIC_AGENCY_WORKER],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_statements',
                    'area_statements_public',
                    'feature_new_statement',
                    'feature_procedure_single_document_upload_zip',
                    'feature_statement_data_input_orga',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_elements_use_negative_report',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_public',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Redakteur ###################
            'editor #1'                         => [
                'roles'                             => [Role::CONTENT_EDITOR],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_admin_faq',
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_mydata',
                    'area_portal_user',
                    'feature_map_use_location_relation',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_gislayer_global_edit',
                    'area_admin_globalnews',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_view_participants',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements_public_published',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_statements_public',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Anonymer User / Gast ###################
            'guest #1'                          => [
                'roles'                             => [Role::GUEST],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_map_search_location',
                    'feature_map_use_location_relation',
                    'feature_participation_area_procedure_detail_map_use_baselayerbox',
                    'feature_procedure_filter_external_orga_name',
                    'feature_procedure_filter_external_public_participation_phase',
                    'feature_procedure_filter_external_public_participation_phase_permissionset',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'feature_statement_public_allowed_needs_verification',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_view_participants',
                    'area_main_xplanning',
                    'area_participants_internal',
                    'area_participation_alternative_prompt',
                    'area_portal_user',
                    'area_preferences',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_software_licenses',
                    'area_statement_data_input_orga',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_admin_export_procedure',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_draft_statement_add_address_to_institutions',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_list_restricted_external_links',
                    'feature_map_use_drawing_tools',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_participation_area_procedure_detail_map_use_get_legend_graphic',
                    'feature_plain_language',
                    'feature_procedure_default_filter_extern',
                    'feature_procedure_default_filter_intern',
                    'feature_procedure_export_include_assessment_table_anonymous',
                    'feature_procedure_export_include_assessment_table_original',
                    'feature_procedure_export_include_public_interest_bodies_member_list',
                    'feature_procedure_export_include_public_statements',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_procedure_require_location',
                    'feature_procedure_single_document_upload_zip',
                    'feature_project_switcher',
                    'feature_project_switcher',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statement_to_entire_document',
                    'feature_statements_feedback_check_email',
                    'feature_statements_feedback_postal',
                    'feature_statements_like',
                    'feature_statements_like_may_like',
                    'feature_statements_represent_orga',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_file',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            // ################### Bürger ###################
            'citizen #1'                        => [
                'roles'                             => [Role::CITIZEN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => 'participation',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_portal_user',
                    'feature_admin_export_procedure',
                    'feature_draft_statement_citizen_immediate_submit',
                    'feature_map_use_drawing_tools',
                    'feature_participation_area_procedure_detail_map_use_baselayerbox',
                    'feature_procedure_export_include_public_statements',
                    'feature_procedure_filter_external_public_participation_phase',
                    'feature_procedure_filter_external_public_participation_phase_permissionset',
                    'feature_statement_public_allowed_needs_verification',
                    'feature_statements_vote_may_vote',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_dashboard',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_view_participants',
                    'area_main_xplanning',
                    'area_manage_segment_places',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_subscriptions',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_list_restricted_external_links',
                    'feature_map_hint',
                    'feature_map_layer_get_legend',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_default_filter_extern',
                    'feature_procedure_default_filter_intern',
                    'feature_procedure_export_include_assessment_table_anonymous',
                    'feature_procedure_export_include_assessment_table_original',
                    'feature_procedure_export_include_public_interest_bodies_member_list',
                    'feature_procedure_filter_internal_phase',
                    'feature_procedure_filter_internal_phase_permissionset',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedures_located_by_maintenance_service',
                    'feature_procedures_mark_participated',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_create_autofill_submitter_citizens',
                    'feature_statement_create_autofill_submitter_institutions',
                    'feature_statement_create_autofill_submitter_invited',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'citizen #2'                        => [
                'roles'                             => [Role::CITIZEN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => 'participation',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_main_procedures',
                    'area_mydata',
                    'feature_admin_export_procedure',
                    'feature_draft_statement_citizen_immediate_submit',
                    'feature_notification_citizen_statement_submitted',
                    'feature_procedure_sort_location',
                    'feature_procedure_sort_orga_name',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_consultations',
                    'area_admin_faq',
                    'area_admin_preferences',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_xplanning',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_software_licenses',
                    'area_statement_segmentation',
                    'area_statements',
                    'area_statements_final',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_list_restricted_external_links',
                    'feature_map_layer_legend_file',
                    'feature_new_statement', // Bürger werden nicht eingeladen
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_procedure_single_document_upload_zip',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_bulk_edit',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'citizen #3'                        => [
                'roles'                             => [Role::CITIZEN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => 'participation',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_mydata',
                    'area_statements_draft',
                    'area_statements_final',
                    'feature_admin_export_procedure',
                    'feature_documents_new_statement',
                    'feature_draft_statement_citizen_immediate_submit',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_statements_draft_relocate',
                    'feature_statements_final_email',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_xplanning',
                    'area_mydata_organisation',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements_released',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_admin_new_procedure',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_list_restricted_external_links',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_procedure_single_document_upload_zip',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            'citizen #4'                        => [
                'roles'                             => [Role::CITIZEN],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => 'closed',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_mydata',
                    'area_statements_draft',
                    'area_statements_final',
                    'feature_admin_export_procedure',
                    'feature_statements_final_email',
                    'field_statement_file',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                ],
                'featuresDenied'                    => [
                    'area_accessibility_explanation',
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_main_xplanning',
                    'area_mydata_organisation',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_data_input_orga',
                    'area_statement_segmentation',
                    'area_statements_released',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_admin_new_procedure',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_documents_new_statement',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_list_restricted_external_links',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_procedure_single_document_upload_zip',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statements_draft_relocate',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Support ###################
            'support #1'                        => [
                'roles'                             => [Role::PLATFORM_SUPPORT],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_portal_user',
                    'feature_orga_get',
                    'feature_organisation_user_list',
                    'feature_procedure_report_public_phase',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_analysis',
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_manage_departments',
                    'area_manage_orgas',
                    'area_manage_orgas_all',
                    'area_organisations_applications_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_assign_procedure_fachplaner_roles',
                    'feature_assign_procedure_invitable_institution_roles',
                    'feature_assign_system_roles',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_procedure_phase',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_department_add',
                    'feature_department_delete',
                    'feature_department_edit',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_add',
                    'feature_orga_delete',
                    'feature_orga_edit',
                    'feature_orga_edit_all_fields',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'feature_user_edit',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Moderator###################
            'forum moderator #1'                => [
                'roles'                             => [Role::BOARD_MODERATOR],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_demosplan',
                    'area_portal_user',
                    'feature_forum_dev_release_edit',
                    'feature_forum_thread_edit',
                    'feature_procedure_single_document_upload_zip',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participants_internal',
                    'area_participation_alternative_prompt',
                    'area_preferences',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_statements_released',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_get_base_data',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
            // ################### Fachplaner-Fachbehörde ###################
            'planning supporting department #1' => [
                'roles'                             => [Role::PLANNING_SUPPORTING_DEPARTMENT],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => true,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_admin',
                    'area_admin_dashboard',
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_procedure_single_document_upload_zip',
                    'feature_procedure_get_base_data',
                    'feature_statement_data_input_orga',
                    'feature_statement_meta_house_number_export',
                    'field_organisation_email_reviewer_admin',
                    'feature_statement_bulk_edit',
                    'field_statement_recommendation',
                ],
                'featuresDenied'                    => [
                    'area_admin_faq',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_data_input_orga',
                    'area_statement_segmentation',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_organisation_email_reviewer_admin',
                    'feature_plain_language',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_required_procedure_end_date',
                    'field_statement_county',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            // ################### Datenerfassung ###################
            'data input #1'                     => [
                'roles'                             => [Role::PROCEDURE_DATA_INPUT],
                'procedurePhase'                    => $this->getNonParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_statement_data_input_orga',
                    'feature_procedure_get_base_data',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_plain_language',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_bulk_edit',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statements_tag',
                    'feature_statements_vote_may_vote',
                    'feature_surveyvote_may_vote',
                    'feature_use_data_input_orga',
                    'field_customer_accessibility_explanation_edit',
                    'field_procedure_recommendation_version',
                    'field_required_procedure_end_date',
                    'field_send_final_email',
                    'field_statement_county',
                    'field_statement_memo',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_recommendation',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                ],
            ],
            'data input #2'                     => [
                'roles'                             => [Role::PROCEDURE_DATA_INPUT],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => '',
                'isInProcedure'                     => false,
                'ownsProcedure'                     => false,
                'isMember'                          => false,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'feature_procedure_single_document_upload_zip',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_faq',
                    'area_admin_procedures',
                    'area_admin_statement_list',
                    'area_customer_send_mail_to_users',
                    'area_customer_settings',
                    'area_gdpr_consent_revoke_page',
                    'area_institution_tag_manage',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_search_submitter_in_procedures',
                    'area_statement_segmentation',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_auto_switch_element_state',
                    'feature_auto_switch_procedure_news',
                    'feature_auto_switch_to_procedure_end_phase',
                    'feature_citizen_registration',
                    'feature_gdpr_consent_revoke_by_token',
                    'feature_has_logout_landing_page',
                    'feature_institution_tag_create',
                    'feature_institution_tag_delete',
                    'feature_institution_tag_read',
                    'feature_institution_tag_update',
                    'feature_optional_tag_propagation',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_planning_area_match',
                    'feature_procedure_preview',
                    'feature_require_locality_confirmation',
                    'feature_send_email_on_procedure_ending_phase',
                    'feature_statement_bulk_edit',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_to_entire_document',
                    'feature_statements_tag',
                    'feature_surveyvote_may_vote',
                    'field_customer_accessibility_explanation_edit',
                    'field_procedure_recommendation_version',
                    'field_required_procedure_end_date',
                    'field_send_final_email',
                    'field_statement_county',
                    'field_statement_memo',
                    'field_statement_municipality',
                    'field_statement_priority_area',
                    'field_statement_recommendation',
                    'field_statement_submitter_email_address',
                    'field_statement_user_group',
                    'field_statement_user_organisation',
                    'field_statement_user_position',
                    'field_statement_user_state',
                    'role_participant',
                    'field_statement_public_allowed',
                ],
            ],
        ];
    }

    protected function featureTestsBugTest(): array
    {
        return [
            [
                'roles'                             => [Role::CITIZEN],
                'procedurePhase'                    => $this->getParticipationPhases(),
                'procedurePublicParticipationPhase' => 'participation',
                'isInProcedure'                     => true,
                'ownsProcedure'                     => false,
                'isMember'                          => true,
                'featuresAllowed'                   => [
                    'area_data_protection_text',
                    'area_demosplan',
                    'area_globalnews',
                    'area_mydata',
                    'area_statements_draft',
                    'area_statements_final',
                    'feature_admin_export_procedure',
                    'feature_documents_new_statement',
                    'feature_map_new_statement',
                    'feature_new_statement',
                    'feature_statements_draft_relocate',
                    'feature_statements_final_email',
                    'field_statement_meta_city',
                    'field_statement_meta_postal_code',
                    'field_statement_meta_street',
                ],
                'featuresDenied'                    => [
                    'area_admin',
                    'area_admin_gislayer_global_edit',
                    'area_admin_statement_list',
                    'area_gdpr_consent_revoke_page',
                    'area_main_xplanning',
                    'area_mydata_organisation',
                    'area_participation_alternative_prompt',
                    'area_procedure_adjustments_general_location',
                    'area_procedure_send_submitter_email',
                    'area_statement_segmentation',
                    'area_statements_released',
                    'area_statements_released_group',
                    'area_survey_management',
                    'feature_admin_element_invitable_institution_or_public_authorisations',
                    'feature_admin_new_procedure',
                    'feature_citizen_registration',
                    'feature_has_logout_landing_page',
                    'feature_list_restricted_external_links',
                    'feature_orga_registration',
                    'feature_procedure_legal_notice_read',
                    'feature_procedure_legal_notice_write',
                    'feature_procedure_preview',
                    'feature_procedure_single_document_upload_zip',
                    'feature_require_locality_confirmation',
                    'feature_statement_data_input_orga',
                    'feature_statement_gdpr_consent',
                    'feature_statement_gdpr_consent_submit',
                    'feature_statement_publish_name',
                    'feature_statement_to_entire_document',
                    'feature_surveyvote_may_vote',
                    'field_statement_submitter_email_address',
                    'role_participant',
                ],
            ],
        ];
    }

    protected function getRoleGroupByRole(string $roleCode): string
    {
        $mapping = [
            Role::API_AI_COMMUNICATOR             => Role::GAICOM,
            Role::BOARD_MODERATOR                 => Role::GMODER,
            Role::CITIZEN                         => Role::GCITIZ,
            Role::CONTENT_EDITOR                  => Role::GTEDIT,
            Role::GUEST                           => Role::GGUEST,
            Role::ORGANISATION_ADMINISTRATION     => Role::GLAUTH,
            Role::PLANNING_AGENCY_ADMIN           => Role::GLAUTH,
            Role::PLANNING_AGENCY_WORKER          => Role::GLAUTH,
            Role::PLANNING_SUPPORTING_DEPARTMENT  => Role::GLAUTH,
            Role::PLATFORM_SUPPORT                => Role::GTSUPP,
            Role::PRIVATE_PLANNING_AGENCY         => Role::GLAUTH,
            Role::PROCEDURE_CONTROL_UNIT          => Role::GFALST,
            Role::PROCEDURE_DATA_INPUT            => Role::GDATA,
            Role::PROSPECT                        => Role::GINTPA,
            Role::PUBLIC_AGENCY_COORDINATION      => Role::GPSORG,
            Role::PUBLIC_AGENCY_WORKER            => Role::GPSORG,
        ];

        if (array_key_exists($roleCode, $mapping)) {
            return $mapping[$roleCode];
        }

        return '';
    }

    /**
     * 'roles' => [Role::RCITIZ],
     * 'procedurePhase' => $this->getParticipationPhases(),
     * 'procedurePublicParticipationPhase' => 'participation',
     * 'isInProcedure' => true,
     * 'ownsProcedure' => false,
     * 'isMember' => true,
     * 'featuresAllowed' => [
     * ],.
     */

    /**
     * @dataProvider permissionsTests
     */
    public function testPermissions(
        array $roles,
        string $procedurePhases,
        string $procedurePublicParticipationPhase,
        bool $isInProcedure,
        bool $ownsProcedure,
        bool $isMember,
        array $allowedPermissions,
        array $disallowedPermissions,
    ): void {
        // do debug a specific permission enable debugging and paste dataset name
        $debugPermission = false;
        if ($debugPermission && 'guest #1' !== $this->dataName()) {
            return;
        }

        // check whether some code is written for a role that is not allowed in project
        // only do this check if we're in a project permissions test
        if (__CLASS__ !== static::class) {
            foreach ($roles as $role) {
                if (!in_array($role, self::$rolesAllowed, true)) {
                    $this->addWarning('Project does not support role '.$role.'. Testcase may be deleted');
                }
                self::$testedRoles[] = $role;
            }
        }

        $inviteOrgaForDataInput = in_array(Role::PROCEDURE_DATA_INPUT, $roles);

        $this->setUpSessionForTestCase($isInProcedure, $ownsProcedure, $isMember);

        $user = $this->getTestUser(compact('roles', 'ownsProcedure'));

        // setze die Phase
        if (0 < strpos($procedurePhases, '||')) {
            $procedurePhases = explode('||', $procedurePhases);
        } else {
            $procedurePhases = [$procedurePhases];
        }

        foreach ($procedurePhases as $procedurePhase) {
            $procedureMock = $this->setUpProcedureForTestCase(
                $procedurePublicParticipationPhase,
                $isInProcedure,
                $isMember,
                $procedurePhase,
                $user,
                $inviteOrgaForDataInput
            );

            $this->permissions = $this->getPermissionsInstance($ownsProcedure, $inviteOrgaForDataInput);
            $this->permissions->setProcedure($procedureMock);
            if (null !== $procedureMock) {
                $procedureRepositoryMock = $this->setUpProcedureRepositoryForTestCase($procedureMock);
                $this->permissions->setProcedureRepository($procedureRepositoryMock);
            }
            $this->permissions->initPermissions($user);
            if ($isInProcedure) {
                $this->permissions->checkProcedurePermission();
            }

            $failureMetaData = compact(
                'roles',
                'procedurePhases',
                'procedurePublicParticipationPhase',
                'isInProcedure',
                'ownsProcedure',
                'isMember'
            );

            foreach ($allowedPermissions as $permission) {
                $this->doTestSinglePermission($permission, true, $failureMetaData);
            }

            foreach ($disallowedPermissions as $permission) {
                $this->doTestSinglePermission($permission, false, $failureMetaData);
            }
        }
    }

    /**
     * Create a mock testuser.
     *
     * @param array $testCase
     *
     * @return User|PHPUnit_Framework_MockObject_MockObject
     */
    protected function getTestUser($testCase)
    {
        // set up data returned by user mock
        $orgaIdForStub = '123';
        if ($testCase['ownsProcedure']) {
            $orgaIdForStub = $this->procedure['orgaId'];
        }
        $orgaStub = $this->getOrgaStub($testCase['roles'], $orgaIdForStub);
        $dplanRoles = new ArrayCollection($this->getRoleEntities($testCase['roles']));
        $loggedIn = !in_array(Role::GUEST, $testCase['roles'], true);
        $rolesInCustomers = $this->getRoleInCustomersMocks($testCase['roles']);

        // create the mock

        $userMockMethods = [
            MockMethodDefinition::withReturnValue(
                'getRoleInCustomers', $rolesInCustomers, 'roleInCustomers'
            ),
            MockMethodDefinition::withReturnValue('getDplanroles', $dplanRoles),
            MockMethodDefinition::withReturnValue('getRoles', $testCase['roles']),
            MockMethodDefinition::withReturnValue('getOrganisationId', $orgaIdForStub),
            MockMethodDefinition::withReturnValue('getOrga', $orgaStub, 'orga'),
            MockMethodDefinition::withReturnValue('isLoggedIn', $loggedIn),
            MockMethodDefinition::withCalledReturn(
                'hasRole',
                static function ($roleString) use ($testCase) {
                    return in_array($roleString, $testCase['roles'], true);
                }
            ),
            MockMethodDefinition::withCalledReturn(
                'hasAnyOfRoles',
                static function ($roles) use ($testCase) {
                    foreach ($roles as $role) {
                        if (in_array($role, $testCase['roles'], true)) {
                            return true;
                        }
                    }

                    return false;
                }
            ),
            MockMethodDefinition::withCalledReturn(
                'isPublicUser',
                static function () use ($testCase) {
                    $publicRoles = [
                        Role::CITIZEN,
                        Role::GUEST,
                    ];
                    foreach ($publicRoles as $role) {
                        if (in_array($role, $testCase['roles'], true)) {
                            return true;
                        }
                    }

                    return false;
                }
            ),
            MockMethodDefinition::withCalledReturn(
                'isPlanner',
                static function () use ($testCase) {
                    $plannerRoles = [
                        Role::PLANNING_AGENCY_ADMIN,
                        Role::PLANNING_AGENCY_WORKER,
                        Role::PRIVATE_PLANNING_AGENCY,
                        Role::HEARING_AUTHORITY_ADMIN,
                        Role::HEARING_AUTHORITY_WORKER,
                    ];
                    foreach ($plannerRoles as $role) {
                        if (in_array($role, $testCase['roles'], true)) {
                            return true;
                        }
                    }

                    return false;
                }
            ),
            MockMethodDefinition::withCalledReturn(
                'isPublicAgency',
                static function () use ($testCase) {
                    $plannerRoles = [
                        Role::PUBLIC_AGENCY_COORDINATION,
                        Role::PUBLIC_AGENCY_WORKER,
                    ];
                    foreach ($plannerRoles as $role) {
                        if (in_array($role, $testCase['roles'], true)) {
                            return true;
                        }
                    }

                    return false;
                }
            ),
        ];

        return $this->getMock(User::class, $userMockMethods);
    }

    /**
     * @param array<int, string> $roleCodes
     *
     * @return Collection<int, UserRoleInCustomer>
     */
    protected function getRoleInCustomersMocks(array $roleCodes): Collection
    {
        $roleInCustomers = array_map(function (string $roleCode): UserRoleInCustomer {
            $roleCodeDefinition = MockMethodDefinition::withReturnValue(
                'getCode', $roleCode, 'code'
            );
            $roleMock = $this->getMock(Role::class, [$roleCodeDefinition]);
            $roleDefinition = MockMethodDefinition::withReturnValue(
                'getRole', $roleMock, 'role'
            );
            $customerDefinition = MockMethodDefinition::withReturnValue(
                'getCustomer',
                $this->getCustomerReference(LoadCustomerData::HINDSIGHT),
                'customer'
            );

            return $this->getMock(
                UserRoleInCustomer::class,
                [$roleDefinition, $customerDefinition]
            );
        }, $roleCodes);

        return new ArrayCollection($roleInCustomers);
    }

    protected function getOrgaStub(array $roles, string $orgaId): Orga
    {
        // mock OrgaType for user orga
        $orgaType = [];
        foreach ($roles as $role) {
            foreach (OrgaType::ORGATYPE_ROLE as $orgaTypeRole) {
                if (in_array($role, $orgaType, true)) {
                    $orgaType[] = $orgaTypeRole;
                }
            }
        }

        $orgaMockMethods = [
            MockMethodDefinition::withReturnValue('getTypes', $orgaType),
            MockMethodDefinition::withReturnValue('getId', $orgaId, 'id'),
        ];

        return $this->getMock(Orga::class, $orgaMockMethods);
    }

    /**
     * Creates Role Instances from String.
     *
     * @param string[] $roles
     *
     * @return Role[] array
     */
    protected function getRoleEntities($roles): array
    {
        $roleEntities = [
            Role::BOARD_MODERATOR                 => (new Role())->setCode(Role::BOARD_MODERATOR)->setGroupCode(Role::GMODER),
            Role::CITIZEN                         => (new Role())->setCode(Role::CITIZEN)->setGroupCode(Role::GCITIZ),
            Role::CONTENT_EDITOR                  => (new Role())->setCode(Role::CONTENT_EDITOR)->setGroupCode(Role::GTEDIT),
            Role::GUEST                           => (new Role())->setCode(Role::GUEST)->setGroupCode(Role::GGUEST),
            Role::ORGANISATION_ADMINISTRATION     => (new Role())->setCode(Role::ORGANISATION_ADMINISTRATION)->setGroupCode(Role::GLAUTH),
            Role::PLANNING_AGENCY_ADMIN           => (new Role())->setCode(Role::PLANNING_AGENCY_ADMIN)->setGroupCode(Role::GLAUTH),
            Role::PLANNING_AGENCY_WORKER          => (new Role())->setCode(Role::PLANNING_AGENCY_WORKER)->setGroupCode(Role::GLAUTH),
            Role::PLANNING_SUPPORTING_DEPARTMENT  => (new Role())->setCode(Role::PLANNING_SUPPORTING_DEPARTMENT)->setGroupCode(Role::GLAUTH),
            Role::PLATFORM_SUPPORT                => (new Role())->setCode(Role::PLATFORM_SUPPORT)->setGroupCode(Role::GTSUPP),
            Role::PRIVATE_PLANNING_AGENCY         => (new Role())->setCode(Role::PRIVATE_PLANNING_AGENCY)->setGroupCode(Role::GLAUTH),
            Role::PROCEDURE_CONTROL_UNIT          => (new Role())->setCode(Role::PROCEDURE_CONTROL_UNIT)->setGroupCode(Role::GFALST),
            Role::PROCEDURE_DATA_INPUT            => (new Role())->setCode(Role::PROCEDURE_DATA_INPUT)->setGroupCode(Role::GDATA),
            Role::PROSPECT                        => (new Role())->setCode(Role::PROSPECT)->setGroupCode(Role::GINTPA),
            Role::PUBLIC_AGENCY_COORDINATION      => (new Role())->setCode(Role::PUBLIC_AGENCY_COORDINATION)->setGroupCode(Role::GPSORG),
            Role::HEARING_AUTHORITY_ADMIN         => (new Role())->setCode(Role::HEARING_AUTHORITY_ADMIN)->setGroupCode(Role::GHEAUT),
        ];

        $currentRoles = [];
        foreach ($roles as $role) {
            $currentRoles[] = array_key_exists($role, $roleEntities) ? $roleEntities[$role] : '';
        }

        return $currentRoles;
    }

    /**
     * @param string $procedurePhase
     * @param array  $testCase
     * @param User   $user
     *
     * @return Procedure|MockObject
     */
    protected function getProcedureMock($procedurePhase, $testCase, $user)
    {
        $stub = $this->getMockBuilder(
            Procedure::class
        )
            ->disableOriginalConstructor()
            ->getMock();

        $stub->method('getAuthorizedUsers')
            ->willReturn($this->procedure['authorizedUsers'] ?? new ArrayCollection());
        $stub->method('getId')
            ->willReturn($this->procedure['id'] ?? '');
        $stub->method('getPlanningOfficesIds')
            ->willReturn($this->procedure['planningOfficesIds'] ?? []);
        $stub->method('getPhase')
            ->willReturn($procedurePhase);
        $stub->method('getPublicParticipationPhase')
            ->willReturn($this->procedure['publicParticipationPhase']);
        $stub->method('getOrgaId')
            ->willReturn($this->procedure['orgaId']);
        $stub->method('isDeleted')
            ->willReturn(false);

        if ($testCase['isMember']) {
            $stub->method('getOrganisation')
                ->willReturn(new ArrayCollection([$user->getOrganisationId()]));
            $stub->method('getOrganisationIds')
                ->willReturn([$user->getOrganisationId()]);
        } else {
            $stub->method('getOrganisation')
                ->willReturn(new ArrayCollection());
            $stub->method('getOrganisationIds')
                ->willReturn([]);
        }

        return $stub;
    }

    protected function setProcedureIsForeign(): void
    {
        $this->procedure['orgaId'] = $this->foreignOrgaId;
    }

    protected function setProcedureIsOwn(): void
    {
        $this->procedure['orgaId'] = $this->userOrgaId;
        // Setze die PlanungsbüroID
        $this->procedure['planningOffices'][0]['ident'] = $this->userOrgaId;
        $this->procedure['planningOfficesIds'][0] = $this->userOrgaId;
    }

    protected function setProcedureIsMember(): void
    {
        $this->procedure['organisation'] = [$this->userOrgaId];
    }

    protected function setProcedureIsNoMember(): void
    {
        $this->procedure['organisation'] = [$this->foreignOrgaId];
    }

    /**
     * Gibt die Beteiligungsphasen zurück.
     */
    protected function getParticipationPhases(): string
    {
        return 'participation';
    }

    /**
     * Gibt die Phasen zurück, in denen nicht beteiligt wird.
     */
    protected function getNonParticipationPhases(): string
    {
        return 'evaluating||closed';
    }

    protected function setUpSessionForTestCase(bool $isInProcedure, bool $ownsProcedure, bool $isMember): void
    {
        if ($isInProcedure) {
            $this->setProcedureIsMember();
        } else {
            $this->procedure = [];
        }

        if ($ownsProcedure) {
            $this->setProcedureIsOwn();
        } else {
            $this->setProcedureIsForeign();
        }

        if ($isMember) {
            $this->setProcedureIsMember();
        } else {
            $this->setProcedureIsNoMember();
        }
    }

    protected function doTestSinglePermission(string $permission, bool $isAllowed, array $failureMetaData): void
    {
        $phases = implode(', ', $failureMetaData['procedurePhases']);
        if ('' !== $failureMetaData['procedurePublicParticipationPhase']) {
            $phases .= "(participation phase: {$failureMetaData['procedurePublicParticipationPhase']})";
        }

        $procedureInfo = [
            sprintf('[%s] ownsProcedure', $failureMetaData['ownsProcedure'] ? 'x' : ' '),
            sprintf('[%s] isInProcedure', $failureMetaData['isInProcedure'] ? 'x' : ' '),
            sprintf('[%s] isMember', $failureMetaData['isMember'] ? 'x' : ' '),
        ];

        $roleClassReflection = new ReflectionClass(Role::class);
        $roleConstants = $roleClassReflection->getConstants();
        $roleConstants = array_filter($roleConstants, static function ($v) {
            return is_string($v) || is_int($v);
        });
        $roleConstants = array_flip($roleConstants);

        $roles = [];

        foreach ($failureMetaData['roles'] as $roleName) {
            $roles[] = $roleConstants[$roleName];
        }

        $failureMessage = sprintf(
            "Expected `%s` to be %s,\nRoles: %s\nPhases: %s\nProcedure info:\n\t%s\n",
            $permission,
            ($isAllowed) ? 'enabled' : 'disabled',
            implode(', ', $roles),
            $phases,
            implode("\n\t", $procedureInfo)
        );

        self::assertSame($isAllowed, $this->permissions->hasPermission($permission), $failureMessage);
    }

    /**
     * @return Procedure|PHPUnit_Framework_MockObject_MockObject|null
     */
    protected function setUpProcedureForTestCase(string $procedurePublicParticipationPhase, bool $isInProcedure, bool $isMember, string $procedurePhase, User $user, bool $inviteOrgaForDataInput)
    {
        $this->procedure['phase'] = $procedurePhase;
        $this->procedure['publicParticipationPhase'] = $procedurePublicParticipationPhase;

        $procedureMock = null;
        if ($isInProcedure || $inviteOrgaForDataInput) {
            $procedureMock = $this->getProcedureMock(
                $procedurePhase,
                ['isMember' => $isMember],
                $user
            );

            if ($inviteOrgaForDataInput) {
                $procedureMock->method('getDataInputOrgaIds')
                    ->willReturn(['123']);
            }
        }

        return $procedureMock;
    }

    protected function setUpProcedureRepositoryForTestCase($procedureMock): ProcedureRepository
    {
        return $this->getProcedureRepositoryMock($procedureMock->getOrganisationIds());
    }

    protected function getProcedureRepositoryMock(?array $invitedOrgas = []): ProcedureRepository
    {
        $procedureRepositoryMock = $this->getMockBuilder(
            ProcedureRepository::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $procedureRepositoryMock->method('getInvitedOrgaIds')
            ->willReturn($invitedOrgas);

        return $procedureRepositoryMock;
    }
}
