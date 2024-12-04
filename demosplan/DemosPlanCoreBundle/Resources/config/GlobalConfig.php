<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Resources\config;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function explode;
use function filter_var;
use function in_array;
use function ini_get;
use function is_array;
use function is_dir;
use function min;
use function realpath;
use function strncasecmp;
use function strpos;
use function substr;
use function trim;

use const FILTER_VALIDATE_BOOLEAN;

class GlobalConfig implements GlobalConfigInterface
{
    private const PHASE_TRANSLATION_KEY_FIELD = 'translationKey';

    /**
     * @var string
     */
    public $salt;
    /**
     * @var string
     */
    protected $projectType;
    /**
     * @var string
     */
    protected $projectName;
    /** @var string */
    protected $projectPagetitle;
    /**
     * @var string
     */
    protected $projectPrefix;
    /** @var string */
    protected $projectFolder;
    /**
     * @var string
     */
    protected $emailSystem;
    /**
     * @var bool
     */
    protected $emailIsLiveSystem;
    /**
     * @var string
     */
    protected $emailTestFrom;
    /**
     * @var string
     */
    protected $emailTestTo;
    /**
     * @var string
     */
    protected $emailBouncefilePath;
    /**
     * @var string
     */
    protected $emailBouncefileFile;
    /**
     * @var string
     */
    protected $emailBouncePrefix;
    /**
     * @var string
     */
    protected $emailBounceDomain;
    /**
     * @var bool
     */
    protected $emailBounceCheck;
    /** @var bool */
    protected $emailUseDataportBounceSystem;
    /**
     * @var string
     */
    protected $emailSubjectPrefix;

    /** @var bool */
    protected $emailUseSystemMailAsSender;

    /** @var string */
    protected $emailFromDomainValidRegex;

    protected string $procedureMetricsReceiver = '';
    /**
     * @var string
     */
    protected $gatewayURL;
    /**
     * @var string
     */
    protected $gatewayRedirectURL;
    /**
     * @var string
     */
    protected $gatewayRegisterURL;
    /**
     * @var string
     */
    protected $gatewayRegisterURLCitizen;
    /**
     * @var string
     */
    protected $gatewayAuthenticateURL;
    /**
     * @var string
     */
    protected $gatewayAuthenticateMethod;
    /**
     * @var string
     */
    protected $contactEMail;
    /**
     * @var bool
     */
    protected $piwikEnable;
    /**
     * @var string
     */
    protected $piwikUrl;
    /**
     * @var int
     */
    protected $piwikSiteID;
    /**
     * @var ?string
     */
    protected $proxyDsn;
    /**
     * @var bool
     */
    protected $platformServiceMode;
    /**
     * @var string
     */
    protected $mapMaxBoundingbox;
    /**
     * @var string
     */
    protected $mapAdminBaselayer;
    /**
     * @var string
     */
    protected $mapAdminBaselayerLayers;

    /**
     * Use as fallback?
     *
     * @var string
     */
    protected $fallbackStatementReplyUrl;

    /**
     * @var string
     */
    protected $mapGlobalAvailableScales;
    /**
     * @var string
     */
    protected $mapPrintBaselayer;
    /**
     * @var string
     */
    protected $mapPrintBaselayerName;
    /**
     * @var string
     */
    protected $mapPrintBaselayerLayers;
    /**
     * @var string
     */
    protected $mapPublicBaselayer;
    /**
     * @var string
     */
    protected $mapPublicBaselayerLayers;
    /**
     * @var string
     */
    protected $mapPublicExtent;
    /**
     * @var string
     */
    protected $mapPublicAvailableScales;
    /**
     * @var string
     */
    protected $mapPublicSearchAutozoom;
    /**
     * @var string
     */
    protected $procedureEntrypointRoute;
    /**
     * @var string
     */
    protected $mapXplanDefaultlayers;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_layer;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v2;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v3 = '';
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v4 = '';
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v2_layer;
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v3_layer = '';
    /**
     * @var string
     */
    protected $mapGetFeatureInfoUrl2_v4_layer = '';
    /**
     * @var bool
     */
    protected $mapGetFeatureInfoUrlUseDb;
    /**
     * @var bool
     */
    protected $mapGetFeatureInfoUrlGlobal;

    /**
     * @var array
     */
    protected $mapAvailableProjections;
    /**
     * @var array<string,array<string,string>>
     */
    protected $mapDefaultProjection;

    protected string $projectCoreVersion;
    /**
     * @var string
     */
    protected $projectVersion;
    /**
     * @var string
     */
    protected $projectShortUrlRedirectRoute;
    /**
     * @var string
     */
    protected $projectShortUrlRedirectRouteLoggedin;
    /**
     * @var bool
     */
    protected $honeypotDisabled;
    protected int $honeypotTimeout;
    /**
     * @var array
     */
    protected $internalPhases;
    /**
     * @var array
     */
    protected $internalPhasesAssoc;

    /**
     * @var string
     */
    protected $entrypointRouteRtedit;

    // aus ServiceLayer/Resources/config
    /**
     * @var array
     */
    protected $externalPhases;
    /**
     * @var array
     */
    protected $externalPhasesAssoc;
    /**
     * @var bool
     */
    protected $alternativeLogin;
    /**
     * @var string
     */
    protected $urlScheme;
    /**
     * path to add between host and url path.
     *
     * @var string
     */
    protected $urlPathPrefix;
    /**
     * @var bool
     */
    protected $useOpenGeoDb;
    /**
     * @var bool
     */
    protected $fetchAdditionalGeodata;
    /**
     * @var bool
     */
    protected $purgeDeletedProcedures;

