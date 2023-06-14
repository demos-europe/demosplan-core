<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Exception\ContentTypeInspectorException;
use Exception;

use function in_array;

use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class JsonApiRequestValidator
{
    private const API_URL_PATH = '/api/2.0/';

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Checks if the given request is directed to a class extending {@link APIController}.
     */
    public function isApiRequest(Request $request): bool
    {
        // We can only validate /api/2.0/ routes because some /api/1.0/ were implemented incorrectly
        // (eg. expecting non-json as request content)
        $path = $request->getPathInfo();
        if (self::API_URL_PATH !== substr($path, 0, strlen(self::API_URL_PATH))) {
            return false;
        }

        $arrayOfParameters = $this->router->matchRequest($request);
        $controller = explode('::', $arrayOfParameters['_controller'])[0];
        try {
            $reflectionController = new ReflectionClass($controller);

            return $reflectionController->isSubclassOf(APIController::class);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Executes basic checks on the request for validity and returns a response object in case it is
     * invalid. If the request seems valid `null` will be returned.
     *
     * According to https://jsonapi.org/format/1.0/#content-negotiation-servers:
     *
     * > Servers MUST respond with a 415 Unsupported Media Type status code
     * > if a request specifies the header Content-Type: application/vnd.api+json
     * > with any media type parameters.
     * >
     * > Servers MUST respond with a 406 Not Acceptable status code if a request’s
     * > Accept header contains the JSON:API media type and all instances of that
     * > media type are modified with media type parameters.
     **/
    public function validateJsonApiRequest(Request $request): ?Response
    {
        $acceptableContentTypes = $request->getAcceptableContentTypes();
        $jsonApiContentType = $request->getMimeType('jsonapi');

        if (0 < count($acceptableContentTypes) && !in_array(
            $jsonApiContentType,
            $acceptableContentTypes,
            true
        )
        ) {
            // the accept header MUST contain the exact match of the json:api mimetype
            return new Response('', Response::HTTP_NOT_ACCEPTABLE);
        }

        try {
            $inspector = new ContentTypeInspector($request);

            if ($jsonApiContentType !== $inspector->getCanonicalType() ||
                ($jsonApiContentType === $inspector->getCanonicalType() && $inspector->hasParameters())) {
                // there MUST NOT be any parameters on the content type
                // and it MUST be the json:api content type
                return new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }
        } catch (Exception|ContentTypeInspectorException $e) {
            // a throwing inspector means there is no content type
            if ('' !== $request->getContent()) {
                // missing content type is only accepted if there is also no content
                return new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }

            return null;
        }

        return null;
    }
}
