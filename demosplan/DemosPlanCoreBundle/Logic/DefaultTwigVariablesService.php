<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Permissions\Permission;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Services\BrandingLoader;
use demosplan\DemosPlanCoreBundle\Services\OrgaLoader;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Tightenco\Collect\Support\Collection;

use function str_replace;

class DefaultTwigVariablesService
{
    protected $variables;

    /**
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * @var BrandingLoader
     */
    private $brandingLoader;

    /** @var OrgaLoader */
    private $orgaLoader;

    /** @var CustomerService */
    private $customerService;

    /**
     * @var SessionHandler
     */
    private $sessionHandler;
    /**
     * @var string
     */
    private $publicCSSClassPrefix;
    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;
    /**
     * @var TransformMessageBagService
     */
    private $transformMessageBagService;
    /**
     * @var PermissionsInterface
     */
    private $permissions;
    /**
     * @var CurrentUserService
     */
    private $currentUser;
    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var JWTTokenManagerInterface
     */
    private $jwtTokenManager;

    public function __construct(
        BrandingLoader $brandingLoader,
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        CustomerService $customerService,
        GlobalConfigInterface $globalConfig,
        JWTTokenManagerInterface $jwtTokenManager,
        OrgaLoader $orgaLoader,
        PermissionsInterface $permissions,
        SessionHandler $sessionHandler,
        TransformMessageBagService $transformMessageBagService,
        string $publicCSSClassPrefix,
        string $defaultLocale
    ) {
        $this->globalConfig = $globalConfig;
        $this->brandingLoader = $brandingLoader;
        $this->orgaLoader = $orgaLoader;
        $this->customerService = $customerService;
        $this->sessionHandler = $sessionHandler;
        $this->publicCSSClassPrefix = $publicCSSClassPrefix;
        $this->currentProcedureService = $currentProcedureService;
        $this->transformMessageBagService = $transformMessageBagService;
        $this->permissions = $permissions;
        $this->currentUser = $currentUser;
        $this->defaultLocale = $defaultLocale;
        $this->jwtTokenManager = $jwtTokenManager;
    }

    protected function extractExposedPermissions(): Collection
    {
        /*
         * Filter all permissions that are enabled and marked as to-be-exposed to the frontend
         * and reformat them to [permission_name => true].
         */
        return collect($this->permissions->getPermissions())->each(
            static function ($permission, $permissionName) {
                if (!is_a($permission, Permission::class)) {
                    throw new RuntimeException(sprintf('Permission %s is not defined in demosplan core anymore', $permissionName));
                }
            }
        )->filter(
            static function (Permission $permission) {
                return $permission->isEnabled() && $permission->isExposed();
            }
        )->flatMap(
            static function (Permission $permission) {
                return [$permission->getName() => true];
            }
        );
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
            'procedureObject'                  => $this->currentProcedureService->getProcedure(),
            'proceduresettings'                => $this->currentProcedureService->getProcedureArray(),
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
            'externalLinks'                    => $this->globalConfig->getExternalLinks(),
        ];
    }

    private function getLocale(Request $request): string
    {
        $languageKey = $request->getSession()->get('_locale');
        if (\is_null($languageKey) || 0 === strlen($languageKey)) {
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
