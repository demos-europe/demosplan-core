<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionIdentifierInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Exception\PermissionException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_key_exists;
use function collect;
use function is_array;

/**
 * Zentrale Berechtigungssteuerung fuer Funktionen.
 */
class Permissions implements PermissionsInterface, PermissionEvaluatorInterface
{
    final public const PERMISSIONS_YML = 'permissions.yml';

    final public const PROCEDURE_PERMISSIONSET_READ = 'read';
    final public const PROCEDURE_PERMISSIONSET_WRITE = 'write';
    final public const PROCEDURE_PERMISSIONSET_HIDDEN = 'hidden';

    final public const PROCEDURE_PERMISSION_SCOPE_NONE = 'none';
    final public const PROCEDURE_PERMISSION_SCOPE_INTERNAL = 'internal';
    final public const PROCEDURE_PERMISSION_SCOPE_EXTERNAL = 'external';

    /**
     * @var Procedure|null
     */
    protected $procedure;

    /**
     * @var array<string, Permission>
     */
    protected $permissions = [];

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var GlobalConfigInterface|GlobalConfig
     */
    protected $globalConfig;

    /**
     * @deprecated This variable is used to minimize changes in permission handling to keep it easier
     *             to remove this in future refactorings.
     *             It will be refactored to be used by SecurityVoters soon but this would
     *             bloat current implementation too much
     *
     * @var bool
     */
    private $userInvitedInProcedure = false;

    /**
     * @var ProcedureAccessEvaluator
     */
    protected $procedureAccessEvaluator;

    /**
     * @var array<non-empty-string, PermissionInitializerInterface>
     */
    private readonly array $addonPermissionInitializers;

    /**
     * Permissions loaded from addons.
     *
     * This property is initialized when {@link self::setInitialPermissions()} is called.
     *
     * @var array<non-empty-string, ResolvablePermissionCollection> mapping from addon name to permissions
     */
    private array $addonPermissionCollections = [];

    public function __construct(
        AddonRegistry $addonRegistry,
        private readonly CustomerService $currentCustomerProvider,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        private readonly PermissionCollectionInterface $corePermissions,
        private readonly PermissionResolver $permissionResolver,
        ProcedureAccessEvaluator $procedureAccessEvaluator,
        private ProcedureRepository $procedureRepository,
        private readonly ValidatorInterface $validator,
        private readonly AccessControlService $accessControlPermission
    ) {
        $this->addonPermissionInitializers = $addonRegistry->getPermissionInitializers();
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
    }

    /**
     * Initialisiere die Permissions.
     */
    public function initPermissions(UserInterface $user): PermissionsInterface
    {
        $this->user = $user;

        $this->setInitialPermissions();

        // set Permissions which are user independent
        $this->setPlatformPermissions();

        // set Permissions which are dependent on role but independent of procedure
        $this->setGlobalPermissions();

        // set Permissions which are store in DB
        $this->loadDynamicPermissions();

        return $this;
    }

    public function loadDynamicPermissions(): void
    {
        // In this case, permission is not core permission, then check if permission is DB in table access_control_permissions

        $permissions = $this->accessControlPermission->getPermissions($this->user->getOrga(), $this->user->getCurrentCustomer(), $this->user->getRoles());

        if (!empty($permissions)) {
            $this->enablePermissions($permissions);
        }
    }

    /**
     * @deprecated see deprecation on property userInvitedInProcedure
     */
    public function evaluateUserInvitedInProcedure(array $invitedProcedures): void
    {
        if (\in_array($this->procedure?->getId(), $invitedProcedures, true)) {
            $this->userInvitedInProcedure = true;
        }
    }

    /**
     * set Permissions which are user independent.
     */
    protected function setPlatformPermissions(): void
    {
        $this->enablePermissions([
            'area_combined_participation_area',
            'area_data_protection_text',
            'area_demosplan',
            'area_imprint_text',
            'area_main_file',
            'area_main_procedures',
            'area_mydata',
            'area_participants_internal',
            'area_portal_user',
            'feature_assessmenttable_export',
            'feature_data_protection_text_customized_view',
            'feature_documents_category_use_file',
            'feature_documents_category_use_paragraph',
            'feature_imprint_text_customized_view',
            'feature_institution_participation',
            'feature_json_api_get',
            'feature_json_api_list',
            'feature_json_rpc_post',
            'feature_map_search_location',
            'feature_map_use_drawing_tools',
            'feature_map_use_location_relation',
            'feature_original_statements_export',
            'feature_participation_area_procedure_detail_map_use_baselayerbox',
            'feature_procedure_filter_any',
            'feature_procedure_filter_external_orga_name',
            'feature_procedure_single_document_upload_zip',
            'feature_procedure_sort_any',
            'feature_procedure_sort_location',
            'feature_procedure_sort_orga_name',
            'feature_send_final_email_cc_to_self',
            'feature_statement_meta_house_number_export',
            'feature_statements_draft_email',
            'feature_statements_draft_release',
            'feature_statements_final_email',
            'feature_statements_released_email',
            'feature_statements_released_group_email',
            'field_county_list',
            'field_municipality_list',
            'field_news_pdf',
            'field_priority_area_list',
            'field_procedure_administration',
            'field_procedure_documents',
            'field_procedure_name',
            'field_procedure_paragraphs',
            'field_procedure_phase',
            'field_procedure_recommendation_version',
            'field_procedure_single_document_title',
            'field_statement_extern_id',
            'field_statement_feedback',
            'field_statement_file',
            'field_statement_meta_address',
            'field_statement_meta_case_worker_name',
            'field_statement_meta_city',
            'field_statement_meta_email',
            'field_statement_meta_orga_department_name',
            'field_statement_meta_orga_name',
            'field_statement_meta_postal_code',
            'field_statement_meta_submit_name',
            'field_statement_phase',
            'field_statement_priority',
            'field_statement_status',
            'field_statement_submit_type',
            'field_statement_text',
        ]);
    }

