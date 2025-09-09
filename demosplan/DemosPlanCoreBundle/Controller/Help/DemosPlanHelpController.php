<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Help;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Logic\Help\HelpHandler;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanHelpController extends BaseController
{
    /**
     * @return RedirectResponse|Response
     *
     * @throws Exception
     *
     * @DplanPermissions("area_admin_contextual_help_edit")
     */
    #[Route(name: 'dplan_contextual_help_list', methods: 'GET|POST', path: '/contextualHelp')]
    public function listAction(
        Request $request,
        HelpHandler $helpHandler
    ): Response {
        $templateVars = [];
        $requestPost = $request->request->all();

        if ($request->isMethod('POST') && array_key_exists('delete', $requestPost)) {
            if (empty($request->get('r_delete'))) {
                $this->getMessageBag()->add('error', 'warning.select.entries');
            } else {
                $amount = $helpHandler->deleteHelpItems($requestPost);
                $this->getMessageBag()->addChoice(
                    'confirm',
                    'confirm.contextual.help.deleted',
                    ['count' => $amount]
                );
            }

            // Redirect to the same route after form processing
            return $this->redirectToRoute('dplan_contextual_help_list');
        }

        $templateVars['contextualHelpList'] = $helpHandler->getHelpNonGisLayer();

        return $this->renderTemplate('@DemosPlanCore/DemosPlanHelp/help_admin_contextual_help_list.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'help.contextualHelp.list',
        ]);
    }

    /**
     * @return RedirectResponse|Response
     *
     * @throws Exception
     *
     * @DplanPermissions("area_admin_contextual_help_edit")
     */
    #[Route(name: 'dplan_contextual_help_new', methods: 'GET', path: '/contextualHelp/new')]
    public function newAction(
        Breadcrumb $breadcrumb,
        TranslatorInterface $translator
    ): Response {
        $breadcrumb->addItem(
            [
                 'title' => $translator->trans('contextual.help'),
                 'url'   => $this->generateUrl('dplan_contextual_help_list'),
             ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanHelp/help_admin_contextual_help_edit.html.twig',
            [
                'formAction'     => 'dplan_contextual_help_create',
                'formParameters' => [],
                'title'          => 'help.contextualHelp.edit',
            ]
        );
    }

    /**
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws Exception
     *
     * @DplanPermissions("area_admin_contextual_help_edit")
     */
    #[Route(name: 'dplan_contextual_help_create', methods: 'POST', path: '/contextualHelp/create')]
    public function createAction(
        HelpHandler $helpHandler,
        Request $request
    ): Response {
        try {
            $helpHandler->createContextualHelp($request->request->all());
            $this->getMessageBag()->add('confirm', 'confirm.contextual.help.saved');

            return new RedirectResponse($this->generateUrl('dplan_contextual_help_list'));
        } catch (MissingPostParameterException) {
            $this->getMessageBag()->add('error', 'error.missing.required.info');

            return $this->redirectToRoute('dplan_contextual_help_create');
        }
    }

    /**
     * @param string|null $contextualHelpId
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     *
     * @DplanPermissions("area_admin_contextual_help_edit")
     */
    #[Route(name: 'dplan_contextual_help_edit', methods: 'GET', path: '/contextualHelp/{contextualHelpId}')]
    public function editAction(
        Breadcrumb $breadcrumb,
        HelpHandler $helpHandler,
        TranslatorInterface $translator,
        $contextualHelpId = null
    ): Response {
        try {
            $breadcrumb->addItem([
                'title' => $translator->trans('contextual.help'),
                'url'   => $this->generateUrl('dplan_contextual_help_list'),
            ]);

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanHelp/help_admin_contextual_help_edit.html.twig',
                [
                    'formAction'     => 'dplan_contextual_help_edit',
                    'formParameters' => ['contextualHelpId'=> $contextualHelpId],
                    'templateVars'   => [
                        'contextualHelp' => $helpHandler->getHelp($contextualHelpId),
                    ],
                    'title'          => 'help.contextualHelp.edit',
                ]
            );
        } catch (InvalidArgumentException) {
            $this->getMessageBag()->add('warning', 'error.entry.missing.database');

            return $this->redirectToRoute('dplan_contextual_help_list');
        }
    }

    /**
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws Exception
     *
     * @DplanPermissions("area_admin_contextual_help_edit")
     */
    #[Route(name: 'dplan_contextual_help_update', methods: 'POST', path: '/contextualHelp/{contextualHelpId}')]
    public function updateAction(
        Request $request,
        HelpHandler $helpHandler,
        string $contextualHelpId
    ): Response {
        try {
            $helpHandler->updateContextualHelp($request->request->all());
            $this->getMessageBag()->add('confirm', 'confirm.contextual.help.saved');

            return new RedirectResponse($this->generateUrl('dplan_contextual_help_list'));
        } catch (MissingPostParameterException) {
            $this->getMessageBag()->add('error', 'error.missing.required.info');

            return $this->redirectToRoute(
                'dplan_contextual_help_edit',
                ['contextualHelpId' => $contextualHelpId]
            );
        } catch (InvalidArgumentException) {
            $this->getMessageBag()->add('warning', 'error.entry.missing.database');

            return $this->redirectToRoute('dplan_contextual_help_list');
        }
    }
}
