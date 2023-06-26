<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\MenuBuilder;

use demosplan\DemosPlanCoreBundle\Event\ConfigureMenuEvent;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function is_string;

class MenuBuilder
{
    final public const SIDE_MENU = 'sidemenu';

    /**
     * @var array<string, array>
     */
    private $availableMenus;
    private $currentProcedure;
    private $request;

    private $availableRouteParameters;

    public function __construct(
        CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUserService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FactoryInterface $factory,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        private readonly TranslatorInterface $translator
    ) {
        $this->availableMenus = $parameterBag->get('menu_definitions');
        $this->currentProcedure = $currentProcedureService->getProcedure();
        $this->request = $requestStack->getCurrentRequest();
        $this->setAvailableRouteParameters($requestStack->getCurrentRequest());
    }

    /**
     * Creates the sidemenu in the admin area.
     */
    public function createSideMenu(): ?ItemInterface
    {
        if (!$this->currentUserService->getUser()->isLoggedIn()) {
            return null;
        }

        $menu = $this->factory->createItem('root');

        foreach ($this->availableMenus[self::SIDE_MENU] as $menuEntry) {
            $this->addMenuEntryToMenu($menu, $menuEntry);
        }

        $this->eventDispatcher->dispatch(new ConfigureMenuEvent(self::SIDE_MENU, $this->factory, $menu));

        return $menu;
    }

    /**
     * Gathers the availableRouteParameters from injected services.
     */
    private function setAvailableRouteParameters(Request $request): void
    {
        // get orgaId
        try {
            $currentUser = $this->currentUserService->getUser();
            $orgaId = $currentUser->getOrganisationId();
        } catch (Throwable) {
            $orgaId = null;
        }

        $procedureId = null;
        $filterHash = null;
        if (null !== $this->currentProcedure) {
            // If there is a procedure, get the procedureId
            $procedureId = $this->currentProcedure->getId();
            $filterHash = $this->getFilterHashForProcedure($procedureId, $request);
        }

        $this->availableRouteParameters = [
            'procedure'         => $procedureId,
            'procedureId'       => $procedureId,
            'orgaId'            => $orgaId,
            'organisationId'    => $orgaId,
            'filterHash'        => $filterHash,
        ];
    }

    /**
     * Get the filterHash for a given procedureId if the procedure can be found in the Hash list.
     * Otherwise returns null as a default value.
     */
    private function getFilterHashForProcedure(string $procedureId, Request $request): ?string
    {
        $filterHash = null;
        if ($request->hasSession() && $request->getSession()->has('hashList')) {
            $hashList = $request->getSession()->get('hashList');
            if (array_key_exists($procedureId, $hashList)) {
                $filterHash = $hashList[$procedureId]['assessment']['hash'];
            }
        }

        return $filterHash;
    }

    /**
     * Adds a menu entry to a menu.
     *
     * @param array<string, mixed> $menuEntry
     */
    private function addMenuEntryToMenu(ItemInterface $parent, array $menuEntry): void
    {
        if ($this->hasCurrentUserAnyOfPermissions($menuEntry)) {
            if (null !== $this->currentProcedure && str_contains('{$procedureName}', (string) $menuEntry['label'])) {
                $menuEntry['label'] = $this->currentProcedure->getName();
            }

            // check whether the menu entry has child menu entries
            // check if the menuEntry is a submenu
            if (isset($menuEntry['children']) && is_string($menuEntry['children'])) {
                $submenuName = $menuEntry['children'];
                if (isset($menuEntry['child_paths'])) {
                    $childPaths = $menuEntry['child_paths'];
                    $menuEntry['child_paths'] = [];
                }
                $menuEntry['children'] = [];
                if (null !== $this->currentProcedure) {
                    $isProcedureTemplate = $this->currentProcedure->getMaster();
                    if ('submenu_procedures' === $submenuName && !$isProcedureTemplate) {
                        $menuEntry['children'] = $submenuName;
                        if (isset($childPaths)) {
                            $menuEntry['child_paths'] = $childPaths;
                        }
                    }
                    if ('submenu_templates' === $submenuName && $isProcedureTemplate) {
                        $menuEntry['children'] = $submenuName;
                        if (isset($childPaths)) {
                            $menuEntry['child_paths'] = $childPaths;
                        }
                    }
                }
            }

            $child = $this->createChild($parent, $menuEntry);
            $this->createSubmenu($child, $menuEntry);
        }
    }

