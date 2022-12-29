<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Breadcrumb;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresRouterTrait;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresTranslatorTrait;
use demosplan\DemosPlanCoreBundle\Logic\Help\HelpService;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use const ENT_QUOTES;

class Breadcrumb
{
    use RequiresTranslatorTrait;
    use RequiresRouterTrait;

    /**
     * @var array
     */
    protected $procedure = [];

    /**
     * @var bool
     */
    protected $administrationMode = false;

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var array
     */
    protected $items = [];

    /** @var array */
    protected $userRoles = [];

    /**
     * @var RequestStack
     */
    protected $requestStack;
    /**
     * @var HelpService
     */
    private $helpService;

    public function __construct(
        HelpService $helpService,
        RouterInterface $router,
        TranslatorInterface $translator,
        RequestStack $requestStack
    ) {
        $this->requestStack = $requestStack;
        $this->helpService = $helpService;
        $this->router = $router;
        $this->translator = $translator;
    }

    /**
     * Gib das Markup der Breadcrumb aus.
     *
     * @param User        $user
     * @param string|null $titleKey  Key aus der page-title.yml
     * @param array|null  $procedure
     * @param bool        $isOwner
     */
    public function getMarkup(User $user = null, $titleKey = null, $procedure = null, $isOwner = false): string
    {
        // Override title only if title hasn't been set before
        if (null !== $titleKey && $titleKey !== $this->title) {
            trigger_error('Tried to reset already set breadcrumb title', E_USER_DEPRECATED);
            $this->setTitleByPageTitleKey($titleKey);
        }

        if (null === $user) {
            return '';
        }

        $this->userRoles = $user->getRoles();

        if (null !== $procedure) {
            $userOrganisationId = $user->getOrganisationId();
            if ($isOwner || (isset($procedure['orgaId']) && $userOrganisationId === $procedure['orgaId'])) {
                //Fachplanergruppe der Orga bekommen die Adminansicht
                if ($user->isPlanner()) {
                    $this->setAdministrationMode(true);
                }
            }
        }

        $liCounter = 1;

        // Startseite
        $markup = $this->getSnippetMarkup($this->getRouter()->generate('core_home_loggedin'), 'Start', $liCounter++);

        // Wenn ein Procedure gesetzt ist, ergänze das Breadcrumb
        if (null !== $procedure) {
            // Fachplanung bekommt eine andere Ansicht als Institutionen
            if (true === $this->isAdministrationMode()) {
                //Wenn es sich um Blaupausen handelt, dann zeig das an
                if (true === $procedure['master']) {
                    $markup .= $this->getSnippetMarkup(
                        $this->getRouter()->generate('DemosPlan_procedure_templates_list'),
                        $this->translator->trans('masters'),
                        $liCounter++
                    );
                } else {
                    $markup .= $this->getSnippetMarkup(
                        $this->getRouter()->generate('DemosPlan_procedure_administration_get'),
                        $this->translator->trans('procedure'),
                        $liCounter++
                    );
                }
            } else {
                foreach ($this->userRoles as $role) {
                    // Dear fellow developers, if your ide tells you that this switch could
                    // be refactored into an if-else it is sort of right.
                    // However, once we take a deeper look at what the actual after-start
                    // pages for the different roles should be, this switch will come in handy
                    // again. That's why I (SG) decided to keep it around.
                    switch ($role) {
                        case Role::PROCEDURE_DATA_INPUT:
                            $markup .= $this->getSnippetMarkup(
                                $this->getRouter()->generate('DemosPlan_procedure_list_data_input_orga_procedures'),
                                $this->translator->trans('procedure'),
                                $liCounter++
                            );
                            break 2;

                        default:
                            $markup .= $this->getSnippetMarkup(
                                $this->getRouter()->generate('core_home'),
                                $this->translator->trans('participation'),
                                $liCounter++
                            );
                            break 2;
                    }
                }
            }

            // Link zum Verfahren
            if (null !== $procedure && isset($procedure['ident'])) {
                $procedureName = $procedure['name'];

                // Bürger sollen den externen Namen sehen
                if (in_array(Role::CITIZEN, $this->userRoles, true)) {
                    $procedureName = $procedure['externalName'];
                }

                $route = $this->getRouter()->generate(
                    'DemosPlan_procedure_entrypoint',
                    ['procedure' => $procedure['ident']]
                );

                //  set a different route for templates
                if (true === $procedure['master']) {
                    $route = $this->getRouter()->generate(
                        'DemosPlan_procedure_edit_master',
                        ['procedure' => $procedure['id']]
                    );
                }

                if (in_array(Role::PROCEDURE_DATA_INPUT, $this->userRoles, true)) {
                    $route = $this->getRouter()->generate(
                        'DemosPlan_statement_orga_list',
                        ['procedureId' => $procedure['ident']]
                    );
                }

                $markup .= $this->getSnippetMarkup($route, $procedureName, $liCounter++);
            }
        }
        // ggf. zusätzliche Items
        if (is_array($this->getItems()) && 0 < count($this->getItems())) {
            foreach ($this->getItems() as $extraItem) {
                $markup .= $this->getSnippetMarkup($extraItem['url'], $extraItem['title'], $liCounter++);
            }
        }

        // Titel der aktuellen Seite
        if (null !== $this->getTitle()) {
            // https://symfony.com/doc/master/routing.html#getting-the-route-name-and-parameters
            $currentRoute = $this->requestStack
                ->getCurrentRequest()
                ->attributes
                ->get('_route');

            $currentRouteParameters = $this->requestStack
                ->getCurrentRequest()
                ->attributes
                ->get('_route_params');

            if (null !== $currentRoute) {
                $currentUrl = $this->getRouter()->generate($currentRoute, $currentRouteParameters);

                $markup .= $this->getSnippetMarkup($currentUrl, $this->getTitle(), $liCounter++, true);
            }
        }

        return '<ol>'.$markup.'</ol>';
    }