    /**
     * Setze die Rechte der Rollen, die unabhängig von Permissionsets sind
     * (in der Regel außerhalb von Verfahren).
     */
    protected function setGlobalPermissions(): void
    {
        if ($this->user->hasAnyOfRoles(
            [
                Role::PUBLIC_AGENCY_COORDINATION,
                Role::PUBLIC_AGENCY_SUPPORT,
                Role::PUBLIC_AGENCY_WORKER,
            ]
        )) {
            $this->enablePermissions([
                'feature_procedure_export_include_statement_final_group',
                'feature_procedure_export_include_statement_released',
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::ORGANISATION_ADMINISTRATION)) {         // Fachplaner-Masteruser GLAUTH Kommune
            $this->enablePermissions([
                'area_manage_departments',  // Abteilungen
                'area_manage_orgadata',  // Daten der eignen Organisation verwalten
                'area_mydata_organisation',  // Daten der Organisation
                'area_organisations_view_of_customer',
                'area_preferences',  // Einstellungen
                'feature_json_api_create',
                'feature_json_api_delete',
                'feature_json_api_list',
                'feature_json_api_update',
                'feature_orga_edit_all_fields',
                'feature_orga_get',
                // In contrast to the permission "area_organisations", the permission "feature_organisation_user_list"
                // allows the display of the organisation list, but does not grant access to the "Organisation" item in the menu
                'feature_organisation_user_list',
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::PLANNING_AGENCY_ADMIN)) {
            $this->enablePermissions([
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasAnyOfRoles([Role::PLANNING_AGENCY_ADMIN, Role::HEARING_AUTHORITY_ADMIN])) {         // Fachplaner-Admin GLAUTH Kommune
            $this->enablePermissions([
                'area_admin_procedures',  // Verfahren verwalten
                'area_manage_orgadata',  // Daten der Organisation
                'area_mydata_organisation',  // Daten der Organisation
                'area_preferences',  // Einstellungen
                'feature_admin_delete_procedure',  // Verfahren loeschen
                'feature_admin_export_procedure',  // Verfahren exportieren
                'feature_json_api_get', // allow get requests to generic api
            ]);
        }

        if ($this->user->hasRole(Role::PRIVATE_PLANNING_AGENCY)) {         // Fachplaner-Planungsbüro GLAUTH Kommune
            $this->enablePermissions([
                'area_admin_procedures',  // Verfahren verwalten
                'area_manage_orgadata',  // Daten der Organisation
                'area_mydata_organisation',  // Daten der Organisation
                'area_preferences',  // Einstellungen
                'feature_admin_export_procedure',  // Verfahren exportieren

                // kann empfehlungen abgeben aber nicht die Bearbeitung abschliessen
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::PLANNING_SUPPORTING_DEPARTMENT)) {         // Fachplaner-Fachbehörde GLAUTH Kommune
            $this->enablePermissions([
                'area_manage_orgadata',  // Daten der Organisation
                'field_statement_recommendation',
                'field_organisation_email_reviewer_admin',  // Email for notifications for reviwer admin
            ]);
        }

        if ($this->user->hasRole(Role::PLANNING_AGENCY_WORKER)) {
            $this->enablePermissions([
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasAnyOfRoles([Role::PLANNING_AGENCY_WORKER, Role::HEARING_AUTHORITY_WORKER])) {         // Fachplaner-Sachbearbeiter GLAUTH Kommune
            $this->enablePermissions([
                'area_admin_procedures',  // Verfahren verwalten
                'area_mydata_organisation',  // Organisation sehen
                'area_preferences',  // Einstellungen
                'feature_admin_export_procedure',  // Verfahren exportieren
                'feature_json_api_get', // allow get requests to generic api
            ]);
        }

        if ($this->user->hasAnyOfRoles([Role::PLANNING_AGENCY_ADMIN, Role::HEARING_AUTHORITY_ADMIN, Role::HEARING_AUTHORITY_WORKER, Role::PLANNING_AGENCY_WORKER, Role::PRIVATE_PLANNING_AGENCY])) {
            // Enable procedure report sections
            $this->enablePermissions([
                'area_admin_procedures',  // Verfahren verwalten
                'feature_procedure_report_general',
                'feature_procedure_report_statements',
            ]);
        }

        if ($this->user->hasRole(Role::PUBLIC_AGENCY_COORDINATION)) {         // Institutions-Koordination GPSORG
            $this->enablePermissions([
                'area_manage_orgadata',  // Daten der Organisation
                'area_mydata_organisation',  // Daten der Organisation
                'feature_admin_export_procedure',  // Verfahren exportieren
                'feature_statements_vote_may_vote',
            ]);
        }

        if ($this->user->hasAnyOfRoles([Role::PUBLIC_AGENCY_WORKER, Role::PUBLIC_AGENCY_COORDINATION])) { // Institutions-Koordination oder Institutions-Sachbearbeitung
            $this->enablePermissions([
                'area_mydata_organisation',  // Organisation sehen
                'feature_admin_export_procedure',  // Verfahren exportieren
                'feature_procedure_filter_internal_phase', // sort for internal phases in procedure list
                'feature_procedure_filter_internal_phase_permissionset', // filter for internal phases permissionset in procedure list
            ]);

            // double role invitable institution and planner
            if ($this->user->hasAnyOfRoles([Role::PLANNING_AGENCY_ADMIN, Role::PLANNING_AGENCY_WORKER])) { // Fachplaner-Admin oder Fachplaner-Sachbearbeiter
                $this->disablePermissions([
                    'feature_procedure_filter_internal_phase',  // filter for internal phases in procedure list
                    'feature_procedure_filter_internal_phase_permissionset',  // filter for internal phases permissionset in procedure list
                ]);
            }
        }

        if ($this->user->hasAnyOfRoles([
            Role::GUEST, // Gast GGUEST
            Role::PROSPECT, // GINTPA Interessent
        ])) {
            $this->enablePermissions([
                'feature_procedure_filter_external_public_participation_phase',  // Filter public participation phase in procedure list
                'feature_procedure_filter_external_public_participation_phase_permissionset',  // Filter public participation phase permissionset in procedure list
                'feature_statement_public_allowed_needs_verification',  // Publishing statements needs verification
                'field_statement_recommendation',
            ]);

            $this->disablePermissions([
                'area_main_procedures',  // Menüitem Planverfahren
                'area_mydata',  // Meine Daten
                'area_participants_internal',  // Übersicht Teilnehmende intern
                'area_portal_user',  // Portal des Users
                'feature_map_use_drawing_tools',  // Einzeichnungen in der Karte vornehmen
                'feature_procedure_single_document_upload_zip',  // guests are not allowed to upload documents into procedures at all, hence do not allow for zip upload
                'field_statement_file',  // Dokument hochladen (beim Abgeben einer STN)
            ]);
        }

        if ($this->user->hasRole(Role::CUSTOMER_MASTER_USER)) {
            $this->enablePermissions([
                'area_organisations',
                'area_organisations_view_of_customer',
                'area_preferences',  // Einstellungen
                'feature_orga_edit',
                'feature_orga_edit_all_fields',
                // In contrast to the permission "area_organisations", the permission "feature_organisation_user_list"
                // allows the display of the organisation list, but does not grant access to the "Organisation" item in the menu
                'feature_organisation_user_list',
                'feature_procedure_report_public_phase',
                'field_data_protection_text_customized_edit_customer',
                'field_imprint_text_customized_edit_customer',
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::PLATFORM_SUPPORT)) {         // Verfahrenssupport GTSUPP Verfahrenssupport
            $this->enablePermissions([
                'area_admin_contextual_help_edit',  // Globale Kontexthilfe bearbeiten
                'area_manage_orgadata',  // Abteilungenverwalten
                'area_mydata_organisation',  // Daten der Organisation
                'area_organisations',
                'area_organisations_view',
                'area_organisations_view_of_customer',
                'area_platformtools',  // Menübereich Plattformtools
                'area_preferences',  // Einstellungen
                'area_statistics',  // Statistiken
                'feature_orga_get',
                // In contrast to the permission "area_organisations", the permission "feature_organisation_user_list"
                // allows the display of the organisation list, but does not grant access to the "Organisation" item in the menu
                'feature_organisation_user_list',
                'feature_procedure_report_public_phase',
                'field_data_protection_text_customized_edit_customer',
                'field_imprint_text_customized_edit_customer',
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::CONTENT_EDITOR)) { // Redakteur Global News
            $this->enablePermissions([
                'area_admin_contextual_help_edit',  // Globale Kontexthilfe bearbeiten
                'area_admin_faq',  // Verwalten
                'feature_json_api_update', // needed to administrate FAQ items
                'area_platformtools',
                'area_preferences',  // Einstellungen
                'field_statement_recommendation',
            ]);
        }

        if ($this->user->hasRole(Role::CITIZEN)) { // angemeldeter Bürger
            $this->enablePermissions([
                'feature_admin_export_procedure',  // Verfahren exportieren
                'feature_admin_export_procedure_in_detail_view',  // Verfahren exportieren in der Detailseite
                'feature_draft_statement_citizen_immediate_submit',
                'feature_notification_citizen_statement_submitted',  // Notification on submitting a new statement
                'feature_procedure_export_include_public_statements',
                'feature_procedure_filter_external_public_participation_phase',  // Filter public participation phase in procedure list
                'feature_procedure_filter_external_public_participation_phase_permissionset',  // Filter public participation phase permissionset in procedure list
                'feature_statement_public_allowed_needs_verification',  // Publishing statements needs verification
                'feature_statements_vote_may_vote',  // May vote other citizens statements
                'field_statement_recommendation',
            ]);

            $this->disablePermissions([
                'feature_procedure_single_document_upload_zip',  // citizens are not allowed to upload documents into procedures at all, hence do not allow for zip upload
            ]);
        }

        if ($this->user->hasRole(Role::BOARD_MODERATOR)) { // Moderator
            $this->enablePermissions([
                'feature_forum_dev_release_edit',  // Release für Weiterentwicklung bearbeiten
                'feature_forum_dev_story_edit', // UserStory für Weiterentwicklung bearbeiten
                'feature_forum_thread_edit',  // einen Thread im Forum bearbeiten
                'field_statement_recommendation',
            ]);

            $this->disablePermissions([
                'area_participants_internal',  // Übersicht Teilnehmende intern
            ]);
        }

        if ($this->user->hasRole(Role::PROCEDURE_CONTROL_UNIT)) { // fachliche Leitstelle
            $this->enablePermissions([
                'field_statement_recommendation',
            ]);
            $this->disablePermissions([
                'area_participants_internal',  // Übersicht Teilnehmende intern
            ]);
        }

        if ($this->user->hasRole(Role::PROCEDURE_DATA_INPUT)) { // Datenerfassung
            $this->enablePermissions([
                'area_statement_data_input_orga',  // Create new submitted statements
                'feature_procedure_get_base_data',  // receive basic procedure data
            ]);

            $this->disablePermissions([
                'field_procedure_recommendation_version', // ältere Abwägungsempfehlungen
                'field_send_final_email', // Schlussmitteilung versenden
            ]);
        }

        if ($this->user->hasRole(Role::API_AI_COMMUNICATOR)) {
            // disable all permissions
            $this->permissions = \array_map(
                static function (Permission $permission) {
                    $permission->disable();

                    return $permission;
                }, $this->permissions);

            // enable ai specific permissions
            $this->enablePermissions([
                'area_main_file',
                'feature_read_source_statement_via_api',
                'field_statement_recommendation',
            ]);
        }
    }

    /**
     * Setze die Rechte, die ein Verfahren betreffen.
     */
    public function setProcedurePermissions(): void
    {
        // Ist Inhaberin des Verfahrens. Nur FP*-Rollen
        if ($this->ownsProcedure()) {
            $this->logger->debug('User owns Procedure');

            $this->enablePermissions([
                'area_admin',  // Verwalten
                'area_admin_dashboard',  // Übersichtsseite
                'area_admin_map',  // Verwalten Planzeichnung
                'area_admin_map_description',  // Verwalten Planzeichenerklärung
                'area_admin_news',  // Verwalten Aktuelles (im Verfahren)
                'area_admin_paragraphed_document',  // Verwalten Begruendung + Textliche Festsettung/Verordnung
                'area_admin_preferences',  // Verwalten Allgemeine Einstellungen
                'area_admin_protocol',  // Verwalten Protokoll
                'area_admin_single_document',  // Verwalten Planungsdokumente
                'feature_export_protocol',
                'feature_json_api_create',
                'feature_json_api_delete',
                'feature_procedure_change_phase',  // Change procedure phase
                'feature_procedure_get_base_data', // receive basic procedure Data
                'feature_statement_bulk_edit', // edit multiple statements at once
                'feature_statement_data_input_orga',  // Create new submitted Statement
                'field_statement_memo',
            ]);

            if ($this->user->hasRole(Role::PLANNING_AGENCY_ADMIN)) { // Fachplaner-Admin
                $this->enablePermissions([
                    'feature_procedure_user_restrict_access_edit',  // edit user restrict access when config variable hasProcedureUserRestrictedAccess is set
                ]);
            }
        }

        // ist eingeladen in das Verfahren als Institution oder Öffentlichkeit RP* und RCITIZ und RGUEST
        if ($this->isMember()) {
            $scope = self::PROCEDURE_PERMISSION_SCOPE_NONE;

            // Institutions-Sachbearbeiter oder Institutions-Koordinator
            if ($this->user->hasAnyOfRoles([Role::PUBLIC_AGENCY_WORKER, Role::PUBLIC_AGENCY_COORDINATION])) {
                $scope = self::PROCEDURE_PERMISSION_SCOPE_INTERNAL;
            } elseif ($this->user->isPublicUser()) {
                $scope = self::PROCEDURE_PERMISSION_SCOPE_EXTERNAL;
            }

            $this->logger->debug('User scope: '.$scope);

            $permissionset = $this->getPermissionset($scope);
            // User hat Leserechte auf das Verfahren
            if (self::PROCEDURE_PERMISSIONSET_READ === $permissionset) {
                $this->setProcedurePermissionsetRead();
                $this->logger->debug('Set Permissionset Read');
            }
            // User hat Leserechte und Schreibrechte auf das Verfahren
            elseif (self::PROCEDURE_PERMISSIONSET_WRITE === $permissionset) {
                $this->setProcedurePermissionsetRead();
                $this->setProcedurePermissionsetWrite();
                $this->logger->debug('Set Permissionset Read & Write');
            }

            // einige Bereiche sind für den Bürger ausgeblendet
            if (self::PROCEDURE_PERMISSION_SCOPE_EXTERNAL === $scope) { // angemeldeter Bürger
                $this->logger->debug('User is citizen');
                $this->disablePermissions([
                    'area_statements_public',  // Stellungnahmen der anderen Institutionen
                    'area_statements_released',  // Eigene Stellungnahmen (Freigaben)
                ]);
            }

            // Zusatzrechte Koordinator
            if ($this->user->hasRole(Role::PUBLIC_AGENCY_COORDINATION)) {  // Institutions-Koordination GPSORG
                $this->logger->debug('User is '.Role::PUBLIC_AGENCY_COORDINATION);
                $this->permissions['area_statements_released_group']->enable(); // Stellungnahmen der Gruppe (Freigaben)
                if (self::PROCEDURE_PERMISSIONSET_HIDDEN !== $permissionset) {
                    $this->enablePermissions([
                        'feature_statements_public',  // Stellungnahme für andere Institutionen sichtbar schalten
                        'feature_statements_released_group_delete',  // Stellungnahmen der Gruppe (Freigaben) Loeschen
                        'feature_statements_released_group_edit',  // Stellungnahmen der Gruppe (Freigaben) Bearbeiten
                        'feature_statements_released_group_reject',  // Stellungnahmen der Gruppe (Freigaben) Zurueckweisen
                        'feature_statements_released_group_relocate',  // Stellungnahmen der Gruppe (Freigaben) Verorten
                        'feature_statements_released_group_submit',  // Stellungnahmen der Gruppe (Freigaben) Einreichen
                    ]);
                }
            }
        }

        if ($this->procedureAccessEvaluator->isAllowedAsDataInputOrga($this->user, $this->procedure)) {
            $this->enablePermissions(
                [
                    'feature_statement_data_input_orga',
                ]);
        }
    }

    /**
     * Ist die Organisation des angemeldeten Nutzers Inhaberin des Verfahrens?
     */
    public function ownsProcedure(): bool
    {
        // Die Organisation ist nicht Inhaberin des Verfahrens
        if (null === $this->user) {
            $this->logger->debug('User is empty');

            return false;
        }

        return $this->procedureAccessEvaluator->isOwningProcedure(
            $this->user,
            $this->procedure
        );
    }

    /**
     * Check the user's orga type.
     *
     * @param string $orgaType
     */
    protected function hasOrgaType($orgaType): bool
    {
        $subdomain = $this->globalConfig->getSubdomain();

        return \in_array($orgaType, $this->user->getOrga()->getTypes($subdomain, true), true);
    }

    /**
     * Checks if the user is Member of a planning organisation.
     */
    protected function isMemberOfPlanningOrganisation(): bool
    {
        $isAcceptedMunicipality = $this->isMemberOfMunicipality();
        $isAcceptedPlanningAgency = $this->isMemberOfPlanningoffice();
        $isAcceptedHearingAuthorityAgency = $this->isMemberOfHearingAuthority();

        return $isAcceptedMunicipality || $isAcceptedPlanningAgency || $isAcceptedHearingAuthorityAgency;
    }

    /**
     * isMemberOfPublicAgency.
     */
    protected function isMemberOfPublicAgency(): bool
    {
        return $this->hasOrgaType(OrgaType::PUBLIC_AGENCY);
    }

    /**
     * isMemberOfMunicipality.
     */
    protected function isMemberOfMunicipality(): bool
    {
        return $this->hasOrgaType(OrgaType::MUNICIPALITY);
    }

    /**
     * isMemberOfPlanningoffice.
     */
    protected function isMemberOfPlanningoffice(): bool
    {
        return $this->hasOrgaType(OrgaType::PLANNING_AGENCY);
    }

    protected function isMemberOfHearingAuthority(): bool
    {
        return $this->hasOrgaType(OrgaType::HEARING_AUTHORITY_AGENCY);
    }

    /**
     * Ist der User mit seiner Organisation beteiligt?
     */
    public function isMember(): bool
    {
        // procedure is deleted
        if ($this->isDeletedProcedure()) {
            return false;
        }

        // Ist ein Bürger in einem öffentlichen Beteiligungsverfahren
        if ($this->user->isPublicUser()) {
            $this->logger->debug('User is Citizen or Guest');
            if (self::PROCEDURE_PERMISSIONSET_HIDDEN !== $this->getPermissionset(self::PROCEDURE_PERMISSION_SCOPE_EXTERNAL)) {
                $this->logger->debug('User is member');

                return true;
            }
        }

        $invitedOrgaIds = $this->procedureRepository->getInvitedOrgaIds($this->procedure->getId());
        // Keine Institution eingeladen
        if (0 === count($invitedOrgaIds)) {
            $this->logger->debug('Procedure doesn\'t have Orgas');

            return false;
        }

        // Ist eine eingeladene Institution
        if (!isset($this->user) || !$this->user instanceof User) {
            $this->logger->debug('No User defined');

            return false;
        }

        $isInvitedInstitution = \in_array($this->user->getOrganisationId(), $invitedOrgaIds, true);

        if ($isInvitedInstitution) {
            $this->logger->debug('Orga is member');

            return true;
        }

        return false;
    }

    /**
     * Returns active permissionset.
     *
     * @param string $scope
     */
    public function getPermissionset($scope): string
    {
        if ('' === $scope) {
            $this->logger->debug('No permissionset given');
            throw new InvalidArgumentException('Parameter scope muss gesetzt werden');
        }

        // hole dir die Phasendefinitionen der Rolle
        switch ($scope) {
            case self::PROCEDURE_PERMISSION_SCOPE_INTERNAL:
                $phase = $this->procedure->getPhase() ?? '';
                $phaseConfig = $this->globalConfig->getInternalPhases();
                break;
            case self::PROCEDURE_PERMISSION_SCOPE_EXTERNAL:
                $phase = $this->procedure->getPublicParticipationPhase() ?? '';
                $phaseConfig = $this->globalConfig->getExternalPhases();
                break;
            default:
                $this->logger->debug('Permissionset: Hidden');

                return self::PROCEDURE_PERMISSIONSET_HIDDEN;
        }
        $this->logger->debug('Phase: '.$phase.' Config: '.DemosPlanTools::varExport($phaseConfig, true));

        // welche Phase ist derzeit aktiv?
        $arrIt = new RecursiveIteratorIterator(new RecursiveArrayIterator($phaseConfig));
        foreach ($arrIt as $sub) {
            $subArray = $arrIt->getSubIterator();
            if ($subArray['key'] === $phase) {
                $outputArray = \iterator_to_array($subArray);
                $permissionset = $outputArray['permissionset'];
                $this->logger->debug('Initial Permissionset: ', [$permissionset]);
                // during Procedure::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN user may participate
                // in read permissionset phase, when ConsultationToken is correctly provided
                if ($this->userInvitedInProcedure && (Procedure::PARTICIPATIONSTATE_PARTICIPATE_WITH_TOKEN === ($outputArray[Procedure::PARTICIPATIONSTATE_KEY] ?? ''))) {
                    $permissionset = self::PROCEDURE_PERMISSIONSET_WRITE;
                }
                // gib das Permissionset der aktiven Phase aus
                $this->logger->debug('Active Permissionset: ', [$permissionset]);

                return $permissionset;
            }
        }

        $this->logger->debug('Permissionset: '.self::PROCEDURE_PERMISSIONSET_HIDDEN);

        return self::PROCEDURE_PERMISSIONSET_HIDDEN;
    }

    /**
     * Rechte, die rollenunabhängig gesetzt werden, wenn ein lesender Zugriff auf das Verfahren besteht.
     */
    protected function setProcedurePermissionsetRead(): void
    {
        $this->logger->debug('Set Permissionset read');
        $this->permissions['area_statements']['enabled'] = true; // Eigene Stellungnahmen (Entwuerfe)
        $this->permissions['area_statements_draft']['enabled'] = true; // // Eigene Stellungnahmen (Entwuerfe)
        $this->permissions['area_statements_final']['enabled'] = true; // Stellungnahmen (Endfassungen)
        $this->permissions['area_statements_public']['enabled'] = true; // Stellungnahmen der anderen Institutionen
        $this->permissions['area_statements_released']['enabled'] = true; // Eigene Stellungnahmen (Freigaben)
        $this->permissions['feature_new_statement']['enabled'] = false; // Neue Stellungnahmen abgeben
        $this->permissions['feature_statements_final_email']['enabled'] = true; // Stellungnahmen (Endfassungen) E-Mail
        $this->permissions['feature_statements_released_email']['enabled'] = true; // Eigene Stellungnahmen (Freigaben) E-Mail
    }

    /**
     * Permissions to set to any user when write access is granted.
     */
    protected function setProcedurePermissionsetWrite(): void
    {
        $this->logger->debug('Set Permissionset write');
        $this->permissions['feature_documents_new_statement']['enabled'] = true; // Planungsdokumente Neue Stellungnahme
        $this->permissions['feature_map_new_statement']['enabled'] = true; // Planzeichnung Neue Stellungnahme
        $this->permissions['feature_new_statement']['enabled'] = true; // Stellungnahmen verfassen
        $this->permissions['feature_new_statement_form']['enabled'] = true; // Stellungnahmen verfassen
        $this->permissions['feature_statements_draft_delete']['enabled'] = true; // Eigene Stellungnahmen (Entwuerfe) Loeschen
        $this->permissions['feature_statements_draft_edit']['enabled'] = true; // Eigene Stellungnahmen (Entwuerfe) Bearbeiten
        $this->permissions['feature_statements_draft_release']['enabled'] = true; // Eigene Stellungnahmen (Entwuerfe) Freigeben
        $this->permissions['feature_statements_draft_relocate']['enabled'] = true; // Eigene Stellungnahmen (Entwuerfe) Neu verorten
    }

    /**
     * Prüfe, ob ein bestimmtes Permissionset für die Rolle in dem Verfahren gilt.
     *
     * @param string      $permissionset
     * @param string|null $scope
     */
    protected function hasPermissionset($permissionset, $scope = null): bool
    {
        // procedure is deleted
        if ($this->isDeletedProcedure()) {
            return false;
        }

        // check whether procedure is only allowed for guests.
        $guestOnly = $this->procedure->getProcedureBehaviorDefinition() instanceof ProcedureBehaviorDefinition
            && $this->procedure->getProcedureBehaviorDefinition()->isParticipationGuestOnly();
        if ($guestOnly && !$this->user->isGuestOnly()) {
            return false;
        }

        if (null === $scope) {
            $scope = self::PROCEDURE_PERMISSION_SCOPE_INTERNAL;

            if ($this->user->isPublicUser()) {
                $scope = self::PROCEDURE_PERMISSION_SCOPE_EXTERNAL;
            }
        }

        $this->logger->debug('Permissionset scope: '.$scope);
        $hasPermissionSet = $this->getPermissionset($scope) === $permissionset;
        $this->logger->debug('Has Permissionset: '.DemosPlanTools::varExport($hasPermissionSet, true));

        return $hasPermissionSet;
    }

    /**
     * Hat der User ein Permissionset Read?
     *
     * @param string|null $scope
     */
    public function hasPermissionsetRead($scope = null): bool
    {
        // Read ist in Write inbegriffen
        return $this->hasPermissionset(self::PROCEDURE_PERMISSIONSET_READ, $scope)
               || $this->hasPermissionset(self::PROCEDURE_PERMISSIONSET_WRITE, $scope);
    }

    /**
     * Hat der User ein Permissionset Write?
     *
     * @param string|null $scope
     */
    public function hasPermissionsetWrite($scope = null): bool
    {
        return $this->hasPermissionset(self::PROCEDURE_PERMISSIONSET_WRITE, $scope);
    }

    /**
     * Setzt das initiale Set von Berechtigungen.
     */
    protected function setInitialPermissions(): void
    {
        // initialize addon permissions
        $this->addonPermissionCollections = collect($this->addonPermissionInitializers)
            ->map(function (PermissionInitializerInterface $initializer): ResolvablePermissionCollection {
                $resolvablePermissions = new ResolvablePermissionCollection($this->validator);
                $initializer->configurePermissions($resolvablePermissions);

                return $resolvablePermissions;
            })
            ->all();

        $this->permissions = $this->corePermissions->toArray();
    }

    /**
     * Infos zu einem bestimmten Permission
     * Liefert einen Array mit den Informationen zum Permission.
     *
     * @param string $permission
     *
     * @return Permission|false
     */
    public function getPermission($permission)
    {
        return $this->permissions[$permission] ?? false;
    }

    /**
     * Array aller Permissions mit Name, Label, Enable-Status, und Highlight-Status.
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * checked, ob der Zugriff auf ein konkretes Permission erlaubt ist
     * wenn nicht wird eine Exception geworfen.
     *
     * @param string $permission
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function checkPermission($permission): void
    {
        $this->evaluatePermission($permission);
    }

    /**
     * Überprüfe mehrere Rechte.
     *
     * @param array|null $permissions
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function checkPermissions($permissions): void
    {
        if (is_array($permissions) && 0 < count($permissions)) {
            foreach ($permissions as $permissionToTest) {
                $this->checkPermission($permissionToTest);
            }
        } else {
            // Give devs a hint that the permissions here need to be reworked
            $this->logger->info('This area has no explicit permission specified! '
                        .'Please provide a permission to be checked using the attribute #[DplanPermissions] or annotation @DplanPermissions.', \debug_backtrace(0, 4));
        }
    }

    /**
     * Prüfe, oder der User in das Verfahren darf.
     */
    public function checkProcedurePermission(): void
    {
        // Prüfe, ob der User ins Verfahren darf
        if (null !== $this->procedure) {
            $this->setProcedurePermissions();
            $readPermission = $this->hasPermissionsetRead();
            $owns = $this->ownsProcedure();
            $apiUserMayAccess = $this->hasPermission('feature_procedure_api_access');
            $hasPermissionToEnter = $readPermission || $owns || $apiUserMayAccess;
            if (!$hasPermissionToEnter) {
                // handle guest Exceptions differently as redirects
                // may be different
                if ($this->user->hasRole(Role::GUEST)) {
                    throw new AccessDeniedGuestException('Sie haben nicht die nötigen Rechte, um diese Seite aufzurufen.');
                }
                throw new AccessDeniedException('Sie haben nicht die nötigen Rechte, um diese Seite aufzurufen.');
            }
        }
    }

    /**
     * Hat der User die Permission?
     *
     * @param string $permission
     */
    public function hasPermission($permission): bool
    {
        try {
            $this->evaluatePermission($permission);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    public function requirePermission($permissionIdentifier): void
    {
        [$permissionName, $addonIdentifier] = $this->permissionIdentifierToPair($permissionIdentifier);

        // not an addon permission, evaluating via core
        if (null === $addonIdentifier) {
            $this->evaluatePermission($permissionName);

            return;
        }

        // addon permission, evaluating via resolver
        $resolvablePermission = $this->getAddonPermission($permissionName, $addonIdentifier);
        if (null === $resolvablePermission) {
            throw AccessDeniedException::unknownAddonPermission($permissionName, $addonIdentifier, $this->user);
        }
        if (!$this->isResolvablePermissionEnabled($resolvablePermission)) {
            throw AccessDeniedException::missingAddonPermission($permissionName, $addonIdentifier, $this->user);
        }
    }

    public function requireAllPermissions(array $permissionIdentifiers): void
    {
        // Simply checks each permission individually for now.
        // Optimizations may be implemented in the future.
        array_map($this->requirePermission(...), $permissionIdentifiers);
    }

    public function isPermissionEnabled($permissionIdentifier): bool
    {
        [$permissionName, $addonIdentifier] = $this->permissionIdentifierToPair($permissionIdentifier);

        // not an addon permission, evaluating via core
        if (null === $addonIdentifier) {
            // shortcut to avoid performance heavy exception creation inside `hasPermission`
            if (!array_key_exists($permissionName, $this->permissions)) {
                return false;
            }

            return $this->hasPermission($permissionName);
        }

        // addon permission, evaluating via resolver
        $resolvablePermission = $this->getAddonPermission($permissionName, $addonIdentifier);

        return null !== $resolvablePermission && $this->isResolvablePermissionEnabled($resolvablePermission);
    }

    public function isPermissionKnown($permissionIdentifier): bool
    {
        [$permissionName, $addonIdentifier] = $this->permissionIdentifierToPair($permissionIdentifier);

        // not an addon permission, check if it exists in core
        if (null === $addonIdentifier) {
            return array_key_exists($permissionName, $this->permissions);
        }

        // addon permission, check if it exists in the correct collection
        return null !== $this->getAddonPermission($permissionName, $addonIdentifier);
    }

    /**
     * Hat der User die Permissions?
     *
     * @param string $operator AND or OR
     */
    public function hasPermissions(array $permissions, string $operator = 'AND'): bool
    {
        return match ($operator) {
            'AND' => array_reduce(
                $permissions,
                fn (bool $carry, string $permission) => $carry && $this->hasPermission($permission),
                true
            ),
            'OR' => array_reduce(
                $permissions,
                fn (bool $carry, string $permission) => $carry || $this->hasPermission($permission),
                false
            ),
            default => throw PermissionException::invalidPermissionCheckOperator($operator),
        };
    }

    /**
     * Überprüfe, ob der User eine entsprechende Permission hat.
     *
     * @param string $permission
     *
     * @throws SessionUnavailableException
     * @throws AccessDeniedException
     */
    protected function evaluatePermission($permission): void
    {
        // deny permission when permissions are not defined at all
        if (!is_array($this->permissions) || 0 === count($this->permissions)) {
            throw AccessDeniedException::missingPermissions($this->user);
        }

        if (!isset($this->permissions[$permission]) || !$this->permissions[$permission] instanceof Permission) {
            $this->logger->warning(
                'Permission ist nicht definiert: '.$permission.' Stacktrace: '.DemosPlanTools::varExport(
                    \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
                    true
                )
            );
            throw new InvalidArgumentException('Permission ist nicht definiert: '.$permission);
        }

        if ($this->permissions[$permission]->isEnabled()) {
            if ($this->permissions[$permission]->isLoginRequired()) {
                if (null === $this->user || !$this->user->isLoggedIn()) {
                    throw new SessionUnavailableException('Für diese Aktion müssen Sie angemeldet sein.', 1001);
                }
            }
        } else {
            // handle guest Exceptions differently as redirects
            // may be different
            if ($this->user->hasRole(Role::GUEST)) {
                throw AccessDeniedGuestException::missingPermissions($this->user);
            }

            throw AccessDeniedException::missingPermission($permission, $this->user);
        }
    }

    public function setProcedure(?ProcedureInterface $procedure): void
    {
        $this->procedure = $procedure;
    }

    /**
     * This method is only needed for dynamic permission testing.
     */
    public function setProcedureRepository(ProcedureRepository $procedureRepository): void
    {
        $this->procedureRepository = $procedureRepository;
    }

    /**
     * Procedure has been deleted.
     */
    protected function isDeletedProcedure(): bool
    {
        return null === $this->procedure || $this->procedure->isDeleted();
    }

    /**
     * Enable a set of permissions.
     *
     * @param array $permissions permission names
     */
    public function enablePermissions(array $permissions): void
    {
        \collect($permissions)->map(
            function ($permissionName) {
                if (!array_key_exists($permissionName, $this->permissions)) {
                    $this->logger->error('Could not find Permission '.$permissionName);

                    return null;
                }

                return $this->permissions[$permissionName];
            }
        )->each(
            static function ($permission) {
                if ($permission instanceof Permission) {
                    $permission->enable();
                }
            }
        );
    }

    /**
     * Disable a set of permissions.
     *
     * @deprecated you should only whitelist permissions whenever possible, disabling is discouraged
     *
     * @param array $permissions permission names
     */
    public function disablePermissions(array $permissions): void
    {
        \collect($permissions)->map(
            function ($permissionName) {
                if (!array_key_exists($permissionName, $this->permissions)) {
                    $this->logger->error('Could not find Permission '.$permissionName);

                    return [];
                }

                return $this->permissions[$permissionName];
            }
        )->each(
            static function ($permission) {
                if ($permission instanceof Permission) {
                    $permission->disable();
                }
            }
        );
    }

    /**
     * @return ResolvablePermissionCollection[]
     */
    public function getAddonPermissionCollections(): array
    {
        return $this->addonPermissionCollections;
    }

    /**
     * @param non-empty-string|PermissionIdentifierInterface $permissionIdentifier
     *
     * @return array{0: non-empty-string, 1: non-empty-string|null}
     */
    protected function permissionIdentifierToPair($permissionIdentifier): array
    {
        if (!$permissionIdentifier instanceof PermissionIdentifierInterface) {
            return [$permissionIdentifier, null];
        }

        return [
            $permissionIdentifier->getPermissionName(),
            $permissionIdentifier->getAddonIdentifier(),
        ];
    }

    protected function getAddonPermission(string $permissionName, string $addonIdentifier): ?ResolvablePermission
    {
        return $this->addonPermissionCollections[$addonIdentifier]?->getResolvablePermission($permissionName);
    }

    protected function isResolvablePermissionEnabled(ResolvablePermission $resolvablePermission): bool
    {
        return $this->permissionResolver->isPermissionEnabled(
            $resolvablePermission,
            $this->user,
            $this->procedure,
            $this->currentCustomerProvider->getCurrentCustomer()
        );
    }
}