    /**
     * @var bool
     */
    protected $avscanEnable;
    /**
     * @var bool
     */
    protected $deleteRemovedFiles;
    /**
     * @var string
     */
    protected $elementsNegativeReportCategoryTitle;
    /**
     * @var string
     */
    protected $elementsStatementCategoryTitle;
    /**
     * @var string
     */
    protected $maintenanceKey;
    /**
     * @var string
     */
    protected $fileServiceFilePath;
    /**
     * @var array
     */
    protected $allowedMimeTypes;
    /**
     * @var string
     */
    protected $instanceAbsolutePath;

    /**
     * @var string
     */
    protected $procedurePublicListDefaultSortKey;
    /**
     * @var string
     */
    protected $kernelRootDir;

    /**
     * @var string
     */
    protected $clusterPrefix;
    /**
     * @var array
     */
    protected $formOptions;

    /**
     * @var bool
     */
    protected $projectSubmissionType;

    /**
     * @var bool
     */
    protected $isMessageQueueRoutingDisabled;

    /** @var array */
    protected $elasticsearchQueryDefinition;

    /** @var int */
    protected $elasticsearchNumReplicas;

    /** @var int */
    protected $elasticsearchMajorVersion;

    /**
     * @var string
     */
    protected $kernelEnvironment;

    /**
     * @var ?string
     */
    protected $htaccessUser;

    /**
     * @var ?string
     */
    protected $htaccessPass;

    /**
     * Links to other projects.
     *
     * @var array
     */
    protected $projects;

    /**
     * Which view mode to use when the assessment table is initially loaded by a user.
     * <p>
     * Possible values are defined in AssessmentTableViewMode.
     *
     * @see AssessmentTableViewMode
     *
     * @var string
     */
    protected $assessmentTableDefaultViewMode;

    /**
     * Which toggle state (a.k.a. "Ansicht") to use when the assessment table
     * is initially loaded by a user.
     *
     * @var string
     */
    protected $assessmentTableDefaultToggleView;

    /**
     * Some elements should be hidden in the adminlist, these are defined here.
     *
     * @var string[]
     */
    protected $adminlistElementsHiddenByTitle;

    /**
     * List of Role codes that are allowed in current project.
     *
     * @var array<int, string>
     */
    protected $rolesAllowed;

    /**
     * List of Role Group codes that may be set as allowed to view Faq articles.
     *
     * @var array<int, string>
     */
    protected $roleGroupsFaqVisibility;

    /**
     * Defines whether access to procedure is granted by owning organisation (false)
     * or whether it is possible to define specific users withing the organisation
     * who are granted access (true).
     *
     * Note that in the latter case (true), a user who would have been granted access
     * via its owning organisation **may not** get access if the following is true:
     * * s/he is **not** set in {@link Procedure::$authorizedUsers}
     *
     * @var bool
     */
    protected $procedureUserRestrictedAccess = false;

    // Bobhh
    /** @var string */
    protected $lgvPlisBaseUrl;
    /** @var string */
    protected $lgvXplanboxBaseUrl;
    /** @var string */
    protected $gatewayURLintern;
    // End Bobhh

    // Robobsh
    /** @var string */
    protected $geoWfsStatementLinien;
    /** @var string */
    protected $geoWfsStatementPolygone;
    /** @var string */
    protected $geoWfsStatementPunkte;
    /** @var string */
    protected $geoWfstStatementLinien;
    /** @var string */
    protected $geoWfstStatementPolygone;
    /** @var string */
    protected $geoWfstStatementPunkte;
    // End Robobsh

    // bauleitplanung-online
    /**
     * @var array
     */
    protected $orgaBrandedRoutes;
    /**
     * @var string
     */
    protected $projectDomain;
    /**
     * @var string
     */
    protected $subdomain;
    /** @var array<string,string> */
    protected $subdomainMap;
    // End bauleitplanung-online

    /** @var array */
    protected $entityContentChangeFieldMapping;

    /** @var string */
    protected $publicIndexRoute;

    /** @var array */
    protected $publicIndexRouteParameters;
    /** @var string[] */
    protected $proxyTrusted;

    /**
     * @var bool
     */
    protected $sharedFolder;

    /**
     * @var bool
     */
    private $mapEnableWmtsExport;

    /**
     * @var string
     */
    private $xPlanLayerBaseUrl;

    /**
     * @var bool
     */
    private $advancedSupport;

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $externalLinks;

    public function __construct(
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        private readonly ValidatorInterface $validator
    ) {
        $this->setParams($params, $translator);
    }