    /**
     * Checks for available children entries to iterate over them if applicable.
     *
     * @param array<string, mixed> $menuEntry
     */
    private function createSubmenu(ItemInterface $child, array $menuEntry): void
    {
        if (!isset($menuEntry['children'])) {
            return;
        }
        if (is_string($menuEntry['children'])) {
            $submenuName = $menuEntry['children'];
            $submenu = $this->availableMenus[$submenuName];
            $menuEntry['children'] = $submenu;
        }
        foreach ($menuEntry['children'] as $childEntry) {
            $this->addMenuEntryToMenu($child, $childEntry);
        }
    }

    /**
     * Creates a child item on the given parent item.
     *
     * @param array<string, mixed> $menuEntry
     */
    private function createChild(ItemInterface $parent, array $menuEntry): ItemInterface
    {
        $label = $this->translator->trans($menuEntry['label']);

        // get route
        $route = $menuEntry['path'];
        $routeParams = $this->getRouteParameters($menuEntry);

        // Handle extra parameters to allow for more custom variables in the template
        $extras = $this->getExtras($menuEntry);

        $child = $parent->addChild($label, [
            'route'             => $route,
            'routeParameters'   => $routeParams,
            'extras'            => $extras,
        ]);

        // check for attributes and add them
        if (isset($menuEntry['list_item_attributes'])) {
            foreach ($menuEntry['list_item_attributes'] as $key => $value) {
                $child->setAttribute($key, $value);
            }
        }
        if (isset($menuEntry['link_attributes'])) {
            foreach ($menuEntry['link_attributes'] as $key => $value) {
                $child->setLinkAttribute($key, $value);
            }
        }

        if ($this->isCurrentRouteAChildOf($menuEntry)) {
            $child->setCurrent(true);
        }

        return $child;
    }

    /**
     * Checks if the current menu entry has a permission and if the current user has it as well.
     *
     * @param array<string, mixed> $menuEntry
     */
    private function hasCurrentUserAnyOfPermissions(array $menuEntry): bool
    {
        // check whether permissions are required
        $userHasPermission = true;
        if (isset($menuEntry['permission'])) {
            if (is_string($menuEntry['permission'])) {
                $menuEntry['permission'] = [$menuEntry['permission']];
            }
            $userHasPermission = $this->currentUserService->hasAnyPermissions(...$menuEntry['permission']);
        }

        return $userHasPermission;
    }

    /**
     * Returns an array of key-value pairs for all given parameters that are actually available in the availableRouteParameters.
     *
     * @param array<string, mixed> $menuEntry
     *
     * @returns array<string, mixed>
     */
    private function getRouteParameters(array $menuEntry): array
    {
        $parameters = [];

        if (isset($menuEntry['path_params']) && is_array($menuEntry['path_params'])) {
            // set parameters
            foreach ($menuEntry['path_params'] as $param) {
                if (isset($this->availableRouteParameters[$param])) {
                    $parameters[$param] = $this->availableRouteParameters[$param];
                }
            }
        }

        return $parameters;
    }

    private function isCurrentRouteAChildOf(array $menuEntry): bool
    {
        $isChildRoute = false;
        if (isset($menuEntry['child_paths'])) {
            $currentRoute = $this->request->attributes->get('_route');
            foreach ($menuEntry['child_paths'] as $childPath) {
                if ($childPath === $currentRoute) {
                    $isChildRoute = true;
                }
            }
        }

        return $isChildRoute;
    }

    /**
     * Returns an array of extras values either as they are or replaced by the corresponding availableRouteParameter.
     *
     * @param array<string, mixed> $menuEntry
     *
     * @return array<string, mixed>
     */
    private function getExtras(array $menuEntry): array
    {
        $processedExtras = [];

        if (isset($menuEntry['extras']) && is_array($menuEntry['extras'])) {
            foreach ($menuEntry['extras'] as $extraKey) {
                $processedExtras[$extraKey] = true;
                if (isset($this->availableRouteParameters[$extraKey])) {
                    $processedExtras[$extraKey] = $this->availableRouteParameters[$extraKey];
                }
            }
        }

        return $processedExtras;
    }
}
