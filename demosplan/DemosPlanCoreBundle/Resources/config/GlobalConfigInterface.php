<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Resources\config;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

interface GlobalConfigInterface
{
    /**
     * @return mixed
     */
    public function setParams(ParameterBagInterface $parameterBag, TranslatorInterface $translator);

    /**
     * Calculates and returns the maximum size a file to upload may have.
     */
    public function getMaxUploadSize(): int;

    public function getProjectType(): string;

    public function getProjectName(): string;

    public function getProjectPagetitle(): string;

    public function getProjectPrefix(): string;

    /**
     * Project name as used in folder structure.
     */
    public function getProjectFolder(): string;

    /**
     * Set the routingKey to '' (empty string) to simplify things.
     */
    public function isMessageQueueRoutingDisabled(): bool;

    public function getGatewayURL(): string;

    public function getGatewayAuthenticateURL(): string;

    public function getGatewayAuthenticateMethod(): string;

    public function getSalt(): string;

    public function getElasticsearchQueryDefinition(): array;

    public function getElasticsearchNumReplicas(): int;

    public function isElasticsearchAsyncIndexing(): bool;

    public function isElasticsearchAsyncIndexingLogStatus(): bool;

    public function getElasticsearchAsyncIndexingPoolSize(): int;

    public function getElasticsearchMajorVersion(): int;

    public function getUrlScheme(): string;

    public function getUrlPathPrefix(): string;

    public function getHtaccessUser(): ?string;

    public function getHtaccessPass(): ?string;

    public function getEmailSystem(): string;

    public function isEmailIsLiveSystem(): bool;

    public function getEmailTestFrom(): string;

    public function getEmailTestTo(): string;

    public function getEmailBouncefilePath(): string;

    public function getEmailBouncefileFile(): string;

    public function getEmailBouncePrefix(): string;

    public function getEmailBounceDomain(): string;

    public function doEmailBounceCheck(): bool;

    public function isEmailDataportBounceSystem(): bool;

    public function getEmailSubjectPrefix(): string;

    public function isEmailUseSystemMailAsSender(): bool;

    public function getEmailFromDomainValidRegex(): string;

    /**
     * Get maximum Boundingbox.
     */
    public function getMapMaxBoundingbox(): string;

    /**
     * Get Global Baselayer.
     */
    public function getMapAdminBaselayerLayers(): string;

    /**
     * Get Global Baselayer layers.
     */
    public function getMapAdminBaselayer(): string;

    /**
     * Get Global Available Mapscales.
     */
    public function getMapGlobalAvailableScales(): string;

    public function getMapGetFeatureInfoUrl(): string;

    public function getMapGetFeatureInfoUrl2(): string;

    public function getMapGetFeatureInfoUrl2V2(): string;

    public function getMapGetFeatureInfoUrl2V3(): string;

    public function getMapGetFeatureInfoUrl2V4(): string;

    public function getMapGetFeatureInfoUrl2Layer(): string;

    public function getMapGetFeatureInfoUrl2V2Layer(): string;

    public function getMapGetFeatureInfoUrl2V3Layer(): string;

    public function getMapGetFeatureInfoUrl2V4Layer(): string;

    public function useMapGetFeatureInfoUrlUseDb(): bool;

    public function getMapAvailableProjections(): array;

    /**
     * @return array<string,array<string,string>>
     */
    public function getMapDefaultProjection(): array;

    /**
     * Get service mode status.
     */
    public function getPlatformServiceMode(): bool;

    public function isMapGetFeatureInfoUrlGlobal(): bool;

    public function isHoneypotDisabled(): bool;

    public function getMaintenanceKey(): string;

    /**
     * Get Print Baselayer URL.
     */
    public function getMapPrintBaselayer(): string;

    /**
     * Get Print Baselayer Name.
     */
    public function getMapPrintBaselayerName(): string;

    /**
     * Get Print Baselayer Layers.
     */
    public function getMapPrintBaselayerLayers(): string;

    /**
     *  Is piwik enable.
     */
    public function isPiwikEnabled(): bool;

    /**
     *  Get piwik url.
     */
    public function getPiwikUrl(): string;

    /**
     *  Get piwik site id.
     *
     * @return string should be int, really
     */
    public function getPiwikSiteId(): string;

    /**
     *  Get piwik site id.
     */
    public function getContactEmail(): string;

    /**
     *  Is proxy enable.
     */
    public function isProxyEnabled(): bool;

    /**
     *  Get proxy host.
     */
    public function getProxyHost(): string;

    /**
     *  Get proxy port.
     *
     * @return string should be int
     */
    public function getProxyPort(): string;

    public function getProjectVersion(): string;

    public function getProjectShortUrlRedirectRoute(): string;

    public function getProjectShortUrlRedirectRouteLoggedin(): string;

    public function getGatewayRedirectURL(): string;

    public function getGatewayRegisterURL(): string;

    public function getGatewayRegisterURLCitizen(): string;

    public function getMapPublicExtent(): string;

    /**
     * @return mixed
     */
    public function getMapPublicAvailableScales();

    public function getMapPublicSearchAutozoom(): string;

