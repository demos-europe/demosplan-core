<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionIdentifier;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Permissions\ResolvablePermission;
use demosplan\DemosPlanCoreBundle\Services\BrandingLoader;
use demosplan\DemosPlanCoreBundle\Services\OrgaLoader;
use Illuminate\Support\Collection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function str_replace;

class DefaultTwigVariablesService
{
    protected $variables;

    public function __construct(
        private readonly BrandingLoader $brandingLoader,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUser,
        private readonly CustomerService $customerService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly OrgaLoader $orgaLoader,
        /**
         * @var PermissionsInterface|Permissions
         */
        private readonly PermissionsInterface $permissions,
        private readonly SessionHandler $sessionHandler,
        private readonly TransformMessageBagService $transformMessageBagService,
        private readonly string $publicCSSClassPrefix,
        private readonly string $defaultLocale)
    {
    }

    protected function extractExposedPermissions(): Collection
    {
        /*
         * Filter all permissions that are enabled and marked as to-be-exposed to the frontend
         * and reformat them to [permission_name => true].
         */
        $permissions = collect($this->permissions->getPermissions())->each(
            static function ($permission, $permissionName) {
                if (!is_a($permission, Permission::class)) {
                    throw new RuntimeException(sprintf('Permission %s is not defined in demosplan core anymore', $permissionName));
                }
            }
        )->filter(
            static fn (Permission $permission) => $permission->isEnabled() && $permission->isExposed()
        )->flatMap(
            static fn (Permission $permission) => [$permission->getName() => true]
        );

        foreach ($this->permissions->getAddonPermissionCollections() as $addonName => $permissionCollection) {
            $permissions = $permissions->merge(collect($permissionCollection->getResolvePermissions())
                ->filter(
                    function (ResolvablePermission $permission) use ($addonName) {
                        if ($permission->isExposed()) {
                            // resolve
                            return $this->permissions->isPermissionEnabled(PermissionIdentifier::forAddon(
                                $permission->getName(), $addonName
                            ));
                        }

                        return false;
                    }
                )->flatMap(
                    static fn (ResolvablePermission $permission) => [$permission->getName() => true]
                ));
        }

        return $permissions;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     */
    public function loadVariables(Request $request): void
    {
        $this->transformMessageBagService->transformMessageBagToFlashes();

        // fetch user data
        $user = $this->currentUser->getUser();

        $exposedPermissions = $this->extractExposedPermissions();

        $languageKey = $this->getLocale($request);

        $projectsFromConfig = $this->globalConfig->getProjects();
        if (!is_array($projectsFromConfig)) {
            $projectsFromConfig = [];
        }

        $projects = [];
        foreach ($projectsFromConfig as $projectName => $projectUrl) {
            $projects[] = [
                'name'     => $projectName,
                'ariaName' => str_replace('&shy;', '', $projectName),
                'url'      => $projectUrl,
                'current'  => $projectName === $this->globalConfig->getProjectName(),
            ];
        }

        $brandingObject = $this->brandingLoader->getBrandingObject($request);
        $orgaObject = $this->orgaLoader->getOrgaObject($request);
        $customerObject = $this->customerService->getCurrentCustomer();

        $basicAuth = '';
        if (null !== $this->globalConfig->getHtaccessUser() && '' !== $this->globalConfig->getHtaccessUser()) {
            $basicAuth = 'Basic '.base64_encode(
                $this->globalConfig->getHtaccessUser().':'.$this->globalConfig->getHtaccessPass(
                ) ?? ''
            );
        }

        $this->variables = [
            'branding'                         => $brandingObject,
            'basicAuth'                        => $basicAuth,
            'customerInfo'                     => $customerObject,
            'currentUser'                      => $user,
            'exposedPermissions'               => $exposedPermissions,
            'gatewayRegisterURL'               => $this->globalConfig->getGatewayRegisterURL(),
            'gatewayRegisterURLCitizen'        => $this->globalConfig->getGatewayRegisterURLCitizen(),
            'gatewayURL'                       => $this->globalConfig->getGatewayURL(),
            'gatewayURLIntern'                 => $this->globalConfig->getGatewayURLintern(),
            'hasProcedureUserRestrictedAccess' => $this->globalConfig->hasProcedureUserRestrictedAccess(),
            'isIntranet'                       => filter_var($user->isIntranet(), FILTER_VALIDATE_BOOLEAN),
            'locale'                           => $languageKey,
            'loggedin'                         => $user->isLoggedIn(),
            'map'                              => $this->loadMapVariables(),
            'maxUploadSize'                    => $this->globalConfig->getMaxUploadSize(),
            'orgaInfo'                         => $orgaObject,
            'jwtToken'                         => $this->jwtTokenManager->create($user),
            'permissions'                      => $this->permissions->getPermissions(),
            'piwik'                            => $this->loadPiwikVariables(),
            'procedure'                        => $this->currentProcedureService->getProcedure()?->getId(), // legacy twig code in twigs
            'procedureId'                      => $this->currentProcedureService->getProcedure()?->getId(),
            'procedureObject'                  => $this->currentProcedureService->getProcedure(),
            'proceduresettings'                => $this->currentProcedureService->getProcedureArray(),
            'projectCoreVersion'               => $this->globalConfig->getProjectCoreVersion(),
            'projectFolder'                    => $this->globalConfig->getProjectFolder(),
            'projectName'                      => $this->globalConfig->getProjectName(),
            'projects'                         => $projects,
            'projectType'                      => $this->globalConfig->getProjectType(),
            'projectVersion'                   => $this->globalConfig->getProjectVersion(),
            'publicCSSClassPrefix'             => $this->publicCSSClassPrefix,
            'roles'                            => $user->getRoles(),
            'route_name'                       => $request->attributes->get('_route'),
            'urlPathPrefix'                    => $this->globalConfig->getUrlPathPrefix(),
            'urlScheme'                        => $this->globalConfig->getUrlScheme() ?? $request->getScheme(),
            'useOpenGeoDb'                     => $this->globalConfig->getUseOpenGeoDb(),
            'externalLinks'                    => $this->getFilteredExternalLinks(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getFilteredExternalLinks(): array
    {
        $externalLinks = $this->globalConfig->getExternalLinks();

        // simple list of urls are given, without intention to filter
        if (!is_array(array_values($externalLinks)[0])) {
            return $externalLinks;
        }

        // In case of current user has no permission to see restricted external links, execute filtering
        if (!$this->currentUser->hasPermission('feature_list_restricted_external_links')) {
            $externalLinks = array_filter($this->globalConfig->getExternalLinks(), function ($link) {
                return !isset($link['restricted']) || !$link['restricted'];
            });
        }

        return array_map(fn (array $data) => $data['url'], $externalLinks);
    }

    private function getLocale(Request $request): string
    {
        $languageKey = $request->getSession()->get('_locale');
        if (\is_null($languageKey) || 0 === strlen((string) $languageKey)) {
            $languageKey = $this->defaultLocale;
        }

        return $languageKey;
    }

    private function loadMapVariables(): array
    {
        $map = [];

        // set map variables
        $map['maxBoundingbox'] = $this->globalConfig->getMapMaxBoundingbox();
        $map['adminBaselayer'] = $this->globalConfig->getMapAdminBaselayer();
        $map['adminBaselayerLayers'] = $this->globalConfig->getMapAdminBaselayerLayers();
        $map['globalAvailableScales'] = $this->globalConfig->getMapGlobalAvailableScales();
        $map['printBaselayer'] = $this->globalConfig->getMapPrintBaselayer();
        $map['printBaselayerName'] = $this->globalConfig->getMapPrintBaselayerName();
        $map['printBaselayerLayers'] = $this->globalConfig->getMapPrintBaselayerLayers();
        $map['publicBaselayer'] = $this->globalConfig->getMapPublicBaselayer();
        $map['publicBaselayerLayers'] = $this->globalConfig->getMapPublicBaselayerLayers();
        $map['publicAvailableScales'] = $this->globalConfig->getMapPublicAvailableScales();
        $map['publicSearchAutozoom'] = $this->globalConfig->getMapPublicSearchAutozoom();
        $map['publicExtent'] = $this->globalConfig->getMapPublicExtent();
        $map['xplanDefaultLayers'] = $this->globalConfig->getMapXplanDefaultlayers();

        // T16986
        // Ensure loading customer-specific values for public index-site only if specific customer-subdomain is available.
        // Otherwise use already set default values.
        $currentCustomer = $this->customerService->getCurrentCustomer();
        if (FunctionalUser::FUNCTIONAL_USER_CUSTOMER_SUBDOMAIN !== $currentCustomer->getSubdomain()) {
            $map['publicBaselayer'] = $currentCustomer->getBaseLayerUrl();
            $map['mapAttribution'] = $currentCustomer->getMapAttribution();
            $map['publicBaselayerLayers'] = $currentCustomer->getBaseLayerLayers();
        }

        return $map;
    }

    private function loadPiwikVariables(): array
    {
        $piwik = [
            'enable' => false,
            'url'    => '',
            'siteId' => '',
        ];

        // set piwik variables, Userpreferences may override
        if ($this->globalConfig->isPiwikEnabled()) {
            $piwik['enable'] = $this->globalConfig->isPiwikEnabled();
            $piwik['url'] = $this->globalConfig->getPiwikUrl();
            $piwik['siteId'] = $this->globalConfig->getPiwikSiteId();
        }

        return $piwik;
    }
}