    public function setParams(ParameterBagInterface $parameterBag, TranslatorInterface $translator): void
    {
        /*
         * Project configurations
         */
        $this->projectType = $parameterBag->get('project_type');

        // set platform service mode (default: true)
        $this->platformServiceMode = $parameterBag->get('service_mode');

        // set project name and prefix
        $this->projectName = $parameterBag->get('project_name');
        $this->projectPrefix = $parameterBag->get('project_prefix');
        $this->projectPagetitle = $parameterBag->get('project_pagetitle');

        $this->projectFolder = $parameterBag->get('project_folder');

        $this->htaccessUser = $parameterBag->get('htaccess_user');
        $this->htaccessPass = $parameterBag->get('htaccess_pass');

        $this->isMessageQueueRoutingDisabled = $parameterBag->get('rabbitmq_routing_disabled');

        // set Emailsettings
        $this->emailSystem = $parameterBag->get('email_system');
        $this->emailIsLiveSystem = $parameterBag->get('email_is_live_system');
        $this->emailTestFrom = $parameterBag->get('email_test_from');
        $this->emailTestTo = $parameterBag->get('email_test_to');
        $this->emailBouncefilePath = $parameterBag->get('email_bouncefile_path');
        $this->emailBouncefileFile = $parameterBag->get('email_bouncefile_file');
        $this->emailBouncePrefix = $parameterBag->get('email_bounce_prefix');
        $this->emailBounceDomain = $parameterBag->get('email_bounce_domain');
        $this->emailBounceCheck = $parameterBag->get('email_bounce_check');
        $this->emailSubjectPrefix = $parameterBag->get('email_subject_prefix');
        $this->emailUseSystemMailAsSender = $parameterBag->get('email_use_system_mail_as_sender');
        $this->emailFromDomainValidRegex = $parameterBag->get('email_from_domain_valid_regex');
        $this->procedureMetricsReceiver = $parameterBag->get('procedure_metrics_receiver');
        $this->emailUseDataportBounceSystem = $parameterBag->get('email_use_bounce_dataport_system');

        // @todo this might be wrong (and is also deprecated)
        $this->instanceAbsolutePath = $parameterBag->get('kernel.root_dir');

        // set projectwide Boundingbox, standard ist deutschlandweit
        $this->mapMaxBoundingbox = $parameterBag->get('map_max_boundingbox');

        // set Print Baselayer URL
        $this->mapPrintBaselayer = $parameterBag->get('map_print_baselayer');

        // set Print Baselayer Name
        $this->mapPrintBaselayerName = $parameterBag->get('map_print_baselayer_name');

        // set Print Baselayer Name
        $this->mapPrintBaselayerLayers = $parameterBag->get('map_print_baselayer_layers');

        // set Global Baselayer
        $this->mapAdminBaselayer = $parameterBag->get('map_admin_baselayer');

        // set Global Baselayer Layers
        $this->mapAdminBaselayerLayers = $parameterBag->get('map_admin_baselayer_layers');
        $this->fallbackStatementReplyUrl = $parameterBag->get('statement_reply_url');

        $this->mapGlobalAvailableScales = $parameterBag->get('map_global_available_scales');

        // Defaultlayer für XplanLayer
        $this->mapXplanDefaultlayers = $parameterBag->get('map_xplan_defaultlayers');

        // Karte Öffentlichkeitsbeteiligung
        // set Print Baselayer Name
        $this->mapPublicBaselayer = $parameterBag->get('map_public_baselayer');
        $this->mapPublicBaselayerLayers = $parameterBag->get('map_public_baselayer_layers');
        $this->mapPublicExtent = $parameterBag->get('map_public_extent');
        $this->mapPublicAvailableScales = $parameterBag->get('map_public_available_scales');
        $this->mapPublicSearchAutozoom = $parameterBag->get('map_public_search_autozoom');
        $this->mapGetFeatureInfoUrl = $parameterBag->get('map_getfeatureinfo_url');
        $this->mapGetFeatureInfoUrlUseDb = $parameterBag->get('map_getfeatureinfo_url_use_db');
        $this->mapGetFeatureInfoUrlGlobal = $parameterBag->get('map_getfeatureinfo_url_use_global');

        $this->mapAvailableProjections = $parameterBag->get('map_available_projections');
        $this->mapDefaultProjection = $parameterBag->get('map_default_projection');

        // Soll die OpenGeoDB genutzt werden?
        $this->useOpenGeoDb = $parameterBag->get('use_opengeodb');
        // Sollen zusätzliche Informationen von Geoservern geholt werden?
        $this->fetchAdditionalGeodata = $parameterBag->get('use_fetch_additional_geodata');
        $this->purgeDeletedProcedures = $parameterBag->get('purge_deleted_procedures');
        $this->avscanEnable = $parameterBag->get('avscan_enable');
        $this->deleteRemovedFiles = $parameterBag->get('delete_removed_files');
        $this->elementsNegativeReportCategoryTitle = $parameterBag->get('elements_title_negative_report');
        $this->elementsStatementCategoryTitle = $parameterBag->get('elements_title_statement');

        // piwik enable;
        $this->piwikEnable = $parameterBag->get('piwik_enable');

        // piwik url;
        $this->piwikUrl = $parameterBag->get('piwik_url');

        // piwik site id;
        $this->piwikSiteID = $parameterBag->get('piwik_site_id');
        // external proxy
        $this->proxyDsn = $parameterBag->get('proxy_dsn');
        $this->proxyTrusted = $parameterBag->get('proxy_trusted');

        // request variable
        $this->urlScheme = trim($parameterBag->get('url_scheme'));
        $this->urlPathPrefix = trim($parameterBag->get('url_path_prefix'));

        // Programmversion
        $this->projectCoreVersion = $parameterBag->get('project_core_version');
        $this->projectVersion = $parameterBag->get('project_version');

        $this->gatewayURL = $parameterBag->get('gateway_url');
        $this->gatewayRegisterURL = $parameterBag->get('gateway_register_url');
        $this->gatewayRegisterURLCitizen = $parameterBag->get('gateway_register_citizen_url');
        $this->gatewayRedirectURL = $parameterBag->get('gateway_redirect_url');
        $this->gatewayAuthenticateURL = $parameterBag->get('gateway_authenticate_url');
        $this->gatewayAuthenticateMethod = $parameterBag->get('gateway_authenticate_method');
        $this->salt = $parameterBag->get('salt');

        $this->procedureEntrypointRoute = $parameterBag->get('procedure_entrypoint_route');
        $this->procedurePublicListDefaultSortKey = $parameterBag->get('procedure_public_list_default_sort_key');

        $this->contactEMail = $parameterBag->get('contact_recipient');

        $this->projectShortUrlRedirectRoute = $parameterBag->get('project_short_url_redirect_route');
        $this->projectShortUrlRedirectRouteLoggedin = $parameterBag->get(
            'project_short_url_redirect_route_loggedin'
        );

        $this->fileServiceFilePath = $parameterBag->get('fileservice_filepath');
        $this->allowedMimeTypes = $parameterBag->get('allowed_mimetypes');

        // Honeypot-Zeitbegrenzung
        $this->honeypotDisabled = $parameterBag->get('honeypot_disabled');
        $this->honeypotTimeout = $parameterBag->get('honeypot_timeout');

        // alternatives Login ermöglichen
        $this->alternativeLogin = $parameterBag->get('alternative_login');

        // Art des Stellungnahmeabgabeprozesses
        $this->projectSubmissionType = $parameterBag->get('project_submission_type');

        // Verfahrensschritte
        $this->internalPhases = $parameterBag->get('internalPhases');
        $this->externalPhases = $parameterBag->get('externalPhases');

        // Links to other projects
        $this->projects = $parameterBag->get('projects');
        if (!is_array($this->projects)) {
            $this->projects = [];
        }

        foreach ($this->internalPhases as $index => $internalPhase) {
            $internalPhase[self::PHASE_TRANSLATION_KEY_FIELD] = $internalPhase['name'];
            $internalPhase['name'] = $translator->trans($internalPhase['name']);
            $this->internalPhases[$index] = $internalPhase;
            $this->internalPhasesAssoc[$internalPhase['key']] = $internalPhase;
        }

        foreach ($this->externalPhases as $index => $externalPhase) {
            $externalPhase[self::PHASE_TRANSLATION_KEY_FIELD] = $externalPhase['name'];
            $externalPhase['name'] = $translator->trans($externalPhase['name']);
            $this->externalPhases[$index] = $externalPhase;
            $this->externalPhasesAssoc[$externalPhase['key']] = $externalPhase;
        }

        // Key für MaintenanceTasks (CronJob)
        $this->maintenanceKey = $parameterBag->get('maintenance_key');

        // @todo we should get rid of this
        $this->kernelRootDir = $parameterBag->get('kernel.root_dir');

        $this->clusterPrefix = $parameterBag->get('cluster_prefix');
        $this->formOptions = $parameterBag->get('form_options');
        $this->entrypointRouteRtedit = $parameterBag->get('entrypoint_route_rtedit');

        $this->elasticsearchQueryDefinition = $parameterBag->get('elasticsearch_query');
        $this->elasticsearchNumReplicas = $parameterBag->get('elasticsearch_number_of_replicas');
        $this->elasticsearchMajorVersion = $parameterBag->get('elasticsearch_major_version');

        $this->kernelEnvironment = $parameterBag->get('kernel.environment');

        $this->assessmentTableDefaultViewMode = $parameterBag->get('assessment_table_default_view_mode');

        $this->assessmentTableDefaultToggleView = $parameterBag->get('assessment_table_default_toggle_view');

        $this->adminlistElementsHiddenByTitle = $parameterBag->get('adminlist_elements_hidden_by_title');

        $this->entityContentChangeFieldMapping = $parameterBag->get('entity_content_change_fields_mapping');

        $this->rolesAllowed = $parameterBag->get('roles_allowed');

        $this->roleGroupsFaqVisibility = $parameterBag->get('role_groups_faq_visibility');

        // project specific params

        // Bobhh
        $this->lgvPlisBaseUrl = $parameterBag->get('lgv_plis_base_url');
        $this->lgvXplanboxBaseUrl = $parameterBag->get('lgv_xplanbox_base_url');
        $this->xPlanLayerBaseUrl = $parameterBag->get('xplan_layer_base_url');
        $this->gatewayURLintern = $parameterBag->get('gateway_url_intern');

        // Robobsh
        $this->geoWfsStatementLinien = $parameterBag->get('geo_wfs_statement_linien');
        $this->geoWfsStatementPolygone = $parameterBag->get('geo_wfs_statement_polygone');
        $this->geoWfsStatementPunkte = $parameterBag->get('geo_wfs_statement_punkte');
        $this->geoWfstStatementLinien = $parameterBag->get('geo_wfst_statement_linien');
        $this->geoWfstStatementPolygone = $parameterBag->get('geo_wfst_statement_polygone');
        $this->geoWfstStatementPunkte = $parameterBag->get('geo_wfst_statement_punkte');

        $this->mapGetFeatureInfoUrl2 = $parameterBag->get('map_getfeatureinfo_url2');
        $this->mapGetFeatureInfoUrl2_layer = $parameterBag->get('map_getfeatureinfo_url2_layer');
        $this->mapGetFeatureInfoUrl2_v2 = $parameterBag->get('map_getfeatureinfo_url2_v2');
        $this->mapGetFeatureInfoUrl2_v2_layer = $parameterBag->get('map_getfeatureinfo_url2_v2_layer');
        $this->mapGetFeatureInfoUrl2_v3 = $parameterBag->get('map_getfeatureinfo_url2_v3');
        $this->mapGetFeatureInfoUrl2_v3_layer = $parameterBag->get('map_getfeatureinfo_url2_v3_layer');
        $this->mapGetFeatureInfoUrl2_v4 = $parameterBag->get('map_getfeatureinfo_url2_v4');
        $this->mapGetFeatureInfoUrl2_v4_layer = $parameterBag->get('map_getfeatureinfo_url2_v4_layer');

        // bauleitplanung-online
        $this->orgaBrandedRoutes = $parameterBag->get('orga_branded_routes');
        if (!is_array($this->orgaBrandedRoutes)) {
            $this->orgaBrandedRoutes = [];
        }

        $this->projectDomain = $parameterBag->get('project_domain');
        $this->publicIndexRoute = $parameterBag->get('public_index_route');
        $this->publicIndexRouteParameters = $parameterBag->get('public_index_route_parameters');

        // when subdomain is not set yet via SubdomainHander use parameter
        if (null === $this->subdomain) {
            $this->setSubdomain($parameterBag->get('subdomain'));
        }
        $this->subdomainMap = $parameterBag->get('subdomain_map');

        // set shared folder
        $this->sharedFolder = $parameterBag->get('is_shared_folder');

        $this->mapEnableWmtsExport = $parameterBag->get('map_enable_wmts_export');

        $this->procedureUserRestrictedAccess = $parameterBag->get('procedure_user_restricted_access');

        $this->advancedSupport = $parameterBag->get('advanced_support');

        $this->externalLinks = $this->getValidatedExternalLinks($parameterBag);
    }

