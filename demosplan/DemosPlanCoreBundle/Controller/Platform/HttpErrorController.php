<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class HttpErrorController extends BaseController
{
    /**
     * Create custom 404 Response.
     *
     *
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: 'notfound', methods: ['GET'], name: 'core_404')]
    public function custom404Action(Request $request): Response
    {
        $content = '';

        try {
            $content = $this->renderTemplate(
                '@DemosPlanCore/DemosPlanCore/404.html.twig',
                [
                    'projects'          => [],
                    'projectName'       => '',
                    'projectVersion'    => '',
                    'projectType'       => '',
                    'gatewayURL'        => '',
                    'urlScheme'         => '',
                    'roles'             => [],
                    'route_name'        => '',
                    'proceduresettings' => '',
                    'currentPage'       => $request->query->get('currentPage', ''),
                ]
            )->getContent();
        } catch (Exception $e) {
            $content = $e->getMessage();
        } finally {
            return new Response($content, Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create custom 500 page.
     *
     *
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: 'error', methods: ['GET'], name: 'core_500')]
    public function custom500Action(TranslatorInterface $translator): Response
    {
        $content = 'Ein Fehler ist aufgetreten';

        try {
            $content = $this->renderTemplate(
                '@DemosPlanCore/DemosPlanCore/error.html.twig',
                [
                    'title' => $translator->trans('500.title', [], 'page-title'),
                ]
            )->getContent();
        } catch (Exception $e) {
            // Actually, there's not much we can do here but at least we can
            // try to tell the user that something went horribly unexplicably wrong?
            $this->getLogger()->error('There was an error during rendering an error message', [$e]);
        } finally {
            return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
