<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Base;

use cebe\openapi\Writer;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use EDT\JsonApi\ApiDocumentation\OpenAPISchemaGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class APIDocumentationController extends BaseController
{
    /**
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: '/api/', methods: ['GET', 'HEAD'])]
    public function indexAction(): Response
    {
        if ('dev' !== $this->globalConfig->getKernelEnvironment()) {
            return $this->redirectToRoute('core_home');
        }

        return $this->renderTemplate('@DemosPlanCore/DemosPlanCore/api_documentation.html.twig');
    }

    /**
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: '/api/openapi.json', methods: ['GET', 'HEAD'], options: ['expose' => true], name: 'dplan_api_openapi_json')]
    public function jsonAction(OpenAPISchemaGenerator $apiDocumentationGenerator): Response
    {
        if ('dev' !== $this->globalConfig->getKernelEnvironment()) {
            return $this->redirectToRoute('core_home');
        }

        $openApi = $apiDocumentationGenerator->getOpenAPISpecification();

        return new Response(
            Writer::writeToJson($openApi),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }
}