    /**
     * Calculates and returns the maximum size a file to upload may have.
     */
    public function getMaxUploadSize(): int
    {
        return min(
            $this->convertSizeSuffix(ini_get('upload_max_filesize')),
            $this->convertSizeSuffix(ini_get('post_max_size'))
        );
    }

    public function hasProcedureUserRestrictedAccess(): bool
    {
        return $this->procedureUserRestrictedAccess;
    }

    /**
     * Converts the suffixed filesize notation from php.ini
     * into plain integer numbers
     * e.g. "1M" turns into 1048576.
     *
     * @param int $phpIniSize
     */
    protected function convertSizeSuffix($phpIniSize): int
    {
        $noprefix = false;
        switch (substr($phpIniSize, -1)) {
            case 'k':
                $exponent = 10;
                break;
            case 'M':
                $exponent = 20;
                break;
            case 'G':
                $exponent = 30;
                break;
            case 'T':
                $exponent = 40;
                break;
            case 'P':
                $exponent = 50;
                break;
            case 'E':
                $exponent = 60;
                break;
            default:
                $exponent = 1;
                $noprefix = true;
        }

        return $noprefix ? (int) $phpIniSize : ((int) substr($phpIniSize, 0, -1)) * (2 ** $exponent);
    }

    public function getProjectType(): string
    {
        return $this->projectType;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getProjectPagetitle(): string
    {
        return $this->projectPagetitle;
    }

    public function getProjectPrefix(): string
    {
        return $this->projectPrefix;
    }

    public function getProjectFolder(): string
    {
        return $this->projectFolder;
    }

    /**
     * Set the routingKey to '' (empty string) to simplify things.
     */
    public function isMessageQueueRoutingDisabled(): bool
    {
        return filter_var($this->isMessageQueueRoutingDisabled, FILTER_VALIDATE_BOOLEAN);
    }

    public function getGatewayURL(): string
    {
        return $this->gatewayURL;
    }

    public function getGatewayAuthenticateURL(): string
    {
        return $this->gatewayAuthenticateURL;
    }

    public function getGatewayAuthenticateMethod(): string
    {
        return $this->gatewayAuthenticateMethod;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function getElasticsearchQueryDefinition(): array
    {
        return $this->elasticsearchQueryDefinition;
    }

    public function getElasticsearchNumReplicas(): int
    {
        return $this->elasticsearchNumReplicas;
    }

    public function getElasticsearchMajorVersion(): int
    {
        return $this->elasticsearchMajorVersion;
    }

    public function getUrlScheme(): string
    {
        return $this->urlScheme;
    }

    public function getUrlPathPrefix(): string
    {
        return $this->urlPathPrefix;
    }

    public function getHtaccessUser(): ?string
    {
        return $this->htaccessUser;
    }

    public function getHtaccessPass(): ?string
    {
        return $this->htaccessPass;
    }

    public function getEmailSystem(): string
    {
        return $this->emailSystem;
    }

    public function isEmailIsLiveSystem(): bool
    {
        return filter_var($this->emailIsLiveSystem, FILTER_VALIDATE_BOOLEAN);
    }

    public function getEmailTestFrom(): string
    {
        return $this->emailTestFrom;
    }

    public function getEmailTestTo(): string
    {
        return $this->emailTestTo;
    }

    public function getEmailBouncefilePath(): string
    {
        return $this->emailBouncefilePath;
    }

    public function getEmailBouncefileFile(): string
    {
        return $this->emailBouncefileFile;
    }

    public function getEmailBouncePrefix(): string
    {
        return $this->emailBouncePrefix;
    }

    public function getEmailBounceDomain(): string
    {
        return $this->emailBounceDomain;
    }

    public function doEmailBounceCheck(): bool
    {
        return filter_var($this->emailBounceCheck, FILTER_VALIDATE_BOOLEAN);
    }

    public function isEmailDataportBounceSystem(): bool
    {
        return filter_var($this->emailUseDataportBounceSystem, FILTER_VALIDATE_BOOLEAN);
    }

    public function getEmailSubjectPrefix(): string
    {
        return $this->emailSubjectPrefix;
    }

    public function isEmailUseSystemMailAsSender(): bool
    {
        return filter_var($this->emailUseSystemMailAsSender, FILTER_VALIDATE_BOOLEAN);
    }

    public function getEmailFromDomainValidRegex(): string
    {
        return $this->emailFromDomainValidRegex;
    }

    public function getProcedureMetricsReceiver(): string
    {
        return $this->procedureMetricsReceiver;
    }

    /**
     * Get maximum Boundingbox.
     */
    public function getMapMaxBoundingbox(): string
    {
        return $this->mapMaxBoundingbox;
    }

    /**
     * Get Global Baselayer.
     */
    public function getMapAdminBaselayerLayers(): string
    {
        return $this->mapAdminBaselayerLayers;
    }

    /**
     * Get Global Baselayer layers.
     */
    public function getMapAdminBaselayer(): string
    {
        return $this->mapAdminBaselayer;
    }

    /**
     * Get Global Available Mapscales.
     */
    public function getMapGlobalAvailableScales(): string
    {
        return $this->mapGlobalAvailableScales;
    }

    public function getMapGetFeatureInfoUrl(): string
    {
        return $this->mapGetFeatureInfoUrl;
    }

    public function getMapGetFeatureInfoUrl2(): string
    {
        return $this->mapGetFeatureInfoUrl2;
    }

    public function getMapGetFeatureInfoUrl2V2(): string
    {
        return $this->mapGetFeatureInfoUrl2_v2;
    }

    public function getMapGetFeatureInfoUrl2V3(): string
    {
        return $this->mapGetFeatureInfoUrl2_v3;
    }

    public function getMapGetFeatureInfoUrl2V4(): string
    {
        return $this->mapGetFeatureInfoUrl2_v4;
    }

    public function getMapGetFeatureInfoUrl2Layer(): string
    {
        return $this->mapGetFeatureInfoUrl2_layer;
    }

    public function getMapGetFeatureInfoUrl2V2Layer(): string
    {
        return $this->mapGetFeatureInfoUrl2_v2_layer;
    }

    public function getMapGetFeatureInfoUrl2V3Layer(): string
    {
        return $this->mapGetFeatureInfoUrl2_v3_layer;
    }

    public function getMapGetFeatureInfoUrl2V4Layer(): string
    {
        return $this->mapGetFeatureInfoUrl2_v4_layer;
    }

    public function useMapGetFeatureInfoUrlUseDb(): bool
    {
        return filter_var($this->mapGetFeatureInfoUrlUseDb, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get service mode status.
     */
    public function getPlatformServiceMode(): bool
    {
        return filter_var($this->platformServiceMode, FILTER_VALIDATE_BOOLEAN);
    }

    public function isMapGetFeatureInfoUrlGlobal(): bool
    {
        return filter_var($this->mapGetFeatureInfoUrlGlobal, FILTER_VALIDATE_BOOLEAN);
    }

    public function isHoneypotDisabled(): bool
    {
        return filter_var($this->honeypotDisabled, FILTER_VALIDATE_BOOLEAN);
    }

    public function getHoneypotTimeout(): int
    {
        return $this->honeypotTimeout;
    }

    public function getMaintenanceKey(): string
    {
        return $this->maintenanceKey;
    }

    /**
     * Get Print Baselayer URL.
     */
    public function getMapPrintBaselayer(): string
    {
        return $this->mapPrintBaselayer;
    }

    /**
     * Get Print Baselayer Name.
     */
    public function getMapPrintBaselayerName(): string
    {
        return $this->mapPrintBaselayerName;
    }

    /**
     * Get Print Baselayer Layers.
     */
    public function getMapPrintBaselayerLayers(): string
    {
        return $this->mapPrintBaselayerLayers;
    }

    /**
     *  Is piwik enable.
     */
    public function isPiwikEnabled(): bool
    {
        return filter_var($this->piwikEnable, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     *  Get piwik url.
     */
    public function getPiwikUrl(): string
    {
        return $this->piwikUrl;
    }

    /**
     *  Get piwik site id.
     *
     * @return string should be int, really
     */
    public function getPiwikSiteId(): string
    {
        return $this->piwikSiteID;
    }

    /**
     *  Get piwik site id.
     */
    public function getContactEmail(): string
    {
        return $this->contactEMail;
    }

    /**
     *  Is proxy enable.
     */
    public function isProxyEnabled(): bool
    {
        return '' !== $this->proxyDsn;
    }

    /**
     *  Get proxy host.
     */
    public function getProxyHost(): string
    {
        $parts = explode(':', $this->proxyDsn);

        return $parts[0] ?? '';
    }

    /**
     *  Get proxy port.
     *
     * @return string should be int
     */
    public function getProxyPort(): string
    {
        $parts = explode(':', $this->proxyDsn);

        return $parts[1] ?? '';
    }

    /**
     * @return string[]
     */
    public function getProxyTrusted(): array
    {
        return $this->proxyTrusted;
    }

    public function getProjectCoreVersion(): string
    {
        return $this->projectCoreVersion;
    }

    public function getProjectVersion(): string
    {
        return $this->projectVersion;
    }

    public function getProjectShortUrlRedirectRoute(): string
    {
        return $this->projectShortUrlRedirectRoute;
    }

    public function getProjectShortUrlRedirectRouteLoggedin(): string
    {
        return $this->projectShortUrlRedirectRouteLoggedin;
    }

    public function getGatewayRedirectURL(): string
    {
        return $this->gatewayRedirectURL;
    }

    public function getGatewayRegisterURL(): string
    {
        return $this->gatewayRegisterURL;
    }

    public function getGatewayRegisterURLCitizen(): string
    {
        return $this->gatewayRegisterURLCitizen;
    }

    public function getMapPublicExtent(): string
    {
        return $this->mapPublicExtent;
    }

    /**
     * @return mixed
     */
    public function getMapPublicAvailableScales()
    {
        return $this->mapPublicAvailableScales;
    }

    public function getMapPublicSearchAutozoom(): string
    {
        return $this->mapPublicSearchAutozoom;
    }

    /**
     * @param string $permissionset    "all" || "read||write"
     * @param bool   $includePreviewed if set to true this function will include internal phases with the 'previewed' property set to 'true' regardless of the permissionset of these
     */
    public function getInternalPhases($permissionset = 'all', bool $includePreviewed = false): array
    {
        return $this->filterPhases($this->internalPhases, $permissionset, $includePreviewed);
    }

    /**
     * Filtere nur bestimmte Permissionsets aus den Phasen.
     *
     * @param array  $phases
     * @param string $permissionset    "all" || "read||write"
     * @param bool   $includePreviewed if set to true this function will include phases with the 'previewed' property set to 'true' regardless of the permissionset of these
     */
    protected function filterPhases($phases, $permissionset, bool $includePreviewed = false): array
    {
        if ('all' === $permissionset) {
            return $phases;
        }

        $permissionsets = explode('||', $permissionset);

        return array_filter($phases, static function ($phase) use ($permissionsets, $includePreviewed) {
            $ignorePermissionset =
                $includePreviewed &&
                array_key_exists('previewed', $phase) &&
                true === $phase['previewed'];

            return $ignorePermissionset || in_array($phase['permissionset'], $permissionsets, true);
        });
    }

    /**
     * @param string $permissionset    "all" || "read||write"
     * @param bool   $includePreviewed if set to true this function will include external phases with the 'previewed' property set to 'true' regardless of the permissionset of these
     */
    public function getExternalPhases($permissionset = 'all', bool $includePreviewed = false): array
    {
        return $this->filterPhases($this->externalPhases, $permissionset, $includePreviewed);
    }

    /**
     * Keys der Phasen als array.
     *
     * @param string $permissionset "all" || "read||write"
     */
    public function getInternalPhaseKeys($permissionset = 'all'): array
    {
        $phases = $this->filterPhases($this->internalPhases, $permissionset);
        $keys = [];
        foreach ($phases as $phase) {
            $keys[] = $phase['key'];
        }

        return $keys;
    }

    /**
     * Keys der Phasen als array.
     *
     * @param string $permissionset "all" || "read||write"
     */
    public function getExternalPhaseKeys($permissionset = 'all'): array
    {
        $phases = $this->filterPhases($this->externalPhases, $permissionset);
        $keys = [];
        foreach ($phases as $phase) {
            $keys[] = $phase['key'];
        }

        return $keys;
    }

    /**
     * @param string $permissionset "all" || "read||write"
     */
    public function getInternalPhasesAssoc($permissionset = 'all'): array
    {
        return $this->filterPhases($this->internalPhasesAssoc, $permissionset);
    }

    /**
     * @param string $permissionset "all" || "read||write"
     */
    public function getExternalPhasesAssoc($permissionset = 'all'): array
    {
        return $this->filterPhases($this->externalPhasesAssoc, $permissionset);
    }

    /**
     * Returns phase name based on key with internal phases being checked first for a match.
     */
    public function getPhaseNameWithPriorityInternal(string $phaseKey): string
    {
        return $this->getPhaseNameWithPriority($phaseKey, $this->internalPhasesAssoc, $this->externalPhasesAssoc);
    }

    /**
     * This method returns the corresponding phase name to a given phase key. The two given arrays must be
     * {@see $internalPhasesAssoc} and {@see $internalPhasesAssoc} with the order telling where to look for the name first.
     * If there is no name found, the key will be returned.
     *
     * @param array<string, array> $higherPriorityPhasesArray
     * @param array<string, array> $lowerPriorityPhasesArray
     */
    protected function getPhaseNameWithPriority(string $phaseKey, array $higherPriorityPhasesArray, array $lowerPriorityPhasesArray): string
    {
        $phaseName = $phaseKey;
        // Check for name in higher priority array
        if (array_key_exists($phaseKey, $higherPriorityPhasesArray)) {
            $phaseName = $higherPriorityPhasesArray[$phaseKey]['name'];
        }

        // Check for name in lower priority array if no name has been found in higher priority array
        $hasNotFoundPhaseKey = $phaseKey === $phaseName;
        if ($hasNotFoundPhaseKey && array_key_exists($phaseKey, $lowerPriorityPhasesArray)) {
            $phaseName = $lowerPriorityPhasesArray[$phaseKey]['name'];
        }

        return $phaseName;
    }

    /**
     * Returns phase name based on key with external phases being checked first for a match.
     */
    public function getPhaseNameWithPriorityExternal(string $phaseKey): string
    {
        return $this->getPhaseNameWithPriority($phaseKey, $this->externalPhasesAssoc, $this->internalPhasesAssoc);
    }

    public function getMapPublicBaselayer(): string
    {
        return $this->mapPublicBaselayer;
    }

    public function getMapPublicBaselayerLayers(): string
    {
        return $this->mapPublicBaselayerLayers;
    }

    public function getMapXplanDefaultlayers(): string
    {
        return $this->mapXplanDefaultlayers;
    }

    /**
     * is alternative login enabled.
     */
    public function isAlternativeLoginEnabled(): bool
    {
        return filter_var($this->alternativeLogin, FILTER_VALIDATE_BOOLEAN);
    }

    public function getUseOpenGeoDb(): bool
    {
        return filter_var($this->useOpenGeoDb, FILTER_VALIDATE_BOOLEAN);
    }

    public function getUseFetchAdditionalGeodata(): bool
    {
        return filter_var($this->fetchAdditionalGeodata, FILTER_VALIDATE_BOOLEAN);
    }

    public function getUsePurgeDeletedProcedures(): bool
    {
        return filter_var($this->purgeDeletedProcedures, FILTER_VALIDATE_BOOLEAN);
    }

    public function isAvscanEnabled(): bool
    {
        return filter_var($this->avscanEnable, FILTER_VALIDATE_BOOLEAN);
    }

    public function doDeleteRemovedFiles(): bool
    {
        return filter_var($this->deleteRemovedFiles, FILTER_VALIDATE_BOOLEAN);
    }

    public function getKernelRootDir(): string
    {
        return $this->kernelRootDir;
    }

    public function getFileServiceFilePath(): string
    {
        return $this->fileServiceFilePath;
    }

    /**
     * @throws Exception
     */
    public function getFileServiceFilePathAbsolute(): string
    {
        $absolutePath = $this->fileServiceFilePath;
        // If a relative path is given, turn it into an absolute path
        if (0 === strncasecmp($absolutePath, '.', 1)) {
            $absolutePath = DemosPlanPath::getRootPath($absolutePath);
        }

        $realpath = realpath($absolutePath);

        // fallback if path could not be resolved
        if (false === $realpath) {
            $realpath = DemosPlanPath::getRootPath('files');
        }

        // check path
        // mkdir, create recursively
        // !is_dir needs to checked twice. First check: Only try to create
        // mkdir if folder does not exist
        // second check: Did something happen during mkdir?
        if (!is_dir($realpath) && !mkdir($realpath, 0755, true)
                && !is_dir($realpath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $realpath));
        }

        return $realpath;
    }

    public function getInstanceAbsolutePath(): string
    {
        return $this->instanceAbsolutePath;
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @return mixed
     */
    public function getProcedureEntrypointRoute()
    {
        return $this->procedureEntrypointRoute;
    }

    public function getElementsNegativeReportCategoryTitle(): string
    {
        return $this->elementsNegativeReportCategoryTitle;
    }

    public function getElementsStatementCategoryTitle(): string
    {
        return $this->elementsStatementCategoryTitle;
    }

    public function getClusterPrefix(): string
    {
        return $this->clusterPrefix;
    }

    public function getFormOptions(): array
    {
        return $this->formOptions;
    }

    public function getEntrypointRouteRtedit(): string
    {
        return $this->entrypointRouteRtedit;
    }

    public function getProjectSubmissionType(): string
    {
        return $this->projectSubmissionType;
    }

    public function getKernelEnvironment(): string
    {
        return $this->kernelEnvironment;
    }

    /**
     * Projects are projects for project switcher (SH only in this incarnation).
     *
     * WARNING: Don't move into a SH specific config files unless the using places
     * check for being in an SH project
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    /**
     * @see assessmentTableDefaultViewMode
     */
    public function getAssessmentTableDefaultViewMode(): string
    {
        return $this->assessmentTableDefaultViewMode;
    }

    /**
     * @see assessmentTableDefaultToggleView
     */
    public function getAssessmentTableDefaultToggleView(): string
    {
        return $this->assessmentTableDefaultToggleView;
    }

    /**
     * @return string[]
     */
    public function getAdminlistElementsHiddenByTitle(): array
    {
        return $this->adminlistElementsHiddenByTitle;
    }

    public function isProdMode(): bool
    {
        return 'prod' === $this->getKernelEnvironment();
    }

    public function getLgvPlisBaseUrl(): string
    {
        return $this->lgvPlisBaseUrl;
    }

    public function getLgvXplanboxBaseUrl(): string
    {
        return $this->lgvXplanboxBaseUrl;
    }

    public function getXPlanLayerBaseUrl(): string
    {
        return $this->xPlanLayerBaseUrl;
    }

    public function getGatewayURLintern(): string
    {
        return $this->gatewayURLintern;
    }

    public function getGeoWfsStatementLinien(): string
    {
        return $this->geoWfsStatementLinien;
    }

    public function getGeoWfsStatementPolygone(): string
    {
        return $this->geoWfsStatementPolygone;
    }

    public function getGeoWfsStatementPunkte(): string
    {
        return $this->geoWfsStatementPunkte;
    }

    public function getGeoWfstStatementLinien(): string
    {
        return $this->geoWfstStatementLinien;
    }

    public function getGeoWfstStatementPolygone(): string
    {
        return $this->geoWfstStatementPolygone;
    }

    public function getGeoWfstStatementPunkte(): string
    {
        return $this->geoWfstStatementPunkte;
    }

    public function getEntityContentChangeFieldMapping(): array
    {
        return $this->entityContentChangeFieldMapping;
    }

    public function getFallbackStatementReplyUrl(): string
    {
        return $this->fallbackStatementReplyUrl;
    }

    public function getProjectDomain(): string
    {
        return $this->projectDomain;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): void
    {
        $this->subdomain = $subdomain;
    }

    /**
     * @return array<string,string>
     */
    public function getSubdomainMap(): array
    {
        return $this->subdomainMap;
    }

    public function getOrgaBrandedRoutes(): array
    {
        return $this->orgaBrandedRoutes;
    }

    public function getPublicIndexRoute(): string
    {
        return $this->publicIndexRoute;
    }

    public function getPublicIndexRouteParameters(): array
    {
        return $this->publicIndexRouteParameters;
    }

    /**
     * @return array<int, string>
     */
    public function getRolesAllowed(): array
    {
        return $this->rolesAllowed;
    }

    public function getRoleGroupsFaqVisibility(): array
    {
        return $this->roleGroupsFaqVisibility;
    }

    public function isSharedFolder(): bool
    {
        return $this->sharedFolder;
    }

    public function getMapEnableWmtsExport(): bool
    {
        return $this->mapEnableWmtsExport;
    }

    public function getMapAvailableProjections(): array
    {
        return $this->mapAvailableProjections;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getMapDefaultProjection(): array
    {
        return $this->mapDefaultProjection;
    }

    public function getInternalPhaseTranslationKey(string $phaseKey): ?string
    {
        return $this->internalPhasesAssoc[$phaseKey][self::PHASE_TRANSLATION_KEY_FIELD] ?? null;
    }

    public function getExternalPhaseTranslationKey(string $phaseKey): ?string
    {
        return $this->externalPhasesAssoc[$phaseKey][self::PHASE_TRANSLATION_KEY_FIELD] ?? null;
    }

    public function isAdvancedSupport(): bool
    {
        return $this->advancedSupport;
    }

    public function getExternalLinks(): array
    {
        return $this->externalLinks;
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    private function getValidatedExternalLinks(ParameterBagInterface $parameterBag): array
    {
        $externalLinks = $parameterBag->get('external_links');
        $violations = $this->validator->validate($externalLinks, [
            new Type('array'),
            new NotNull(),
            new All([
                new Type('string'),
                new NotBlank(null, null, false),
                new Url(),
            ]),
        ]);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $violations->addAll($this->validator->validate(array_keys($externalLinks), [
            new All([
                new Type('string'),
                new NotBlank(null, null, false),
            ]),
        ]));
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return $externalLinks;
    }
}