    /**
     * Gib das Markup zu einem Teil der Breadcrumb.
     * Besser wäre via Twig, aber das benötigt eine feste Struktur im Bundle.
     */
    protected function getSnippetMarkup(string $url, string $title, int $counter, bool $isCurrentPage = false): string
    {
        $encodedTitle = htmlspecialchars($title, ENT_QUOTES);
        $dataCy = "data-cy=\"breadcrumb_$counter\"";

        if ($isCurrentPage) {
            return '<li><a href="'.$url.'" aria-current="page" '.$dataCy.'>'.$encodedTitle.'</a></li>';
        }

        return '<li><a href="'.$url.'" '.$dataCy.'>'.$encodedTitle.'</a></li>';
    }

    /**
     * Get contextual help by page title.
     *
     * @param string $title page title
     *
     * @return string
     */
    public function getContextualHelp($title)
    {
        $helpText = '';
        try {
            $key = 'help.'.$title;
            $contextualHelp = $this->helpService->getHelpByKey($key);
            if (null !== $contextualHelp) {
                $helpText = $contextualHelp->getText();
                $helpText = str_replace(["\n", "\r", "\t"], '', $helpText);
            }
        } catch (Exception $e) {
        }

        return $helpText;
    }

    public function isAdministrationMode(): bool
    {
        return $this->administrationMode;
    }

    /**
     * @param bool $administrationMode
     */
    public function setAdministrationMode($administrationMode)
    {
        $this->administrationMode = $administrationMode;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array $item
     */
    public function addItem($item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Setze den Titel der aktuellen Seite per Translationkey der Pagetitle.
     *
     * @param string $titleKey
     */
    public function setTitleByPageTitleKey($titleKey)
    {
        $this->title = $this->translator->trans($titleKey, [], 'page-title');
    }
}