    /**
     * @param string $permissionset    "all" || "read||write"
     * @param bool   $includePreviewed if set to true this function will include internal phases with the 'previewed' property set to 'true' regardless of the permissionset of these
     */
    public function getInternalPhases($permissionset = 'all', bool $includePreviewed = false): array;

    /**
     * @param string $permissionset    "all" || "read||write"
     * @param bool   $includePreviewed if set to true this function will include external phases with the 'previewed' property set to 'true' regardless of the permissionset of these
     */
    public function getExternalPhases($permissionset = 'all', bool $includePreviewed = false): array;

    /**
     * Keys der Phasen als array.
     *
     * @param string $permissionset "all" || "read||write"
     */
    public function getInternalPhaseKeys($permissionset = 'all'): array;

    /**
     * Keys der Phasen als array.
     *
     * @param string $permissionset "all" || "read||write"
     */
    public function getExternalPhaseKeys($permissionset = 'all'): array;

    /**
     * @param string $permissionset "all" || "read||write"
     */
    public function getInternalPhasesAssoc($permissionset = 'all'): array;

    /**
     * @param string $permissionset "all" || "read||write"
     */
    public function getExternalPhasesAssoc($permissionset = 'all'): array;

    /**
     * Returns phase name based on key with internal phases being checked first for a match.
     */
    public function getPhaseNameWithPriorityInternal(string $key): string;

    /**
     * Returns phase name based on key with external phases being checked first for a match.
     */
    public function getPhaseNameWithPriorityExternal(string $key): string;

    public function getMapPublicBaselayer(): string;

    public function getMapPublicBaselayerLayers(): string;

    public function getMapXplanDefaultlayers(): string;

    /**
     * is alternative login enabled.
     */
    public function isAlternativeLoginEnabled(): bool;

    public function getUseOpenGeoDb(): bool;

    public function getUseFetchAdditionalGeodata(): bool;

    public function getUsePurgeDeletedProcedures(): bool;

    public function isAvscanEnabled(): bool;

    public function doDeleteRemovedFiles(): bool;

    public function getKernelRootDir(): string;

    public function getFileServiceFilePath(): string;

    /**
     * @throws Exception
     */
    public function getFileServiceFilePathAbsolute(): string;

    /**
     * @throws Exception
     */
    public function getDatasheetFilePathAbsolute(): string;

    public function getInstanceAbsolutePath(): string;

    public function getAllowedMimeTypes(): array;

    /**
     * @return mixed
     */
    public function getProcedureEntrypointRoute();

    public function getElementsNegativeReportCategoryTitle(): string;

    public function getElementsStatementCategoryTitle(): string;

    public function getClusterPrefix(): string;

    public function getFormOptions(): array;

    public function getEntrypointRouteRtedit(): string;

    public function getProjectSubmissionType(): string;

    /**
     * @param string $procedureId
     */
    public function getDatasheetVersion($procedureId): int;

    public function getKernelEnvironment(): string;

    /**
     * Projects are projects for project switcher (SH only in this incarnation).
     *
     * WARNING: Don't move into a SH specific config files unless the using places
     * check for being in an SH project
     */
    public function getProjects(): array;

    public function hasProcedureUserRestrictedAccess(): bool;

    /**
     * @see assessmentTableDefaultViewMode
     */
    public function getAssessmentTableDefaultViewMode(): string;

    /**
     * @see assessmentTableDefaultToggleView
     */
    public function getAssessmentTableDefaultToggleView(): string;

    /**
     * @return array<int, string>
     */
    public function getAdminlistElementsHiddenByTitle(): array;

    public function isProdMode(): bool;

    public function getLgvPlisBaseUrl(): string;

    public function getLgvXplanboxBaseUrl(): string;

    public function getGatewayURLintern(): string;

    public function getGeoWfsStatementLinien(): string;

    public function getGeoWfsStatementPolygone(): string;

    public function getGeoWfsStatementPunkte(): string;

    public function getGeoWfstStatementLinien(): string;

    public function getGeoWfstStatementPolygone(): string;

    public function getGeoWfstStatementPunkte(): string;

    public function getEntityContentChangeFieldMapping(): array;

    public function getFallbackStatementReplyUrl(): string;

    public function getProjectDomain(): string;

    public function getSubdomain(): string;

    public function setSubdomain(string $subdomain): void;

    /**
     * Defines which subdomains (aka {@link Customer}s) are allowed.
     *
     * @return array<int,string>
     */
    public function getSubdomainsAllowed(): array;

    public function getOrgaBrandedRoutes(): array;

    public function getPublicIndexRoute(): string;

    public function getPublicIndexRouteParameters(): array;

    /**
     * @return string may be empty if not configured properly
     */
    public function getAiServiceSalt(): string;

    /**
     * @return string may be empty if not configured properly
     */
    public function getAiServicePostUrl(): string;

    /**
     * @return array<int, string>
     */
    public function getRolesAllowed(): array;

    public function isSharedFolder(): bool;

    public function getMapEnableWmtsExport(): bool;

    public function isAdvancedSupport(): bool;

    /**
     * @return array<string,string>
     */
    public function getSubdomainMap(): array;

    public function getXPlanLayerBaseUrl(): string;

    public function getInternalPhaseTranslationKey(string $phaseKey): ?string;

    public function getExternalPhaseTranslationKey(string $phaseKey): ?string;
}
